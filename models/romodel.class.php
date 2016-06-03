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
	* Alias of magic method __get.
	* 
	* @param string $name Attribute name.
	* @return mixed Attribute value
	*/
	public function getAttribute($name){
		return $this->__get($name);
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
	 * Validación implícita de un objeto.
	 * @param array $attributes Array con los atributos de objeto.
	 * @return array Array con los atributos de objeto preparados para crear el objeto.
	 * */
	public static function cleanObjectAttributes($attributes){
		// Lo primero que hace este método es asignarle a los atributos
		// que no existen en el array que sea pasa como parámetro
		// sus valores por defecto
		$definedAttributes = array_keys(static::$ATTRIBUTES);
		foreach($definedAttributes as $definedAttribute){
			if(!isset($attributes[$definedAttribute]) and isset(static::$ATTRIBUTES[$definedAttribute]["default"])){
				$attributes[$definedAttribute] = static::$ATTRIBUTES[$definedAttribute]["default"];
			}
		}
		// Este método deberá devolver los atributos validados y en caso
		// de que un atributo tenga un valor erróneo,
		// devolver una excepción siguiendo estas reglas.
		// - InvalidArgumentException: si el atributo no es del tipo adecuado.
		// - DomainException: si el valor para un atributo no es un valor legal.
		return $attributes;
	}
	
	
	/**
	* Constructor por defecto a partir de un array de argumentos.
	* Como la nueva forma de llamar a los arrays permite el uso de [],
	* este constructor requiere un array con todos sus parámetros.
	* @param array $attributes Cada uno de los atributos del objeto como un array.
	* @param boolean $applyImplicitCleaning Indica si se ha de aplicar la validación y limpieza implícita del modelo.
	*/
	public function __construct($attributes, $applyImplicitCleaning=true){
		// Si se aplica la limpieza implícita de objeto, limpiamos
		// y validamos los atributos de objeto
		if($applyImplicitCleaning){
			$attributes = static::cleanObjectAttributes($attributes);
		}
		// Asignación de cada uno de los atributos
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
	* Fábrica desde una tupla
	*
	* Los atributos extra que se pasen en $row y que no estén entre los atributos
	* de instancia de esta clase (DIR_Sede), se crearán como atributos
	* dinámicos en la instancia devuelta
	*
	* @param array $row Array con los datos extraídos de una tupla de la BD
	* @return DIR_Sede Objeto inicializado con los datos del array
	*
	*/
	public static function factoryFromRow($row){
		$currentClass = get_called_class();
		$object = new $currentClass($row, false);
		$object->callTrigger("postFactoryFromRow");
		return $object;
	}
	
	
	/**
	* Crea un objeto a partir de un array.
	* @param array $data Array con los datos del objeto como pares atributo=>valor.
	* @return Objeto actual construido a partir de un array.
	*/
	public static function factoryFromArray($data){
		$currentClass = get_called_class();
		$object = new $currentClass($data, true);
		$object->callTrigger("postFactoryFromRow");
		return $object;
	}
	
	
	/**
	* Fábrica desde tuplas
	* @param array $rows Array de array con tuplas extraídas de la BD
	* @return array Array de objetos DIR_Sede inicializados con los datos del array
.	*/
	public static function arrayFactoryFromRows($rows){
		$res = array();
		foreach($rows as $row){
			$res[] = static::factoryFromRow($row);
		}
		return $res;
	}
	
	
	/**
	* Informa si los dos objetos son iguales, esto es, si contiene datos con los mismos datos.
	* @param object $other Objeto con el que se desea comparar la igualdad (que no identidad).
	* @return boolean true si los objetos comparados contienen los mismos datos, false en otro caso.
	*/
	public function equals($other){
		// Lo primero es comprobar que el nombre
		// de la clase de ambos objetos es el mismo
		if(static::CLASS_NAME != get_class($other)){
			return false;
		}
		// Para cada uno de los atributos, deben tener el mismo valor
		$attributeNames = static::metaGetAttributeNames();
		foreach($attributeNames as $attributeName){
			// Sólo comprobamos si los valores para los campos que no sean blob
			// son iguales
			if(static::$ATTRIBUTES[$attributeName]["type"]!="blob" and $this->$attributeNames != $other->$attributeName){
				return false;
			}
		}
		return true;
	}
	
	
	/**
	* Devuelve los datos del objeto como array.
	* @return array Array con los datos del objeto como pares atributo=>valor.
	*/
	public function getAsArray(){
		$data = $this->attributeValues;
		return $data;
	}
	
	
	/**
	* Devuelve los datos dinámicos del objeto como array.
	* @return array Array con los datos dinámicos del objeto como pares atributo=>valor.
	*/
	public function getDynamicAttributes(){
		return $this->dynamicAttributes;
	}
	
	
	/**
	* Devuelve los datos dinámicos del objeto como array. Sobrecarga de getDynamicAttributes.
	* @return array Array con los datos dinámicos del objeto como pares atributo=>valor.
	*/
	public function getDynamicAttributesAsArray(){
		$dynamicData = $this->getDynamicAttributes();
		if(is_null($dynamicData) or !is_array($dynamicData)){
			return [];
		}
		return $dynamicData;
	}
	
	
	/**
	* Devuelve los datos del objeto como array formateados para que sean legibles por un humano.
	* @return array Array con los datos del objeto como pares atributo=>valor.
	*/
	public function getAsFormattedArray(){
		return $this->getAsArray();
	}
	

	/**
	 * Informa de las condiciones implícitas de carga de los objetos.
	 * Esto es, condiciones obtenidas en todo método dbLoad, dbLoadAll, dbDelete y sus derivados.
	 * Estas condiciones se añadirán a las condiciones que pase el desarrollador
	 * de este modelo, por lo que ha de tener cuidado al definirlas.
	 * Esta funcionaldad permite tener atributos como "is_erased" que indican
	 * estados "sumidero", o lo que es igual, estados de los que es imposible salir.
	 * También permite tener varios sitios web usando el mismo modelo y
	 * evitando que desde un sitio web se puedan ver otros.
	 * @return array Array con las condiciones implícitas de carga de objetos de este modelo.
	 * */
	public static function implicitBaseCondition(){
		// Por defecto, no hay condiciones implícitas
		return [];
	}
	
	
	/**
	 * Devuelve la condición de una consulta de carga de objetos.
	 * Es decir, añade las condiciones implícitas a las condiciones que
	 * ha introducido el desarrollador.
	 * @return array Array con las condiciones finales que se deben ejecutar para cargar objetos. 
	 * */
	protected static function getBaseCondition($explicitCondition){
		// Condición implícita
		$implicitCondition = static::implicitBaseCondition();
		// Si no hay condiciones explícitas, devolvemos las implícitas
		if(is_null($explicitCondition) or (is_array($explicitCondition) and count($explicitCondition)==0)){
			return $implicitCondition;
		}
		// Hay condiciones explícitas, por lo tanto, hemos de añadirles
		// las condiciones implícitas sin romper nada.
		// Por tanto, las condiciones finales serán las condiciones implícitas
		// con los cambios introducidos desde las condiciones explícitas
		$finalCondition = $implicitCondition;
		foreach($explicitCondition as $attribute=>$value){
			$finalCondition[$attribute] = $value;
		}
		return $finalCondition;
	}

	
	/**
	* Devuelve el atributo textual. Se le pueden aplicar modificadores textuales de TextHelper.
	* @param string|array $modifiers Modificadores de textos existentes en TextHelper.
	* @return string Atributo del objeto.
	*/ 
	public function getAttr($attribute, $modifiers=null){
		if($modifiers==null){ return $this->$attribute; }
		$text = TextHelper::modifyText($this->$attribute, $modifiers);
		return $text;
	}
	
	
	/**
	* Asigna al atributo un nuevo valor.
	* @param string Nuevo valor para el atributo $value del objeto.
	*/
	public function setAttr($attribute, $value){
		$this->$attribute = $value;
	}
	
	
	/**
	* Asigna al atributo un nuevo valor.
	* @param $values Pares clave-valor que asignan a los atributos del objeto.
	* @return array Array con los nombres de los atributos modificados.
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
	 * Método clean para el método setFromArray.
	 * Establece los valores por defecto en el array que va a asignar atributos
	 * al objeto $object.
	 * 
	 * @param array $data Array con los datos a asignar.
	 * @param object $object Objeto sobre el que se van a asignar.
	 * @return array Array con los datos preparados para asignarlos a $object.
	 * 	 */
	public static function cleanSetFromArray($data, $object){
		// Para cada atributo, comprobamos si está presente en $data
		// Si no está presente pero tenemos forma de obtener un valor,
		// lo obtenemos de donde podamos (del objeto o el valor por defecto definido)
		$attributeNames = array_keys(static::$ATTRIBUTES);
		foreach($attributeNames as $attributeName){
			// Si el atributo no está presente en los datos
			if(!array_key_exists($attributeName, $data)){
				// Si el objecto tiene un valor asignado, asignamos este valor
				if(isset($object->$attributeName)){
					$data[$attributeName] = $object->$attributeName;
				}
				// Si la clase tiene un valor por defecto,
				// asignamos este valor predeterminado
				elseif(isset(static::$ATTRIBUTES[$attributeName]["default"])){
					$data[$attributeName] = static::$ATTRIBUTES[$attributeName]["default"];
				}
			}
		}
		return $data;
	}
	
	/**
	* Asigna al atributo un nuevo valor a partir de un formulario.
	* @param $values Pares clave-valor que asignan.
	*/
	public function setFromArray($values){
		$processedValues = [];
		// Si existe el método cleanFromArray, se ejecuta y convierte los datos
		// del array a datos que puede interpretar el objeto de forma correcta
		$cleanMethodName = "cleanSetFromArray";
		if(method_exists(static::CLASS_NAME, $cleanMethodName) and is_callable([static::CLASS_NAME, $cleanMethodName])){
			$processedValues = static::$cleanMethodName($values, $this);
		}
		// Si no existe el método cleanFromArray
		else{
			$processedValues = $values;
		}
		
		// Array que contendrá los atributos correctos en formato
		// adecuado para el método $this->setAttributes
		$attributeAssignement = [];
		// Para cada valor que hemos introducido, si existe el método
		// clean_<attribute>, lo ejecutamos antes para validar
		// o convertir el atributo antes de asignarlo.
		// Si no existe el método clean_<attribute> lo asignamos directamente.
		foreach($processedValues as $name=>$value){
			// Si existe un método "clean" para ese atributo que comprueba
			// si tiene un valor adecuado, se asigna al array de datos
			// procesados
			$cleanAttributeMethodName = "clean_{$name}";
			$hasCleanAttribute = method_exists(static::CLASS_NAME, $cleanAttributeMethodName) and is_callable([static::CLASS_NAME, $cleanAttributeMethodName]);
			if($hasCleanAttribute){
				$attributeAssignement[$name] = static::$method_name($value);
			}
			else{
				$attributeAssignement[$name] = $value;
			}
		}
		// Asignación de los datos procesados
		$this->setAttributes($attributeAssignement);
		
		// Asignación en una variable dinámica de los valores del formulario
		$this->_source_values = $values;
	}
	
	/**
	* Comprueba si el atributo $attribute empareja con la cadena pasada como parámetro.
	* @param $regex Expresión regular en formato PERL (formato preg_match en PHP) del atributo que se desea comprobar si empareja con la cadena.
	* @return boolean true si empareja con la $regex, false en otro caso.
	*/ 
	public function pregMatchAttr($regex, $attribute){
		return (preg_match($regex, $this->$attribute) >= 1);
	}
	
	
	/**
	* Comprueba si el atributo $COD_SEDE está vacío (empty).
	* @return boolean true si el atributo del objeto está vacío, false en otro caso. I. E. devuelve el resultado de aplicar empty sobre el atributo.
	*/ 
	public function attrIsEmpty($attribute){
		return empty($this->$attribute);
	}
	
	
	/**
	* Comprueba si un valor para el atributo $value es válido según la especificación del atributo $value del modelo.
	* @param string $value Valor que se va a comprobar si obedece las especificaciones del atributo con el mismo nombre.
	* @return boolean True si $value cumple las especificaciones (es un valor para $value válido); false en otro caso.
	*/
	public static function attrValueIsValid($attribute, $value){
		$type = $this->attributeTypes[$attribute]["type"];
		return gettype($value) == $type;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	
	/**
	 * Comprueba si este objeto obedece a sus restricciones propias
	 * @return true si es correcto, false en otro caso.
	 * */
	public function isValid(){
		return true;
	}
	
	
	
	/**************************************************************************/
	/**************************************************************************/
	/**************** Gestión de atributos a nivel de objeto ******************/
	
	/**
	 * Informa, a nivel de objeto si existe un atributo dinámico.
	 * @param string $attributeName Nombre del atributo dinámico a comprobar si existe.
	 * @return boolean Booleano informando sobre si existe el atributo dinámico o no en este objeto.
	 */
	public function hasDynamicAttribute($attributeName){
		return array_key_exists($attributeName, $this->dynamicAttributes);
	}
	
	
	/**
	 * Informa, a nivel de objeto si existe un atributo (estándar o dinámico).
	 * @param string $attributeName Nombre del atributo a comprobar si existe.
	 * @return boolean Booleano informando sobre si existe el atributo (estándar o dinámico) o no en este objeto.
	 */
	public function hasAttribute($attributeName){
		return ( static::metaHasAttribute($attributeName) or $this->hasDynamicAttribute($attributeName) );
	}
	
	
	/**
	 * Asciende un atributo convirtiéndolo en un objeto.
	 * @param string $attributeName campo que se asciende.
	 * @param array $extraParemeters Parámetros extra de la conversión a objeto.
	 * @return object Objecto que representa al atributo.
	 * */
	public function a($attributeName, $extraParemeters=null){
		// Modelo actual
		$model = static::CLASS_NAME;
		
		// Si no existe el atributo como atributo estándar, será un atributo
		// dinámico. Si no existe tampoco como atributo dinámico, entonces,
		// lanzamos una excepción
		if(!isset($model::$ATTRIBUTES[$attributeName])){
			if($this->hasDynamicAttribute($attributeName)){
				return $this->$attributeName;
			}
			// No existe el atributo dinámico, devolvemos una excepción
			$strPk = $this->getStrPk();
			throw new \UnexpectedValueException("El atributo {$attributeName} no existe en el objeto {$strPk} del modelo {$model}");
		}
		
		// Propiedades del atributo
		$attributeProperties = $model::$ATTRIBUTES[$attributeName];
		
		// Si el atributo no tiene subtipo, simplemente devolvemos
		// su valor simple
		if(!isset($attributeProperties["subtype"])){
			return $this->$attributeName;
		}
		
		// Para cada uno de los subtipos aceptados, ascendemos el atributo a la
		// clase adecuada según el subtipo
		$subtype = $attributeProperties["subtype"];
		if($subtype == "date" || $subtype == "datetime" || $subtype == "time"){
			return new \DateTime($this->$attributeName);
		}
		return $this->$attributeName;
	}
	
	/**
	 * Asciende un atributo convirtiéndolo en un objeto. Sobrecarga del método "a".
	 * @param string $attributeName campo que se asciende.
	 * @param array $extraParemeters Parámetros extra de la conversión a objeto.
	 * @return object Objecto que representa al atributo.
	 * */	
	public function getObjAttr($attributeName, $extraParemeters=null){
		return $this->a($attributeName, $extraParemeters);
	}
	
}
/************************************************************************************************/

?>
