<?php

namespace lulo\models\traits;

/**
 * Initialize the LuloModel object.
 *  */
trait Init {
	
	/**************************************************************************/
	/* DIRECT RELATIONSHIPS */
	
	/**
	 * Adds a direct relationship given its name and its properties.
	 * 
	 * @param string $attributeName ForeignKey subtype attribute name.
	 * @param array $attributeProperties Attribute properties.
	 * */
	protected static function addForeignRelationshipFromAttribute($attributeName, $attributeProperties){
		// Relationship must exist
		if(!isset($attributeProperties["name"])){
			throw new \OutOfBoundsException("Attribute {$attributeName} is a relationship ForeignKey but does not contains key 'name' with the relationship unique name.");
		}
		$relationshipName = $attributeProperties["name"];
		
		// Relationship must be on some model
		if(!isset($attributeProperties["on"])){
			throw new \OutOfBoundsException("Attribute {$attributeName} is a relationship ForeignKey but does not contains key 'on' with the value <Model>.<relationship_attribute>");
		}
		
		$model = static::CLASS_NAME;
		$matches = [];
		if(preg_match("#^([^\.]+)\.(\w[\w\d]+)$#", $attributeProperties["on"], $matches)==0){
			throw new \UnexpectedValueException("'on' key must have follow <RemoteModel>.<attribute> pattern where <RemoteModel> is the remote model and <attribute> is the attribute used to link the objects.");
		}
		$remoteModel = $matches[1];
		$remoteAttribute = $matches[2];
		
		// Creation of the direct relationship
		static::$RELATIONSHIPS[$relationshipName] = [
			"type" => "ForeignKey",
			"model" => $remoteModel,
			"table" => $remoteModel::getTableName(),
			"condition" => [$attributeName=>$remoteAttribute],
		];
		
		// Optional relationship properties
		$optionalProperties = [
			"verbose_name" => true, "related_name" => true,
			"related_verbose_name" => true, "nullable" => true,
			"readonly" => true, "on_master_deletion" => true
		];
		// Assigning to static::$RELATIONSHIPS as if it were another standard relationship
		foreach($attributeProperties as $attributeProperty=>$attributePropertyValue){
			if(isset($optionalProperties[$attributeProperty])){
                static::$RELATIONSHIPS[$relationshipName][$attributeProperty] = $attributePropertyValue;
			}
		}
	}
	
	
	/**
	 * Initialize direct relationships contained in the attributes of the model.
	 * */
	protected static function initDirectRelationshipsFromAttributes(){
		// For each attribute, test if it has subtype and if it is "ForeignKey"
		// In the future other relationship types will be added
		foreach(static::$ATTRIBUTES as $attributeName=>$attributeProperties){
			// Addition of direct relationships based on attribute metada
			if(isset($attributeProperties["subtype"]) and $attributeProperties["subtype"]=="ForeignKey"){
				static::addForeignRelationshipFromAttribute($attributeName, $attributeProperties);
			}
		}
	}
	
	
	/**
	 * Initialize direct relationships of this model.
	 * 
	 * Currently, only direct relationships described in model attributes
	 * are initialized.
	 * 
	 * */
	protected static function initDirectRelationships(){
		// Introduce automatic attributes for each relationship.
		// For the moment, only the table name of the remote model is needed.
		foreach(static::$RELATIONSHIPS as $relationshipName=>&$relationshipProperties){
			if(!isset($relationshipProperties["table"])){
				$model = $relationshipProperties["model"];
				$relationshipProperties["table"] = $model::getTableName();
			}
		}
		// Initialize direct relationships contained in the attributes
		// of the model
		static::initDirectRelationshipsFromAttributes();
	}
	
	
	/* END OF DIRECT RELATIONSHIPS */
	/******************************************************************/
	
	
	/******************************************************************/
	/* INVERSE RELATIONSHIPS */
	
	/**
	 * Get inverse relationship name.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function getInverseRelationshipName($relationName, $relationship){
		// If there is a name for the inverse relationship, return it
		if(isset($relationship["related_name"])){
			$inverseRelationName = $relationship["related_name"];
		
		// Otherwise, create an automatic name
		}else{
			$inverseRelationName = "{$relationName}_inverse";
		}
		// If that name of the inverse relationship exists, return false
		// otherwise return this name
		if(isset(static::$RELATIONSHIPS[$inverseRelationName])){
			return false;
		}
		return $inverseRelationName;
	}
	
	
	/**
	 * Get inverse relationship verbose name.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function getInverseRelationshipVerboseName($model, $relationName, $relationship){
		$inverseVerboseName = "Invers relationship {$relationship['verbose_name']} of {$model}";
		if(isset($relationship["related_verbose_name"])){
			$inverseVerboseName = $relationship["related_verbose_name"];
		}
		return $inverseVerboseName;
	}
	
	
	/**
	 * Adds an inverse ManyToMany relationship  $relationName with $model to current model.
	 * @param string $model Name of the remote model.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function addInverseManyToManyRelationship($model, $relationName, $relationship){
		// New relationship name
		$inverseRelationName = static::getInverseRelationshipName($relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// New relationship verbose name
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Intervet nexus
		$inverseJunctions = array_reverse($relationship["junctions"]);
		
		// Inverse condition
		$reverseConditions = array_reverse($relationship["conditions"]);
		
		// New relationship condition
		$inverseConditions = [];
		foreach($reverseConditions as $condition){
			$inverseConditions[] = array_flip($condition);
		}
		
		// Inverse relationship in current model
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "ManyToMany",
			"model" => $model,
			"table" => $model::getTableName(),
			"related_name" => $relationName,
			"verbose_name" => $inverseVerboseName,
			"junctions" => $inverseJunctions,
			"conditions" => $inverseConditions,
			"nulllable" => ( isset($relationship["nullable"]) and $relationship["nullable"] ),
			"readonly" => ( isset($relationship["readonly"]) and $relationship["readonly"] ),
			"inverse_of" => $relationName,
			"on_master_deletion" => (isset($relationship["on_master_deletion"])?$relationship["on_master_deletion"]:null),
		];
	}
	
	
	/**
	 * Adds an inverse OneToMany relationship  $relationName with $model to current model.
	 * @param string $model Name of the remote model.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function addInverseForeignKeyRelationship($model, $relationName, $relationship){
		
		// New relationship name
		$inverseRelationName = static::getInverseRelationshipName($relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// New relationship verbose name
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Inverse condition
		$inverseCondition = array_flip($relationship["condition"]);
		
		// What to do in case of deletion of the "many" side of the relationship
		$on_delete = false;
		if(isset($relationship["on_master_deletion"])){
			$on_delete = $relationship["on_master_deletion"];
		}
		
		// Inverse relationship in current model
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "OneToMany",
			"model" => $model,
			"table" => $model::getTableName(),
			"verbose_name" => $inverseVerboseName,
			"related_name" => $relationName,
			"condition" => $inverseCondition,
			"nulllable" => ( isset($relationship["nullable"]) and $relationship["nullable"] ),
			"readonly" => ( isset($relationship["readonly"]) and $relationship["readonly"] ),
			"on_master_deletion" => $on_delete,
			"inverse_of" => $relationName,
		];
	}
	
	/**
	 * Adds an inverse ForeignKey relationship  $relationName with $model to current model.
	 * @param string $model Name of the remote model.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function addInverseOneToManyRelationship($model, $relationName, $relationship){
		
		// New relationship verbose name
		$inverseRelationName = static::getInverseRelationshipName($relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// New relationship verbose name
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Inverse condition
		$inverseCondition = array_flip($relationship["condition"]);
		
		// What to do in case of deletion of the "many" side of the relationship
		$on_master_deletion = false;
		if(isset($relationship["on_master_deletion"])){
			$on_master_deletion = $relationship["on_master_deletion"];
		}
		
		// Inverse relationship in current model
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "ForeignKey",
			"model" => $model,
			"table" => $model::getTableName(),
			"verbose_name" => $inverseVerboseName,
			"condition" => $inverseCondition,
			"nulllable" => ( isset($relationship["nullable"]) and $relationship["nullable"] ),
			"readonly" => ( isset($relationship["readonly"]) and $relationship["readonly"] ),
			"on_master_deletion" => $on_master_deletion,
			"inverse_of" => $relationName,
		];
	}
	
	/**
	 * Adds an inverse relationship  $relationName with $model to current model.
	 * @param string $model Name of the remote model.
	 * @param string $relationName Direct relation name.
	 * @param array $relationship Array with properties of the direct relationship.
	 * @return string Inverse relationship name (it should be the "related_name" attribute of the direct relationship).
	 * */
	protected static function addInverseRelationship($model, $relationName, $relationship){
		// If relationship is already an inverse relationship of any other
		// relationship ignore it
		if(isset($relationship["inverse_of"])){
			return false;
		}
		// For each type of relationship, create its particular inverse type one
		if($relationship["type"] == "ManyToMany"){
			static::addInverseManyToManyRelationship($model, $relationName, $relationship);
		}elseif($relationship["type"] == "ForeignKey" or $relationship["type"] == "ManyToOne"){
			static::addInverseForeignKeyRelationship($model, $relationName, $relationship);
		}elseif($relationship["type"] == "OneToMany"){
			static::addInverseOneToManyRelationship($model, $relationName, $relationship);
		}else{
			// Non-recognized relationship type
			throw new \UnexpectedValueException("Relationship {$relationName} has a non-recognizable type");
		}
		return true;
	}
	
	
	/**
	 * Initialize inverse relationships.
	 * 
	 * An inverse relationship is a relationship automatically created that is
	 * the inverse of an already defined relationship in other model.
	 * */
	protected static function initInvertedRelationships(){
		// Flag to control if inverse relationships have already been activated
		if(isset(static::$INVERSE_RELATIONSHIPS_ACTIVATED[static::CLASS_NAME])){
			return false;
		}
		// Every model can be related to itself
		$relatedModels = array_merge(static::$RELATED_MODELS, [static::CLASS_NAME]);
		//print "Relaciones de ".static::CLASS_NAME."<br>";
		// For each related model
		foreach($relatedModels as $model){
			// For each relationship, a new inverse relationship is created
			$relationships = $model::$RELATIONSHIPS;
			foreach($relationships as $name=>$properties){
				// Test if 'model' attribute exists
				if(!isset($properties["model"])){
					throw new \InvalidArgumentException("There is no 'model' attribute for relationship {$name}");
				}
				// Remote model
				$rModel = $properties["model"];
				// Addition of inverse relationships
				if($rModel == static::CLASS_NAME){
					static::addInverseRelationship($model, $name, $properties);
				}
			}
		}
		// Flag this class to don't repeat inverse relationship creation
		static::$INVERSE_RELATIONSHIPS_ACTIVATED[static::CLASS_NAME] = true;
		return true;
	}
	
	/***************** END OF INVERSE RELATIONSHIPS *****************/
	/******************************************************************/
	
	
	/**
	 * Initialize model attributes.
	 * 
	 * It is mandatory to call this method when we want to use inverse relationship.
	 * 
	 * */
	public static function init(){
		// Direct relationships initialization
		static::initDirectRelationships();
		// Inverse relationships initialization
		static::initInvertedRelationships();
	}

}
