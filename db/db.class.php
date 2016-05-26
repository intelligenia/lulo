<?php

namespace lulo\db;

require LULO_DIR__DEPENDENCES__VENDOR . "/autoload.php";


/**
 * Dastabase abstraction layer.
 */
class DB {

	protected static $db_connection = null;

	/** Motor de base de datos */
	const ENGINE = "mysql";

	/** Controlador de conexión con la base de datos */
	const DRIVER = "mysqli";
	const BLOB_MAX_PACKET_LENGTH = 52428800;

	/**
	 * Crea la conexión con la base de datos
	 * */
	public static function connect($server, $user, $password, $database = null) {

		/* Creamos la conexion con ADOdb  */
		try {
			// nivel de error a E_ERROR para no imprimir los warnings que se pueden producir aunque sí
			// se captura la excepción en esos casos.
			$error = error_reporting(E_ERROR);

			switch (static::DRIVER) {
				case "oci8":
				case "oci8po":
					static::$db_connection = NewADOConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->Connect($server, $user, $password);
					break;

				case "mysql":
					static::$db_connection = ADONewConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					mysql_set_charset('utf8');
					break;

				case "mysqli":
					static::$db_connection = ADONewConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					mysqli_set_charset(static::$db_connection->_connectionID, 'utf8');
					break;

				default:
					trigger_error("No se ha definido un método válido de conexión a la BD. Contacte con el administrador.", E_USER_ERROR);
					break;
			}

			// restablecer nivel de error anterior
			error_reporting($error);

			static::$db_connection->SetFetchMode(ADODB_FETCH_ASSOC);
			/* DESCOMENTAR ESTA LÍNEA PARA HABILITAR MODO DEBUG */
			//static::$db_connection->debug = true;
		} catch (exception $e) {
			// si algo ha ido mal al instanciar la conexión a la BD, devolver error 503
			require_once __DIR__ . "error503.php";
			die();
		}
	}

	public static function resetQueryCache() {

		static::$db_connection->execute("RESET QUERY CACHE");
	}

	/*
	 * Escapa una cadena de texto para ser usada como cadena en la BD.
	 * @param string $str Cadena a escapar.
	 * @return string Cadena $str escapada (si hacía falta).
	 * */

	public static function qstr($str) {

		return static::$db_connection->qstr($str);
	}

	/**
	 * Describe la estructura de una tabla de la base de datos.
	 * @param string $table Nombre de la tabla.
	 * @return array con los siguientes campos
	 * Field (nombre del campo), Type (tipo del campo), Null (NO si no es null o YES si sí lo es),
	 * 	Key (si es clave primaria [PRI], único [UNI]. multiclave [MUL]),
	 * Default (valor por defecto), Extra (si es autoincrementado y cosas así).
	 */
	public static function describe($table) {

		return static::$db_connection->execute("DESCRIBE " . $table);
	}

	/**
	 * Describe completamente la estructura de una tabla de la base de datos.
	 * @param string $table Nombre de la tabla.
	 * @return array con los siguientes campos
	 * Field (nombre del campo), Type (tipo del campo), Collation (codificación),
	 * 	Null (NO si no es null o YES si sí lo es), Key (si es clave primaria [PRI], único [UNI]. multiclave [MUL]),
	 * 	Default (valor por defecto), Extra (si es autoincrementado y cosas así),
	 * 	Privileges (privilegios de los usuarios para ver el campo), Comment (comentarios a cada uno de los campos).
	 *
	 */
	public static function showFullColumns($table) {

		return static::$db_connection->execute("show full columns from `$table`");
	}

	/**
	 * 	Obtiene la información acerca de los índices definidos sobre una tabla
	 *
	 * @param string $table Nombre de la tabla
	 *
	 * @return Object ADORecordSet correspondiente al motor de BD empleado, con todas
	 * 	las columnas sobre las que hay definido un índice. Al iterar sobre el objeto,
	 * 	cada columna viene dada por un array de claves-valor con los campos:
	 * 		***************************************************************************
	 * 		[!!!] IMPORTANTE: los campos indicados solo se han comprobado sobre BDs MySQL,
	 * 			aunque se espera que otras BDs tengan una estructura similar. Por tanto,
	 * 			esta documentación debe considerarse como "parcial" hasta que se efectúen
	 * 			las pruebas necesarias sobre distintos SGBDs.
	 * 		***************************************************************************
	 * 			'Table' => string Nombre de la tabla a la que pertenece la columna
	 * 			'Non_unique' => string indicando si el índice al que pertenece la columna
	 * 			puede o no contener duplicados. Puede ser '0' (no puede tener duplicados)
	 * 			ó '1' (puede tener duplicados).
	 * 			'Key_name' => string nombre del índice al que pertenece la columna
	 * 			'Seq_in_index' => string orden de la columna en el índice, empezando desde 1
	 * 			'Column_name' => string nombre de la columna
	 * 			'Collation' => string criterio de ordenación de la columna en el índice.
	 * 			En MySQL, puede tener valores 'A' (Ascendente) o NULL (No ordenado).
	 * 			'Cardinality' => string número de valores únicos en el índice, null si no
	 * 			hay valores únicos (o no se han actualizado)
	 * 			'Sub_part' => string número de caracteres indexados si la columna sólo
	 * 			está indexada parcialmente, null si la columna entera está indexada.
	 * 			'Packed' => string indica cómo está empaquetada la clave, null si no lo está.
	 * 			'Null' => string 'YES' si la columna puede contener NULL; si no, contiene 'NO'
	 * 			desde MySQL 5.0.3, y '' antes.
	 * 			'Index_type' => string método de índice usado ('BTREE', 'FULLTEXT', 'HASH', 'RTREE').
	 * 			'Comment' => string comentarios sobre la columna en el índice
	 *
	 */
	public static function showIndex($table) {


		if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
			$query = "SELECT * FROM all_indexes WHERE table_name='" . strtoupper($table) . "'";
		} else
			$query = "SHOW INDEX FROM $table";

		return static::$db_connection->execute($query);
	}

	/**
	 * 	Alias de self::showIndex. Obtiene la información acerca de los índices
	 * 	definidos sobre una tabla
	 *
	 * @param string $table Nombre de la tabla
	 *
	 * @return Object ADORecordSet según lo descrito en self::showIndex
	 *
	 */
	public static function showKeys($table) {

		return self::showIndex($table);
	}

	/**
	 * Devuelve la comprobación '=' de un campo que puede ser 'NULL'.
	 * Así por ejemplo ante los parámetros
	 * 		 - ('resource', NULL) --> resource IS NULL
	 * 		 - ('resource', 'yo/tu') --> resource = 'yo/tu'
	 * Realiza el escapado del valor, no del nombre del campo
	 * @param string $field_name nombre del campo
	 * @param mixed|null $field_value valor del campo.
	 * @return string check para incluir en la consulta SQL
	 * @see strEq
	 * @see strNotEq
	 */
	static function check($field_name, $field_value = null) {
		/*

		  echo "habria devuelto "."$field_name ".self::strEq($field_value)."<br/>";

		  if($field_value === null)
		  {
		  echo "devuelvo $field_name IS NULL<br/><br/>";
		  return "$field_name IS NULL";
		  }
		  echo "devuelvo $field_name = ".static::$db_connection->qstr($field_value)."<br/><br/>";
		  return "$field_name = ".static::$db_connection->qstr($field_value);
		 */
		return "$field_name " . self::strEq($field_value);
	}

	/**
	 * 	Inserta en BD un campo largo. Lo hace por partes para no agotar la memoria destinada a un script
	 * 	@param string $tname nombre de la tabla.
	 * 	@param string $field nombre del campo largo.
	 * 	@param string $value valor del campo largo. ES MODIFICADO.
	 * 	@param string $where_cond condición para obtener la tupla a modificar.
	 */
	public static function UpdateBlob($tname, $field, &$value, $where_cond) {
		$max_packet = static::BLOB_MAX_PACKET_LENGTH;
		$actual = 0;
		while ($actual < strlen($value)) {
			$packet = substr($value, $actual, $max_packet);
			$actual+=$max_packet;

			$data = static::$db_connection->qstr($packet);
			static::$db_connection->Execute("UPDATE $tname SET $field=concat($field,$data) WHERE $where_cond");
		}
		/* $data = array();
		  $data[$field]=&$value;
		  self::update($tname, &$data, $where_cond); */
	}

	/**
	 * Convertimos un array con los nuevos datos a una cadena que pueda ser interpretada como SQL.
	 * @param array $new_data Array de datos con los nuevos datos.
	 * @return string Cadena SQL con los nuevos datos.
	 */
	public static function convertDataArrayToSQLModificationString($new_data) {

		// Para cada valor, le metemos su valor especial si hace falta
		foreach ($new_data as $nkey => &$nvalue) {
			if (is_string($nvalue))
				$nvalue = static::$db_connection->qstr($nvalue);
			elseif (is_null($nvalue))
				$nvalue = "NULL";
			elseif (is_bool($nvalue))
				$nvalue = $nvalue ? 1 : 0;
			elseif (is_float($nvalue))
				$nvalue = str_replace(",", ".", $nvalue);
		}
		$new_data_sql = "(" . implode(",", $new_data) . ")";
		return $new_data_sql;
	}

	/**
	 * Inserción de datos que (INSERT) tiene en cuenta las peculiaridades del SGBD en el tratamiento
	 * de BLOBs y CLOBs
	 * Los datos se reciben sin los caracteres especiales citados. Este método ya se encarga de hacer eso.
	 * @param string $tname Nombre de la tabla donde hacer la inserción
	 * @param array $data Array asociativo con los nombres de campo como clave y sus valores asociados
	 * @param mixed $blobfields Array con lista de campos o string con nombres de campo separados por espacio. Campos tipo BLOB
	 * @param mixed $clobfields Array con lista de campos o string con nombres de campo separados por espacio. Campos tipo CLOB
	 * @return boolean TRUE si la fila se insertó correctamente o FALSE en caso contrario
	 */
	public static function insert($tname, &$data, $blobfields = array(), $clobfields = array()) {
		if (is_string($blobfields)) {
			$blobfields = preg_split('/ +/', $blobfields);
		}

		if (is_string($clobfields)) {
			$clobfields = preg_split('/ +/', $clobfields);
		}

		//Calculamos el tamaño máximo de los BLOBs para que la consulta final no exceda el tamaño máximo de consulta
		$num_blobs = count($blobfields) + count($clobfields);
		if ($num_blobs > 0) {
			$max_packet = static::BLOB_MAX_PACKET_LENGTH / $num_blobs;
		}

		// lista de campos con valores "normales" y LOBs convertidos a NULL
		foreach ($data as $field => $value) {
			if (in_array($field, $blobfields) || in_array($field, $clobfields)) {
				$new_data[$field] = "";
				if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
					//Todo el control de tamaño máximo de consulta lo hacemos para otros DBMSs que no son Oracle, ya que este tiene su propio mecanismo para BLOBs
					if (strlen($value) < $max_packet) {
						//Aunque sea BLOB, si contiene pocos datos, lo insertamos directamente en la consulta.
						//Así optimizamos los accesos a la BD
						$new_data[$field] = $value;
						unset($blobfields[array_search($field, $blobfields)]);
						unset($clobfields[array_search($field, $clobfields)]);
					}
				}
			} else {
				$new_data[$field] = $value;
			}
		}
		////////////////////////////////////////////////////////////////////////////
		/// Inserción de campos que no son blob
		//$ok = static::$db_connection->AutoExecute($tname,$new_data,'INSERT');
		$data_keys = array_keys($new_data);
		$data_keys_sql = "(" . implode(",", $data_keys) . ")";
		// Para cada valor, le metemos su valor especial si hace falta
		$new_data_sql = self::convertDataArrayToSQLModificationString($new_data);
		// Generación del SQL de inserción
		$insertSQL = "INSERT INTO $tname $data_keys_sql VALUES $new_data_sql";
		// print_r($insertSQL);
		$ok = static::$db_connection->Execute($insertSQL);
		////////////////////////////////////////////////////////////////////////////
		/// BLOBs y CLOBs. Construir clausula where y hacer actualizaciones
		if ($ok) {
			foreach ($new_data as $field => $value) {
				if ($value != "")
					$where[] = $field . self::strEq($value);
			}

			$where_cond = implode(" AND ", $where);

			foreach ($blobfields as $field) {
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateBlob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
			}

			foreach ($clobfields as $field)
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateClob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
		}

		return $ok !== false;
	}

	/**
	 * Obtiene el último ID insertado en la BD.
	 * Es único por sesión. Leer http://dev.mysql.com/doc/refman/5.5/en/information-functions.html#function_last-insert-id
	 * para más información.
	 * @return Último ID insertado en la base de datos.
	 * */
	public static function getLastInsertId() {

		return static::$db_connection->GetOne("SELECT LAST_INSERT_ID()");
	}

	/**
	 * Convierte un array con pares campo, valor.
	 * @param array $new_data Array con los nuevos datos.
	 * @return string Cadena con el contenido SQL.
	 */
	public static function convertDataArrayToSQLUpdateString($new_data) {
		// Vamos generando la cadena de '<campo>'=<valor>
		$new_data_sql = "";
		foreach ($new_data as $nkey => $nvalue) {
			if (is_null($nvalue))
				$new_data_sql .= "$nkey=NULL";
			elseif (is_bool($nvalue))
				$new_data_sql .= "$nkey=" . ($nvalue ? "1" : "0");
			elseif (is_float($nvalue))
				$new_data_sql .= "$nkey=" . str_replace(",", ".", $nvalue);
			else
				$new_data_sql .= $nkey . DB::strEq($nvalue);
			$new_data_sql .= ",";
		}
		// Quitamos la última coma
		$new_data_sql = substr($new_data_sql, 0, strlen($new_data_sql) - 1);
		return $new_data_sql;
	}

	/** Actualización de datos (UPDATE) que tiene en cuenta las peculiaridades del SGBD en
	 * el tratamiento de BLOBs y CLOBs
	 *
	 * Los datos en $data se reciben sin los caracteres especiales citados. Este método ya
	 * se encarga de hacer eso. La claúsula WHERE ($where) <strong>sí</strong> debe ir con
	 * los caracteres especiales citados.
	 *
	 * En $data no es necesario que aparezcan todos los campos de la tupla, tan sólo aquellos que
	 * se desea actualizar.
	 *
	 * NOTA: Las condiciones indicadas en la cláusula WHERE no pueden hacer referencia a los
	 * campos de tipo BLOB o CLOB si el SGBD es Oracle
	 *
	 * @param string $tname nombre de la tabla donde hacer la inserción
	 * @param array $data array asociativo con los nombres de campo como clave y sus valores asociados de
	 *              las columnas que se van a actualizar
	 * @param string $where cláusula WHERE usada en la actualización
	 * @param mixed $blobfields array con lista de campos o string con nombres de campo separados por espacio. Campos tipo BLOB
	 * @param mixed $clobfields array con lista de campos o string con nombres de campo separados por espacio. Campos tipo CLOB
	 * @return boolean TRUE si la consulta se ejecutó correctamente o FALSE en caso contrario
	 */
	public static function update($tname, &$data, $where = "", $blobfields = array(), $clobfields = array()) {

		if (is_string($blobfields))
			$blobfields = preg_split('/ +/', $blobfields);

		if (is_string($clobfields))
			$clobfields = preg_split('/ +/', $clobfields);

		//Calculamos el tamaño máximo de los BLOBs para que la consulta final no exceda el tamaño máximo de consulta
		$num_blobs = count($blobfields) + count($clobfields);
		if ($num_blobs > 0)
			$max_packet = static::BLOB_MAX_PACKET_LENGTH / $num_blobs;

		// convertir cadenas vacías en IS NULL en las condiciones (en Oracle la cadena vacía
		// es igual a NULL)
		$where = preg_replace('/=\s*((\'\')|(""))/', " IS NULL ", $where);
		$where = preg_replace('/((!=)|(<>))\s*((\'\')|(""))/', " IS NOT NULL ", $where);

		// lista de campos con valores "normales" y LOBs convertidos a NULL
		foreach ($data as $field => $value) {
			if (in_array($field, $blobfields) || in_array($field, $clobfields)) {
				$new_data[$field] = "";
				if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
					//Todo el control de tamaño máximo de consulta lo hacemos para otros DBMSs que no son Oracle, ya que este tiene su propio mecanismo para BLOBs
					if (strlen($value) < $max_packet) {
						//Aunque sea BLOB, si contiene pocos datos, lo insertamos directamente en la consulta.
						//Así optimizamos los accesos a la BD
						$new_data[$field] = $value;
						unset($blobfields[array_search($field, $blobfields)]);
						unset($clobfields[array_search($field, $clobfields)]);
					}
				}
			} else {
				$new_data[$field] = $value;
			}
		}

		// insertar campos "normales"
		//$ok = static::$db_connection->AutoExecute($tname,$new_data,'UPDATE',$where);
		////////////////////////////////////////////////////////////////////////////
		/// Insertar campos que no son blob
		// Para cada valor, le metemos su valor especial si hace falta
		$new_data_sql = self::convertDataArrayToSQLUpdateString($new_data);
		// Generación del SQL de inserción
		$updateSQL = "UPDATE $tname SET $new_data_sql WHERE $where";
		#print $updateSQL." <br>\n";
		//die();
		$ok = static::$db_connection->Execute($updateSQL);
		// BLOBs y CLOBs. Construir clausula where y hacer actualizaciones
		if ($ok) {
			foreach ($blobfields as $field) {
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateBlob($tname, $field, $data[$field], $where);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where);
					}
			}

			foreach ($clobfields as $field)
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateClob($tname, $field, $data[$field], $where);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
		}
		return $ok !== false;
	}

	/** Comparación de cadenas dentro de una consulta SQL con comprobación de NULL en Oracle
	 *
	 * Este método sirve para hacer la comparación de igualdad de cadenas teniendo en cuenta que
	 * en Oracle las cadenas vacías se tratan como NULL.
	 *
	 * Por ejemplo, la siguiente consulta en MySQL es correcta:
	 * <code>SELECT * FROM tabla WHERE campo=""</code>
	 *
	 * En Oracle, la misma consulta hay que hacerla de la siguiente manera:
	 * <code>SELECT * FROM tabla WHERE campo IS NULL</code>
	 *
	 * Este método abstrae las dos sintaxis. La forma de usarlo es:
	 * <code>$sql="SELECT * FROM tabla WHERE campo" . DB::strEq($valor);</code>
	 *
	 * Si $valor es la cadena vacía y el SGBD es Oracle automáticamente pone "IS NULL"
	 *
	 * @see strNotEq
	 * @param string $value cadena sin los caracteres especiales citados con el valor
	 * @return string cadena adaptada al SGBD con los caracteres especiales citados
	 */
	public static function strEq($value) {
		if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
			if ((is_string($value) && empty($value)) || is_null($value))
				return " IS NULL ";
		}
		else {
			if (is_null($value))
				return " IS NULL ";
		}

		return "=" . static::$db_connection->qstr($value) . " ";
	}

	/** Comparación de cadenas dentro de una consulta SQL con comprobación de NULL en Oracle
	 *
	 * Este método sirve para hacer la comparación de igualdad de cadenas teniendo en cuenta que
	 * en Oracle las cadenas vacías se tratan como NULL.
	 *
	 * Por ejemplo, la siguiente consulta en MySQL es correcta:
	 * <code>SELECT * FROM tabla WHERE campo!=""</code>
	 *
	 * En Oracle, la misma consulta hay que hacerla de la siguiente manera:
	 * <code>SELECT * FROM tabla WHERE campo IS NOT NULL</code>
	 *
	 * Este método abstrae las dos sintaxis. La forma de usarlo es:
	 * <code>$sql="SELECT * FROM tabla WHERE campo" . DB::strNotEq($valor);</code>
	 *
	 * Si $valor es la cadena vacía y el SGBD es Oracle automáticamente pone "IS NOT NULL"
	 *
	 * @see strEq
	 * @param string $value cadena sin los caracteres especiales citados con el valor
	 * @return string cadena adaptada al SGBD con los caracteres especiales citados
	 */
	public static function strNotEq($value) {
		//Comento esta linea porque IS NULL / IS NOT NULL también funcionan y vienen bien en MySQL.
		if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
			if ((is_string($value) && empty($value)) || is_null($value))
				return " IS NOT NULL ";
		}
		else {
			if (is_null($value))
				return " IS NOT NULL ";
		}
		return "<>" . static::$db_connection->qstr($value) . " ";
	}

	/*	 * ***************************************************************************************************************** */
	/*	 * ***************************************************************************************************************** */
	/* Métodos de ayuda de DB */

	/**
	 * Cuenta el número de tuplas que verifican una condición sobre una tabla determinada.
	 * @param string $table Nombre de la tabla sobre la que se va a operar.
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @return integer Número de tuplas que cumplen la condición $condition en la tabla $table.
	 */
	public static function count($table, $condition) {
		$sql = "select count(*) as number_of_tuples FROM " . $table . " " . self::makeWhereCondition($condition);
		#print_r($sql);
		$res = static::$db_connection->getRow($sql);
		return $res["number_of_tuples"];
	}

	/**
	 * Cuenta el número de tuplas que verifican una condición sobre una tabla determinada.
	 * @param string $table Nombre de la tabla sobre la que se va a operar.
	 * @param string $field Nombre del campo cuyo máximo se desea conocer.
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @return integer|double Valor máximo de la columan $field de acuerdo con las condiciones pasadas como parámetro..
	 */
	public static function max($table, $field, $condition = null) {

		$maximo = static::$db_connection->getOne("select max($field) FROM " . $table . " " . self::makeWhereCondition($condition));
		return $maximo;
	}

	/**
	 * Elimina las filas de la tabla que cumplen una condición de igualdad.
	 * @param string $table Nombre de la tabla sobre la que se va a operar.
	 * @param array $fields Array asociativo donde las claves son los campos de la tabla que participan en la condición where, y los valores del array son los valores que deben tener los campos para que la fila se elimine.
	 * @return boolean true si todo ha ido bien, false en otro caso.
	 */
	public static function deleteIfFieldsAreEqualTo($table, $fields) {

		return static::$db_connection->execute("DELETE FROM " . $table . " " . self::makeWhereCondition($fields));
	}

	/**
	 * Elimina las filas de la tabla que cumplen una condición de igualdad.
	 * @param string $table Nombre de la tabla sobre la que se va a operar.
	 * @param array $fields Array asociativo donde las claves son los campos de la tabla que participan en la condición where, y los valores del array son los valores que deben tener los campos para que la fila se elimine.
	 * @return boolean true si todo ha ido bien, false en otro caso.
	 */
	public static function delete($table, $fields) {
		return self::deleteIfFieldsAreEqualTo($table, $fields);
	}

	/**
	 * Elimina las filas de la tabla que cumplen una condición de igualdad.
	 * @param string $table Nombre de la tabla sobre la que se va a operar.
	 * @param string $field Nombre del campo que participará en la cláusula where.
	 * @param string $value Valor del campo que debe tener éste para que se elimine su fila.
	 * @return boolean true si todo ha ido bien, false en otro caso.
	 */
	public static function deleteIfFieldIsEqualTo($table, $field, $value) {

		return static::$db_connection->execute("DELETE FROM " . $table . " WHERE " . $field . self::strEq($value));
	}

	/**
	 * Genera una condición un unión natural entre dos tablas. No usar desde fuera.
	 * @param array $joinCondition Array con las correspondencias entre columnas.
	 * @param string $table1 Nombre de la tabla 1.
	 * @param string $table1 Nombre de la tabla 2.
	 * @return string SQL con la condición WHERE de la unión natural.
	 * */
	public static function makeJoinCondition($joinCondition, $table1, $table2) {
		$sql = "";
		foreach ($joinCondition as $field1 => $field2)
			$sql .= "$table1.$field1=$table2.$field2 AND ";
		$sql = substr($sql, 0, strlen($sql) - 4);
		return $sql;
	}

	/**
	 * Compone la cadena de selección desde una array.
	 * @param array|string $fields Array en el que cada elemento es un campo que se va a seleccionar. Si se pasa una cadena se devuelve sin cambios.
	 * @return string Cadena que contiene los columnas de la tabla que se seleccionarán separadas por comas.
	 */
	public static function makeFieldsToSelect($fields, $table = null) {
		if ($table == null) {
			$table = "";
			if (is_array($fields))
				return implode(",", $fields);
		} else
			$table = $table . ".";

		if ($fields == "*")
			return $fields;
		$sqlFields = "";
		if (is_string($fields)) {
			if (strpos($fields, ",") !== false) {
				if (strpos($fields, ",") != (strlen($fields) - 1))
					$fields = explode(",", $fields);
				else
					$fields = substr($fields, 0, strlen($fields) - 1);
			} else
				return $fields;
		}
		foreach ($fields as $field)
			$sqlFields .= $table . $field . ",";
		$sqlFields = substr($sqlFields, 0, strlen($sqlFields) - 1);
		return $sqlFields;
	}

	/**
	 * Genera la cláusula LIMIT.
	 * @param array|string $limit Array con el inicio y el tamaño de la cláusula limit. También permite que se le pase una cadena LIMIT correcta (con o sin la palabra clave LIMIT).
	 * @return string Cadena con la cláusula LIMIT correcta.
	 * */
	public static function makeLimit($limit = null) {
		if ($limit === null)
			return "";
		// Si alguien ha sido tan ceporro de meterle una cadena, comprueba si tiene la palabra clave LIMIT
		// En caso de que no, se le añade, y esperemos que haya metido el límite correctamente.
		if (is_string($limit)) {
			if (!preg_match("/^limit/i", $limit))
				$limit = " LIMIT " . $limit;
			return $limit;
		}
		$ini = 0;
		if (is_int($limit) and $limit > 0) {
			$size = $limit;
		} elseif (is_array($limit)) {
			$size = $limit[0];
			if (count($limit) > 1) {
				$ini = $limit[0];
				$size = $limit[1];
			}
		}
		return " LIMIT $ini, $size";
	}

	/**
	 * Genera la cláusula ORDER BY.
	 * @param array $order Array con pares <campo>=>"asc|desc"
	 * @return string Cadena SQL con una cláusula ORDER BY correcta.
	 */
	public static function makeOrderBy($order = null) {
		$st = "";
		if ($order != null and is_array($order)) {
			$st = "order by ";
			foreach ($order as $k => $v) {
				// Si es de la forma <0-9>.<atributo>, lo incluimos como table_<0-9>.<atributo>
				if (strpos($k, ".") !== false and preg_match("/^(\d+)\.(.+)$/", $k, $matches) > 0)
					$k = "table_" . $matches[1] . "." . $matches[2];
				// Añadimos a la cadena del orden
				$st .= $k . " " . $v . ",";
			}
			$st = substr($st, 0, strlen($st) - 1); //elimina la última coma (",")
		}
		return $st;
	}

	/** Genera una condición WHERE completa preparada para ser añadida a una sentencia SQL.
	 * ==Índices==
	 * Los índices del array pasado suelen ser los campos sobre los que hacer las operaciones.
	 * Hay dos índices especiales: "OR" y "AND". Si el índice es uno de estos dos valores, se espera
	 * un array como valor de dicho índice, que será un array preparado para MakeWhereCondition, 
	 * y se ejecutará de forma recursiva MakeWhereCondition para generar una subcondición, usando como 
	 * logicalNexus el valor pasado
	 *
	 * Ejemplo:
	 *
	 * array("OR"=> array('campo' => array ("<>" => "hola mundo"), 'campo2' => array ("<>" => "hola mundo")))
	 *   --> Procesaría 
	 * 				array('campo' => array ("<>" => "hola mundo"), 'campo2' => array ("<>" => "hola mundo"))
	 * 		con logicalNexus OR, obteniendo
	 * 				(campo <> "hola mundo" OR campo2 <> "hola mundo")
	 * 		y luego lo añadiría con el logicalNexus establecido
	 *
	 * ==COMPARACIONES DE IGUALDAD==
	 *
	 * Las condiciones se especifican en el argumento $fields en forma de array $campo => $valor.
	 * En caso de que $valor sea un tipo simple de PHP (string, numérico, booleano, etc), se
	 * hace una condición del tipo:
	 *
	 *     WHERE campo='$valor'
	 *
	 * a no ser que se trate de uno de los casos especiales descritos a continuación, en cuyo caso
	 * $valor tiene una interpretación especial.
	 *
	 * ==CASOS ESPECIALES PARA VALORES SIMPLES==
	 *
	 *   (string) "self::otrocampo"
	 *       Se hace una comparación tipo 'campo = otrocampo'.
	 *
	 *   (string) "valor1[or]valor2[or]valor3[or]..."
	 *       Se hace una comparación tipo '(campo = "valor1" OR campo = "valor2" OR ...)'. Si se
	 *       dejan espacios en blanco entre los valores indicados y los [or], estos espacios
	 *       formarán parte de los valores en la comparación final. Por ejemplo:
	 *
	 *          campo => 'hola [or] mundo'
	 *
	 *       se transformará en SQL en el código:
	 *
	 *          ... (campo="hola " OR campo=" mundo") ...
	 *
	 *       El token especial '[or]' debe ir escrito en minúsculas, tal y como se muestra en el
	 *       ejemplo anterior.
	 *
	 *   (boolean) TRUE / FALSE
	 *       Se hace una comparación tipo 'campo=TRUE' o 'campo=FALSE'.
	 *
	 *   NULL
	 *       Se hace una comparación tipo 'campo IS NULL'. Para hacer una comparación 'IS NOT NULL',
	 *       usar el operador "ISNOTNULL" (ver más adelante).
	 *
	 *
	 * ==OPERADORES==
	 *
	 * Si $valor es un array asociativo, la condición se interpreta como $operador => $argumento. El
	 * operador puede ser cualquiera de los permitidos por SQL, usándose la notación infijo:
	 *
	 *    'campo' => array ("<>" => "hola mundo")
	 *
	 * se transforma en SQL en el código:
	 *
	 *    ... campo<>'hola mundo' ...
	 *
	 * Algunos operadores se interpretan de forma especial y se explican más adelante.
	 *
	 * $argumento puede ser cualquier valor simple de PHP o alguno de los siguientes con significado
	 * especial:
	 *
	 *   (object) DateTime
	 *       Se convierte a fecha en formato MySQL y se hace una comparación tipo
	 *       'campo $operador "fecha"'.
	 *
	 *   (string) "self::otrocampo"
	 *       Se hace una comparación tipo 'campo $operador otrocampo'.
	 *
	 *   (boolean) TRUE / FALSE
	 *       Se hace una comparación tipo 'campo $operador TRUE' o 'campo $operador FALSE'.
	 *
	 *   NULL
	 *       Se hace una comparación tipo 'campo $operador NULL'.
	 *
	 *
	 * ==OPERADORES ESPECIALES==
	 * nota: los operadores son cadenas de texto insensibles a mays/mins
	 *
	 *   %LIKE%
	 *       ejecuta la comparación 'campo LIKE %$valor%', haciendo escapado automático de
	 *       los caracteres % y _ que se encuentren en $valor.
	 *
	 *   %NOTLIKE%
	 *       ejecuta la comparación 'campo NOT LIKE %$valor%', haciendo escapado automático de
	 *       los caracteres % y _ que se encuentren en $valor.
	 *   LIKE
	 *       ejecuta la comparación 'campo LIKE $valor', SIN HACER escapado automático de
	 *       los caracteres % y _ que se encuentren en $valor.
	 *
	 *   NOTLIKE
	 *       ejecuta la comparación 'campo NOT LIKE $valor', SIN HACER escapado automático de
	 *       los caracteres % y _ que se encuentren en $valor.
	 *
	 *   ISNOTNULL
	 *       ejecuta la comparación 'campo IS NOT NULL', ignorando el $argumento que tenga asociado
	 *       esta operación.
	 *
	 *   OR
	 *       hace una unión OR con comparación de igualdad (por defecto) de cada uno de los valores recibidos,
	 *       por ejemplo:
	 *
	 *           'campo' => array('OR' => array('a','b','c', NULL))
	 *
	 *       se transforma en SQL en el código:
	 *
	 *            ... (campo='a' OR campo='b' OR campo='c' OR campo IS NULL) ...

	 *
	 * 			Si se desea hacer otro tipo de comparación, se define de la siguiente forma:
	 *
	 *           'campo' => array('OR' => array('>'=>'a','b', '!='=>'c', NULL))
	 *       se transforma en SQL en el código:
	 *
	 *            ... (campo > 'a' OR campo='b' OR campo != 'c' OR campo IS NULL) ...
	 * 
	 * 			Si es para un mismo operador se puede hacer con "operardor"=>array(values,values, values);
	 *
	 *           'campo' => array('OR' => array('like'=>array('a','b'), '!='=>'c', NULL))
	 *
	 *       se transforma en SQL en el código:
	 *
	 *            ... (campo like 'a' OR campo like 'b' OR campo != 'c' OR campo IS NULL) ...
	 *
	 *   NOT_IN
	 *       hace una unión AND con comparación de 'distinto de' con cada uno de los valores
	 *       recibidos, por ejemplo:
	 *
	 *           'campo' => array('NOT_IN' => array('a','b','c', NULL))
	 *
	 *       se transforma en SQL en el código:
	 *
	 *            ... (campo<>'a' AND campo<>'b' AND campo<>'c' AND campo IS NOT NULL) ...
	 *
	 *   IN
	 *       la acción IN para el array recibido, por ejemplo:
	 *
	 *           'campo' => array('IN' => array('a','b','c', NULL, 'ISNOTNULL'))
	 *
	 *       se transforma en SQL en el código:
	 *
	 *            ... campo IS NULL or campo IS NOT NULL OR campo in ('a','b','c')    ...
	 *
	 *
	 * @param mixed array $fields Array asociativo donde las claves son los campos de la tabla que
	 *              participan en la condición where, y los valores del array son los valores
	 *              que deben tener los campos para que la fila se seleccione.
	 *
	 *              string con la cláusula WHERE completa
	 *
	 * @param bool  $includeWhereKeyword Booleano que hace que se incluye la palabra reservada
	 *              WHERE. Por defecto está a TRUE. Si se le pasa una cadena se toma este parámetro
	 *              como el parámetro $table y al $includeWhereKeyword se le mete FALSE.
	 *
	 * @param string $table Nombre de la tabla de la que se desea generar la condición WHERE.
	 *               Por defecto es NULL. Si el parámetro $includeWhereKeyword es una cadena,
	 *               se le asignará ese valor.
	 *
	 * @param string $logicalNexus Operación lógica que se ejecutará entre las subcondiciones.
	 *               Por defecto es "AND".
	 *
	 * @return string Devuelve una cadena con la condición WHERE generada.
	 */
	public static function makeWhereCondition($fields, $includeWhereKeyword = true, $table = null, $logicalNexus = "AND") {
		//print __METHOD__."</br>";
		//var_dump($fields);
		//print_r($fields);
		// Si no se especifica como segundo parámetro un valor booleano pero sí una cadena,
		// se considera que se desea introducir una condición sin where pero específica de una tabla
		if (is_string($includeWhereKeyword)) {
			$table = $includeWhereKeyword;
			$includeWhereKeyword = false;
		}

		$where = "";

		if ($fields == null or count($fields) == 0)
			return "";

		if (is_array($fields) and count($fields) > 0) {
			$sqlTable = "";

			// en este array se irán almacenando las partes de la condición que luego serán unidas
			// con $logicalNexus
			$whereConditions = array();

			if ($table != null)
				$sqlTable = $table . ".";

			foreach ($fields as $f => $v) {
				// Tipos especiales de valores de campos, que son condiciones anidades
				if ($f == "OR" || $f == "AND") {
					$sql_anidado = "(" . static::makeWhereCondition($v, false, $table, $f) . ")";
					$whereConditions[] = $sql_anidado;
					continue;
				}
				if (is_array($v)) {
					// Por compatibilidad hacia atrás
					if (isset($v[0]) and count($v) == 2) {
						// en el caso de que sea un array de la forma array($op, $valor), se
						// convierte al nuevo formato
						$v = array($v[0] => $v[1]);
					}
					// en el caso de que sea un array, de la forma array($op=>$valor)
					foreach ($v as $op => $value) {
						//print $op."<br>";flush();
						// valores especiales para la parte $value
						if (is_object($value) && ($value instanceof DateTime)) {
							$valor = static::$db_connection->qstr($value->format("Y-m-d H:i:s"));
						} elseif (is_string($value) && preg_match("/^(self::)(.+)/", $value, $matches)) {
							$valor = $matches[2];
						} elseif (is_null($value)) {
							$valor = "NULL";
						} elseif ($value === FALSE) {
							$valor = "FALSE";
						} elseif ($value === TRUE) {
							$valor = "TRUE";
						} elseif (!is_array($value)) {
							$valor = static::$db_connection->qstr($value);
						}

						// operadores especiales (en $op)
						if (mb_strtoupper($op) == "%LIKE%") {
							$valor = addcslashes($valor, "%_");

							// eliminar ' al principio y final de la cadena que haya podido poner
							// anteriormente qstr()
							if ($valor[0] == "'")
								$valor = substr($valor, 1);

							if ($valor[strlen($valor) - 1] == "'")
								$valor = substr($valor, 0, strlen($valor) - 1);

							$whereConditions[] = "{$sqlTable}{$f} LIKE '%{$valor}%'";
						}
						elseif (mb_strtoupper($op) == "%NOTLIKE%") {
							$valor = addcslashes($valor, "%_");

							// eliminar ' al principio y final de la cadena que haya podido poner
							// anteriormente qstr()
							if ($valor[0] == "'")
								$valor = substr($valor, 1);

							if ($valor[strlen($valor) - 1] == "'")
								$valor = substr($valor, 0, strlen($valor) - 1);

							$whereConditions[] = "{$sqlTable}{$f} NOT LIKE '%{$valor}%'";
						}
						elseif (mb_strtoupper($op) == "LIKE") {
							// eliminar ' al principio y final de la cadena que haya podido poner
							// anteriormente qstr()
							if ($valor[0] == "'")
								$valor = substr($valor, 1);

							if ($valor[strlen($valor) - 1] == "'")
								$valor = substr($valor, 0, strlen($valor) - 1);

							$whereConditions[] = "{$sqlTable}{$f} LIKE '{$valor}'";
						}
						elseif (mb_strtoupper($op) == "NOTLIKE") {
							// eliminar ' al principio y final de la cadena que haya podido poner
							// anteriormente qstr()
							if ($valor[0] == "'")
								$valor = substr($valor, 1);

							if ($valor[strlen($valor) - 1] == "'")
								$valor = substr($valor, 0, strlen($valor) - 1);

							$whereConditions[] = "{$sqlTable}{$f} NOT LIKE '{$valor}'";
						}
						elseif (mb_strtoupper($op) == "IS NOT" and is_null($value)) {
							$whereConditions[] = "{$sqlTable}{$f} IS NOT NULL";
						} elseif (mb_strtoupper($op) == "ISNOTNULL") {
							$whereConditions[] = "{$sqlTable}{$f} IS NOT NULL";
						} elseif (mb_strtoupper($op) == "OR") {
							if (!is_array($value))
								trigger_error("se esperaba un array de valores para el operador 'OR'", E_USER_ERROR);

							if (!empty($value)) {
								$conditionParts = array();
								foreach ($value as $o => $val) {
									if (is_numeric($o))
										$o = "=";
									if (mb_strtoupper($o) == "ISNOTNULL" || (!is_array($val) && mb_strtoupper($val) == "ISNOTNULL"))
										$conditionParts[] = "{$sqlTable}{$f} IS NOT NULL";
									elseif (is_null($o) || is_null($val))
										$conditionParts[] = "{$sqlTable}{$f} IS NULL";
									else {
										if (!is_array($val))
											$conditionParts[] = "{$sqlTable}{$f} {$o} " . static::$db_connection->qstr($val);
										else
											foreach ($val as $iter)
												$conditionParts[] = "{$sqlTable}{$f} {$op} " . static::$db_connection->qstr($iter);
									}
								}

								$whereConditions[] = "(" . implode(" OR ", $conditionParts) . ")";
							}
						}elseif (mb_strtoupper($op) == "NOT_IN") {
							if (!is_array($value))
								trigger_error("se esperaba un array de valores para el operador 'NOT_IN'", E_USER_ERROR);

							if (!empty($value)) {
								$conditionParts = array();
								foreach ($value as $val) {
									$conditionParts[] = "{$sqlTable}{$f}" . self::strNotEq($val);
								}

								$whereConditions[] = "(" . implode(" AND ", $conditionParts) . ")";
							}
						} elseif (mb_strtoupper($op) == "IN") {
							if (!is_array($value))
								trigger_error("se esperaba un array de valores para el operador 'IN'", E_USER_ERROR);

							if (!empty($value)) {
								$conditionParts = "";
								$extraConditionPart = "";
								foreach ($value as $val) {
									//si en la lista de elementos le pones un null hay que sacarlo a parte
									if ($val == null)
										$extraConditionPart = "{$sqlTable}{$f} IS NULL OR";
									else if (mb_strtoupper($val) == "ISNOTNULL")
										$extraConditionPart .= " {$sqlTable}{$f} IS NOT NULL OR";
									else
										$conditionParts[] = static::$db_connection->qstr($val);
								}

								if (empty($conditionParts))
									$whereConditions[] = substr($extraConditionPart, 0, -3);
								else
									$whereConditions[] = " ( $extraConditionPart {$sqlTable}{$f} IN ( " . implode(", ", $conditionParts) . ")  )";
							}
						} else {
							$whereConditions[] = "{$sqlTable}{$f} {$op} {$valor}";
						}
					}
				} else {

					// caso especial de valor simple: [or]
					if (strpos($v, "[or]") !== false) {
						$vs = explode("[or]", $v);

						$orConditionParts = array();
						foreach ($vs as $val) {
							$orConditionParts[] = "$sqlTable$f" . self::strEq($val);
						}

						$orCondition = "(" . implode(" OR ", $orConditionParts) . ")";
						$whereConditions[] = "{$orCondition}";

						// pasar a la siguiente iteración del bucle de condiciones
						continue;
					}

					// otros casos de valores simples
					if (preg_match("/^(self::)(.+)/", $v, $matches)) {
						$v = "=" . $matches[2];
					} elseif ($v === FALSE) {
						$v = "=FALSE";
					} elseif ($v === TRUE) {
						$v = "=TRUE";
					} elseif (mb_strtoupper($v) == "ISNOTNULL") {
						$v = " IS NOT NULL";
					} else {
						$v = self::strEq($v);
					}

					$whereConditions[] = "{$sqlTable}{$f}{$v}";
				}
			}

			// encadenar partes de la sentencia con el operador lógico definido en la llamada
			// a este método para generar la salida final
			if ($includeWhereKeyword) {
				$where .= " WHERE";
			}

			$where .= " " . implode(" {$logicalNexus} ", $whereConditions);
		} else {
			// $fields no es un array. Se asume que es una sentencia WHERE ya construida
			// Si no tiene un WHERE delante se lo metemos
			if (!preg_match("/^where/i", $fields)) {
				$where .= " WHERE";
			}

			$where .= " " . $fields;
		}

		#var_dump($where);
		return $where;
	}

	/**
	 * Genera una condición DISYUNTIVA WHERE completa preparada para ser añadida a una sentencia SQL.
	 * @param array $fields Array asociativo donde las claves son los campos de la tabla que participan en la condición where, y los valores del array son los valores que deben tener los campos para que la fila se elimine.
	 * @param bool $includeWhereKeyword Booleano que hace que se incluye la palabra reservada WHERE. Por defecto está a true. Si se le pasa una cadena se toma este parámetro como el parámetro $table y al $includeWhereKeyword se le mete false.
	 * @param string $table Nombre de la tabla de la que se desea generar la condición where. Por defecto es null. Si el parámetro $includeWhereKeyword es una cadena, se le asignará ese valor.
	 * @return string Devuelve una cadena con la condición where asociada al array.
	 */
	public static function makeORWhereCondition($fields, $includeWhereKeyword = true, $table = null) {
		return self::makeWhereCondition($fields, $includeWhereKeyword, $table, "or");
	}

	/**
	 * Devuelve la primera fila de una tabla que cumple con una condición determinada.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...".
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 */
	public static function getRow($table, $fields = "*", $condition = null, $order = null) {
		$fields = self::makeFieldsToSelect($fields);

		//print "select ".$fields." from ".$table." ".self::makeWhereCondition($condition)." ".self::makeOrderBy($order) . " LIMIT 1";
		return static::$db_connection->getRow("select " . $fields . " from " . $table . " " . self::makeWhereCondition($condition) . " " . self::makeOrderBy($order) . " LIMIT 1");
	}

	/**
	 * Devuelve el valor de una columna de una fila que cumple con una condición determinada.
	 * @param array|string $selectedField Columna a seleccionar de las fila obtenida de la consulta.
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...".
	 */
	public static function getOne($table, $selectedField, $condition = null) {

		// Recordar que esta consulta lleva implícita el LIMIT 1
		return static::$db_connection->getOne("select " . $selectedField . " from " . $table . " " . self::makeWhereCondition($condition));
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una condición determinada.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {

		$fields = self::makeFieldsToSelect($fields);
		$pcondition = self::makeWhereCondition($condition);
		$order = self::makeOrderBy($order);
		$limit = self::makeLimit($limit);

		$sql = "SELECT $fields FROM $table $pcondition $order $limit";
		//print $sql."\n<br>";

		$res = static::$db_connection->getAll($sql);
		// var_dump($res);
		return $res;
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una condición determinada ordenadas de forma aleatoria.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getRandom($table, $fields = "*", $condition = null, $limit = null) {

		$fields = self::makeFieldsToSelect($fields);
		$pcondition = self::makeWhereCondition($condition);
		//$order = self::makeOrderBy($order);
		$order = "order by rand()";
		$limit = self::makeLimit($limit);

		$sql = "SELECT $fields FROM $table $pcondition $order $limit";
		#print_r($sql);

		$res = static::$db_connection->getAll($sql);
		// var_dump($res);
		return $res;
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una disyunción determinada.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' OR field2...". Por defecto es null.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getDisjunctiveAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {
		$fields = self::makeFieldsToSelect($fields);

		//print "select ".$fields." from ".$table." ".self::makeORWhereCondition($condition)." ".self::makeOrderBy($order)." ".self::makeLimit($limit);
		return static::$db_connection->getAll("select " . $fields . " from " . $table . " " . self::makeORWhereCondition($condition) . " " . self::makeOrderBy($order) . " " . self::makeLimit($limit));
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una disyunción determinada. ANTICUADO.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' OR field2...". Por defecto es null.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getDisjunctiveLikeAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {
		return self::getDisjunctiveAll($table, $fields, $condition, $order, $limit);
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una disyunción determinada con condiciones extra.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' OR field2...". Por defecto es null.
	 * @param array|string $extraConditions Condiciones extra que se aplicarán a la consulta.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getDisjunctiveAllWithExtraConditions($table, $fields = "*", $condition = null, $extraCondition = null, $order = null, $limit = null) {
		$fields = self::makeFieldsToSelect($fields);

		$condition = self::makeORWhereCondition($condition, false);
		$extraCondition = self::makeWhereCondition($extraCondition, false);
		if ($condition != "")
			$condition = "where (" . $condition . ")";
		if ($extraCondition != "")
			$condition .= " and (" . $extraCondition . ")";
		$order = self::makeOrderBy($order);
		$limit = self::makeLimit($limit);
		//print "select ".$fields." from ".$table." ".$condition." ".$order." ".$limit;
		return static::$db_connection->getAll("select " . $fields . " from " . $table . " " . $condition . " " . $order . " " . $limit);
	}

	/**
	 * Devuelve todas las filas de una tabla que cumplen con una disyunción determinada con condiciones extra. ANTICUADO.
	 * @param string $table Nombre de la tabla a consultar.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' OR field2...". Por defecto es null.
	 * @param array|string $extraConditions Condiciones extra que se aplicarán a la consulta.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getDisjunctiveLikeAllWithExtraConditions($table, $fields = "*", $condition = null, $extraCondition = null, $order = null, $limit = null) {
		return self::getDisjunctiveAllWithExtraConditions($table, $fields, $condition, $extraCondition, $order, $limit);
	}

	/**
	 * Elimina la tabla $table.
	 * @param string $table Nombre de la tabla a eliminar.
	 */
	public static function dropTable($table) {

		return static::$db_connection->Execute("DROP TABLE $table");
	}

	/**
	 * Genera el siguiente ID de una tabla de secuencia.
	 * @param string $table Nombre de la tabla de secuencia.
	 */
	public static function generateNextID($table) {

		//print "<br/>La tabla con la secuencia es $table-> ";
		// Comprobamos si la tabla existe
		if (self::existsTable($table)) {
			$filas = self::getRow($table, "*");
			if (count($filas) == 0)
				DB::dropTable($table);
		}
		$id = static::$db_connection->GenID($table);
		//print $id;
		return $id;
	}

	const SEQ_SUFFIX = "_seq";

	/**
	 * Genera el siguiente ID de una tabla de secuencia.
	 * @param string $table Nombre de la tabla de secuencia.
	 */
	public static function generateNextIDForTable($table) {
		return self::generateNextID(str_replace("-", "_", $table . self::SEQ_SUFFIX));
	}

	/**
	 * Genera el siguiente ID de una tabla de secuencia por stack.
	 * @param string $table Nombre de la tabla de secuencia.
	 * @param string $stack Nombre del stack. Por defecto es el stack actual.
	 */
	public static function generateNextIDForTableByStack($table, $stack = EWSTACK) {
		return self::generateNextID(str_replace("-", "_", $table . "_" . $stack . self::SEQ_SUFFIX));
	}

	/**
	 * Devuelve un id único de elemento.
	 * @param string $prefix Prefijo que se incluirá en la generación del md5.
	 * @return string UUID de 2 letras [a-z] aleatorias + 32 caracteres [0-9a-f] (md5 de muchas cosas)
	 */
	public static function uuid($prefix = "") {
		$uuid = uniqid($prefix . $_SERVER["SERVER_NAME"] . rand(0, 1000), true);
		$chars = "abcdefghijklmnopqrstuvwxyz";
		$rand1 = mt_rand(0, strlen($chars) - 1);
		$rand2 = mt_rand(0, strlen($chars) - 1);
		$uuid = $prefix . $chars[$rand1] . $chars[$rand2] . md5($uuid);
		return $uuid;
	}

	/**
	 * Hace un slug "delgado" único en una tabla.
	 * @param string $text Texto que queremos convertir en slug.
	 * @param integer $limit Tamaño máximo del slug. Normalmente tendrá el mismo valor que el tamaño del varchar de la BD que contenga el slug.
	 * @param string $tableName Nombre de la tabla que se ha de consultar para asegurar la unicidad del slug.
	 * @param string $field Columna de la tabla $tableName que se desea que sea única.
	 * @param array $extraCondition Condición extra de generación del slug (además de la unicidad de ese campo).
	 * */
	public static function dbMakeUniqueGlobalLargeSlug($text, $limit, $tableName, $field, $extraCondition = null) {
		$slug = Slug::makeLargeSlug($text, $limit);
		if (empty($slug))
			$slug = "xyzzy";
		//$condition = array("stack"=>EWSTACK, $field=>$slug);
		$condition = array($field => $slug);
		if ($extraCondition != null and is_array($extraCondition) and count($extraCondition) > 0)
			$condition = array_merge($condition, $extraCondition);
		$i = 2;
		$res = DB::getOne($tableName, $field, $condition);
		$existe = is_string($res);

		while ($existe) {
			$slugAux = $slug;

			//Coletilla a añadir para que el slug sea único
			$coletilla = "-" . $i;

			//Si la longitud del slug obtenido es igual al límite indicado, recortamos el slug para que al añadir la coletilla la longitud se mantenga
			//Hasta ahora no se controlaba esta longitud, de forma que al añadir la "coletilla" la longitud podría ser mayor a la indicada.
			if (strlen($slug) == $limit) {
				$slugAux = substr($slug, 0, $limit - strlen($coletilla));
			}

			$condition[$field] = $slugAux . $coletilla;

			//$condition[$field] = $slug."-".$i;
			$res = DB::getOne($tableName, $field, $condition);
			$existe = is_string($res);
			$i++;
		}
		return $condition[$field];
	}

	/**
	 * Genera un slug único según el campo "stack".
	 * @param string $text Texto que queremos convertir en slug.
	 * @param integer $limit Tamaño máximo del slug. Normalmente tendrá el mismo valor que el tamaño del varchar de la BD que contenga el slug.
	 * @param string $tableName Nombre de la tabla que se ha de consultar para asegurar la unicidad del slug.
	 * @param string $field Columna de la tabla $tableName que se desea que sea única.
	 * @param array $extraCondition Condición extra de generación del slug (además de la unicidad de ese campo).
	 * @return string Cadena de URL única por sitio web con el mismo campo stack (slug)
	 */
	public static function dbMakeUniqueLargeSlug($text, $limit, $tableName, $field, $extraCondition = null, $stackField = "stack") {
		if ($extraCondition == null)
			$extraCondition = array();
		$extraCondition = array_merge(array($stackField => EWSTACK), $extraCondition);
		return self::dbMakeUniqueGlobalLargeSlug($text, $limit, $tableName, $field, $extraCondition);
	}

	/**
	 * Genera un slug único según el campo "_stack". Esto es, el campo automático de stack.
	 * @param string $text Texto que queremos convertir en slug.
	 * @param integer $limit Tamaño máximo del slug. Normalmente tendrá el mismo valor que el tamaño del varchar de la BD que contenga el slug.
	 * @param string $tableName Nombre de la tabla que se ha de consultar para asegurar la unicidad del slug.
	 * @param string $field Columna de la tabla $tableName que se desea que sea única.
	 * @param array $extraCondition Condición extra de generación del slug (además de la unicidad de ese campo).
	 * @return string Cadena de URL única por sitio web con el mismo campo stack (slug)
	 */
	public static function dbMakeUniqueLargeSslug($text, $limit, $tableName, $field, $extraCondition = null) {
		return self::dbMakeUniqueLargeSlug($text, $limit, $tableName, $field, $extraCondition, $stackField = "_stack");
	}

	/**
	 * Incrementa una columna de una tupla de forma atómica.
	 * @param string $id Columna que se incrementará, esto es, la columna que se usa como identificador.
	 * @param string $table Tabla sobre la que se va a ejecutar la consulta.
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @return integer Valor del identificador actualizado para la tabla $table.
	 * */
	public static function increment($id, $table, $condition = null) {

		$condition = self::makeWhereCondition($condition);
		$res = static::$db_connection->execute("update $table set $id=@var:=$id+1 $condition;");
		//update pruebas set $id=@var:=$id+1 where texto1="cont1"; select @var;
		$res = static::$db_connection->execute("select @var;");
		//$f0 = $res->FetchField(0);
		//$res = $res->MetaType($f0->type, $f0->max_length);
		return $res;
	}

	/**
	 * Muestra las tablas de una base de datos.
	 * @param string $database Nombre de la base de datos.
	 * @param boolean $reformat Si es true se envía como un array en el que los valores son los nombres de las tablas; si es false, no se cambia el formato de la consulta de la BD.
	 * @return array Array con las tablas de la base de datos.
	 * */
	public static function showTables($database = null, $reformat = true) {
		$rows = null;

		if ($database == null)
			$database = static::$db_connection->database;
		if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
			$rows = static::$db_connection->getAll("SHOW TABLES FROM " . $database);
			$res = array();
			if ($reformat)
				foreach ($rows as $row)
					$res[] = $row["Tables_in_" . $database];
			else
				$res = $rows;
			return $res;
		}
		// En caso de que sea Oracle, el desarrollador deberá hacer la comprobación antes
		// UNDER YOUR RESPONSIBILITY
		if ($database != null)
			static::$db_connection->execute("use " . $database);
		$rows = static::$db_connection->getAll("select * from  dba_tables");
		return $rows;
	}

	/**
	 * Muestra todas las tablas de un stack concreto. Es decir, las tablas que dependen ÚNICAMENTE de un stack. Estas tablas se caracterizan
	 * por contener _<Nombre del stack>_ (nótese el guión delante y detrás del stack) en su nombre.
	 * @param string $stack Identificador del stack del que se desean recuperar sus tablas. Por defecto es el stack actual.
	 * @param string $database Identificador de la base de datos.
	 */
	public static function showTablesFromStack($stack = EWSTACK, $database = null) {
		$tables = self::showTables($database);
		$res = array();
		foreach ($tables as $table)
			if (preg_match("/_" . $stack . "_/", $table))
				$res[] = $table;
		return $res;
	}

	/**
	 * Comprueba si existe la tabla en la base de datos actual.
	 * @param $table Nombre de la tabla.
	 * @return boolean true si la tabla $table existe en la BD actual, false en otro caso.
	 * */
	public static function existsTableInCurrentDatabase($table) {
		$tableList = self::showTables();
		return in_array($table, $tableList);
	}

	/**
	 * Comprueba si existe la tabla en la base de datos actual.
	 * @param $table Nombre de la tabla.
	 * @return boolean true si la tabla $table existe en la BD actual, false en otro caso.
	 * */
	public static function existsTable($table) {
		return self::existsTableInCurrentDatabase($table);
	}

	/**
	 * Comprueba si existe la tabla en la base de datos actual.
	 * @param $table Nombre de la tabla.
	 * @return boolean true si la tabla $table existe en la BD actual, false en otro caso.
	 * */
	public static function existsTableList($tables) {
		foreach ($tables as $table)
			if (!self::existsTableInCurrentDatabase(trim($table)))
				return false;
		return true;
	}

	/**
	 * Ejecuta código SQL arbitrario. Sólo se permite la ejecución de código SQL que sea aceptado por el método execute de AdoDB.
	 * @param string $sqlCode Código SQL que se desea ejecutar.
	 * @return mixed Lo que devuelva la ejecución. Normalmente, un array o una cadena.
	 * */
	public static function execute($sqlCode) {
		$results = static::$db_connection->execute($sqlCode);
		return $results;
	}

	/**
	 * Devuelve el código SQL de creación de la tabla.
	 * @params string $tableName Nombre de la tabla cuyo SQL se va a crear.
	 * @return string Código SQL de la tabla.
	 */
	public static function getCreateTable($tableName) {
		$res = static::$db_connection->getRow("SHOW CREATE TABLE `$tableName`");
		if (!isset($res["Create Table"]) or ! is_string($res["Create Table"]))
			return "";
		return $res["Create Table"];
	}

	/**
	 * Crea una tabla en la base de datos. NO USAR. Sólo para propósitos de experimentación.
	 * @param string $tableName Nombre de la tabla a crear.
	 * @param array $tableFields Campos de la tabla.
	 * @return boolean true si la creación ha sido un éxito, false en otro caso.
	 * */
	public function createTable($tableName, $tableFields) {
		return static::$db_connection->CreateTableSQL($tableName, $tableFields);
	}

	/**
	 * Devuelve todas las filas DISTINTAS de una tabla que cumplen con una condición determinada.
	 * @param string $table Nombre de la tabla sobre la que se ejecuta la consulta.
	 * @param array|string $fields Columnas a seleccionar de las filas. Por defecto, selecciona todas.  Por defecto es * (selecciona todos los campos).
	 * @param array|string $condition Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2...". Por defecto es null.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @param array|string $limit Límite de filas que se seleccionarán. Por defecto es null.
	 */
	public static function getDistinctAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {
		$fields = self::makeFieldsToSelect($fields);
		$condition = self::makeWhereCondition($condition);
		$order = self::makeOrderBy($order);
		$sql = "SELECT DISTINCT {$fields} FROM {$table} {$condition} {$order} " . self::makeLimit($limit);

		return static::$db_connection->getAll($sql);
	}

	/**
	 * Devuelve la longitud de un campo de una tabla.
	 * @param string $table Nombre de la tabla sobre la que se ejecuta la consulta.
	 * @param string $field Nombre del campo cuyo tamaño se desea consultar.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @return integer Tamaño en bytes del campo de la tabla.
	 * */
	public static function fieldLength($table, $field, $condition = null) {
		$lengthField = "_length_" . $field;

		$sql = "select " . static::$db_connection->length . "($field) as $lengthField from $table" . self::makeWhereCondition($condition);
		$res = static::$db_connection->getRow($sql);
		//print_r($res);
		if (count($res) == 0 or $res == null or ! isset($res[$lengthField]))
			return null;
		return (int) ($res[$lengthField]);
	}

	/**
	 * Informa si un campo es nulo. NO USAR. Sólo para propósitos de depuración.
	 * @param string $table Nombre de la tabla sobre la que se ejecuta la consulta.
	 * @param string $field Nombre del campo cuyo nulidad se desea consultar.
	 * @param array $order Orden que ha de llevar. Array con pares <campo>=>"asc|desc" que generarán una cláusula ORDER BY <campo>=>ASC|DESC. Por defecto es null.
	 * @return boolean true si el campo es null, false en otro caso.
	 * */
	public static function fieldIsNull($table, $field, $condition = null) {
		$length = self::fieldLength($table, $field, $condition);
		//print "Length es";
		if ($length == null)
			return true;
		return false;
	}

	const TABLE_FIELD_SEPARATOR = ".";
	const TABLE_ALIAS_PREFIX = "table_";

	protected static $I_TABLE = 0;

	const UNIQUE_PREFIX_NUMBER_SUFFIX = "@@@###@@@";

	protected static function asUniqueTableName($tableName) {
		self::$I_TABLE++;
		return $tableName . self::UNIQUE_PREFIX_NUMBER_SUFFIX . self::$I_TABLE;
	}

	protected static function asTableName($tableName) {
		$pos = strpos($tableName, self::UNIQUE_PREFIX_NUMBER_SUFFIX);
		if ($pos === false)
			return $tableName;
		return substr($tableName, 0, $pos);
	}

	/**
	 * Unión natural de tablas con condiciones complejas.
	 * Implementa el inner join de las tablas que se le pasa, como lo hace. Interpreta que la primera tabla es la que va en el select y añade tantos inner join como tablas se añadan
	 * @param Array $tables Array Contiene los nombres de las tablas sobre los que vamos a efectuar el inner join, para cada tabla se le puede indicar que campos son los que debe obtener, si no se le indica nada obtiene *, si no se necesita nada se le pasa array()
	 * @param Array $onConditions Es mixto, si sólo va un array interpreta que es la parte on, es de la forma "tabla_i-1.campo"=>"tabla_i.campo". Puede llevar indie "on" que indica que es la condición on y "extra" para añadir condición sobre la tabla_i
	 * @param Array $whereConditions Array de condiciones sobre tables_0
	 * @param Array $params, opciones de configuración para personalizar el inner
	 * 	"order" => es un array "table_name"=>array("field"=>ORDER, "field"=>ORDER)
	 * 	"limit" => int valor para limitar los resultados de búsqueda
	 * 	"debug" => para que te muestre la consulta completa y detenga la ejecución
	 * 	"distinct" => indica si los resultados son únicos o no
	 * 	"explain" => añade la cláusula explain a la plataforma
	 * 	"join" => añade la cláusula explain a la plataforma
	 * 	"straight_join" => añade el parámetro STRAIGHT_JOIN en el select, OJO sólo utilizar cuando no quede más remedio
	 * @return array Array de arrays con los valores de los campos de la/s tabla/s indicada/s.
	 * */
	public static function join($tables, $onConditions = null, $whereConditions = null, $params = null) {


		$tablesDict = array();
		$tablesOrig = array();
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Obtenemos los campos, si no es un array cogemos el *
		$fieldSQL = "";
		$i = 0;
		foreach ($tables as $table_name => $fields) {
			$tablesOrig[] = $table_name;
			if (!is_array($fields) && !is_null($fields)) {
				$table_name = $fields;
				$fieldSQL .= "tabla_$i.*,";
			} else {
				if (!empty($fields))
					foreach ($fields as $f) {
						$fieldSQL .= "tabla_$i.$f,";
					}
			}

			$table_name = preg_replace("#-alias-$#", "", $table_name);
			$tablesDict[] = $table_name;
			$i++;
		}

		$fieldSQL = substr($fieldSQL, 0, strlen($fieldSQL) - 1);

		/////////////////////////////////////////////////////////////////////////////////////////////////
		// obtenemos las condiciones del on
		$i = 1;
		$innerSql = "";

		$join = "INNER";
		if (isset($params["join"]))
			$join = strtoupper($params["join"]);

		$extraWhereCond = array();
		foreach ($onConditions as $oC) {
			if (!isset($oC["on"]))
				$on = array_pop($oC);
			else
				$on = $oC["on"];

			$joinCond = self::makeJoinCondition($on, "tabla_" . ($i - 1), "tabla_$i");
			$extraJoin = "";
			if (isset($oC["extra"]) && !empty($oC["extra"])) {
				$extraJoinCond = array();
				foreach ($oC["extra"] as $field => $cond) {
					if ($field != "OR" and $field != "AND") {
						$extraJoinCond["tabla_$i." . $field] = $cond;
					} else {
						foreach ($cond as $f => $c)
							$extraJoinCond[$field]["tabla_$i." . $field] = $cond;
					}
				}
				$extraJoin = " AND " . self::makeWhereCondition($extraJoinCond, false);
				//$extraJoin = preg_replace("#\s* WHERE \s*#"," AND ",$extraJoin);
			}
			//como el left join obtiene resultados distintos segun pongas condiciones en el ON o en el WHERE, permitimos que se definan donde quieren ir las condiciones que no relacionan tablas
			if (isset($oC["where"]) && !empty($oC["where"])) {
				$extraJoinCond = array();
				foreach ($oC["where"] as $field => $cond) {
					if ($field != "OR" and $field != "AND") {
						$extraWhereCond["tabla_$i." . $field] = $cond;
					} else {
						foreach ($cond as $f => $c)
							$extraWhereCond[$field]["tabla_$i." . $field] = $cond;
					}
				}
			}

			$innerSql .= "$join JOIN {$tablesDict[$i]} tabla_$i ON $joinCond $extraJoin \n";
			$i++;
		}
		//condiciones sobre la tabla_0 para hacer el where

		if (!is_null($whereConditions)) {

			foreach ($whereConditions as $field => $cond) {
				if ($field != "OR" and $field != "AND") {
					$extraWhereCond["tabla_0." . $field] = $cond;
				} else {
					foreach ($cond as $f => $c)
						$extraWhereCond[$field]["tabla_0." . $field] = $cond;
				}
			}
		}
		$where = self::makeWhereCondition($extraWhereCond);
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Generación de la consulta SQL
		// Selección de tuplas

		$sqlDistinct = "DISTINCT";
		if (!isset($params["distinct"]) || !$params["distinct"])
			$sqlDistinct = "";

		$sqlExplain = "EXPLAIN";
		if (!isset($params["explain"]) || !$params["explain"])
			$sqlExplain = "";

		$sqlStraight = "STRAIGHT_JOIN";
		if (!isset($params["straight_join"]) || !$params["straight_join"])
			$sqlStraight = "";

		$sql = "$sqlExplain SELECT $sqlStraight $sqlDistinct $fieldSQL \nFROM {$tablesDict[0]} tabla_0 \n $innerSql \n$where ";

		// Si tiene un ORDEN, se lo metemos
		if (isset($params["order"]) && $params["order"] != null) {
			$ordenFinal = array();
			foreach ($params["order"] as $table_name => $fields) {
				foreach ($fields as $f => $order) {
					$pos = array_search($table_name, $tablesOrig);
					$ordenFinal["tabla_$pos." . $f] = $order;
				}
			}
			$sql .= self::makeOrderBy($ordenFinal) . "\n";
		}

		// Si tiene un LÍMITE, se lo metemos
		if (isset($params["limit"]) && $params["limit"] != null)
			$sql .= self::makeLimit($params["limit"]) . "\n";
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Devolución de resultados
		if (isset($params["debug"])) {
			print $sql . "<br/><br/>";
			die;
		}

		$results = static::$db_connection->getAll($sql);
		return $results;
	}

	/**
	 * Unión natural de tablas con condiciones complejas.
	 * Las tablas se identificarán por su clave en el array $tables, de manera que la tabla "0" es la primera tabla, la tabla "1" es la segunda, etc.
	 * @param array $tables Array con los nombres de las tablas de las que se va a hacer la unión natural.
	 * @param mixed $fields Cadena con la selección o un array con los campos a seleccionar. Si se trata de un array, se usará un nombre del estilo <id_tabla>.<campo>.
	 * @param array $conditions Array de condiciones, del tipo <id_tabla>.<campo>=><condición>.
	 * @param array $conditions Array de condiciones extra, del tipo <id_tabla>.<campo>=><condición>.
	 * @param array $order Array con el orden de los campos, del tipo <id_tabla>.<campo>=><orden>.
	 * @param mixed $limit Límite de la consulta.
	 * @param string $operator Operador de conjunción de la condición de la consulta.
	 * @param boolean $distinct Indica si la consulta ha de ser DISTINCT.
	 * @return array Array de arrays con los valores de los campos de la/s tabla/s indicada/s.
	 * */
	public static function naturalJoin($tables, $fields = "*", $conditions = null, $explicitCondition = null, $order = null, $limit = null, $operator = "and", $distinct = false) {

		//var_dump($conditions);
		//var_dump($explicitCondition);
		//var_dump($order);
		$newTables = array();
		foreach ($tables as $table) {
			$uniqueTableName = self::asUniqueTableName($table);
			$newTables[] = $uniqueTableName;
			$tableAliasDictionary[$table] = $uniqueTableName;
		}

		$oldTables = $tables;
		$tables = $newTables;

		// Nombres de las tablas
		$aTables = array();
		$i = 0;
		$tableAlias = array();
		foreach ($tables as $table) {
			$tableAlias[$table] = self::TABLE_ALIAS_PREFIX . "$i";
			$aTables[] = self::asTableName($table) . " " . $tableAlias[$table];
			$i++;
		}
		$tableSQL = implode(",", $aTables);
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Columnas que se van a seleccionar (X.columna_1, Y.columna_2, X.columna_8, etc)
		$fieldSQL = "";
		if (is_string($fields) and $fields !== "*")
			$fields = explode(",", $fields);
		elseif ($fields === "*")
			$fieldSQL = $fields;
		if (is_array($fields)) {
			foreach ($fields as $field) {
				$f = explode(self::TABLE_FIELD_SEPARATOR, $field);
				$fieldTable = $tables[$f[0]];
				$uniqueField = $tableAlias[$fieldTable] . "." . $f[1];
				$fieldSQL .= $uniqueField . ",";
			}
			$fieldSQL = substr($fieldSQL, 0, strlen($fieldSQL) - 1);
		} else
			return null;

		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Condición implícita, es decir, X.columna => Y.columna
		$conditionSQL = "";
		//var_dump($conditions);
		if (is_array($conditions)) {
			foreach ($conditions as $fieldLeft => $fieldRight) {
				$l = explode(self::TABLE_FIELD_SEPARATOR, $fieldLeft);
				//print_r($l);
				$fieldLeft = $tableAlias[$tables[$l[0]]] . "." . $l[1];

				$r = explode(self::TABLE_FIELD_SEPARATOR, $fieldRight);
				//print_r($r);
				$fieldRight = $tableAlias[$tables[$r[0]]] . "." . $r[1];

				$fieldCondition = $fieldLeft . "=" . $fieldRight;
				$conditionSQL .= "$fieldCondition and ";
				//print $field."<br>";
			}
			$conditionSQL = substr($conditionSQL, 0, strlen($conditionSQL) - 4);
		}
		// Si es una cadena, tenemos una condición compleja, y rezamos porque el usuario
		// la haya introducido correctamente
		elseif (is_string($conditions)) {
			// Sustituimos el carácter \d+. por el alias de la tabla que sea adecuado
			foreach ($tables as $tableIndex => $tableName)
				$conditions = str_replace($tableIndex . ".", $tableAlias[$tableName] . ".", $conditions);
			// La condición SQL es la que ha metido el usuario con los alias
			$conditionSQL = $conditions;
		} else {
			trigger_error("El método " . __METHOD__ . " sólo acepta como parámetro \$conditions array o string.", E_USER_ERROR);
			die();
		}
		if (empty($conditionSQL))
			$conditionSQL = "1=1";
		//print $conditionSQL;
		//die();
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Condición explícita, es decir, X.columna => "valor"
		$explicitConditionSQL = "";
		if (!is_null($explicitCondition)) {
			///////////////////////////////////////////////////////////////////////////////////////////////////
			// Para cada condición explícita, tenemos que procesarla según los parámetros que tenga
			foreach ($explicitCondition as $field => $value) {
				// $fieldLeft tiene el nombre del campo con prefijo de tabla,
				// lo hacemos así para evitar ambigüedades entre nombres de campo
				$f = explode(self::TABLE_FIELD_SEPARATOR, $field);
				$fieldLeft = $tableAlias[$tables[$f[0]]] . "." . $f[1];
				// Por defecto, a menos que se especifique otro, el operador entre atributos es el de IGUALDAD
				$op = "=";
				///////////////////////////////////////////////////////////////////////////////////////////////////
				// Condición con operadores que relacionan dos tablas
				// Es decir, condiciones del tipo '<id_tabla_m>.<atributo_i>' => '<id_tabla_n>.<atributo_j>'
				if (is_array($value)) {
					///////////////////////////////////////////////////////////////////////////////////////////////////
					// Condiciones al estilo antiguo, es decir "date"=>array('>=','2012-01-19').
					// Esta condición "antigua" está ANTICUADA. Y NO ha de usarse.
					// Esta condición sería equivalente a hacer en el estilo NUEVO: "date"=>array('>='=>'2012-01-19')
					if (isset($value[0]) and isset($value[1])) {
						$op = $value[0];
						$value = $value[1];

						if (is_string($value)) {
							$value = static::$db_connection->qstr($value);
						} elseif (is_null($value)) {
							$value = "NULL";
						} elseif ($value instanceof DateTime) {
							$value = $value->format('\'Y-m-d H:i:s\'');
						}

						$explicitConditionSQL .= "$fieldLeft $op $value $operator ";
					} else {
						///////////////////////////////////////////////////////////////////////////////////////////////////
						// Para condiciones compuestas
						// Estilo "date"=>array('>='=>'2012-01-19', '<='=>'2012-01-27')
						foreach ($value as $op => $val) {
							//var_dump($op);
							if (is_string($val)) {
								// Condición de igualdad entre columnas de tablas (en lugar de valores estáticos)
								// La sintáxis utilizada es del estilo "campo"=>array("operador"=>"{nombre_tabla}::nombre_campo")
								//, o bien "campo"=>array("operador"=>"alias_externo_tabla.nombre_campo")
								if (preg_match("#^\{(.+)\}::(.+)#", $val, $matches)) {
									$val = $tableAlias[$tableAliasDictionary[$matches[1]]] . "." . $matches[2];
								} else if (preg_match("#^(.+)\.(.+)#", $val, $matches)) {
									$val = self::TABLE_ALIAS_PREFIX . $matches[1] . "." . $matches[2];
								} else {
									$val = static::$db_connection->qstr($val);
								}
							}

							if (is_null($val)) {
								$val = "NULL";
							} elseif ($val instanceof DateTime) {
								$val = $val->format('\'Y-m-d H:i:s\'');
							}

							$explicitConditionSQL .= "$fieldLeft $op $val $operator ";
						}
						//$op = key($value);
						//$value = $value[$op];
					}
				}
				///////////////////////////////////////////////////////////////////////////////////////////////////
				// Condición con valores explícitos, es decir, '<id_tabla>.<atributo>' =>'<valor>'
				// Consideramos que se le pasa un entero, un flotante o una cadena
				else {
					if (is_string($value)) {
						$value = static::$db_connection->qstr($value);
					} elseif (is_null($value)) {
						$value = "NULL";
					} elseif ($value instanceof DateTime) {
						$value = $value->format('\'Y-m-d H:i:s\'');
					}

					$explicitConditionSQL .= "$fieldLeft $op $value $operator ";
				}
			}
		}
		///////////////////////////////////////////////////////////////////////////////////////////////////
		// Recortamos el último operador de la consulta SQL (vamos, normalmente quitaremos el último AND de la consulta SQL)
		$explicitConditionSQL = substr($explicitConditionSQL, 0, strlen($explicitConditionSQL) - (strlen($operator) + 1));
		//var_dump($explicitConditionSQL);
		///////////////////////////////////////////////////////////////////////////////////////////////////
		// Si la condición explícita tiene valores ilegales (esto no debería pasar nunca)
		// Le ponemos una condición a TRUE siempre
		if (!is_string($explicitConditionSQL) or empty($explicitConditionSQL))
			$explicitConditionSQL = "1=1"; // Poco ortodoxo, pero paso, sinceramente
			
/////////////////////////////////////////////////////////////////////////////////////////////////
		// Generación de la consulta SQL
		// Selección de tuplas
		$sqlDistinct = "DISTINCT";
		if (!$distinct)
			$sqlDistinct = "";
		//$sql = "SELECT $sqlDistinct $fieldSQL \nFROM $tableSQL \nWHERE ($conditionSQL) AND ($explicitConditionSQL)";
		$sql = "SELECT $sqlDistinct $fieldSQL \nFROM {$aTables[0]} ";
		for ($i = 1; $i < count($aTables); $i++)
			$sql .= "INNER JOIN {$aTables[$i]} ";
		$sql .= "\n";
		$sql .=" ON ($conditionSQL) AND ($explicitConditionSQL)";
		// Si tiene un ORDEN, se lo metemos
		if ($order != null)
			$sql .= self::makeOrderBy($order) . "\n";
		// Si tiene un LÍMITE, se lo metemos
		if ($limit != null)
			$sql .= self::makeLimit($limit) . "\n";
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// Devolución de resultados
		//print $sql."<br/><br/>";
		$results = static::$db_connection->getAll($sql);
		return $results;
	}

	/**
	 * Unión natural de tablas con condiciones complejas.
	 * Las tablas se identificarán por su clave en el array $tables, de manera que la tabla "0" es la primera tabla, la tabla "1" es la segunda, etc.
	 * @param array $tables Array con los nombres de las tablas de las que se va a hacer la unión natural.
	 * @param mixed $fields Cadena con la selección o un array con los campos a seleccionar. Si se trata de un array, se usará un nombre del estilo <id_tabla>.<campo>.
	 * @param array $conditions Array de condiciones, del tipo <id_tabla>.<campo>=><condición>.
	 * @param array $conditions Array de condiciones extra, del tipo <id_tabla>.<campo>=><condición>.
	 * @param array $order Array con el orden de los campos, del tipo <id_tabla>.<campo>=><orden>.
	 * @param mixed $limit Límite de la consulta.
	 * @param string $operator Operador de conjunción de la condición de la consulta.
	 * @return array Array de arrays con los valores de los campos de la/s tabla/s indicada/s.
	 * */
	public static function distinctNaturalJoin($tables, $fields = "*", $conditions = null, $explicitCondition = null, $order = null, $limit = null, $operator = "and") {
		return self::naturalJoin($tables, $fields, $conditions, $explicitCondition, $order, $limit, $operator, $distinct = true);
	}

	/**
	 * Actualiza una tabla de la base de datos.
	 * @param string $table Nombre de la tabla a actualizar.
	 * @param array $newValues Array asociativo con los campos y sus nuevos valores.
	 * @param array|string Condición SQL. O bien es un array asociativo, o bien un string estilo "WHERE field1='' and field2..."
	 */
	public static function updateFields($table, $newValues, $condition) {
		return self::update($table, $newValues, self::makeWhereCondition($condition, false)); // No queremos que incluya el 'where' en la condición
	}

	/* Método que bloquea las tablas pasadas con argumento con el tipo de bloqueo indicado.
	 *
	 * LOCK TABLES
	  tbl_name [[AS] alias] lock_type
	  [, tbl_name [[AS] alias] lock_type] ...

	  lock_type:
	  READ [LOCAL]
	  | [LOW_PRIORITY] WRITE
	 *
	 * 	Formatos válidos
	 * lockTables(array(
	 * 	TABLA => array("lock_type"),
	 * 	TABLA => array("lock_type", "alias"),
	 * 	TABLA => array(array("lock_type"), array("lock_type", "alias1"), ...)
	 * 	TABLA => array(array("lock_type", "alias1"), array("lock_type", "alias2"), ...)
	 * ))
	 * 
	 * @param $tablas Array. Las tablas que se quieren bloquear se pasan como índice del array. Como valor de cada índice se pasa un array con uno o dos elementos donde el primero es el tipo de bloque a utilizar y el segundo el alias, en caso de querer utilizarlo, que se le da a la tabla.
	 * @pre El usuario debe asegurarse de que el array tablas es correcto y tiene algún elemento. Aquí no se va a realizar ninguna comprobación.
	 */

	public static function lockTables($tablas) {

		$sql = "";
		foreach ($tablas as $tabla => $opciones) {
			if ($sql != "")
				$sql .= ", ";

			// V2 => mantengo estos dos primeros ifs por compatibilidad hacia atrás.
			// Formato TABLA => array("READ") o TABLA => array("WRITE") ...
			if (count($opciones) == 1 && is_string($opciones[0])) {
				$sql .= " " . $tabla . " " . $opciones[0];
			}
			// Formato TABLA => array("READ", "alias") o TABLA => array("WRITE, "alias") ...
			elseif (count($opciones) == 2 && is_string($opciones[0]) && is_string($opciones[1])) {
				$sql .= " " . $tabla . " AS " . $opciones[1] . " " . $opciones[0];
			}
			// http://dev.mysql.com/doc/refman/5.5/en/lock-tables.html
			// You cannot refer to a locked table multiple times in a single query
			// using the same name. Use aliases instead, and obtain a separate lock
			// for the table and each alias.
			// Formato TABLA => array(array("READ"), array("READ", "alias1"), array("WRITE", "alias2"), ...)
			// o
			// TABLA => array(array("READ", "alias1"), array("READ", "alias2"), array("WRITE", "alias3"), ...)
			else {
				$sql2 = "";
				foreach ($opciones as $opcion) {
					if ($sql2 != "")
						$sql2 .= ", ";
					//var_dump($sql2);
					// Damos por hecho que son arrays con dos valores
					if (count($opcion) == 1 && is_string($opcion[0])) {
						$sql2 .= " " . $tabla . " " . $opcion[0];
					}
					// Formato TABLA => array("READ", "alias") o TABLA => array("WRITE, "alias") ...
					elseif (count($opcion) == 2 && is_string($opcion[0]) && is_string($opcion[1])) {
						$sql2 .= " " . $tabla . " AS " . $opcion[1] . " " . $opcion[0];
					}
				}
				$sql .= $sql2;
			}
		}
		$sql = "LOCK TABLES " . $sql;
		//print $sql; die();


		static::$db_connection->execute($sql);
	}

	/* Método que libera un bloqueo de tablas previo.
	 *
	 */

	public static function unlockTables() {

		static::$db_connection->execute("UNLOCK TABLES");
	}

	//FUNCIONES PARA TRANSACCIONES
	// para un correcto orden de la transacción debe hacerse lo siguiente
	// DBHelper::desactivateAutocommit(); //si usamos START TRANSACTION no hace falta
	// DBHelper::startTransaction();
	// 	operaciones de sql
	//si hay que volver 
	// DBHelper::rollback();
	//si todo correcto
	// DBHelper::commit();
	// DBHelper::activateAutocommit();//si usamos START TRANSACTION no hace falta
	//
	
	
	
	private static function activateAutocommit() {

		static::$db_connection->execute("SET autocommit = 1");
	}

	private static function desactivateAutocommit() {

		static::$db_connection->execute("SET autocommit = 0");
	}

	/*
	 *  work -> STRING indica el punto sobre el que hacer el commit
	 *  chain -> 0|1 -> indica si hace chain o no
	 *  release -> 0|1 -> si se hace release o no 
	 * 
	 * */

	public static function commit($work = null, $chain = null, $release = null) {
		$commit_sql = "";

		if ($work !== NULL)
			$commit_sql = $work;

		if ($chain !== NULL)
			if ($chain == 1)
				$commit_sql .= " AND CHAIN";
			else
				$commit_sql .= " AND NO CHAIN";

		if ($release !== NULL)
			if ($release == 1)
				$commit_sql .= " AND RELEASE";
			else
				$commit_sql .= " AND NO RELEASE";



		static::$db_connection->execute("commit $commit_sql");
	}

	public static function rollback($work = null, $chain = null, $release = null) {

		$sql = "ROLLBACK";
		if (!is_null($work))
			$sql = "ROLLBACK WORK";

		if ($chain !== NULL)
			if ($chain == 1)
				$sql .= " AND CHAIN";
			else
				$sql .= " AND NO CHAIN";

		if ($release !== NULL)
			if ($release == 1)
				$sql .= " AND RELEASE";
			else
				$sql .= " AND NO RELEASE";


		static::$db_connection->execute($sql);
	}

	/*
	 * transaction_characteristic:
	 *     WITH CONSISTENT SNAPSHOT
	 *   | READ WRITE
	 *   | READ ONLY
	 * 
	 * */

	public static function startTransaction($type = "begin", $work = "", $characteristics = []) {

		//con begin es necesario desactivar el autocommit;

		if ($type === "begin")
			$sql = "BEGIN {$work};";
		else {
			$sql = "START TRANSACTION";
			if (!empty($characteristics)) {
				foreach ($characteristics as $c)
					$sql .= " {$c},";
				//quitamos la última coma que se incluyó
				$sql = substr($sql, 0, -1);
			}

			$sql .= ";";
		}

		static::$db_connection->execute($sql);
	}

}

?>
