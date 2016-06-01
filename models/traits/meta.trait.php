<?php

namespace lulo\models\traits;

/**
 * Metainformation methods of the model.
 *  */
trait Meta{
	
	/**
	* Returns the attribute that belong to the primary key paired with their type
	* @return array Array with the form <attribute_name>=><type>.
	*/
	public static function metaGetPkAttributes(){
		$data = array();
		$pkAttributeNames = array_keys(static::$PK_ATTRIBUTES);
		foreach($pkAttributeNames as $pkAttributeName){
			$data[$pkAttributeName] = static::$ATTRIBUTES[$pkAttributeName]["type"];
		}
		return $data;
	}
	
	
	/**
	* Returns the attributes that form the primary key.
	* @return array Array with the attribute that form the primary key.
	*/
	public static function metaGetPkAttributeNames(){
		$data = static::$PK_ATTRIBUTES;
		return $data;
	}
	
	
	/**
	* Returns the attributes of this model paired with their properties.
	* Each property is composed of the following attributes:
	* -type: type of the attribute.
	* -default: default value of the attribute.
	* -null: is nullable?
	* -auto: if the field is auto fillable.
	* -subtype: some types have also subtypes, e. g. relationships have "ForeignKey" as a subtype.
	* @return array Array with the form <attribute_name>=><properties>.
	*/
	public static function metaGetAttributes(){
		$data = static::$ATTRIBUTES;
		return $data;
	}
	
	
	/**
	* Returns the attribute properties of $attrName.
	* 
	* @param type $attrName Name of the attribute.
	* @return array of properties, see metaGetAttributes for an explanation.
	* @throws UnexpectedValueException
	 */
	public static function metaGetAttribute($attrName){
		if(!isset(static::$ATTRIBUTES[$attrName])){
			$class = get_called_class();
			throw new \UnexpectedValueException("{$attrName} is not an attribute of model {$class}");
		}
		
		$data = static::$ATTRIBUTES[$attrName];
		return $data;
	}
	
	
	/**
	* Returns the blob attributes of this model paired with their properties.
	* @return array Array with the form <blob_attribute_name>=><properties>.
	*/
	public static function metaGetBlobAttributes(){
		$blobs = [];
		foreach(static::$ATTRIBUTES as $attribute=>$properties){
			if($properties["type"] == "blob"){
				$blobs[$attribute] = $properties;
			}
		}
		return $blobs;
	}
	
	
	/**
	* Returns the attribute names paired with their type
	* @return array Array with the form <attribute_name>=><type>.
	*/
	public static function metaGetAttributeTypes(){
		$data = array();
		foreach(static::$ATTRIBUTES as $attribute=>$properties){
			$data[$attribute] = $properties["type"];
		}
		return $data;
	}
	
	
	/**
	 * Return a list of attributes that are not blobs.
	 * @return array Array with the names of the non-blob attributes.
	 * */
	public static function metaGetSelectableAttributes(){
		$data = array();
		foreach(static::$ATTRIBUTES as $attribute=>$properties){
			if($properties["type"] != "blob"){
				$data[] = $attribute;
			}
		}
		return $data;
	}
	
	
	/**
	 * Return a list of attributes.
	 * @return array Array with the names of the attributes.
	 * */
	public static function metaGetAttributeNames(){
		$class_attributes = static::initAttributesMetaInformation();
		return $class_attributes["attribute_names"];
	}
	
	
	/**
	* Inform if the object as defined $attributeName as an attribute.
	* @return boolean true if this attribute belongs to the model, false otherwise.
	*/
	public static function metaHasAttribute($attributeName){
		return array_key_exists($attributeName, static::$ATTRIBUTES);
	}
	
	
	/**
	* Return the value for a particular value of an enumerable attribute.
	* @param string $attributeName Enumerable attribute name.
	* @param string $key Key of that enumerable attribute name.
	* @return string Paired value with $key for attribute $attributeName.
	**/
	public static function metaGetEnumAttributeValue($attributeName, $key){
		// Attribute must belong to model
		if(!static::metaHasAttribute($attributeName)){
			throw new \UnexpectedValueException("Attribute {$attributeName} does not exist in this model");
		}
		// Attribute must be an enum
		$attribute = static::$ATTRIBUTES[$attributeName];
		if(!isset($attribute["subtype"]) or $attribute["subtype"]!="enum"){
			throw new \UnexpectedValueException("Attribute {$attributeName} is not an enum");
		}
		// Attribute must have values
		if(!isset($attribute["values"]) or !is_array($attribute["values"])){
			throw new \UnexpectedValueException("Attribute {$attributeName} does not have values");
		}
		// Does our $key exists?
		if(!isset($attribute["values"][$key])){
			throw new \UnexpectedValueException("Key {$key} does not exist in the attribute {$attributeName}");
		}
		// Return associated value
		return $attribute["values"][$key];
	}
	
	
	/**
	* Returns an array with the relationships.
	* See docs for more information about relationship format.
	* @return array Array of pairs <relationship_name> => <relationship_properties>.
	*/
	public static function metaGetRelationships(){
		return static::$RELATIONSHIPS;
	}
	
	
	/**
	* Gets data forma a relationship.
	* @param string $relationshipName Name of the relationship.
	* @return array Array with the properties of that relationship.
	*/
	public static function metaGetRelationship($relationshipName){
		if(!isset(static::$RELATIONSHIPS[$relationshipName])){
			throw new Exception("La relación {$relationshipName} no existe en el modelo ".static::CLASS_NAME);
		}
		$relationship = static::$RELATIONSHIPS[$relationshipName];
		
		if($relationship["type"]=="ManyToMany" and !isset($relationship["junctions"]) and isset($relationship["nexii"])){
			$relationship["junctions"] = $relationship["nexii"];
		}
		return $relationship;
	}
	
	
	/**
	 * Does the model have a relationship with the name $relationshipName.
	 * 
	 * @param string $relationshipName Relationship name.
	 * @return boolean true if $relationshipName relationship exists, false otherwise.
	 **/
	public static function metaHasRelationship($relationshipName){
		return isset(static::$RELATIONSHIPS[$relationshipName]);
	}
	
	
	/**
	 * Returns a relationship as a LuloQuery filter. It is useful for computing
	 * conditions in the remote tables.
	 * 
	 * @param string $remoteModel Remote model name.
	 * @param string $remoteRelationshipName Relationship name.
	 * @return array Array with a LuloQuery filter.
	 **/
	protected function metaGetRelationshipAsLuloQueryFilter($remoteModel, $remoteRelationshipName){
		
		$relationship = $remoteModel::$RELATIONSHIPS[$remoteRelationshipName];
		$relationshipType = $relationship["type"];
		
		// If it is many to many, use the operator :: to show that there are
		// remote attributes in the filter
		if($relationshipType == "ManyToMany"){
			$relationshipConditions = $relationship["conditions"];
			$filter = [];
			$lastConditionIndex = count($relationshipConditions)-1;
			foreach($relationshipConditions[$lastConditionIndex] as $localAttribute=>$remoteAttribute){
				$filter["{$remoteRelationshipName}::{$remoteAttribute}"] = $this->getAttr($remoteAttribute);
			}
			return $filter;
		}
		
		// If the relationship is ForeighKey or OneToMany, there is no
		// intermediate table and it is easier
		$relationshipCondition = $relationship["condition"];
		// Filter creation
		$filter = [];
		foreach($relationshipCondition as $localAttribute=>$remoteAttribute){
			// Relationship with the same table
			if($relationship["model"] == static::CLASS_NAME){
				$filter["$localAttribute"] = $this->getAttr($remoteAttribute);
			}else{
				$filter["{$remoteRelationshipName}::{$remoteAttribute}"] = $this->getAttr($remoteAttribute);
			}
		}
		return $filter;
	}
}

?>
