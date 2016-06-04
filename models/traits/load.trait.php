<?php

namespace lulo\models\traits;

use lulo\containers\Collection as Collection;
use lulo\containers\QueryResult as QueryResult;


/**
 * Legacy loading of objects methods.
 * Use with caution and only in legacy applications.
 *  */
trait Load {
	
	/**
	* Load $blobName blob as a string.
	* @return string String that contains $blobName blob.
	*/ 
	public function dbBlobLoad($blobName){
		// DB connection
		$db = static::DB;
		
		// Current object condition based on its primary key
		$condition = $this->getPk();
		
		// Blob as string
		$blob = $db::getOne(static::getTableName(), $blobName, $condition);
		return $blob;
	}

	
	/**
	* Informs if $blobName blob is NULL.
	* @return boolean true if $blobName blob is NULL, false otherwise.
	*/ 
	public function dbBlobIsNull($blobName){
		// DB connection
		$db = static::DB;
		
		// Current object condition based on its primary key
		$condition = $this->getPk();
		
		// Is the blob null?
		$blobIsNull = $db::fieldIsNull(static::getTableName(), $blobName, $condition);
		return $blobIsNull;
	}
	
	
	/**
	 * Load all objects based on string representation of this model primary key (strPk).
	 * @deprecated You should be using Lulo Queries instead of this method.
	 * @param array $strPks Array with several strPks.
	 * @param array $extraConditions Extra conditions for returned objects.
	 * @return array Array of objects that contained $strPks.
	 * */
	public static function dbLoadAllFromStrPk($strPks, $extraConditions=null){
		$objects = [];
		// Loading of each object
		foreach($strPks as $strPk){
			$object = static::dbLoadFromStrPk($strPk, $extraConditions);
			$objects[] = $object;
		}
		// Return array of objects
		return $objects;
	}
	
	
	/**
	 * Load a container, array or queryresult of objects of this model that comply a condition.
	 * @deprecated You should be using Lulo Queries instead of this method.
	 * @param array $condition Object filtering condition.
	 * @param array $order Order of the remote objects. E. g. ["name" => "asc, "number" => "desc"]
	 * @param array $limit Limit with the values [offset, size].
	 * @param string $container Container type that will be returned.
	 * @return mixed A container witha the objects that comply with the condition.
	*/ 
	public static function dbLoadAll($condition=null, $order=null, $limit=null, $container="queryresult"){
		// Database connection
		$db = static::DB;
		
		// Fields to select in SELECT statement
		$columnsStr = static::getSelectColumnExpressionSQL();
		
		// Final condition
		$finalCondition = static::getBaseCondition($condition);
		
		// If container is queryresult, get the recordset of objects of a model
		if($container == "queryresult"){
			$rs = $db::getAllAsRecordSet(static::getTableName(), $columnsStr, $finalCondition, $order, $limit);
			return new QueryResult($rs, get_called_class());
		
		}elseif($container == "query"){
			$raw_filter = $db::makeWhereCondition($finalCondition, static::TABLE_ALIAS);
			return static::objects()->raw_filter($raw_filter);
			
		}elseif($container == "collection"){
			$rows = $db::getAll(static::getTableName(), $columnsStr, $finalCondition, $order, $limit);
			// If there is no objects, return an empty collection
			if(count($rows)==0 or $rows==null or $rows==false){
				if($container === "collection"){
					return new Collection();
				}
				return null;
			}

			// Return a full collection
			$arrayOfObjects = static::arrayFactoryFromRows($rows);
			if($container === "collection"){
				return new Collection($arrayOfObjects);
			}
			return $arrayOfObjects;
		}
	}
	
	
	/**
	* Load a model object that comply with a condition.
	* @deprecated You should be using Lulo Queries instead of this method.
	* @param array $condition Condition the returned object must comply.
	* @return object First object that comply this condition.
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
	* Load the object according to its primary key.
	* @deprecated You should be using Lulo Queries instead of this method. 
	* @param array $pk is an array of pairs attribute => value. 
	* @return object Object with that primary key if it exists. Null otherwise.
	*/ 
	public static function dbLoadByPK($pk){
		$condition = $pk;
		return static::dbLoad($condition);
	}
	
	
	/**
	 * Load an object based on the string representation of this model primary key (strPk).
	 * @deprecated You should be using Lulo Queries instead of this method.
	 * @param array $strPk String representation of an object primary key.
	 * @param array $extraConditions Extra conditions for returned object.
	 * @return array object that have a primary key equal to $strPk.
	 * */
	public static function dbLoadFromStrPk($strPk, $extraConditions=null){
		/// Getting the primary key 
		$condition = static::strToPk($strPk); 
		
		// Extra conditions
		if(is_array($extraConditions) and count($extraConditions)>0){
			foreach($extraConditions as $attribute=>$value){
				$condition[$attribute] = $value;
			}
		}
		// Loading of the object
		$object = static::dbLoad($condition);
		return $object;
	}

	/**
	* Count the number of objects that comply with a condition.
	* @deprecated You should be using Lulo Queries instead of this method. 
	* @param array $condition Condition of existence.
	* @return integer Number of objects that comply with condition.
	*/ 
	public static function dbCount($condition=null){
		$db = static::DB;
		// Final conditions
		$finalCondition = static::getBaseCondition($condition);
		// Object count
		$count = $db::count(static::getTableName(), $finalCondition);
		return $count;
	}
	
	
	/**
	* Informs if exixts an object that complies with a condition.
	* @deprecated You should be using Lulo Queries instead of this method. 
	* @param array $condition Condition of existence.
	* @return boolean true exists an object that comply $condition.
	*/ 
	public static function dbExists($condition=null){
		$db = static::DB;
		// Final conditions where base conditions are added
		$finalCondition = static::getBaseCondition($condition);
		// Count of objects
		$count = $db::count(static::getTableName(), $finalCondition);
		return ($count > 0);
	}
	
	
	/**
	 * Get SQL for field selection in SELECT statement.
	 * @return string String with SQL fields separated by commas.
	 * */
	private static function getSelectColumnExpressionSQL(){
		$class_attributes = static::initAttributesMetaInformation();
		$str = $class_attributes["attribute_names_str"];
		return $str;
	}

}

