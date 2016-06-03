<?php

namespace lulo\models;

/**
 * Abstract class that allows read-only access to a table.
 * 
 * @author Diego J. Romero López at intelligenia.
 */
abstract class ROModel{
	
	use \lulo\models\traits\Init;
	use \lulo\models\traits\Load;
	use \lulo\models\traits\LoadRelated;
	use \lulo\models\traits\Meta;
	use \lulo\models\traits\Query;
	use \lulo\models\traits\Repr;
	
	/** Model table that will be read */
	const TABLE_NAME = "<TABLE_NAME>";
	
    /** Name of autoincrementable id attribute */
    const ID_ATTRIBUTE_NAME = "id";
	
	/** Database connection used */
	const DB = "\lulo\db\DB";
		
	/** Class name */
	const CLASS_NAME = "<CLASS_NAME>";
	
	/** Metainformation about this model **/
	protected static $META = [
		"model_description" =>"<Model description>",
		"verbose_name" => "<ROModel>",
		"verbose_name_plural" => "<ROModels>",
		"gender" => "<neutral>",
	];
	
	/** Attributes */
	protected static $ATTRIBUTES = array();
	
	
	/** Attributes that form the primary key */
	protected static $PK_ATTRIBUTES = array();
	
	/**
	 * Models related somehow to this model.
	 * 
	 * This attribute is mandatory and MUST contain a list of strings
	 * with the names of the models that have a direct (or inverse) relationship
	 * with this model.
	 * 
	 * Otherwise, inverse relationships will not work.
	 * */
	protected static $RELATED_MODELS = [];
	
	/** Relationships with other models */
	protected static $RELATIONSHIPS = array();

	/** Flag inverse relationship already automatically created */
	protected static $INVERSE_RELATIONSHIPS_ACTIVATED = [];
	
	/* ENDOVERWRITE */
	
	/**
	 * Stats and computed attributes of children models of ROModel.
	 * */
	protected static $CHILDREN_CLASS_ATTRIBUTES = [
		//"<clase>" => [
		//	"attribute_names" => [/*Attribute name*/]
		//	"atribute_names_str" => "/*string with the fields that will be used in SQL SELECT */"
		//]
	];
	
	/* Object properties */
	
	/** Stores object attributes. These are tuple attributes data. */
	protected $attributeValues = array();
	
	
	/** Dynamic attributes. */
	protected $dynamicAttributes = array();
	
	
	/** Triggers used for doing some operations automatically */
	protected static $triggerCache = array();
	
	
	/**
	 * Init model static attributes
	 * - $attribute_names: model attribute names.
	 * - $attribute_names_str: string with the non-blob attribute names separated by commas.
	 * @return boolean true if attributes are initiated, false otherwise.
	 * */
	private static function initAttributesMetaInformation(){
		$class = get_called_class();
		
		if(!isset(static::$CHILDREN_CLASS_ATTRIBUTES[$class])){
			static::$CHILDREN_CLASS_ATTRIBUTES[$class] = [];
		}
		
		// Non-blob attributes of this model
		if(!isset(static::$CHILDREN_CLASS_ATTRIBUTES[$class]["attribute_names"])){
			$nonBlobAttributes = [];
			foreach(static::$ATTRIBUTES as $attribute=>$attributeProperties){
				if($attributeProperties["type"] != "blob"){
					$nonBlobAttributes[] = $attribute;
				}
			}
			static::$CHILDREN_CLASS_ATTRIBUTES[$class] = [
				"attribute_names" => $nonBlobAttributes,
				"attribute_names_str" => implode(",", $nonBlobAttributes)
			];
		}
		return static::$CHILDREN_CLASS_ATTRIBUTES[$class];
	}
	
	
	/**
	* Return the name of the table of this model.
	* @return string Table name this model depends on.
 	*/
	public static function getTableName(){ return static::TABLE_NAME; }
	
	
	/**
	* Return the name of this class.
	* @return string Class name of this model.
 	*/
	public static function getClassName(){ return static::CLASS_NAME; }
	
	
	/**
	* Inform if $object is of class Model
	* @param string $object Objet to test if its class is of this model.
	* @return boolean true if $object is of this model's class, false otherwise.
 	*/
	public static function isInstanceOf($object){
		// In case $object is not an object, return false
		if(!is_object($object)){
			return false;
		}
		$className = static::CLASS_NAME;
		return ($object instanceof $className);
	}
	
	
	/**
	* Inform if $object is of class Model or a class that inherits of this model.
	* @param string $object Objet to test if its class is of this model.
	* @return boolean true if $object is of this model's class, false otherwise.
 	*/
	public static function isA($object){
		$className = static::CLASS_NAME;
		return is_a($object, $className);
	}
	
	////////////////////////// MAGIC METHODS ///////////////////////////////////
	
	/**
	* Magic method: treat relationships as function.
	* 
	* E. g., if a relationship "tags" exists in this model, calling $this->tags
	* will return a Query with the relationship "tags".
	* 
	* If there is no relationship called $methodName, this method will search
	* through the attributes of this object until it find one that has a method
	* called $methodName and will call it.
	* 
	* @param string $methodName Relationship name.
	* @param array $args Array with arguments of this method.
	* @return mixed Query with the realtionship if exists with the name $methodName
	 * or the results of calling method $methodName of one object in a dynamic attribute.
	*/
	public function __call($methodName, $args){
		// If there is a relationship with name $methodName
		if(array_key_exists($methodName, static::$RELATIONSHIPS)){
			$relationship = static::$RELATIONSHIPS[$methodName];
			$relatedModel = $relationship["model"];
			$query = new \lulo\query\Query($relatedModel);
			// Relationship condition converted to Query filter
			$remoteFilter = $this->metaGetRelationshipAsLuloQueryFilter($relatedModel, $relationship["related_name"]);
			$relationshipQuery = $query->filter($remoteFilter);
			// If there are some arguments, they act as a filter
			if(count($args)>0){
				if(is_array($args) and isset($args[0]) and is_array($args[0]) and isset($args[0][0])){
					$args = $args[0];
				}
				return $relationshipQuery->filter($args);
			}
			return $relationshipQuery;
		}

		// Test if one of the dynamic objects has a method called $methodName
		// in that case, call it.
		if($this->dynamicAttributes!=null and count($this->dynamicAttributes)>0){
			foreach($this->dynamicAttributes as $dynat){
				if(is_object($dynat) and is_callable(array($dynat, $methodName))){
					if(!empty($args) and count($args)>0){
						return call_user_func_array(array($dynat, $methodName), $args);
					}
					return $dynat->$methodName();
				}
			}
		}
		
		// Bad method call. It does not exist anywhere
		throw new \BadMethodCallException("Method {$methodName} does not exist in ".static::CLASS_NAME." nor in any of its dynamic attributes.");
	}
	
	
	/**
	* Test if attribute exists.
	* 
	* NOTE: isset($this->attr) returns true although $this->attr = null;
	* 
	* @param string $name Attribute name.
	* @return boolean true if $name is the name of a standard or dynamic attribute. False otherwise.
	*/
	public function __isset($name){
		// If is a standard attribute, check if exists
		if(static::metaHasAttribute($name)){
			return array_key_exists($name, $this->attributeValues);
		}
		// If is a dynamic attribute, check if exists
		if(array_key_exists($name, $this->dynamicAttributes)){
			return array_key_exists($name, $this->dynamicAttributes);
		}
		return false;
	}
	
	
	/**
	* Return attribute value.
	* @param string $name Attribute name.
	* @return mixed Attribute value
	*/
	public function __get($name){
		// If is a standard attribute, return it
		if(array_key_exists($name, $this->attributeValues)){
			return $this->attributeValues[$name];
		}
		// If is a dynamic attribute, return it
		if(array_key_exists($name, $this->dynamicAttributes)){
			return $this->dynamicAttributes[$name];
		}
		return null;
	}
	
	
	/**
	 * Test if one attribute is editable
	 * @param string $attribute Attribute name.
	 * @return boolean True if is editable, false otherwise.
	 * */
	protected static function canEditAttribute($attribute){
		return (
			!isset(static::$ATTRIBUTES[$attribute]["access"]) or (
				// Access permissions
				(static::$ATTRIBUTES[$attribute]["access"]=="rw" or static::$ATTRIBUTES[$attribute]["access"]=="writable")
			)
		);
	}
	
	
	/**
	 * Inform if edition of attribute has been made from one of Lulo classes or
	 * from the outside
	 * @return boolean true if call was made from inside, false otherwise.
	 * */
	protected static function attributeEditionIsCalledFromClassScope(){
		$backtrace = debug_backtrace(false, 4);
		if(is_array($backtrace) and count($backtrace)>0){
			$caller = $backtrace[3];
			$isAllowed = (
				isset($caller["class"]) and
				(
					$caller["class"] == get_called_class() or
					$caller["class"] == RWModel::CLASS_NAME or
					$caller["class"] == __CLASS__ or
					$caller["class"] == LuloModel::CLASS_NAME
				)
			);
			return $isAllowed;
		}
		return true;
	}
	
	
	/**
	 * Test if attribute is editable.
	 * NOTE: thrown exception if attribute is not editable.
	 * @param string $attribute Attribute name to check access.
	 * */
	protected static function assertAttributeEdition($attribute){
		// Depending on attribute privilege level access, check it
		if(!static::canEditAttribute($attribute) and !static::attributeEditionIsCalledFromClassScope()){
			throw new \Exception("Edition not allowed for {$attribute} in ".static::CLASS_NAME.".");
		}
	}
	
	
	/**
	* Set an attribute.
	* @param string $attribute Attribute name.
	* @param string $value Attribute value.
	*/
	public function __set($attribute, $value){
		// If is an standard attribute, check if is editable and edit it
		if(array_key_exists($attribute, static::$ATTRIBUTES)){
			static::assertAttributeEdition($attribute);
			$this->attributeValues[$attribute] = $value;
		
		// If is not an standard attribute, assign it as a dynamic attribute
		}else{
			$this->dynamicAttributes[$attribute] = $value;
		}
	}
	
	
	/**
	* Unset an attribute
	* @param $attribute Attribute name.
	*/
	public function __unset($attribute){
		// If is an standard attribute, check if is editable and unset it
		if(array_key_exists($attribute, $this->attributeValues)){
			static::assertAttributeEdition($attribute);
			unset($this->attributeValues[$attribute]);
		
		// Unset the dynamic attribute with this name	
		}else{
			unset($this->dynamicAttributes[$attribute]);
		}
	}
	
	
	/**
	* Return attribute value.
	* 
	* @param string $name Attribute name.
	* @param function $modifier Function to be applied to the value of the attribute. If null, it is ignored.
	* @return mixed Attribute value (posibilly modified by $modifier).
	*/
	public function getAttribute($name, $modifier=null){
		$value = $this->__get($name);
		if(is_null($modifier)){ return $value; }
		return $modifier($value);
	}
	
	
	/**
	* Return the value of an attribute.
	* Alias of getAttribute.
	* @param function $modifier Function that will be applied to attribute.
	* @return mixed Value of this attibute.
	*/ 
	public function getAttr($attribute, $modifier=null){
		return $this->getAttribute($attribute, $modifier);
	}
	
	
	/**
	* Return several attribute values.
	* 
	* @param array $names Array with the attributes to return.
	* @return array Array of the form <attribute>=><value> for all attributes in $names.
	*/
	public function getAttributes($names){
		if(is_string($names)){
			$names = explode('|', $names);
		}
		// Get the value of each attribute
		$selectedAttributes = array();
		foreach($names as $name){
			$selectedAttributes[$name] = $this->$name;
		}
		return $selectedAttributes;
	}
	
	
	/**
	 * Return an array with the values of the attributes that are not blobs.
	 * @return array Array of the form <attribute>=><value> for all non-blob attributes.
	 * */
	public function getNonBlobAttributes(){
		$attributeMetadata = static::initAttributesMetaInformation();
		$nonBlobAttributes = $attributeMetadata["attribute_names"];
		return $this->getAttributes($nonBlobAttributes);
	}
	
	
	/**
	 * Return primary key as a array of values.
	 * @return array Array of the form <attribute>=><value> for all attributes that form the primary key.
	 * */
	public function getPk(){
		return $this->getAttributes(static::$PK_ATTRIBUTES);
	}

	
	/**
	* Call of the trigger
	* @param $name Trigger name.
	* @param $data Extra data for the trigger.
	* @return mixed true if trigger was executed without problems, false otherwise. Null if trigger was not executed.
	*/
	protected function callTrigger($name, $data=null){
		$tname = "trigger".ucfirst($name);
		if(isset(static::$triggerCache[$tname])){
			return $this->$tname($data);
		}elseif(method_exists($this, $tname)){
			static::$triggerCache[$tname] = true;
			return $this->$tname($data);
		}
		return null;
	}
	
	
	/**
	 * Implicit validation of an object.
	 * @param array $attributes Object attibutes as an array.
	 * @return array Array with the attributes ready to create a new object of this model.
	 * */
	public static function cleanObjectAttributes($attributes){
		// Default value for each attribute that is not present
		$definedAttributes = array_keys(static::$ATTRIBUTES);
		foreach($definedAttributes as $definedAttribute){
			if(!isset($attributes[$definedAttribute]) and isset(static::$ATTRIBUTES[$definedAttribute]["default"])){
				$attributes[$definedAttribute] = static::$ATTRIBUTES[$definedAttribute]["default"];
			}
		}
		// Rewrite this method and call the parent method, throwing
		// - InvalidArgumentException: if attribute has not the right type.
		// - DomainException: if the attribute value is not a legal value.
		return $attributes;
	}
	
	
	/**
	* Default constructor.
	* NOTE: require an array with all its attributes.
	* @param array $attributes An array with all the attributes for this object.
	* @param boolean $applyImplicitCleaning Should this input data be validated?
	*/
	public function __construct($attributes, $applyImplicitCleaning=true){
		// If the implicit cleaning of input attribute values is needed
		// apply it 
		if($applyImplicitCleaning){
			$attributes = static::cleanObjectAttributes($attributes);
		}
		// Attribute assignement. Depending on if is a known attribute
		// or an unknown attribute, it will go to an standard attribute or
		// a dynamic attribute (resp.).
		foreach($attributes as $argumentName=>$value){
			if(isset(static::$ATTRIBUTES[$argumentName])){
				$this->attributeValues[$argumentName] = $value;
			}
			else{
				$this->dynamicAttributes[$argumentName] = $value;
			}
		}
	}
	
	
	/**
	* Construct an object from a database tuple
	*
	* This method do not apply implicit cleaning to input data ($row).
	* 
	* @param array $row Database tuple as array.
	* @return object Model object initialized with the $row data.
	*
	*/
	public static function factoryFromRow($row){
		$currentClass = get_called_class();
		$object = new $currentClass($row, false);
		$object->callTrigger("postFactoryFromRow");
		return $object;
	}
	
	
	/**
	* Construct an object from an array.
	* 
	* This method applies implicit cleaning to input data ($data).
	* 
	* @param array data Array with data needed to create an object of this model.
	* @return object Model object initialized with the $data data.
	*
	*/
	public static function factoryFromArray($data){
		$currentClass = get_called_class();
		$object = new $currentClass($data, true);
		$object->callTrigger("postFactoryFromRow");
		return $object;
	}
	
	
	/**
	* Create an array of objects from an array of database tuples.
	* 
	* @param array $rows Array of database tuples (array of arrays)
	* @return array Model objects array.
.	*/
	public static function arrayFactoryFromRows($rows){
		$res = array();
		foreach($rows as $row){
			$res[] = static::factoryFromRow($row);
		}
		return $res;
	}
	
	
	/**
	* Check if two objects of this model are equal. That is, if they
	* contain the same data.
	* 
	* @param object $other Object to check.
	* @return boolean true if both object contain the same data, false otherwise.
	*/
	public function equals($other){
		// If the objects have different classes, they can't be equal
		if(static::CLASS_NAME != get_class($other)){
			return false;
		}
		// For each attribute check if their values are equal
		$attributeNames = static::metaGetAttributeNames();
		foreach($attributeNames as $attributeName){
			// Only non-blob attributes are checked
			if(static::$ATTRIBUTES[$attributeName]["type"]!="blob" and $this->$attributeNames != $other->$attributeName){
				return false;
			}
		}
		return true;
	}
	
	
	/**
	* Return current object attributes as an array.
	* @return array Array with object data as a pair of <attribute>=><value>.
	*/
	public function getAsArray(){
		$data = $this->attributeValues;
		return $data;
	}
	
	
	/**
	* Return current object dynamic attributes as an array.
	* @return mixed Array with object dynamic attributes as a pair of
	* <attribute>=><value> or null if no dynamic attribute is present.
	*/
	public function getDynamicAttributes(){
		return $this->dynamicAttributes;
	}
	
	
	/**
	* Return current object dynamic attributes as an array.
	* @return array Array with object dynamic attributes as a pair of <attribute>=><value>.
	*/
	public function getDynamicAttributesAsArray(){
		$dynamicData = $this->getDynamicAttributes();
		if(is_null($dynamicData) or !is_array($dynamicData)){
			return [];
		}
		return $dynamicData;
	}
	
	
	/**
	* Return current object attributes as an array.
	* This method should be overwritten to return data in a way that's readable
	* for humans.
	* @return array Array with object data as a pair of <attribute>=><value>.
	*/
	public function getAsFormattedArray(){
		return $this->getAsArray();
	}
	

	/**
	 * Implicit conditions when loading objects.
	 * 
	 * All methods dbLoad, dbLoadAll, dbDelete and Queries will be affected
	 * by this implicit base conditions.
	 * 
	 * The idea is overwrite this conditions to include attributes that help us
	 * to mark objects as deleted and ignore them when dealing with objects.
	 * 
	 * @return array Array with pairs <attribute>=>[<operator> => <"value">].
	 * */
	public static function implicitBaseCondition(){
		// By default there are no implicit conditions
		return [];
	}
	
	
	/**
	 * Add implicit conditions to dbLoad or dbLoadAll condition.
	 * 
	 * @param array $explicitCondition Explicit condition that will be extended
	 * with the implicit base condition.
	 * @return array Final conditions to apply to query. 
	 * */
	protected static function getBaseCondition($explicitCondition){
		// Implicit condition
		$implicitCondition = static::implicitBaseCondition();
		// If there are no explicit conditions, return implicit ones
		if(is_null($explicitCondition) or (is_array($explicitCondition) and count($explicitCondition)==0)){
			return $implicitCondition;
		}
		// Otherwise, merge both conditions
		$finalCondition = $implicitCondition;
		foreach($explicitCondition as $attribute=>$value){
			$finalCondition[$attribute] = $value;
		}
		return $finalCondition;
	}

	
	/**
	* Assign a value to an attribute.
	* @param string $attribute Name of the attribute to assign.
	* @param mixed New value to $attribute attribute.
	*/
	public function setAttr($attribute, $value){
		$this->$attribute = $value;
	}
	
	
	/**
	* Assign values to object attributes.
	* @param array $values Array of pairs <attribute>=><value>.
	*/
	public function setAttributes($values){
		// Para cada atributo que hemos pasado como parámetro,
		// le asignamos el valor que tenga asociado
		$selectedAttributes = array();
		foreach($values as $name=>$value){
			if(isset($this->$name)){
				$this->$name = $value;
				$selectedAttributes[] = $name;
			}
		}
		return $selectedAttributes;
	}
	
	
	/**
	 * Clean method for setFromArray.
	 * Set default values for the array that will be used to assign attributes
	 * to a model object.
	 * 
	 * 
	 * @param array $data Array with data to assign.
	 * @param object $object Object to be edited.
	 * @return array Array with cleaned data to assign it to object.
	 * 	 */
	public static function cleanSetFromArray($data, $object){
		// Attribute value setting
		$attributeNames = array_keys(static::$ATTRIBUTES);
		foreach($attributeNames as $attributeName){
			// If attribute is not in data
			if(!array_key_exists($attributeName, $data)){
				// If attribute has a value in object
				if(isset($object->$attributeName)){
					$data[$attributeName] = $object->$attributeName;
				}
				// Default value
				elseif(isset(static::$ATTRIBUTES[$attributeName]["default"])){
					$data[$attributeName] = static::$ATTRIBUTES[$attributeName]["default"];
				}
			}
		}
		return $data;
	}
	
	/**
	* Assign values to an object.
	* @param $values Array of pairs with the data to assign.
	*/
	public function setFromArray($values){
		$processedValues = [];
		// If method cleanSetFromArray exists and is callable, apply it to
		// input data values
		$cleanMethodName = "cleanSetFromArray";
		if(method_exists(static::CLASS_NAME, $cleanMethodName) and is_callable([static::CLASS_NAME, $cleanMethodName])){
			$processedValues = static::$cleanMethodName($values, $this);
		}
		// If cleanFromArray is not callable, assign input data values
		// directly to input object values
		else{
			$processedValues = $values;
		}
		
		$attributeAssignement = [];
		
		// For each attribute, call clean_<attribute> if it exists.
		// Otherwise, assign its value
		foreach($processedValues as $name=>$value){
			$cleanAttributeMethodName = "clean_{$name}";
			$hasCleanAttribute = method_exists(static::CLASS_NAME, $cleanAttributeMethodName) and is_callable([static::CLASS_NAME, $cleanAttributeMethodName]);
			if($hasCleanAttribute){
				$attributeAssignement[$name] = static::$cleanAttributeMethodName($value);
			}
			else{
				$attributeAssignement[$name] = $value;
			}
		}
		// Assignement of attribute values to this objects
		$this->setAttributes($attributeAssignement);
		
		// Assignement of original source values to a dynamic attribute to
		// keep them just in case
		$this->_source_values = $values;
	}
	
	/**
	 * Check if $attribute attribute value matches with a regex.
	 * @param string $regex Regular expression to match $attribute.
	 * @param string $attribute Attribute to check if matches with $regex.
	 * @return boolean true if preg_match($regex, $this->$attribute). False otherwise.
	*/ 
	public function pregMatchAttr($regex, $attribute){
		return (preg_match($regex, $this->$attribute) >= 1);
	}
	
	
	/**
	 * Check if the value of an attrribute is empty.
	 * Check if $this->$attribute is empty.
	 * @param string $attribute Attribute to check if its value is empty
	 * @return boolean true if attribute value is empty, false otherwise.
	*/ 
	public function attrIsEmpty($attribute){
		return empty($this->$attribute);
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	
	/**
	 * Check if this object is valid.
	 * This method should be overwritten.
	 * @return true if this object is valid, false otherwise.
	 * */
	public function isValid(){
		return true;
	}

	
	/**
	 * Check if this object has a dynamic attribute.
	 * @param string $attributeName Dynamic attribute name.
	 * @return boolean True if $attributeName is the name of a dynamic attribute
	 * in this object, false otherwise.
	 */
	public function hasDynamicAttribute($attributeName){
		return array_key_exists($attributeName, $this->dynamicAttributes);
	}
	
	
	/**
	 * Check if this object has a attribute.
	 * It does not matter if the attribute is a dynamic or standard attribute.
	 * @param string $attributeName Attribute name.
	 * @return boolean True if $attributeName is the name of a dynamic
	 * or standard attribute in this object, false otherwise.
	 */
	public function hasAttribute($attributeName){
		return ( static::metaHasAttribute($attributeName) or $this->hasDynamicAttribute($attributeName) );
	}
	
	
	/**
	 * Ascends an attribute converting it to an object.
	 * @param string $attributeName Attribute to ascend.
	 * @param array $extraParemeters Extra paramenters of the ascendancy.
	 * @return object Object that represents this $attributeName value.
	 * */
	public function a($attributeName, $extraParemeters=null){
		$model = static::CLASS_NAME;
		
		// Check if this attribute exists as a dynamic attribute
		if(!isset($model::$ATTRIBUTES[$attributeName])){
			if($this->hasDynamicAttribute($attributeName)){
				return $this->$attributeName;
			}
			// It does not exist as a dynamic attribute, throw exception
			$strPk = $this->getStrPk();
			throw new \UnexpectedValueException("{$attributeName} does not exist in object {$strPk} of model {$model}");
		}
		
		// Attribute properties
		$attributeProperties = $model::$ATTRIBUTES[$attributeName];
		
		// Subtype is what helps us identify how to ascend the attribute
		// if attribute metadata does not have subtype is a common attribute
		// that couldn't be ascended
		if(!isset($attributeProperties["subtype"])){
			return $this->$attributeName;
		}
		
		// According to its subtype, the attribute is ascended if possible
		$subtype = $attributeProperties["subtype"];
		if($subtype == "date" || $subtype == "datetime" || $subtype == "time"){
			return new \DateTime($this->$attributeName);
		}
		// Otherwise, returns the value of the attribute
		return $this->$attributeName;
	}
	
}
/************************************************************************************************/

?>
