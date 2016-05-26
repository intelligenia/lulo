<?php

namespace lulo\models\traits;

use lulo\containers\Collection as Collection;

trait Load {
	
	/**
	 * Obtiene el SQL de obtención de las columnas del SELECT SQL.
	 * @return string Cadena con los atributos de este modelo separados por comas.
	 * */
	private static function getSelectColumnExpressionSQL(){
		$class_attributes = static::initAttributesMetaInformation();
		$str = $class_attributes["attribute_names_str"];
		return $str;
	}
	
	/**
	* Carga el $blobName, que está en BD, asociado al objeto actual en una cadena (string).
	* @return string String que contiene el blob $blobName del objeto actual.
	*/ 
	public function dbBlobLoad($blobName){
		// DB connection
		$db = static::DB;
		
		// Condición del objeto actual
		$condition = $this->getPk();
		
		// Obtiene el blob como string 
		$blob = $db::getOne(static::getTableName(), $blobName, $condition);
		return $blob;
	}

	
	/**
	* Informa si el blob $blobName, que está en BD, asociado al objeto actual es NULL.
	* @return boolean true si el blob $blobName es NULL, false en otro caso.
	*/ 
	public function dbBlobIsNull($blobName){
		// DBHelper
		$db = static::DB;
		
		// Condición del objeto actual
		$condition = $this->getPk();
		
		// Obtiene si el blob es NULL
		$blobIsNull = $db::fieldIsNull(static::getTableName(), $blobName, $condition);
		return $blobIsNull;
	}
	
	
	/**
	 * Carga varios objetos de este modelo a partir de la representación de cadena de su PK.
	 * @param array $strPks Array con la representación en cadena de la clave primaria de cada objeto.
	 * @param array $extraConditions Condiciones extra que ha de cumplir el objeto a cargar.
	 * @return array Array de objetos que tenían las claves primarias $strPks.
	 * */
	public static function dbLoadAllFromStrPk($strPks, $extraConditions=null){
		// Objetos resultantes
		$objects = [];
		// Para cada objeto, lo cargamos a partir de su PK
		foreach($strPks as $strPk){
			$object = static::dbLoadFromStrPk($strPk, $extraConditions);
			$objects[] = $object;
		}
		// Devolvemos el array de objetos
		return $objects;
	}
	
	
	/**
	 * Carga un objeto de este modelo a partir de la representación de cadena de su PK.
	 * @param array $strPks Array con la representación en cadena de la clave primaria de cada objeto.
	 * @param array $extraConditions Condiciones extra que ha de cumplir el objeto a cargar.
	 * @return object Objeto Collection con los objetos que tenían las claves primarias $strPks.
	 * */
	public static function dbLoadAllFromStrPkAsCollection($strPks, $extraConditions=null){
		$objects = static::dbLoadAllFromStrPk($strPks, $extraConditions);
		return new Collection($objects);
	}
	
	
	/**
	 * Carga una array de objetos de la BD según una condición
	 * @param array $condition Condición compleja de selección de objetos. Si es null (valor por defecto), no se aplica ninguna condición.
	 * Es un array de pares atributo=>"valor" (si se desea usar la selección por identidad) o de pares atributo=>array(<OP>, <VALOR>) donde <OP> es una operación de selección (=, >, <, >=, <=) y <VALOR> es el valor de comparación sobre el que se aplicará ese operador
	 * @param array $order Orden que han de llevar. Array con pares <campo>=>"asc|desc". Por defecto es null.
	 * @param array|string $limit Límite de objetos que se devolverán. Por defecto es null.
	 * @param string Tipo de contenedor que se va a devolver.
	 * @return array Array de objetos que cumplen esa condición
	 * @pre El operador de comparación pasado en $condition ha de ser consistente con el tipo de dato de la columna de la tabla.
	*/ 
	public static function dbLoadAll($condition=null, $order=null, $limit=null, $container="collection"){
		// El onmipresente conector con la base de datos
		$db = static::DB;
		// Obtención de los campos que se cargan den el SELECT
		$columnsStr = static::getSelectColumnExpressionSQL();
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalCondition = static::getBaseCondition($condition);
		// TODOs :
		// 1.- Comprobar que no existe ningún campo que sea una relación
		// 2.- Comprobar que todos los campos de las condiciones existan
		$rows = $db::getAll(static::getTableName(), $columnsStr, $finalCondition, $order, $limit);
		if(count($rows)==0 or $rows==null or $rows==false){
			if($container === "collection"){
				return new Collection();
			}
			return null;
		}
		
		// Devolvemos en función del contenedor que se haya seleccionado
		$arrayOfObjects = static::arrayFactoryFromRows($rows);
		if($container === "collection"){
			return new Collection($arrayOfObjects);
		}
		return $arrayOfObjects;
	}
	
	
	/**
	* Carga una array de objetos de la BD según una consulta SQL.
	* NOTA: la consulta SQL debe devolver la clave primaria para poder cargar los objetos.
	* 
	* @param string $sql Sentencia SQL a ejecutar que devuelve un array de objetos de esta clase.
	* @return mixed Array de objetos cargados mediante la consulta SQL, o null si la consulta no ha obtenido resultados.
	*/ 
	public static function dbLoadAllUsingSQL($sql, $type="collection"){
		$db = static::DB;
		$res = $db::execute($sql);
		$out = array();
		foreach($res as $r){
			$out[] = static::dbLoad($r);
		}                
        if($type == "collection"){
			if(is_null($out) or count($out)==0){
				return new Collection();
            }
            return new Collection($out);
        }
		return $out;
	}
	
	
	/**
	* Carga un objeto de este modelo según una condición.
	* @param array $condition Condición compleja de selección de objetos. Por defecto es null (sin condición, devuelve el objeto que contenga la primera fila que haya en la tabla)
	* Es un array de pares atributo=>"valor" (si se desea usar la selección por identidad) o de pares atributo=>array(<OP>, <VALOR>) donde <OP> es una operación de selección (=, >, <, >=, <=) y <VALOR> es el valor de comparación sobre el que se aplicará ese operador
	* @return object Primer objeto seleccionado de la BD que cumple esa condición
	* @pre El operador de comparación pasado en $condition ha de ser consistente con el tipo de dato de la columna de la tabla.
	*/ 
	public static function dbLoad($condition=null, $order=null){
		// Obtención de los campos de este modelo que se pueden cargar
		$columnsStr = static::getSelectColumnExpressionSQL();
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalCondition = static::getBaseCondition($condition);
		$db = static::DB;
		$row = $db::getRow(static::getTableName(), $columnsStr, $finalCondition, $order);
		if(count($row)==0 or $row==null or $row==false){
			return null;
		}
		return static::factoryFromRow($row);
	}
	
	/**
	* Carga el objeto de BD según sus campos de clave primaria.
	* El parámetro $pk es un array de pares atributo=>valor.
	* @param array Array con la clave primaria del a cargar.
	* @return object Objeto con esa clave primaria si existe, null en otro caso.
	*/ 
	public static function dbLoadByPK($pk){
		$condition = $pk;
		return static::dbLoad($condition);
	}
	
	
	/**
	 * Carga un objeto de este modelo a partir de la representación 
	 * @param string $strPk Representación en cadena de la clave primaria.
	 * @param array $extraConditions Condiciones extra que ha de cumplir el objeto a cargar.
	 * @return object Objeto a cargar con la clave primaria $strPk.
	 * */
	public static function dbLoadFromStrPk($strPk, $extraConditions=null){
		/// Obtención de la clave primaria como condición base
		// de selección de objeto
		$condition = static::strToPk($strPk); 
		
		// Condiciones extra (si existen)
		if(is_array($extraConditions) and count($extraConditions)>0){
			foreach($extraConditions as $attribute=>$value){
				$condition[$attribute] = $value;
			}
		}
		// Carga del objeto a partir de la clave primaria
		$object = static::dbLoad($condition);
		
		return $object;
	}

	/**
	* Cuenta el número de objetos que hay en la BD que cumplen una condición
	* @param array $condition Condición compleja de selección de objetos. Por defecto es null (sin condición, devuelve el objeto que contenga la primera fila que haya en la tabla)
	* Es un array de pares atributo=>"valor" (si se desea usar la selección por identidad) o de pares atributo=>array(<OP>, <VALOR>) donde <OP> es una operación de selección (=, >, <, >=, <=) y <VALOR> es el valor de comparación sobre el que se aplicará ese operador
	* @return integer El número de objetos (tuplas en la BD) que verifican la condición pasada como parámetro
	* @pre El operador de comparación pasado en $condition ha de ser consistente con el tipo de dato de la columna de la tabla.
	*/ 
	public static function dbCount($condition=null){
		$db = static::DB;
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalCondition = static::getBaseCondition($condition);
		// Cuenta de objetos
		$count = $db::count(static::getTableName(), $finalCondition);
		return $count;
	}
	
	
	/**
	* Informa si existe un objeto que cumpla con una condición dada.
	* @param array $condition Condición compleja de selección de objetos. Por defecto es null (sin condición, devuelve el objeto que contenga la primera fila que haya en la tabla)
	* Es un array de pares atributo=>"valor" (si se desea usar la selección por identidad) o de pares atributo=>array(<OP>, <VALOR>) donde <OP> es una operación de selección (=, >, <, >=, <=) y <VALOR> es el valor de comparación sobre el que se aplicará ese operador
	* @return boolean true si existe un objeto en base de datos que cumpla la condición pasada como parámetro, false en otro caso.
	* @pre El operador de comparación pasado en $condition ha de ser consistente con el tipo de dato de la columna de la tabla.
	*/ 
	public static function dbExists($condition=null){
		$db = static::DB;
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalCondition = static::getBaseCondition($condition);
		// Cuenta de objetos y comparacón para ver si hay más de 0
		$count = $db::count(static::getTableName(), $finalCondition);
		return ($count > 0);
	}
	
	
	/**
	* Informa si no existe un objeto que cumpla con una condición dada.
	* @param array $condition Condición compleja de selección de objetos. Por defecto es null (sin condición, devuelve el objeto que contenga la primera fila que haya en la tabla)
	* Es un array de pares atributo=>"valor" (si se desea usar la selección por identidad) o de pares atributo=>array(<OP>, <VALOR>) donde <OP> es una operación de selección (=, >, <, >=, <=) y <VALOR> es el valor de comparación sobre el que se aplicará ese operador
	* @return boolean true si no existe un objeto en base de datos que cumpla la condición pasada como parámetro, false en otro caso.
	* @pre El operador de comparación pasado en $condition ha de ser consistente con el tipo de dato de la columna de la tabla.
	*/ 
	public static function dbNotExists($condition=null){
		return !(static::dbExists($condition));
	}
	
	
	/**
	* Informa de los objetos que contienen en alguno de sus campos un valor igual al pasado como parámetro.
	* @param string $search Cadena de búsqueda.
	* @return array Array con los objetos que cumplen que uno de sus campos es igual a $search.
	*/ 
	public static function dbSearch($search){
		$class_attributes = static::initAttributesMetaInformation();
		$columnsStr = static::getSelectColumnExpressionSQL();
		$condition = array();
		$attribute_names = $class_attributes["attribute_names"];
		foreach($attribute_names as $attribute){
			$condition[$attribute] = array("like",$search);
		}
		$db = static::DB;
		return static::arrayFactoryFromRows($db::getDisjunctiveAll(static::getTableName(), $columnsStr, $condition));
	}
	
	
	/**
	* Informa de los objetos que contienen en alguno de sus campos de texto una cadena igual al que sea pasa como parámetro.
	* @param string $search Cadena de búsqueda.
	* @param string $condition Condición extra adicional que se ejecuta sobre la búsqueda. Por defecto es null.
	* @param string $order Orden de los objetos que se devolverán, si es null se obvia. Por defecto es null.
	* @param string $limit Número máximo de objetos a cargar, si es null se devuelven todos. Por defecto es null.
	* @return array Array con los objetos que cumplen que uno de sus campos de texto es igual a $search.
	*/ 
	public static function dbTextSearch($search, $condition=null, $order=null, $limit=null){
		$class_attributes = static::initAttributesMetaInformation();
		$columnsStr = static::getSelectColumnExpressionSQL();
		$likeCondition = array();
		$attribute_names = $class_attributes["attribute_names"];
		foreach($attribute_names as $attribute){
			$condition[$attribute] = array("like","%$search%");
		}
		$dbHelper = static::DB;
		if($condition!=null){
			return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAllWithExtraConditions(static::getTableName(), $columnsStr, $likeCondition, $condition, $order, $limit));
		}
		return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAll(static::getTableName(), $columnsStr, $likeCondition, $order, $limit));
	}
	
	
	/**
	* Informa de los objetos que contienen en alguno de los campos (pasados como claves de los elementos del array) un valor determinado (valor del elemento del array).
	* @param array $search Campos y cadenas de búsqueda..
	* @return array Array con los objetos que cumplen la condición dada por el array $search.
	*/ 
	public static function dbSearchIn($search){
		$columnsStr = static::getSelectColumnExpressionSQL();
		$dbHelper = static::DB;
		return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAll(static::getTableName(), $columnsStr, $search));
	}
	
}

