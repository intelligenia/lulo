<?php

namespace lulo\models\traits;

use lulo\containers\Collection as Collection;
use lulo\containers\QuerySet as QuerySet;

trait LoadRelated {
	/**
	 * Carga información de tablas asociadas pero sin basarse en modelos.
	 * @param $relationName Nombre de la relación.
	 * @param $remoteCondition Nombre de la condición de los objetos remotos.
	 * @param $order Orden de los objetos remotos.
	 * @param $limit Límite de los objetos remotos.
	 * @return array Array simple si la relación es *ToOne, o array de arrays si es OneToMany.
	 * */
	protected static function _dbLoadRelatedNoModel($relationName, $remoteCondition=[], $order=null, $limit=null, $container="array"){
		// Objeto DBHelper en un variable para poder llamar a
		// su método estático de carga
		$db = static::DB;
		
		// Si vamos a cargar varias relaciones
		$relationship = static::metaGetRelationship($relationName);
		
		// Tipo de relación (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		// Tabla remota que se desea cargar
		$relatedTable = $relationship["table"];
		
		// Tiene definidos ¿los campos que hemos de traernos?
		$columns = $relationship["attributes"];
		$columnsStr = implode(",", $columns);
		
		// Indica si se ha de aplicar el modificador DISTINCT a la
		// consulta de INNER JOIN final
		$isDistinct = (isset($relationship["distinct"]) and $relationship["distinct"]);
		if($isDistinct){
			$columnsStr = "DISTINCT {$columnsStr}";
		}
		
		// Relación muchos a uno
		if($relationshipType == "ManyToOne" or $relationshipType == "OneToOne"){
			$row = $db::getRow($relatedTable, $columnsStr, $remoteCondition, $order);
			if(count($row)==0 or $row==null or $row==false){
				return null;
			}
			return $row;
		}
		
		// Relación uno a muchos
		if($relationshipType == "OneToMany"){
			if($container == "collection"){
				return new Collection($db::getAll($relatedTable, $columnsStr, $remoteCondition, $order, $limit));
			}
			if($container == "queryset"){
				return new QuerySet($db::getAllAsRecordSet($relatedTable, $columnsStr, $remoteCondition, $order, $limit));
			}
			return $db::getAll($relatedTable, $columnsStr, $remoteCondition, $order, $limit);
		}
		
		// No se aceptan relaciones de muchos a muchos
		throw \InvalidArgumentException("Sólo se aceptan los tipos 'OneToMany' y 'ManyToOne' en las relaciones a tabla");
	}
	
	
	/**
	* Devuelve los objetos relacionado de la relación $relationName con el objeto actual según las relaciones definidas en esta clase.
	* @param string $relationName Nombre de la relación.
	* @param array $remoteCondition Condición extra de selección de objetos remotos. Si es null, no se aplica ninguna condición extra. Por defecto es null.
	* @param array $order Orden que han de llevar. Array con pares <campo>=>"asc|desc". Por defecto es null.
	* @param array|string $limit Límite de objetos que se devolverán. Por defecto es null.
	* @param array $container Tipo de contenedor a usar en el caso de que la relación sea "a-muchos".
	* @return array|collection|object Array, colección u objeto remoto.
	*/ 
	protected function _dbLoadRelatedManyToMany($relationName, $remoteCondition=[], $order=null, $limit=null, $container="collection"){
		// Si vamos a cargar varias relaciones
		$relationship = static::metaGetRelationship($relationName);
		
		// Modelo que hemos de cargar
		$foreignClass = $relationship["model"];
		
		// Tablas de nexo
		$junctionTables = $relationship["junctions"];
		
		// Indica si se ha de aplicar el modificador DISTINCT a la
		// consulta de INNER JOIN final
		$isDistinct = (isset($relationship["distinct"]) and $relationship["distinct"]);
		
		// Las tablas son (en este orden): los nexos y la tabla destino
		// La tabla destino se incluye sólo en los campos a seleccionar
		// porque se presupone su presencia
		$tables = array_merge([], $junctionTables, [$foreignClass::getTableName()]);
		$numTables = count($tables);
		
		// Para las tablas intermedias, sólo para las tablas nexo se incluyen
		// los campos y sólo si así lo indica la relación
		$fieldsByTable = [];
		$TABLE_NAME = static::getTableName();
		foreach(array_merge([$TABLE_NAME],$tables) as $tableName){
			$fieldsByTable[$tableName] = [];
		}
		
		// Si hemos establecido que se incluyan los campos de las tablas nexo
		// en la relación, incluimos los atributos del nexo
		if(
			(isset($relationship["include_junctions_attributes"]) and $relationship["include_junctions_attributes"]) or
			(isset($relationship["include_nexus_attributes"]) and $relationship["include_nexus_attributes"]) or 
			(isset($relationship["include_nexii_attributes"]) and $relationship["include_nexii_attributes"])
			){
				// Para cada tabla, de nexo añadimos todos los datos del nexo
				foreach($junctionTables as $junctionTableName){
					$fieldsByTable[$junctionTableName] = ["*"];
				}
		}
		
		// Los campos son todos los campos que no sean blob de la tabla de destino (última tabla)
		$fieldsByTable[$foreignClass::getTableName()] = $foreignClass::metaGetSelectableAttributes();
		
		// Condiciones sobre la tabla original (0)
		$localConditions = $this->getPk();
		
		// Condiciones entre cada tabla y la siguiente
		$conditions = $relationship["conditions"];
		$relations = [];
		$i = 0;
		foreach($conditions as $condition){
			$relations[$i] = array('on'=>$condition);
			$i++;
		}
		
		// Condición explícita
		// Condiciones de los objetos remotos
		if(is_array($remoteCondition)){
			// Tenemos una condición compleja
			if(isset($remoteCondition["remoteObjectConditions"]) and isset($remoteCondition["nexiiConditions"])){
				// Condiciones sobre los objetos remotos (objetos a cargar)
				$relations[$numTables-1]["extra"] = $remoteCondition["remoteObjectConditions"];
				// Condiciones sobre cada uno de los nexos
				$i = 0;
				foreach($remoteCondition["nexiiConditions"] as $nexusCondition){
					$relations[$i]["extra"] = $nexusCondition;
					$i++;
				}
			}
			// La condición es básica, en el sentido de que es sólo para
			// los destinatarios
			else if(!isset($remoteCondition["remoteObjectConditions"]) and !isset($remoteCondition["nexiiConditions"])){
				$relations[$numTables-1]["extra"] = $remoteCondition;
			}
			else{
				throw \Exception("Se esperaba ['remoteObjectConditions'=>[], 'nexiiConditions'=>[ [Condiciones Nexo1], [Condiciones Nexo2], ..., [Condiciones NexoN] ]]");
			}
		}
		
		/////////////////
		// Parámetros extra sobre la consulta de INNER JOIN
		$params = [];
		
		// ¿Ha de comprobar unicidad de cada uno de los resultados?
		$params['distinct'] = $isDistinct;
		
		// Orden de los elementos remotos (no tiene sentido tener otro orden)
		$params['order'] = array();
		if(!is_null($order)){
			$params['order'][$foreignClass::getTableName()] = $order;
		}
		
		// Límite de la consulta
		$params['limit'] = $limit;
		
		//////////////////
		// Consulta SQL a la base de datos
		$db = static::DB;
		
		if($container=="queryset"){
			return new QuerySet($db::joinAsRecordSet($fieldsByTable, $relations, $localConditions, $params), $foreignClass);
		}
		
		$rows = $db::join($fieldsByTable, $relations, $localConditions, $params);
		// Obtención de los objetos remotos
		$foreignObjects = $foreignClass::arrayFactoryFromRows($rows);
		
		if($container=="collection"){
			return new Collection($foreignObjects);
		}
		
		return $foreignObjects;
	}
	
	
	/**
	 * Obtiene la condición para una relación de clave externa.
	 * @param array $relationship Propiedades de la relación.
	 * @param array $expliticRemoteConditions Condiciones explícitas de los objetos remotos. Por defecto es un array vacío.
	 * */
	protected function getForeignKeyRemoteCondition($relationship, $expliticRemoteConditions=[]){
		/////////////
		// Para el caso de que no sea una relación muchos a muchos, 
		// no necesitamos una table NEXO.
		/////////////
		// Condición de carga del objeto, inicialmente tiene la condición
		$remoteCondition = [];
		$foreignCondition = $relationship["condition"];
		foreach($foreignCondition as $localAttribute=>$remoteAttribute){
			$remoteCondition[$remoteAttribute] = $this->getAttribute($localAttribute);
		}
		// Añadimos las condiciones explícitas sobre los objetos remotos
		// que le pasamos como parámetro
		if(is_array($expliticRemoteConditions) and count($expliticRemoteConditions)>0){
			foreach($expliticRemoteConditions as $attr=>$value){
				$remoteCondition[$attr] = $value;
			}
		}
		// Devolvemos la condición sobre los objetos remotos
		return $remoteCondition;
	}
	
	
	/**
	* Devuelve el objeto relacionado de la relación $relationName con el objeto actual según las relaciones definidas en esta clase.
	* @param string $relationName Nombre de la relación.
	* @param array $remoteCondition Condición extra de selección de objetos remotos. Si es [], no se aplica ninguna condición extra. Por defecto es [].
	* @param array $order Orden que han de llevar. Array con pares <campo>=>"asc|desc". Por defecto es null.
	* @param array|string $limit Límite de objetos que se devolverán. Por defecto es null.
	* @param array $container Tipo de contenedor a usar en el caso de que la relación sea "a-muchos".
	* @return array|collection|object Array, colección u objeto remoto relacionado con el objeto llamador.
	*/ 
	public function dbLoadRelated($relationName, $remoteCondition=[], $order=null, $limit=null, $container="collection"){
		// Relación definida en __CLASS__
		if(!isset(static::$RELATIONSHIPS[$relationName])){
			throw new UnexpectedValueException("La relación {$relationName} no existe en el modelo ".static::CLASS_NAME);
		}
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Modelo que hemos de cargar
		$foreignClass = $relationship["model"];
		
		// Si no hay un modelo que cargar, lo creamos nosotros WOW
		if(is_null($foreignClass)){
			if(isset($relationship["table"])){
				return static::_dbLoadRelatedNoModel($relationName, $remoteCondition, $order, $limit, $container);
			}
			throw new UnexpectedValueException("Falta la clave 'table' con el nombre de la tabla");
		}
		
		// Tipo de relación (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		//////////////
		/// 1. ManyToMany: el objeto actual tiene muchas referencias con
		/// otros objetos y usa para ello una tabla intermedia
		if($relationshipType == "ManyToMany"){
			$remoteObjects = static::_dbLoadRelatedManyToMany($relationName, $remoteCondition, $order, $limit, $container);
			return $remoteObjects;
		}
		
		/////////////
		// Para el caso de que no sea una relación muchos a muchos, 
		// no necesitamos una table NEXO.
		$remoteCondition = $this->getForeignKeyRemoteCondition($relationship, $remoteCondition);
		
		//////////////
		/// 2. ForeignKey (ManyToOne): el objeto actual tiene
		/// una única referencia externa.
		if($relationshipType=="ForeignKey" or $relationshipType=="ManyToOne" or $relationshipType=="OneToOne"){
			return $foreignClass::dbLoad($remoteCondition);
		}
		
		//////////////
		/// 3. OneToMany: el objeto actual es una referencia externa
		/// del objeto remoto.
		if($relationshipType=="OneToMany"){
			$container = strtolower($container);
			$remoteObjects = $foreignClass::dbLoadAll($remoteCondition, $order, $limit, $container);
			return $remoteObjects;
		}
		
		// Por si hemos introducido una relación que no está implementada
		throw \UnexpectedValueException("El tipo de relación {$relationshipType} no está implementado");
	}
	
	
	/**
	 * Informa si el objeto actual tiene una relación con el objeto remoto.
	 * @param string $relationName Nombre de la relación.
	 * @param object $object Objeto con el que queremos comprobar si hay relación.
	 * @return boolean True si hay relación entre $this y $object via $relationName, false en otro caso.
	 * */
	public function dbHasRelation($relationName, $object){
		// Para facilitar las cosas y no tener que ir
		// arrastrando static::$RELATIONSHIPS
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Tipo de relación (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		////////////////////////////////////////////////////////////////
		// Relación muchos a muchos
		if($relationshipType == "ManyToMany"){
			$remoteCondition = $object->getPk();
			return ( count(static::_dbLoadRelatedManyToMany($relationName, $remoteCondition))>0 );
		}
		
		////////////////////////////////////////////////////////////////
		// Clave externa
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne"){
			// Modelo remoto (el otro extremo de la relación)
			$foreignModel = $relationship["model"];
			/////////////
			// Para el caso de que no sea una relación muchos a muchos, 
			// no necesitamos una table NEXO.
			$remoteCondition = $object->getPk();
			$foreignKeyCondition = static::getForeignKeyRemoteCondition($relationship, $remoteCondition);
			return ( $foreignModel::dbCount($foreignKeyCondition) > 0 );
		}
		
		// Por si hemos introducido una relación que no existe
		throw new \UnexpectedValueException("La relación {$relationName} no existe");
	}
}