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
	 * Inicializa los atributos estáticos. Actualmente, son los siguientes:
	 * - $attribute_names: nombre de los atributos del modelo.
	 * - $attribute_names_str: cadena con los nombres atributos del modelo separados por comas. 
	 * @return boolean true si los atributos han sido iniciados, false en otro caso.
	 * */
	private static function initAttributesMetaInformation(){
		$class = get_called_class();
		
		if(!isset(static::$CHILDREN_CLASS_ATTRIBUTES[$class])){
			static::$CHILDREN_CLASS_ATTRIBUTES[$class] = [];
		}
		
		// Establece los atributos que no son blobs en un array
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
	* Devuelve el nombre de la tabla (el objetivo es la compatibilidad con PHP 5.2).
	* @return string Nombre de la tabla que contiene la información en BD.
 	*/
	public static function getTableName(){ return static::TABLE_NAME; }
	
	
	/**
	* Devuelve el nombre de la clase a la que pertenece (el objetivo es la compatibilidad con PHP 5.2).
	* @return string Nombre de la clase de la que es el objeto.
 	*/
	public static function getClassName(){ return static::CLASS_NAME; }
	
	
	/**
	* Informa si el objeto pasado como parámetro es de esta clase (DIR_Sede).
	* @param string $object Objeto que queremos ver si es de la clase DIR_Sede.
	* @return boolean true si $object es de clase DIR_Sede, false en otro caso.
 	*/
	public static function isInstanceOf($object){
		// Si no es un objeto, devolvemos false
		if(!is_object($object)){ return false; }
		$className = static::CLASS_NAME;
		return ($object instanceof $className);
	}
	
	
	/**
	* Informa si el objeto pasado como parámetro es de esta clase (DIR_Sede) o de una clase que hereda de ésta.
	* @param string $object Objeto que queremos ver si es de la clase DIR_Sede o de una clase que herede de DIR_Sede.
	* @return boolean true si $object es de clase DIR_Sede o de una clase que herede de DIR_Sede, false en otro caso.
 	*/
	public static function isA($object){
		$className = static::CLASS_NAME;
		return is_a($object, $className);
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////// METODOS MÁGICOS /////////////////////////////////////
	
	/**
	* Método mágico: devuelve lo que devuelva la llamada a un método que no se encuentra en el objeto actual pero sí en un objeto que está guardado en sus atributos dinámicos.
	* @param string $methodName Nombre del método llamado.
	* @param array $args Array con los atributos con los que se llama el método.
	* @return mixed Valor devuelto por la llamada del método en el objeto guardado en el array de atributos dinámicos o null si no existe.
	*/
	public function __call($methodName, $args){
		// Carga de objetos relacionados
		// Si el atributo que no existe es el nombre de una relación,
		// estamos ante la carga de una entidad relacionada
		// Cargamos un LuloQuery con los objetos relacionados
		if(array_key_exists($methodName, static::$RELATIONSHIPS)){
			$relationship = static::$RELATIONSHIPS[$methodName];
			$relatedModel = $relationship["model"];
			$query = new \lulo\query\Query($relatedModel);
			// Necesitamos pasarle un filtro con la relación convertida a formato
			// de filtro LuloQuery
			$remoteFilter = $this->metaGetRelationshipAsLuloQueryFilter($relatedModel, $relationship["related_name"]);
			// Filtro con la condición de relación remota
			$relationshipQuery = $query->filter($remoteFilter);
			// Si hay argumentos a la llamada, los argumentos (args) actúan como
			// filtro adicional, ahorrándonos la inclusión de una llamada a
			// "filter" y por tanto dejando el código mucho más limpio
			if(count($args)>0){
				if(is_array($args) and isset($args[0]) and is_array($args[0]) and isset($args[0][0])){
					$args = $args[0];
				}
				return $relationshipQuery->filter($args);
			}
			return $relationshipQuery;
		}

		// La idea es comprobar si el método existe en algún objeto que está almacenado en el array de atributos dinámicos.
		// Este método es sólo ~5 veces más lento que la llamada directa (La mejora del hardware SÍ es suficiente).
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
		
		// Avisamos de que ha ejecutado un método que no existe ni en él ni en sus atributos dinámicos
		throw new BadMethodCallException("El método {$methodName} no existe en la clase ".static::CLASS_NAME." ni en ninguno de sus atributos dinámicos.");
	}
	
	
	/**
	* Método mágico: comprueba si está asignado el atributo en tiempo de ejecución.
	* @param string $name Nombre del atributo.
	* @return boolean true si está asignado como atributo dinámico, false en otro caso (comportamiento de isset).
	*/
	public function __isset($name){
		// Si está entre los atributos normales, se establece ese valor
		if(static::metaHasAttribute($name)){
			return array_key_exists($name, $this->attributeValues);
		}
		if(array_key_exists($name, $this->dynamicAttributes)){
			return array_key_exists($name, $this->dynamicAttributes);
		}
		return false;
	}
	
	
	/**
	* Método mágico: devuelve un atributo asignado en tiempo de ejecución.
	* @param string $name Nombre del atributo.
	* @return mixed Valor del atributo.
	*/
	public function __get($name){
		// Si está entre los atributos normales, se devuelve
		if(array_key_exists($name, $this->attributeValues)){
			return $this->attributeValues[$name];
		}
		// Si está entre los atributos dinámicos, se devuelve
		if(array_key_exists($name, $this->dynamicAttributes)){
			return $this->dynamicAttributes[$name];
		}
		return null;
	}
	
	
	/**
	 * Comprueba si puede editar un atributo determinado.
	 * @param string $attribute Nombre del atributo que se desea comprobar si se tiene acceso.
	 * @return boolean True si se tiene acceso desde fuera, false en otro caso.
	 * */
	protected static function canEditAttribute($attribute){
		return (
			!isset(static::$ATTRIBUTES[$attribute]["access"]) or (
				// Comprueba si el acceso es de escritura también
				(static::$ATTRIBUTES[$attribute]["access"]=="rw" or static::$ATTRIBUTES[$attribute]["access"]=="writable")
			)
		);
	}
	
	
	/**
	 * Informa si la llamada al método se ha producido desde una de las
	 * clases de Lulo o desde la misma clase principal.
	 * @return boolean true si la llamada ha sido desde la clase principal o una clase Lulo, false en otro caso.
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
	 * Comprueba si se puede editar un atributo llamado mediante el
	 * método mágico __set o __unset.
	 * en caso contrario, lanza una excepción
	 * @param string $attribute Nombre del atributo que se desea comprobar si se tiene acceso.
	 * */
	protected static function assertAttributeEdition($attribute){
		// Comprobamos si puede editar el atributo en función del
		// acceso definido por el desarrollador
		if(!static::canEditAttribute($attribute) and !static::attributeEditionIsCalledFromClassScope()){
			throw new Exception("No se tiene acceso de escritura para el atributo {$attribute} en ".static::CLASS_NAME.".");
		}
	}
	
	
	/**
	* Método mágico: asigna un atributo dinámico en tiempo de ejecución.
	* @param string $attribute Nombre del atributo.
	* @param string $value Valor del atributo.
	*/
	public function __set($attribute, $value){
		// Si está entre los atributos normales, se establece ese valor
		if(array_key_exists($attribute, static::$ATTRIBUTES)){
			// Aseguramos que puede editarse el atributo
			static::assertAttributeEdition($attribute);
			// Edición del atributo
			$this->attributeValues[$attribute] = $value;
		// Si no existe en los atributos, lo mete como atributo dinámico
		}else{
			$this->dynamicAttributes[$attribute] = $value;
		}
	}
	
	
	/**
	* Método mágico: ejecuta unset sobre un atributo asignado en tiempo de ejecución.
	* @param $attribute Nombre del atributo.
	*/
	public function __unset($attribute){
		// Si está entre los atributos normales, se establece ese valor
		if(array_key_exists($attribute, $this->attributeValues)){
			// Aseguramos que puede editarse el atributo
			static::assertAttributeEdition($attribute);
			// Edición (unset) del atributo
			unset($this->attributeValues[$attribute]);
		}else{
			unset($this->dynamicAttributes[$attribute]);
		}
	}
	
	
	/**
	* Devuelve el atributo cuyo nombre se le pasa como parámetro al método.
	* @param string $name Nombre del atributo.
	* @return mixed|null Atributo $name del objeto.
	*/
	public function getAttribute($name){
		return $this->__get($name);
	}
	
	
	/**
	* Devuelve los atributos cuyos nombres se les pasan como parámetro al método.
	* @param array $names Array con los nombres de los atributos a devolver.
	* @return array Atributos $names del objeto.
	*/
	public function getAttributes($names){
		if(is_string($names)){
			$names = explode('|', $names);
		}
		// Para cada atributo que hemos pasado como parámetro,
		// le asignamos el valor que tenga en el objeto y lo devolvemos
		$selectedAttributes = array();
		foreach($names as $name){
			$selectedAttributes[$name] = $this->$name;
		}
		return $selectedAttributes;
	}
	
	
	/**
	 * Devuelve un array con los atributos que no son blobs.
	 * @return array Atributos que no son de tipo blob.
	 * */
	public function getNonBlobAttributes(){
		$attributeMetadata = static::initAttributesMetaInformation();
		$nonBlobAttributes = $attributeMetadata["attribute_names"];
		return $this->getAttributes($nonBlobAttributes);
	}
	
	
	/**
	 * Devuelve la clave primaria de este objeto como un hash.
	 * @return array Array de par atributo de clave primaria y valor.
	 * */
	public function getPk(){
		return $this->getAttributes(static::$PK_ATTRIBUTES);
	}
	
	/// Atributos heredados de los padres
	/**
	* Invocación de disparadores de la instancia.
	* @param $name Nombre del disparador a llamar.
	* @param $data Datos extra que se le pasan al disparador.
	* @return mixed true si el disparador se ha ejecutado con éxito, false en otro caso. Null si no se ejecutó el disparador.
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
			throw new UnexpectedValueException("El atributo {$attributeName} no existe en el objeto {$strPk} del modelo {$model}");
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
			return new DateTime($this->$attributeName);
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
