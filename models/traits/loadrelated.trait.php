<?php

namespace lulo\models\traits;

use lulo\containers\Collection as Collection;
use lulo\containers\QueryResult as QueryResult;


/**
 * Legacy related loading objects methods.
 * Use with caution and only in legacy applications.
 *  */
trait LoadRelated {
	
	
	/**
	 * Load information of the associated tables.
	 * This method does not rely on models.
	 * @param string $relationName Relationship name.
	 * @param array $remoteCondition Array with the condition.
	 * @param array $order Order of the remote objects. E. g. ["name" => "asc, "number" => "desc"]
	 * @param array $limit Limit with the values [offset, size].
	 * @return array Simple array if relatinoship is *ToOne, or array of arrays if relationship type is OneToMany.
	 * */
	protected static function _dbLoadRelatedNoModel($relationName, $remoteCondition=[], $order=null, $limit=null, $container="queryresult"){
		// Database
		$db = static::DB;
		
		// Get relationship properties
		$relationship = static::metaGetRelationship($relationName);
		
		// Relationship type (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		// Remote table
		$relatedTable = $relationship["table"];
		
		// What fields should we get?
		$columns = $relationship["attributes"];
		$columnsStr = implode(",", $columns);
		
		// Should be DISTINCT applied to the query?
		$isDistinct = (isset($relationship["distinct"]) and $relationship["distinct"]);
		if($isDistinct){
			$columnsStr = "DISTINCT {$columnsStr}";
		}
		
		// Many-to-One or One-to-One relationship 
		if($relationshipType == "ManyToOne" or $relationshipType == "OneToOne"){
			$row = $db::getRow($relatedTable, $columnsStr, $remoteCondition, $order);
			if(count($row)==0 or $row==null or $row==false){
				return null;
			}
			return $row;
		}
		
		// One-To-Many relationship
		if($relationshipType == "OneToMany"){
			if($container == "collection"){
				return new Collection($db::getAll($relatedTable, $columnsStr, $remoteCondition, $order, $limit));
			}
			if($container == "queryresult"){
				return new QueryResult($db::getAllAsRecordSet($relatedTable, $columnsStr, $remoteCondition, $order, $limit));
			}
			return $db::getAll($relatedTable, $columnsStr, $remoteCondition, $order, $limit);
		}
		
		// Many-to-Many relationships are not allowed for relationships with tables
		throw \InvalidArgumentException("When dealing with relationships with tables, only 'OneToMany' and 'ManyToOne' types are allowed");
	}
	
	
	/**
	* Return related objects of the relationship $relationName with current object.
	* @param string $relationName Relationship name.
	* @param array $remoteCondition Extra condition applied to remote objects. If null, it is ignored.
	* @param array $order Order of the remote objects. E. g. ["name" => "asc, "number" => "desc"]
	* @param array $limit Limit with the values [offset, size].
	* @param array $container Container used for the result.
	* @return array|collection|queryresult Array, collection or queryresult according to what is specified in $container parameter.
	*/ 
	protected function _dbLoadRelatedManyToMany($relationName, $remoteCondition=[], $order=null, $limit=null, $container="queryresult"){
		// Relationship properties
		$relationship = static::metaGetRelationship($relationName);
		
		// Remote model
		$foreignClass = $relationship["model"];
		
		// Nexus tables
		$junctionTables = $relationship["junctions"];
		
		// Should be DISTINCT applied to the query?
		$isDistinct = (isset($relationship["distinct"]) and $relationship["distinct"]);
		
		// Tables are in this order: nexus and destination table
		// Destination table is included only to select its fields
		$tables = array_merge([], $junctionTables, [$foreignClass::getTableName()]);
		$numTables = count($tables);
		
		// Nexus table fields
		$fieldsByTable = [];
		$TABLE_NAME = static::getTableName();
		foreach(array_merge([$TABLE_NAME],$tables) as $tableName){
			$fieldsByTable[$tableName] = [];
		}
		
		// Nexus table fields can be forced to be included in the relationship
		// by setting this attributes to true
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
		
		// Selected fields are non-blob fields of remote table
		$fieldsByTable[$foreignClass::getTableName()] = $foreignClass::metaGetSelectableAttributes();
		
		// Conditions for original table (0)
		$localConditions = $this->getPk();
		
		// Conditions between original table and the next table
		$conditions = $relationship["conditions"];
		$relations = [];
		$i = 0;
		foreach($conditions as $condition){
			$relations[$i] = array('on'=>$condition);
			$i++;
		}
		
		// Explicit condition
		// Condition applied to remote objects
		if(is_array($remoteCondition)){
			// Complex condition
			if(isset($remoteCondition["remoteObjectConditions"]) and isset($remoteCondition["nexiiConditions"])){
				// Remote objects condition
				$relations[$numTables-1]["extra"] = $remoteCondition["remoteObjectConditions"];
				// Nexii conditions
				$i = 0;
				foreach($remoteCondition["nexiiConditions"] as $nexusCondition){
					$relations[$i]["extra"] = $nexusCondition;
					$i++;
				}
			}
			// If the remote condition is only applied to remote objects
			else if(!isset($remoteCondition["remoteObjectConditions"]) and !isset($remoteCondition["nexiiConditions"])){
				$relations[$numTables-1]["extra"] = $remoteCondition;
			}
			else{
				throw \Exception("['remoteObjectConditions'=>[], 'nexiiConditions'=>[ [Nexus1 conditions], [Nexus2 conditions], ..., [NexusN conditions] ]] where expected");
			}
		}
		
		// Extra INNER JOIN parameters
		$params = [];
		
		// Should be DISTINCT applied to the query?
		$params['distinct'] = $isDistinct;
		
		// Order of remote objects
		$params['order'] = array();
		if(!is_null($order)){
			$params['order'][$foreignClass::getTableName()] = $order;
		}
		
		// Query limit
		$params['limit'] = $limit;
		
		// Database to be queried
		$db = static::DB;
		
		// If container is QueryResult it alone can create the container
		// from the $db recordset
		if($container=="queryresult"){
			return new QueryResult($db::joinAsRecordSet($fieldsByTable, $relations, $localConditions, $params), $foreignClass);
		}
		
		
		// Otherwise, all the objects are returned
		$rows = $db::join($fieldsByTable, $relations, $localConditions, $params);
		
		// Getting the remote objects
		$foreignObjects = $foreignClass::arrayFactoryFromRows($rows);
		
		if($container=="collection"){
			return new Collection($foreignObjects);
		}
		
		return $foreignObjects;
	}
	
	
	/**
	 * Get ForeignKey remote condition.
	 * @param array $relationship Relationship properties.
	 * @param array $expliticRemoteConditions Explicit remote object conditions.
	 * @return array Array with the remote condition of the foreign key, based on my values.
	 * */
	protected function getForeignKeyRemoteCondition($relationship, $expliticRemoteConditions=[]){
		// Remote condition based on foreign key link
		$remoteCondition = [];
		$foreignCondition = $relationship["condition"];
		foreach($foreignCondition as $localAttribute=>$remoteAttribute){
			$remoteCondition[$remoteAttribute] = $this->getAttribute($localAttribute);
		}
		// Addition of remote conditions
		if(is_array($expliticRemoteConditions) and count($expliticRemoteConditions)>0){
			foreach($expliticRemoteConditions as $attr=>$value){
				$remoteCondition[$attr] = $value;
			}
		}
		// Final remote condition
		return $remoteCondition;
	}
	
	
	/**
	* Return related object defined by relationship $relationName.
	* @deprecated You should be using Lulo Queries instead of this method.
	* @param array $remoteCondition Extra condition applied to remote objects. If null, it is ignored.
	* @param array $order Order of the remote objects. E. g. ["name" => "asc, "number" => "desc"]
	* @param array $limit Limit with the values [offset, size].
	* @param array $container Container used for the result.
	* @return array|collection|queryresult Array, collection or queryresult according to what is specified in $container parameter.
	*/ 
	public function dbLoadRelated($relationName, $remoteCondition=[], $order=null, $limit=null, $container="queryresult"){
		// Relationship $relationName must exist in this model
		if(!isset(static::$RELATIONSHIPS[$relationName])){
			throw new \UnexpectedValueException("Relationship {$relationName} does not exist in model ".static::CLASS_NAME);
		}
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Remote model
		$foreignClass = $relationship["model"];
		
		// If relationship is between this object and a table
		// returned data will be array, collection or queryresult
		if(is_null($foreignClass)){
			if(isset($relationship["table"])){
				return static::_dbLoadRelatedNoModel($relationName, $remoteCondition, $order, $limit, $container);
			}
			throw new \UnexpectedValueException("Relationship {$relationName} does not have 'table' key");
		}
		
		// Relationshihp type (ForeignKey, OneToMany or ManyToMany)
		$relationshipType = $relationship["type"];
		
		/// 1. ManyToMany: current object has many refernces with other objects
		/// and uses (at least) one intermediate table
		if($relationshipType == "ManyToMany"){
			$remoteObjects = static::_dbLoadRelatedManyToMany($relationName, $remoteCondition, $order, $limit, $container);
			return $remoteObjects;
		}
		
		// Remote condition
		$toOneCondition = $this->getForeignKeyRemoteCondition($relationship, $remoteCondition);
		
		/// 2. ForeignKey (ManyToOne): current object has only one reference.
		if($relationshipType=="ForeignKey" or $relationshipType=="ManyToOne" or $relationshipType=="OneToOne"){
			return $foreignClass::dbLoad($toOneCondition);
		}
		
		/// 3. OneToMany: current object is an external reference of the remote object
		if($relationshipType=="OneToMany"){
			$container = strtolower($container);
			$remoteObjects = $foreignClass::dbLoadAll($remoteCondition, $order, $limit, $container);
			return $remoteObjects;
		}
		
		// Not implemente type of relationship
		throw \UnexpectedValueException("{$relationshipType} relationship type is not implemented");
	}
	
	
	/**
	 * Informs if a relationships exists between current object and an object.
	 * @param string $relationName Relationship to test.
	 * @param object $object Object to test if there is a relationships.
	 * @return boolean true if $this and $object are related via $relationName. False otherwise.
	 * */
	public function dbHasRelation($relationName, $object){
		// Relationship properties
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Relationship type (ForignKey, OneToMany or ManyToMany)
		$relationshipType = $relationship["type"];
		
		// Many-to-Many relationship
		if($relationshipType == "ManyToMany"){
			$remoteCondition = $object->getPk();
			return ( count(static::_dbLoadRelatedManyToMany($relationName, $remoteCondition))>0 );
		}
		
		// Foreign key relationship
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne"){
			// Remote model
			$foreignModel = $relationship["model"];
			// Remote condition
			$remoteCondition = $object->getPk();
			// Count of remote objects
			$foreignKeyCondition = static::getForeignKeyRemoteCondition($relationship, $remoteCondition);
			return ( $foreignModel::dbCount($foreignKeyCondition) > 0 );
		}
		
		// Warn that relationship does not exist
		throw new \UnexpectedValueException("Relationship {$relationName} does not exist");
	}
}