<?php

namespace lulo\models;

require_once __DIR__."/romodel.class.php";

use lulo\containers\Collection as Collection;
use lulo\containers\QueryResult as QueryResult;

/**
 * Parent class that allows writting data in database.
 * @author Diego J. Romero López at intelligenia.
 * */
abstract class RWModel extends ROModel{
	
	use \lulo\models\traits\Delete;
	use \lulo\models\traits\Save;
	use \lulo\models\traits\Update;
	
	/**
	 * Should the relationships of this model be updated when deleting
	 * its objects?
	 **/
	const UPDATE_RELATIONS_ON_OBJECT_DELETION_BY_DEFAULT = true;
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** CLEAN *****************************************/
	
	/**
	 * Add relationship fields for a relationship of cadinality 
	 * ForeignKey (ManyToOne), or OneToOne.
	 * @param array $data Received data from form.
	 * @param string $relationName Relationship name.
	 * @param array $properties Relationship properties.
	 * @return array Array with form data and needed relationship extra fields
	 * */
	protected static function cleanToOneRelationship($data, $relationName, $properties){
		// Remote model
		$relatedModelName = $properties["model"];
		// Relationship type
		$relationType = $properties["type"];
		// Relationship condition (<local_attr> => <remote_attr>)
		$relationCondition = $properties["condition"];
		
		// If relationship attribute exists and is not empty, 
		if(isset($data[$relationName]) and $data[$relationName]!=""){
			// In case it is an object and is of the right class 
			// use it
			if(is_object($data[$relationName])){
				if(get_class($data[$relationName]) != $relatedModelName){
					throw new \InvalidArgumentException("El objeto {$data[$relationName]} no es de tipo {$relatedModelName}");
				}
				$relatedObject = $data[$relationName];
			
			// Otherwise, load the object from its string representation of its primary key	
			}else{
				$relatedObject = $relatedModelName::dbLoadFromStrPk($data[$relationName]);
			}
			// Existence condition
			if(is_object($relatedObject)){
				foreach($relationCondition as $localAttribute=>$remoteAttribute){
					$data[$localAttribute] = $relatedObject->getAttribute($remoteAttribute);
				}
				return $data;
			}
		}
		
		// If the remote object does not exists, we will make null all its
		// nullable relationship attributes.
		foreach($relationCondition as $localAttribute=>$remoteAttribute){
			if(!isset($data[$localAttribute]) or is_null($data[$localAttribute])){
				$data[$localAttribute] = null;
			}
		}
		
		return $data;
	}
	
	
	/**
	 * Add relationship fields to the data that comes from a form.
	 * @param array $data Form data.
	 * @return array Array with form data and needed relationship extra fields
	 * */
	protected static function cleanRelationships($data){
		
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			$relatedModelName = $properties["model"];
			$relationType = $properties["type"];
			// Relationship "to one"
			if($relationType == "ForeignKey" or $relationType == "ManyToOne" or $relationType == "OneToOne"){
				$data = static::cleanToOneRelationship($data, $relationName, $properties);
			}
			// Many-to-many relationships are not implemented
			elseif($relationType == "ManyToMany"){
				// Nothing
			}
			// Inverse relationship
			elseif($relationType == "OneToMany"){
				// Nothing
			}
			// Unexpected relationship type
			else{
				throw new \UnexpectedValueException("Relationship {$relationName} is of an invalid type: {$relationType}.");
			}
		}
		
		// Return form data with extra relationship data
		return $data;
	}
	
	
	/**
	 * Clean data to create an object of this model
	 * @param array $data Form data array.
	 * @return array Array with cleaned data to use in class::factoryFromArray
	 * */
	public static function cleanCreation($data){
		$cleanedData = static::cleanRelationships($data);
		return $cleanedData;
	}
	
	
	/**
	 * Clean data to edit an object of this model
	 * @param array $data Form data array.
	 * @return array Array with cleaned data to use in $object->setFromArray
	 * */
	public static function cleanEdition($data){
		$cleanedData = static::cleanRelationships($data);
		return $cleanedData;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/************* FORMULARIOS ****************************************/
	
	/**
	 * This method returns the default value for a field.
	 * This default value will be used in creation or edition forms
	 * (if $object is not null)
	 * It is useful to define default values for relationships.
	 * @param string $formFieldName Field name.
	 * @param object $object If present, it means get the default value for an edition.
	 * @return mixed Default value for creation/edition of objects of this model.
	 * */
	public static function defaultFormValue($formFieldName, $object=null){
		// If there is no objects, there is no default value
		if(is_null($object)){
			return null;
		}
		// Otherwise, it could be a default value for a relationship
		if(isset(static::$RELATIONSHIPS[$formFieldName])){
			$relationType = static::$RELATIONSHIPS[$formFieldName]["type"];
			// Returning of the QueryResult of the Many-to-Many relationship
			if($relationType == "ManyToMany"){
				$relatedObjects = $object->dbLoadRelated($formFieldName);
				return $relatedObjects;
			}
			// If the relationship is "to-one"
			// there is a related object, if it exists, return it
			$relatedObject = $object->dbLoadRelated($formFieldName);
			if(is_null($relatedObject)){
				return "";
			}
			return $relatedObject;
		}
		
		// There is no default value
		return null;
	}
	
	/**
	 * Values for enumerated fields.
	 * @param string $formFieldName Field name.
	 * @param object $object If present, it means get the default value for an edition.
	 * @return mixed Default value for creation/edition of objects of this model.
	 * */
	public static function formValues($formFieldName, $object=null){
		return null;
	}
	
	
	/**
	 * Form validation.
	 * Este método tiene las llamadas a los métodos de validación de cada uno.
	 * Throw and exception if forma data is invalid.
	 * @param object $object If present, it means get the default value for an edition.
	 * @return array .
	 * */
	public static function formValidation($object=null){
		return [];
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** BLOBS *****************************************/
	
	/**
	 * Read a blob, convert it to string and insert it to $_model_data
	 * @param string $blobName Name of the blob attribute.
	 * @param mixed $blobObject Blob in several types.
	 * @param array $_object_data Data of the object. IT IS MODIFIED.
	 * */
	protected static function _dbReadBlob($blobName, $blobObject, &$_object_data){
		$blobType = gettype($blobObject);
		$blobString = null;
		// A blob can be...
		
		// 1. An object with toString method
		if($blobType=="object" and is_callable([$blobObject, "toString"])){
			$blobString = $blobObject->toString();
		
		// 2. A string with the contents of the blob
		}elseif($blobType=="string"){
			$blobString = $blobObject;
		
		// 3. An array with path key
		}elseif(is_array($blobObject) and isset($blobObject["path"])){ 
			$blobString = file_get_contents($blobObject);
		
		// 4. A file descriptor
		}elseif(get_resource_type($blobObject)==="stream"){
			$blobString = stream_get_contents($blobObject);
		
		// 5. Unknown
		}else{
			throw new \InvalidArgumentException("Blob {$blobName} is not of valid type. is a {$blobType}");
		}
		
		// Blob attribute assignement
		$_object_data[$blobName] = $blobString;
	}
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/************** RELATIONSHIPS *************************************/

	/**
	 * Assert if relationship does not allow edition.
	 * @param string $relationName Relationship name.
	 * @param array $relationship Relationsip properties.
	 * */
	protected static function assertRWRelationship($relationName, $relationship){
		if(isset($relationship["readonly"]) and $relationship["readonly"]){
			throw new \UnexpectedValueException("La relación {$relationName} es de sólo lectura (y no se permite edición)");
		}
	}

	/******************************************************************/
	/******************************************************************/
	/*************************** ADD **********************************/

	/**
	 * Get the values of nexus $junction between $this and $object.
	 * */
	protected function getJunctionValues($conditions, $junction, $object=null){
		// Primary keys of the source object
		$sourceAttributes = $this->getPk();
		
		// Model to nexus condition
		$condition0 = $conditions[0];
		foreach($condition0 as $sourceAttribute=>$junctionAttribute){
			$junctionData[$junctionAttribute] = $sourceAttributes[$sourceAttribute];
		}
		
		// Nexus to destination condition
		if(is_object($object)){
			$objectAttributes = $object->getPk();
			$condition1 = $conditions[1];
			foreach($condition1 as $junctionAttribute=>$nextAttribute){
				$junctionData[$junctionAttribute] = $objectAttributes[$nextAttribute];
			}
		}
		return $junctionData;
	}
	
	
	/**
	 * Adds a new object to a relationships Many-to-Many.
	 * NOTE: several nexus relationships are NOT IMPLEMENTED.
	 * @param array $relationship Relationship properties array.
	 * @param object $object Object to be added to relationship.
	 * @return boolean true if everything went ok, false otherwise.
	 * */
	protected function _dbAddObjectToManyToManyRelation($relationship, $object){
		$db = static::DB;
		
		$model = $relationship["model"];
		$junctions = $relationship["junctions"];
		$conditions = $relationship["conditions"];
		
		// Only add object to one nexii relationships are allowed
		$junction = $junctions[0];
		if(count($junctions)>1){
			throw new \UnexpectedValueException("Not allowed. Relationships with many junctions not supported.");
		}
		
		$junctionValues = $this->getJunctionValues($conditions, $junction, $object);
		
		// Database insertion
		$ok = $db::insert($junction, $junctionValues);
		return $ok;
	}
	
	
	/**
	 * Adds an object to a relationship.
	 * @param string $relationName Relationship name.
	 * @param mixed $value Object to be added to the relationship of current object.
	 * */
	public function dbAddRelation($relationName, $value){
		// Getting relationship properties
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Is the relationship editable?
		static::assertRWRelationship($relationName, $relationship);
		
		// Remote model
		$foreignClass = $relationship["model"];
		
		// If remote model is null, is a relationship to tuple and edition is not allowed
		if(is_null($foreignClass)){
			throw new \UnexpectedValueException("Relationship {$relationName} is of type table. These relationships does not allow edition.");
		}
		
		// Relationship type (ForignKey, OneToMany or ManyToMany)
		$relationshipType = $relationship["type"];
		
		// To-One relationship
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne" or $relationshipType == "OneToMany"){
			$object = $value;
			$condition = $relationship["condition"];
			// $object should be of type $foreignClass
			if(is_object($object) and get_class($object)==$foreignClass){
				// Assignement of attributes that form the link between
				// $this and $object
				foreach($condition as $localAttribute=>$remoteAttribute){
					$this->$localAttribute = $object->$remoteAttribute;
				}
				return $this->dbSave();
			}
			throw new \InvalidArgumentException("Relationship {$relationName} require an object of class {$foreignClass}");
		}
		
		// Many-to-Many relationship
		if($relationshipType == "ManyToMany"){
			$values = $value;
			// If values is an array, each element is a strpk of a remote object
			// or the objects
			if(is_array($values)){
				$values = new Collection();
				foreach($value as $strPk){
					if(is_string($strPk)){
						$object = $foreignClass::dbLoadFromStrPk($strPk);
					}elseif(is_object($strPk)){
						$object = $strPk;
					}
					$values->add($object);
				}
			}
			// If values is a string, only one object has to be added to this
			// many-to-many relationship
			elseif(is_string($value)){
				$value = $foreignClass::dbLoadFromStrPk($value);
				$values = new Collection();
				$values->add($value);
			}
			// If values is an object, this object has to be added to this
			// many-to-many relationship
			elseif(is_object($value) and get_class($value)==$foreignClass){
				$values = new Collection();
				$values->add($value);
			}
			// If values is a collection, we have to iterate and add each one
			// of its object to this relationship
			if(is_object($values) and ($values instanceof \Iterator )){
				$ok = true;
				foreach($values as $object){
					$ok = ($ok and static::_dbAddObjectToManyToManyRelation($relationship, $object));
				}
				return $ok;
			}
			return false;
		}
		// Unknown relationship type
		throw new \InvalidArgumentException("Relationship {$relationName} is not a known type. It is {$relationshipType}.");
	}
	
}
?>
