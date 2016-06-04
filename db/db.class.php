<?php

namespace lulo\db;

require LULO_DIR__DEPENDENCES__VENDOR . "/autoload.php";


/**
 * Dastabase abstraction layer.
 * Contains deprecated operations like 
 */
class DB {

	/** AdoDB database connection */
	protected static $db_connection = null;

	/** Database engine */
	const ENGINE = "mysql";

	/** Driver used to make the connection */
	const DRIVER = "mysqli";
	
	/** Blob max length */
	const BLOB_MAX_PACKET_LENGTH = 52428800;

	/**
	 * Create database connection.
	 * Initializes $db_connection attribute.
	 * @param string $server Server address.
	 * @param string $user Username.
	 * @param string $password Password used to authenticate user $user.
	 * @param string $database Database to connect.
	 * @param boolean $debug Should we use debug mode?
	 * */
	public static function connect($server, $user, $password, $database=null, $debug=false) {

		try {
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
					mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
					break;

				case "mssql2008":
				case "mssql2012":
				case "mssqlnative":
					static::$db_connection = ADONewConnection("mssqlnative");
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					static::$db_connection->Execute("USE " . $database);
					break;

				default:
					trigger_error("Not valid database connection ".static::DRIVER, E_USER_ERROR);
					break;
			}

			error_reporting($error);

			// Fetch data mode
			static::$db_connection->SetFetchMode(ADODB_FETCH_ASSOC);
		
			// Debug mode
			static::$db_connection->debug = $debug;
		} catch (Exception $e) {
			// If there is some exception show 503 error
			require_once __DIR__ . "error503.php";
			die();
		}
	}

	/**
	 * Describe table structure.
	 * @param string $table Database table.
	 * @return array with the information of the table. It depends on the DBMS.
	 * For example, for MySQL is:
	 * - Field: field name
	 * - Type: field type
	 * - Null: NO or YES,
	 * - Key: PRI if primary key, UNI if unique, MUL if multikey
	 * - Default: default value
	 * - Extra: if it is AUTOINCREMENT and more options.
	 */
	public static function describe($table) {
		return static::executeSqlTemplate("describe/query.twig.sql", $table);
	}
	
	/**
	 * Clear query cache.
	 * @return array Results of clearing the cache
	 * 	 */
	public static function clearQueryCache() {
		return static::executeSqlTemplate("cache/clear/query.twig.sql");
	}
	
	/**
	 * Executes a SQL statement loaded from a path.
	 * @param string $path Path of the SQL template.
	 * @param string $table Table name the statement will be execute.
	 * @param array $replacements Extra replacements needed for getting the SQL.
	 * @return array Array with the results of the statement execution.
	 * */
	protected static function executeSqlTemplate($path, $table=null, $replacements=[]){
		$sql = static::getSqlFromSqlTemplate($path, $table, $replacements);
		return static::$db_connection->execute($sql);
	}
	
	/**
	 * Gets the SQL of a SQL statement loaded from a path.
	 * @param string $path Path of the SQL template.
	 * @param string $table Table name the statement will be returned.
	 * @param array $replacements Extra replacements needed for getting the SQL.
	 * @return string SQL code with the replacements for table and other variables done.
	 * */
	protected static function getSqlFromSqlTemplate($path, $table=null, $replacements=[]){
		// Statement
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource($path);
		if(!is_null($table)){
			$replacements["table"] = $table;
		}
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
	
	/*
	 * Escape a variable to be included in a SQL statement..
	 * @param string $str String to escape.
	 * @return string Escaped $str string.
	 * */
	public static function qstr($str) {
		return static::$db_connection->qstr($str);
	}

	
	/**
	 * 	Get information about table indices.
	 *
	 * @param string $table Table anem
	 *
	 * @return Object ADORecordSet with the information of the index of the table.
	 * Note thata the information depends on the DBMS used.
	 * 
	 * For example, we show the MySQL returned fields:
	 * 			'Table' => string Table name
	 * 			'Non_unique' => 0 if can't contain duplicate values, 1 otherwise.
	 * 			'Key_name' => index name.
	 * 			'Seq_in_index' => string order in the column of index, starting from 1
	 * 			'Column_name' => string column name
	 * 			'Collation' => string 'A' (ascending order) o NULL (without order).
	 * 			'Cardinality' => string number of unique values of the index. Null if
	 *			there are no unique values.
	 * 			'Sub_part' => string indexed character number that are really indexed
	 *			useful for knowing if indexing is done partially. Null if full column is indexed.
	 * 			'Packed' => key is packed by this method, null if key is not packed.
	 * 			'Null' => string 'YES' if column can contain NULL; 'NO' otherwise.
	 * 			'Index_type' => index type ('BTREE', 'FULLTEXT', 'HASH', 'RTREE').
	 * 			'Comment' => comments about index column
	 *
	 */
	public static function showIndex($table) {
		return static::executeSqlTemplate("index/show/query.twig.sql", $table, $replacements=[]);
	}

	/**
	 * Get the last ID inserted in DB.
	 * Unique for session.
	 * Works in all DBMS that implements this
	 * Read http://dev.mysql.com/doc/refman/5.5/en/information-functions.html#function_last-insert-id for MySQL explanation
	 * @return last inserted id in database.
	 * */
	public static function getLastInsertId() {
		$results = static::executeSqlTemplate("get_last_inserted_id/query.twig.sql");
		return $results->fields["id"];
	}
	
	/**
	 * 	Insert a longblob
	 *  Used to avoid fully storing the blob in memroy
	 * 	@param string $tname table name.
	 * 	@param string $field name of the LONGBLOB field.
	 * 	@param string $value LONGBLOB value. MODIFIED.
	 * 	@param string $where_cond condition to get tuple.
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
	}

	
	/**
	 * Convert an array with new data to its SQL equivalent string.
	 * Basically, escape each one of the values of $new_data and prepare a
	 * VALUES part of INSERT SQL statement.
	 * @param array $new_data Array with data to use in an SQL INSERT.
	 * @return string SQL string with the values ready to be used.
	 */
	public static function convertDataArrayToSQLModificationString($new_data) {
		// Conversion for each value if it is needed
		foreach ($new_data as $nkey => &$nvalue) {
			if (is_string($nvalue)){
				$nvalue = static::$db_connection->qstr($nvalue);
			}
			elseif (is_null($nvalue)){
				$nvalue = "NULL";
			}elseif (is_bool($nvalue)){
				$nvalue = $nvalue ? 1 : 0;
			}elseif (is_float($nvalue)){
				$nvalue = str_replace(",", ".", $nvalue);
			}
		}
		// Construction of the SQL VALUES string
		$new_data_sql = "(" . implode(",", $new_data) . ")";
		return $new_data_sql;
	}

	/**
	 * Insertion of data. This function try to insert BLOBs in a non-memory exhausting operation.
	 * This method escape all values, don't worry about the data.
	 * @param string $tname Table name to make the insertion.
	 * @param array $data Associative array of the form <field name> => <value>
	 * @param mixed $blobfields List of columns that are blobs.
	 * @param mixed $clobfields List of columns that are clobs.
	 * @return boolean true if insertion went right, false otherwise.
	 */
	public static function insert($tname, &$data, $blobfields = array(), $clobfields = array()) {
		if (is_string($blobfields)) {
			$blobfields = preg_split('/ +/', $blobfields);
		}

		if (is_string($clobfields)) {
			$clobfields = preg_split('/ +/', $clobfields);
		}

		// Max size of blobs
		$num_blobs = count($blobfields) + count($clobfields);
		if ($num_blobs > 0) {
			$max_packet = static::BLOB_MAX_PACKET_LENGTH / $num_blobs;
		}

		// Standard attributes and NULLs
		foreach ($data as $field => $value) {
			if (in_array($field, $blobfields) || in_array($field, $clobfields)) {
				$new_data[$field] = "";
				if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
					// Assuming memory control is only needed in MySQL
					if (strlen($value) < $max_packet) {
						// If blob is small enough, insert it in the query
						$new_data[$field] = $value;
						unset($blobfields[array_search($field, $blobfields)]);
						unset($clobfields[array_search($field, $clobfields)]);
					}
				}
			} else {
				$new_data[$field] = $value;
			}
		}
		
		/// INSERT of non-blob attributes
		$data_keys = array_keys($new_data);
		$data_keys_sql = "(" . implode(",", $data_keys) . ")";
		// Escape of the values
		$new_data_sql = self::convertDataArrayToSQLModificationString($new_data);
		// SQL code for the insertion
		$insertSQL = "INSERT INTO $tname $data_keys_sql VALUES $new_data_sql";
		$ok = static::$db_connection->Execute($insertSQL);
		
		/// BLOBs & CLOBs
		if ($ok) {
			foreach ($new_data as $field => $value) {
				if ($value != ""){
					$where[] = $field . self::strEq($value);
				}
			}

			$where_cond = implode(" AND ", $where);

			foreach ($blobfields as $field) {
				if ($ok !== false && isset($data[$field])){
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateBlob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
				}
			}

			foreach ($clobfields as $field){
				if ($ok !== false && isset($data[$field])){
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateClob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
				}
			}
		}

		return $ok !== false;
	}


	/**
	 * Convert an array with new data to its SQL equivalent string.
	 * Basically, escape each one of the values of $new_data and prepare a
	 * VALUES part of UPDATE SQL statement.
	 * @param array $new_data Array with data to use in an SQL UPDATE.
	 * @return string SQL string with the values ready to be used.
	 */
	public static function convertDataArrayToSQLUpdateString($new_data) {
		// Creation of pairs of '<field>'=<value> escaping when needed
		$new_data_sql = "";
		foreach ($new_data as $nkey => $nvalue) {
			if (is_null($nvalue)){
				$new_data_sql .= "$nkey=NULL";
			}elseif (is_bool($nvalue)){
				$new_data_sql .= "$nkey=" . ($nvalue ? "1" : "0");
			}elseif (is_float($nvalue)){
				$new_data_sql .= "$nkey=" . str_replace(",", ".", $nvalue);
			}else{
				$new_data_sql .= $nkey . DB::strEq($nvalue);
			}
			$new_data_sql .= ",";
		}
		// Deletion of last comma
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

	public static function makeLimit($mylimit=null)
	{
		
		//esta función también hay que sobrescribirla siempre debemos empezar en la posición 0
		$limit = array(0,-1);
		
		if(is_numeric($mylimit)){
			$limit = array(0,$mylimit);
		}
		
		elseif(is_string($mylimit)){
			// Si alguien ha sido tan ceporro de meterle una cadena, comprueba si tiene la palabra clave LIMIT
			// En caso de que no, se le añade, y esperemos que haya metido el límite correctamente.
			preg_match("#[^\d]*(\d+)[^\d]*(\d+)?#", $mylimit, $aux);//extrae los valores usados en la consulta limit
			$limit[0] = $aux[1];
			if(isset($aux[2]))
				$limit[1] = $aux[2];
			
		}elseif(is_array($mylimit)){
			if(isset($mylimit[1])){
					$limit = $mylimit;
			} else {
					$limit[1] = $mylimit[0];
			}
		}

		return $limit;
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
	 * Return a tuple as an array.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return array Array that represents a tuple, so its keys are the names of the columns of the table.
	 */
	public static function getRow($table, $fields="*", $condition = null, $order = null) {
		$selected_fields = self::makeFieldsToSelect($fields);
		return static::$db_connection->getRow("SELECT $selected_fields FROM $table " . self::makeWhereCondition($condition) . " " . self::makeOrderBy($order) . " LIMIT 1");
	}

	/**
	 * Return one value of a tuple.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $selectedField Column which value we want to return.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @return string Value of selected field in table $table with condition $condition.
	 * 	 */
	public static function getOne($table, $selectedField, $condition=null) {
		// This query contains an implicit LIMIT 1, so only the value of the first
		// tuple will be returned
		return static::$db_connection->getOne("SELECT $selectedField FROM $table " . self::makeWhereCondition($condition));
	}

	/**
	 * Get array of arrays to loop to the results of a getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return array Array of arrays where each contained array represents a tuple.
	 * 	 */
	public static function getAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {
		$rs = static::getAllAsRecordSet($table, $fields, $condition, $order, $limit);
		return $rs->getArray();
	}

	
	/**
	 * Get recordset to loop to the results of a getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return object RecordSet object to loop through the results.
	 * 	 */
	public static function getAllAsRecordSet($table, $fields="*", $condition=null, $order=null, $limit=null)
	{
		$limit_interval = static::makeLimit($limit);
		$sql = static::generateSQLForGetAll($table, $fields, $condition, $order);
		$rs = static::$db_connection->SelectLimit($sql, $limit_interval[1], $limit_interval[0]);
		return $rs;
	}
	
	
	/**
	 * Get SQL for getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return string SQL code of the selection statement.
	 * 	 */
	private static function generateSQLForGetAll($table, $fields="*", $condition=null, $order=null){
		$fields_to_select = static::makeFieldsToSelect($fields);
		return "SELECT {$fields_to_select} FROM {$table} ".static::makeWhereCondition($condition)." ".static::makeOrderBy($order);
	}
	
	/**
	 * Return random tuples of a table.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $limit Limit of tuples. If null, no limit will be enforced.
	 */
	public static function getRandom($table, $fields = "*", $condition = null, $limit = null) {

		$field_selection = self::makeFieldsToSelect($fields);
		$where_condition_clause = self::makeWhereCondition($condition);

		$order_clause = "ORDER BY RAND()";
		$limit_clause = self::makeLimit($limit);

		$sql = "SELECT $field_selection FROM $table $where_condition_clause $order_clause $limit_clause";

		$res = static::$db_connection->getAll($sql);
		return $res;
	}


	/**
	 * Elimina la tabla $table.
	 * @param string $table Nombre de la tabla a eliminar.
	 */
	public static function dropTable($table) {

		return static::$db_connection->Execute("DROP TABLE $table");
	}


	/**
	 * Create an unique uuid.
	 * @param string $prefix UUID prefix.
	 * @return string UUID with 2 random chars [a-z] + 32 chars [0-9a-f] (based on md5)
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
	 * Show tables in the database.
	 * @param string $database Database name.
	 * @return array Array with the tables of $database database.
	 * */
	public static function showTables($database) {
		return static::executeSqlTemplate("index/show_tables/query.twig.sql", $table=null, $replacements=["database"=>$database]);
	}


	/**
	 * Check if table exists in current database.
	 * @param $table Table name.
	 * @return boolean true if table exists, false otherwise.
	 * */
	public static function existsTableInCurrentDatabase($table) {
		$tableList = self::showTables();
		return in_array($table, $tableList);
	}

	
	/**
	 * Check if table exists in current database.
	 * @param $table Table name.
	 * @return boolean true if table exists, false otherwise.
	 * */
	public static function existsTable($table) {
		return self::existsTableInCurrentDatabase($table);
	}


	/**
	 * Executes arbitrary SQL code
	 * @param string $sqlCode SQL code to execute.
	 * @return mixed What the execution returns. Usually an AdoDB object.
	 * */
	public static function execute($sqlCode) {
		$results = static::$db_connection->execute($sqlCode);
		return $results;
	}

	
	/**
	 * Return create table SQL code.
	 * @params string $tableName Table name whose create table statement is needed.
	 * @return string SQL code with the creation of table $tableName.
	 */
	public static function getCreateTable($tableName) {
		return static::executeSqlTemplate("create/get_from_table.twig.sql", $tableName);
	}
		

	/**
	 * Create a table in the DBMS
	 * @param string $tableName Table name.
	 * @param array $tableFields Table fields.
	 * @return boolean true if creation went right, false otherwise.
	 * */
	public function createTable($tableName, $tableFields) {
		return static::$db_connection->CreateTableSQL($tableName, $tableFields);
	}

	/**
	 * Return the field length of a tuple.
	 * @param string $table Table name.
	 * @param string $field Field name.
	 * @param array $condition Condition of the selected tuple.
	 * @return integer Bytes of the field of the selected tuple.
	 * */
	private static function fieldLength($table, $field, $condition=null) {
		$lengthField = "_length_" . $field;

		$sql = "SELECT " . static::$db_connection->length . "($field) AS $lengthField FROM $table " . self::makeWhereCondition($condition);
		$res = static::$db_connection->getRow($sql);
		if (count($res) == 0 or $res == null or ! isset($res[$lengthField])){
			return null;
		}
		return (int) ($res[$lengthField]);
	}

	/**
	 * Check if value in a field is NULL.
	 * @param string $table Table name.
	 * @param string $field Field name.
	 * @param array $condition Condition of the selected tuple.
	 * @return boolean true if field value is NULL, false otherwise.
	 * */
	public static function fieldIsNull($table, $field, $condition = null) {
		$length = self::fieldLength($table, $field, $condition);
		if ($length == null){
			return true;
		}
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
		if ($pos === false){
			return $tableName;
		}
		return substr($tableName, 0, $pos);
	}

	
	/**
	 * Natural join between several tables.
	 * Implements INNER JOIN between several tables.
	 * - First key in $tables is main table
	 * - The other tables are joined to main table.
	 * 
	 * @param array $tables Array Hash with the tables as the keys. The values are the fields to be selected of each table. If "*", all fields will be selected.
	 * @param array $onConditions Conditions for each table. For each table (table_i), can contains two keys:
	 * - "on" => conditions that link fields of different tables "tabla_i-1.field"=>"tabla_i.field"
	 * - "extra" => extra conditions  on table_i
	 * @param Array $whereConditions Conditions on main table (first table in $tables).
	 * @param Array $params extra options to custom INNER JOIN results:
	 * 	"order" => order results "table_name"=>array("field"=>ORDER, "field"=>ORDER)
	 * 	"limit" => limit results
	 * 	"debug" => show generated SQL and stops
	 * 	"distinct" => should the results be distinct?
	 * 	"explain" => adds EXPLAIN keyword to obtain metainformation about performance.
	 * 	"straight_join" => adds STRAIGHT_JOIN in SELECT.
	 * @return array Array of arrays with the values of the selected fields.
	 * */
	public static function join($tables, $onConditions=null,  $whereConditions=null, $params=null)
	{
		$rs = static::joinAsRecordSet($tables, $onConditions,  $whereConditions, $params);
		return $rs->getArray();
	}

	/**
	 * Natural join between several tables.
	 * Implements INNER JOIN between several tables.
	 * - First key in $tables is main table
	 * - The other tables are joined to main table.
	 * 
	 * @param array $tables Array Hash with the tables as the keys. The values are the fields to be selected of each table. If "*", all fields will be selected.
	 * @param array $onConditions Conditions for each table. For each table (table_i), can contains two keys:
	 * - "on" => conditions that link fields of different tables "tabla_i-1.field"=>"tabla_i.field"
	 * - "extra" => extra conditions  on table_i
	 * @param Array $whereConditions Conditions on main table (first table in $tables).
	 * @param Array $params extra options to custom INNER JOIN results:
	 * 	"order" => order results "table_name"=>array("field"=>ORDER, "field"=>ORDER)
	 * 	"limit" => limit results
	 * 	"debug" => show generated SQL and stops
	 * 	"distinct" => should the results be distinct?
	 * 	"explain" => adds EXPLAIN keyword to obtain metainformation about performance.
	 * 	"straight_join" => adds STRAIGHT_JOIN in SELECT.
	 * @return recordset Recordset with the values of the selected fields.
	 * */	
	public static function joinAsRecordSet($tables, $onConditions=null,  $whereConditions=null, $params=null)
	{
		$tablesDict = array();
		$tablesOrig = array();

		// Fields to select, if not array, take all (*)
		$fieldSQL = "";
		$i = 0;
		foreach($tables as $table_name=>$fields){
			$tablesOrig[] = $table_name;
			if(!is_array($fields) && !is_null($fields)){
				$table_name = $fields;
				$fieldSQL .= "tabla_$i.*,";
			}
			else{
				if(!empty($fields)){
					foreach($fields as $f){
						$fieldSQL .= "tabla_$i.$f,";
					}
				}

			}
			
			$table_name = preg_replace("#-alias-$#", "",$table_name);
			$tablesDict[] = $table_name;
			$i++;
		}

		// Erasing the last comma
		$fieldSQL = substr($fieldSQL, 0, strlen($fieldSQL)-1);

		// Getting ON conditions
		$i = 1;
		$innerSql = "";
		
		$join = "INNER";
		if(isset($params["join"])){
			$join = strtoupper($params["join"]);
		}
		
		$extraWhereCond = array();	
		foreach($onConditions as $oC){
			if(!isset($oC["on"]))
				$on = array_pop($oC);
			else
				$on = $oC["on"];
			
			$joinCond = static::makeJoinCondition($on, "tabla_".($i-1), "tabla_$i");
			$extraJoin = "";
			if(isset($oC["extra"]) && !empty($oC["extra"])){
				$extraJoinCond = array();
				foreach($oC["extra"] as $field=>$cond){
					if($field != "OR" and $field != "AND"){
							$extraJoinCond["tabla_$i.".$field] = $cond;
					}else{
						foreach($cond as $f=>$c)
							$extraJoinCond[$field]["tabla_$i.".$field] = $cond;
					}
				}
				$extraJoin = " AND ".static::makeWhereCondition($extraJoinCond, false);
			}
			
			// Conditions 
			if(isset($oC["where"]) && !empty($oC["where"])){
				$extraJoinCond = array();
				foreach($oC["where"] as $field=>$cond){
					if($field != "OR" and $field != "AND"){
							$extraWhereCond["tabla_$i.".$field] = $cond;
					}else{
						foreach($cond as $f=>$c)
							$extraWhereCond[$field]["tabla_$i.".$field] = $cond;
					}
				}
			}
			
			$innerSql .= "$join JOIN {$tablesDict[$i]} tabla_$i ON $joinCond $extraJoin \n";
			$i++;
		}
		
		// Table 0 conditions in WHERE clause
		if(!is_null($whereConditions)){
			
			foreach($whereConditions as $field=>$cond){
				if($field != "OR" and $field != "AND"){
						$extraWhereCond["tabla_0.".$field] = $cond;
				}else{
					foreach($cond as $f=>$c)
						$extraWhereCond[$field]["tabla_0.".$field] = $cond;
				}
			}
		}
		$where = static::makeWhereCondition($extraWhereCond);
		
		// SQL code generation
		$sqlDistinct = "DISTINCT";
		if(!isset($params["distinct"]) || !$params["distinct"]){
			$sqlDistinct = "";
		}
		
		$sqlExplain = "EXPLAIN";
		if(!isset($params["explain"]) || !$params["explain"]){
			$sqlExplain = "";
		}
			
		$sqlStraight = "STRAIGHT_JOIN";
		if(!isset($params["straight_join"]) || !$params["straight_join"]){
			$sqlStraight = "";
		}

		$sql = "$sqlExplain SELECT $sqlStraight $sqlDistinct $fieldSQL \nFROM {$tablesDict[0]} tabla_0 \n $innerSql \n$where ";
		
		// ORDER of the query
		if(isset($params["order"]) && $params["order"] != null){
			$ordenFinal = array();
			foreach($params["order"] as $table_name=>$fields){
				foreach($fields as $f=>$order){
					$pos = array_search($table_name, $tablesOrig);
					$ordenFinal["tabla_$pos.".$f] = $order;
				}
			}
			$sql .= static::makeOrderBy($ordenFinal)."\n";
		}
		
		// Limit of the query
		if(isset($params["limit"]) && $params["limit"] != null)
			$limit = $params["limit"];
		else
			$limit = null;
		
		$limit = static::makeLimit($limit);
		
		// Results
		if(isset($params["debug"])){
			print $sql."<br/><br/>";die;
		}

		$rs = static::$db_connection->SelectLimit($sql,$limit[1],$limit[0]);
		
		return $rs;
	}


	/**
	 * Update a table of our current database.
	 * @param string $table Name of the table to update.
	 * @param array $newValues Associative array with pairs of <field>=><new value>.
	 * @param array|string SQL condition. One associative array or a string "WHERE field1='' and field2..."
	 */
	public static function updateFields($table, $newValues, $condition) {
		return self::update($table, $newValues, self::makeWhereCondition($condition, false));
	}

	
	/* Lock tables.
	 *
	 * LOCK TABLES
	  tbl_name [[AS] alias] lock_type
	  [, tbl_name [[AS] alias] lock_type] ...

	  lock_type:
	  READ [LOCAL]
	  | [LOW_PRIORITY] WRITE
	 *
	 * 	Valid formats:
	 * lockTables(array(
	 * 	TABLA => array("lock_type"),
	 * 	TABLA => array("lock_type", "alias"),
	 * 	TABLA => array(array("lock_type"), array("lock_type", "alias1"), ...)
	 * 	TABLA => array(array("lock_type", "alias1"), array("lock_type", "alias2"), ...)
	 * ))
	 * 
	 * @param $tablas Array. Tables that we want to be locked are index of the array. Values are the type lock and the alias.
	 */
	public static function lockTables($tablas) {

		$sql = "";
		foreach ($tablas as $tabla => $opciones) {
			if ($sql != "")
				$sql .= ", ";

			// Legacy compatibility.
			// Format TABLA => array("READ") o TABLA => array("WRITE") ...
			if (count($opciones) == 1 && is_string($opciones[0])) {
				$sql .= " " . $tabla . " " . $opciones[0];
			}
			// Format TABLA => array("READ", "alias") o TABLA => array("WRITE, "alias") ...
			elseif (count($opciones) == 2 && is_string($opciones[0]) && is_string($opciones[1])) {
				$sql .= " " . $tabla . " AS " . $opciones[1] . " " . $opciones[0];
			}
			// http://dev.mysql.com/doc/refman/5.5/en/lock-tables.html
			// You cannot refer to a locked table multiple times in a single query
			// using the same name. Use aliases instead, and obtain a separate lock
			// for the table and each alias.
			// Format TABLA => array(array("READ"), array("READ", "alias1"), array("WRITE", "alias2"), ...)
			// or
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

	
	/* 
	 * Unlock tables of a previous lock.
	 */
	public static function unlockTables() {

		static::$db_connection->execute("UNLOCK TABLES");
	}

	// Transaction functions
	// USE:
	// DBHelper::desactivateAutocommit(); //si usamos START TRANSACTION no hace falta
	// DBHelper::startTransaction();
	// 	SQL OPERATIONS
	// In case of error:
	//   DBHelper::rollback();
	// If everything went right:
	//   DBHelper::commit();
	//   DBHelper::activateAutocommit();//si usamos START TRANSACTION no hace falta
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

		// If using BEGIN, deactivate AUTOCOMMIT
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
