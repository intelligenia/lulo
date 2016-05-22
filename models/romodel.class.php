<?php

namespace lulo\models;

use lulo\containers\Collection as Collection;

/**
 * Clase que abstrae una tabla proporcionando
 * acceso de insertado, edición, y borrado a dicha tabla
 * @author Diego J. Romero López en intelligenia.
 */
abstract class ROModel{
	
	/* OVERWRITE */
	/** Tabla en la que se basa esta clase */
	const TABLE_NAME = "<TABLE_NAME>";
	
    /** Name of autoincrementable id attribute */
    const ID_ATTRIBUTE_NAME = "id";
	
	/** Conexión usada */
	const DB = "DB";
	
	
	/** Nombre de la clase */
	const CLASS_NAME = "<CLASS_NAME>";
	
	/** Metainformación sobre la clase **/
	protected static $META = [
		"model_description" =>"<Descripción del modelo>",
		"verbose_name" => "<ROModel>",
		"verbose_name_plural" => "<ROModels>",
		"gender" => "<neutral>",
	];
	
	/** Tipos de los atributos de este modelo */
	// <nombre_atributo>=>propiedades('type'=><tipo_semántico>,'default'=><valor por defecto>,'values'=>'valores asignados si es un select'
	protected static $ATTRIBUTES = array();
	
	
	/** Listado de atributos que forman la clave primaria */
	protected static $PK_ATTRIBUTES = array();
	
	/**
	 * Clases con las que tiene alguna relación.
	 * Este atributo es muy importante y debe completarse con los nombres
	 * de las clases con las que tenga relaciones. Si no, las relaciones
	 * inversas no funcionarán.
	 * */
	protected static $RELATED_MODELS = [];
	
	/** Relaciones con otros modelos */
	/*
	$data = array(
			'<relationship_name>' => array('type'=>"ForeignKey|ManyToMany|OneToMany", 'model'=>"<model_name>", "condition"=>[<remote_attribute>=><local_attribute>]),
		);
	*/
	protected static $RELATIONSHIPS = array();

	/* ENDOVERWRITE */
	
	/**
	 * Información estadística y de control de las clases que
	 * heredan de esta clase abstracta.
	 * */
	protected static $CHILDREN_CLASS_ATTRIBUTES = [
		//"<clase>" => [
		//	"attribute_names" => [/*Array con los nombres de los atributos*/]
		//	"atribute_names_str" => "/*Cadena con la selección de atributos en el SELECT de SQL*/"
		//]
	];
	
	/* Propiedades de objeto */
	
	/** Almacén de atributos obtenidos de la tupla de la tabla */
	protected $attributeValues = array();
	
	
	/** Array para establecer propiedades dinámicas */
	protected $dynamicAttributes = array();
	
	
	/** Caché estática de triggers existentes en esta clase */
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
	 * Obtiene el SQL de obtención de las columnas del SELECT SQL.
	 * @return string Cadena con los atributos de este modelo separados por comas.
	 * */
	private static function getSelectColumnExpressionSQL(){
		$class_attributes = static::initAttributesMetaInformation();
		$str = $class_attributes["attribute_names_str"];
		return $str;
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
	///////////////////////////////////////////////// META //////////////////////////////////////////
	/**
	* Devuelve los nombres de los atributos que forman la clave primaria del objeto junto con su tipo.
	* @return array Array con los atributos que forman la clave primaria del objeto del objeto como pares <nombre_atributo>=><tipo>.
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
	* Devuelve los nombres de los atributos que forman la clave primaria de los objetos de tipo DIR_Sede.
	* @return array Array con los atributos que forman la clave primaria de los objetos de tipo DIR_Sede.
	*/
	public static function metaGetPkAttributeNames(){
		$data = static::$PK_ATTRIBUTES;
		return $data;
	}
	
	
	/**
	* Devuelve los nombres de los atributos del objeto junto con su tipo semántico como array.
	* @return array Array con los atributos del objeto del objeto como pares <nombre_atributo>=>propiedades('type'=><tipo_semántico>,'default'=><valor por defecto>,'values'=>'valores asignados si es un select'.
	*/
	public static function metaGetAttributes(){
		$data = static::$ATTRIBUTES;
		return $data;
	}
	
	
	/**
	* Devuelve los metatributos del atributo cuyo nombre se le pasa como parámetro a la función
	* @param string attrName Nombre del atributo que se quiere consultar
	* @return array Array con los metaatributos del atributo pasado como parámetro.
	*/
	public static function metaGetAttribute($attrName){
		if(!isset(static::$ATTRIBUTES[$attrName])){
			$class = get_called_class();
			throw new UnexpectedValueException("El atributo {$attrName} no existe en el modelo {$class}");
		}
		
		$data = static::$ATTRIBUTES[$attrName];
		return $data;
	}
	
	
	/**
	* Devuelve los atributos del objeto que son de tipo blob.
	* @return array Array con los atributos del objeto del objeto que son de tipo blob con el formato <nombre_atributo>=><propiedades>.
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
	* Devuelve los nombres de los atributos del objeto junto con su tipo como array.
	* @return array Array con los atributos del objeto del objeto como pares <nombre_atributo>=><tipo>.
	*/
	public static function metaGetAttributeTypes(){
		$data = array();
		foreach(static::$ATTRIBUTES as $attribute=>$properties){
			$data[$attribute] = $properties["type"];
		}
		return $data;
	}
	
	
	/**
	 * Devuelve los nombres de los atributos del objeto cuyo tipo no es blob.
	 * @return array Array con los nombres de los atributos del objeto del objeto que no son blob.
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
	* Devuelve los nombres de los atributos del objeto.
	* @return array Array con los atributos del objeto del objeto <nombre_atributo>.
	*/
	public static function metaGetAttributeNames(){
		$class_attributes = static::initAttributesMetaInformation();
		return $class_attributes["attribute_names"];
	}
	
	
	/**
	* Informa si la clase tiene un atributo (original, no un atributo dinámico) con ese nombre.
	* @return boolean true si la clase tiene un atributo con ese nombre, false en otro caso.
	*/
	public static function metaHasAttribute($attributeName){
		return array_key_exists($attributeName, static::$ATTRIBUTES);
	}
	
	
	/**
	* Devuelve un valor de un enumerado para una clave determinada.
	* @param string $attributeName Nombre del atributo cuyo valor se quiere obtener para una clave determinada.
	* @param string $key Clave determinada cuyo valor quiere obtenerse.
	* @return string Valor determinado asociado a la clave $key en el atributo $attributeName.
	**/
	public static function metaGetEnumAttributeValue($attributeName, $key){
		// Primero comprobamos si existe el atributo
		if(!static::metaHasAttribute($attributeName)){
			throw new UnexpectedValueException("El atributo {$attributeName} no existe en este modelo");
		}
		// Después comprobamos si es de tipo enumerado
		$attribute = static::$ATTRIBUTES[$attributeName];
		if(!isset($attribute["subtype"]) or $attribute["subtype"]!="enum"){
			throw new UnexpectedValueException("El atributo {$attributeName} no es de tipo enumerado");
		}
		// También comprobamos si el atributo tiene valores definidos
		if(!isset($attribute["values"]) or !is_array($attribute["values"])){
			throw new UnexpectedValueException("El atributo {$attributeName} no tiene valores");
		}
		// Por último, comprobamos que existe la clave que nos interesa
		if(!isset($attribute["values"][$key])){
			throw new UnexpectedValueException("La clave {$key} no existe en el atributo enumerado {$attributeName}");
		}
		// Ya sabemos que existe y por tanto, devolvemos el valor asociado
		// a la clave $key
		return $attribute["values"][$key];
	}
	
	
	/**
	* Informa de las relaciones (y de su cardinalidad) que tiene este modelo de objeto con el resto del espacio de objetos.
	* Notemos que para puede haber varias relaciones con el mismo modelo, así que por eso se usa el curstom_name como clave.
	* @return array Array con pares <nombre personalizado del modelo> => array('type'=>'one-to-one'|'one-to-many'|'many-to-one'|'many-to-many', 'model'=><nombre del modelo> , 'method'=><Nombre del método en este modelo que carga los objetos de ese tipo relacionados>), 'source_is_master'=><true|false en función de si el origen actúa como máster>.
	*/
	public static function metaGetRelationships(){
		return static::$RELATIONSHIPS;
	}
	
	
	/**
	* Obtiene una relación.
	* @return array Array con la información de la relación.
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
	 * Informa si el modelo tiene una relación con el nombre $relationshipName.
	 * 
	 * @param string $relationshipName Nombre de la relación.
	 * @return boolean True si el modelo actual tiene una relación con el nombre $relationshipName.
	 **/
	public static function metaHasRelationship($relationshipName){
		return isset(static::$RELATIONSHIPS[$relationshipName]);
	}
	
	
	/**
	 * Devuelve una relación como un array convertido a formato que toma el método filter de LuloQuery.
	 * 
	 * @param string $remoteModel Nombre del modelo remoto.
	 * @param string $remoteRelationshipName Nombre de la relación remota.
	 * @return array Array con la estructura para ser usada en un filter de LuloQuery.
	 **/
	protected function metaGetRelationshipAsLuloQueryFilter($remoteModel, $remoteRelationshipName){
		
		$relationship = $remoteModel::$RELATIONSHIPS[$remoteRelationshipName];
		$relationshipType = $relationship["type"];
		
		// Caso de que la relación sea muchos a muchos
		if($relationshipType == "ManyToMany"){
			$relationshipConditions = $relationship["conditions"];
			$filter = [];
			$lastConditionIndex = count($relationshipConditions)-1;
			foreach($relationshipConditions[$lastConditionIndex] as $localAttribute=>$remoteAttribute){
				$filter["{$remoteRelationshipName}::{$remoteAttribute}"] = $this->getAttr($remoteAttribute);
			}
			return $filter;
		}
		
		// Para el caso de que sea ForeighKey o OneToMany, la relación es entre
		// las dos tablas y no tiene tabla intermedia.
		$relationshipCondition = $relationship["condition"];
		// Creamos el filtro a partir de lo especificado en la relación
		// del modelo remoto
		$filter = [];
		foreach($relationshipCondition as $localAttribute=>$remoteAttribute){
			// Si la relación es con la misma tabla, no le ponemos el prefijo
			// con el nombre de la relación
			if($relationship["model"] == static::CLASS_NAME){
				$filter["$localAttribute"] = $this->getAttr($remoteAttribute);
			}else{
				$filter["{$remoteRelationshipName}::{$remoteAttribute}"] = $this->getAttr($remoteAttribute);
			}
		}
		return $filter;
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
		//throw new DomainException("El atributo {$name} no existe en este modelo");
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
	
	
	/******************************************************************/
	/******************************************************************/
	/********************** CARGA DE OBJETOS **************************/
	
	/**
	 * Creación de un LuloQuery para cargar los objetos de este Modelo.
	 * AVISO: funcionalidad experimental.
	 * @return object LuloQuery con los objetos de este modelo.
	 * */
	public static function objects(){
		// Creación del LuloQuery para este modelo
		$query = new \lulo\query\Query(static::CLASS_NAME);
		// Condición implícita de selección, si es necesario,
		// se aplica al LuloQuery.
		$implicitBaseCondition = static::implicitBaseCondition();
		if(is_array($implicitBaseCondition) and count($implicitBaseCondition)>0){
			$query = $query->filter($implicitBaseCondition);
		}
		// Devuelve el LuloQuery para que los desarrolladores puedan 
		// encadenar condiciones
		return $query;
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
	* Carga el $blobName, que está en BD, asociado al objeto actual en una cadena (string).
	* @return string String que contiene el blob $blobName del objeto actual.
	*/ 
	public function dbBlobLoad($blobName){
		// DB connection
		$db = static::DB;
		
		// Condición del objeto actual
		$condition = $this->getPk();
		
		// Obtiene el blob como string 
		$blob = $db::getOne(static::TABLE_NAME, $blobName, $condition);
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
		$blobIsNull = $db::fieldIsNull(static::TABLE_NAME, $blobName, $condition);
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
		$rows = $db::getAll(static::TABLE_NAME, $columnsStr, $finalCondition, $order, $limit);
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
		$row = $db::getRow(static::TABLE_NAME, $columnsStr, $finalCondition, $order);
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
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/* RELACIONES */
	
	/**
	 * Carga información de tablas asociadas pero sin basarse en modelos.
	 * @param $relationName Nombre de la relación.
	 * @param $remoteCondition Nombre de la condición de los objetos remotos.
	 * @param $order Orden de los objetos remotos.
	 * @param $limit Límite de los objetos remotos.
	 * @return array Array simple si la relación es *ToOne, o array de arrays si es OneToMany.
	 * */
	protected static function _dbLoadRelatedNoModel($relationName, $remoteCondition=[], $order=null, $limit=null){
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
			$rows = $db::getAll($relatedTable, $columnsStr, $remoteCondition, $order, $limit);
			return $rows;
		}
		
		// No se aceptan relaciones de muchos a muchos
		throw InvalidArgumentException("Sólo se aceptan los tipos 'OneToMany' y 'ManyToOne' en las relaciones a tabla");
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
		$tables = array_merge([], $junctionTables, [$foreignClass::TABLE_NAME]);
		$numTables = count($tables);
		
		// Para las tablas intermedias, sólo para las tablas nexo se incluyen
		// los campos y sólo si así lo indica la relación
		$fieldsByTable = [];
		foreach(array_merge([static::TABLE_NAME],$tables) as $tableName){
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
		$fieldsByTable[$foreignClass::TABLE_NAME] = $foreignClass::metaGetSelectableAttributes();
		
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
				throw Exception("Se esperaba ['remoteObjectConditions'=>[], 'nexiiConditions'=>[ [Condiciones Nexo1], [Condiciones Nexo2], ..., [Condiciones NexoN] ]]");
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
			$params['order'][$foreignClass::TABLE_NAME] = $order;
		}
		
		// Límite de la consulta
		$params['limit'] = $limit;
		
		//////////////////
		// Consulta SQL a la base de datos
		$db = static::DB;
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
			if($container=="collection"){
				$remoteObjects = $foreignClass::dbLoadAll($remoteCondition, $order, $limit, $container);
				return $remoteObjects;
			}
			throw UnexpectedValueException("El tipo de contenedor {$container} no está implementado");
		}
		
		// Por si hemos introducido una relación que no está implementada
		throw UnexpectedValueException("El tipo de relación {$relationshipType} no está implementado");
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
		throw new UnexpectedValueException("La relación {$relationName} no existe");
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/* RELACIONES DIRECTAS */
	
	/**
	 * Añade una relación directa a partir de un nombre de atributo y sus propiedades.
	 * @param string $attributeName Nombre del atributo que es de subtipo ForeignKey.
	 * @param array $attributeProperties Propiedades del atributo.
	 * */
	protected static function addForeignRelationshipFromAttribute($attributeName, $attributeProperties){
		// Comprobamos que existe el nombre de la relación
		if(!isset($attributeProperties["name"])){
			throw new OutOfBoundsException("El atributo {$attributeName} es una relación ForeignKey pero no contiene la clave 'name' con el nombre único de la relación");
		}
		$relationshipName = $attributeProperties["name"];
		
		// Comprobamos que el atributo on existe
		if(!isset($attributeProperties["on"])){
			throw new OutOfBoundsException("El atributo {$attributeName} es una relación ForeignKey pero no contiene la clave 'on' con la forma <Modelo>.<atributo_remoto>");
		}
		
		$model = static::CLASS_NAME;
		$matches = [];
		if(preg_match("#(\w[\w\d]+)\.(\w[\w\d]+)#", $attributeProperties["on"], $matches)==0){
			throw new UnexpectedValueException("La clave 'on' ha de ser de la forma <ModeloRemoto>.<AtributoRemoto> donde <AtributoRemoto> es el atributo por el que se une el modelo remoto <ModeloRemoto> con el atributo {$attributeName} del modelo actual, {$model}");
		}
		$remoteModel = $matches[1];
		$remoteAttribute = $matches[2];
		
		// Creación de la relación directa
		static::$RELATIONSHIPS[$relationshipName] = [
			"type" => "ForeignKey",
			"model" => $remoteModel,
			"table" => $remoteModel::TABLE_NAME,
			"condition" => [$attributeName=>$remoteAttribute],
		];
		
		// Propiedades de relación opcionales
		$optionalProperties = [
			"verbose_name" => true, "related_name" => true,
			"related_verbose_name" => true, "nullable" => true,
			"readonly" => true, "on_master_deletion" => true
		];
		// Para cada propiedad del atributo que sea de la relación,
		// la asignamos al array con la información de la relación.
		foreach($attributeProperties as $attributeProperty=>$attributePropertyValue){
			// Comprobamos si existe la propiedad, y si es el caso, la asigna a la relación
			if(isset($optionalProperties[$attributeProperty])){
                            static::$RELATIONSHIPS[$relationshipName][$attributeProperty] = $attributePropertyValue;
			}
		}
	}
	
	
	/**
	 * Inicializa las relaciones directas que están en atributos del modelo.
	 * */
	protected static function initDirectRelationshipsFromAttributes(){
		// Para cada atributo del modelo, comprobamos si tiene como
		// subtipo ForeignKey (la única relación que se permite ahora
		// mismo)
		foreach(static::$ATTRIBUTES as $attributeName=>$attributeProperties){
			// Para cada atributo que sea una ForeignKey, le añadimos la
			// relación al modelo.
			if(isset($attributeProperties["subtype"]) and $attributeProperties["subtype"]=="ForeignKey"){
				static::addForeignRelationshipFromAttribute($attributeName, $attributeProperties);
			}
		}
	}
	
	
	/**
	 * Inicializa las relaciones directas implícitas en el modelo.
	 * Por ahora, sólo inicializa las relaciones directas que provienen
	 * de atributos del modelo.
	 * */
	protected static function initDirectRelationships(){
		// Introduce nuevos atributos en las relaciones, por ahora, sólo
		// la tabla
		foreach(static::$RELATIONSHIPS as $relationshipName=>&$relationshipProperties){
			if(!isset($relationshipProperties["table"])){
				$model = $relationshipProperties["model"];
				$relationshipProperties["table"] = $model::TABLE_NAME;
			}
		}
		// Inicializa las relaciones directas a partir de atributos
		static::initDirectRelationshipsFromAttributes();
	}
	
	
	/* FIN DE RELACIONES DIRECTAS */
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/* RELACIONES INVERSAS */
	
	/**
	 * Obtiene el nombre único de la relación invertida.
	 * Si no puede obtener un nombre único, da un error.
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return string Nombre de la relación inversa (normalmente el "related_name" de la relación original).
	 * */
	protected static function getInverseRelationshipName($model, $relationName, $relationship){
		$localModel = static::CLASS_NAME;
		// Si existe el nombre de la relación inversa
		if(isset($relationship["related_name"])){
			$inverseRelationName = $relationship["related_name"];
		// Si no, tratamos de extraerlo de forma automática
		}else{
			$inverseRelationName = "{$relationName}_inverse";
		}
		if(isset(static::$RELATIONSHIPS[$inverseRelationName])){
			//throw new InvalidArgumentException("En {$localModel}, al intentar crear la relación {$inverseRelationName} (inversa de {$relationName}) con {$model} se ha detectado otra relación con el mismo nombre ya existente");
			return false;
		}
		return $inverseRelationName;
	}
	
	
	/**
	 * Obtiene el nombre legible de la relación invertida.
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return string Nombre legible de la relación invertida (normalmente el "related_verbose_name" de la relación original).
	 * */
	protected static function getInverseRelationshipVerboseName($model, $relationName, $relationship){
		$inverseVerboseName = "Inversa de la relación {$relationship['verbose_name']} de {$model}";
		if(isset($relationship["related_verbose_name"])){
			$inverseVerboseName = $relationship["related_verbose_name"];
		}
		return $inverseVerboseName;
	}
	
	
	/**
	 * Añade la relación ManyToMany inversa de la ManyToMany $relationName con el modelo $model.
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return boolean True si se ha añadido una nueva relación, false en otro caso.
	 * */
	protected static function addInverseManyToManyRelationship($model, $relationName, $relationship){
		// Tenemos que añadir una nueva relación ManyToMany
		
		// Nombre del modelo local
		$localModel = static::CLASS_NAME;
		
		// Nombre de la relación
		$inverseRelationName = static::getInverseRelationshipName($model, $relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// Nombre legible por humanos de la relación
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Nexos en orden ivertido
		$inverseJunctions = array_reverse($relationship["junctions"]);
		
		// Condición normal en orden inverso
		$reverseConditions = array_reverse($relationship["conditions"]);
		
		// Condición de la relación inversa
		$inverseConditions = [];
		foreach($reverseConditions as $condition){
			$inverseConditions[] = array_flip($condition);
		}
		
		// Creamos la nueva relación en el modelo actual
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "ManyToMany",
			"model" => $model,
			"table" => $model::TABLE_NAME,
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
	 * Añade la relación OneToMany inversa de la ForeignKey $relationName con el modelo $model.
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return boolean True si se ha añadido una nueva relación, false en otro caso.
	 * */
	protected static function addInverseForeignKeyRelationship($model, $relationName, $relationship){
		// Tenemos que añadir una nueva relación OneToMany
		
		// Nombre de la relación
		$inverseRelationName = static::getInverseRelationshipName($model, $relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// Nombre legible por humanos de la relación
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Condición inversa
		$inverseCondition = array_flip($relationship["condition"]);
		
		// ¿Qué hacer en el lado MANY en caso de eliminación?
		$on_delete = false;
		if(isset($relationship["on_master_deletion"])){
			$on_delete = $relationship["on_master_deletion"];
		}
		
		// Creamos la nueva relación en el modelo actual
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "OneToMany",
			"model" => $model,
			"table" => $model::TABLE_NAME,
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
	 * Añade la relación ForeignKey inversa de la OneToMany $relationName con el modelo $model.
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return boolean True si se ha añadido una nueva relación, false en otro caso.
	 * */
	protected static function addInverseOneToManyRelationship($model, $relationName, $relationship){
		// Tenemos que crear una nueva relación ForeignKey
		
		// Nombre de la relación
		$inverseRelationName = static::getInverseRelationshipName($model, $relationName, $relationship);
		if(!is_string($inverseRelationName)){
			return false;
		}
		
		// Nombre legible por humanos de la relación
		$inverseVerboseName = static::getInverseRelationshipVerboseName($model, $relationName, $relationship);
		
		// Condición inversa
		$inverseCondition = array_flip($relationship["condition"]);
		
		// ¿Qué hacer en el lado MANY en caso de eliminación?
		$on_master_deletion = false;
		if(isset($relationship["on_master_deletion"])){
			$on_master_deletion = $relationship["on_master_deletion"];
		}
		
		// Creamos la nueva relación en el modelo actual
		static::$RELATIONSHIPS[$inverseRelationName] = [
			"type" => "ForeignKey",
			"model" => $model,
			"table" => $model::TABLE_NAME,
			"verbose_name" => $inverseVerboseName,
			"condition" => $inverseCondition,
			"nulllable" => ( isset($relationship["nullable"]) and $relationship["nullable"] ),
			"readonly" => ( isset($relationship["readonly"]) and $relationship["readonly"] ),
			"on_master_deletion" => $on_master_deletion,
			"inverse_of" => $relationName,
		];
	}
	
	/**
	 * Añade una relación invertida $relationship (con $modelo).
	 * @param string $model Nombre del modelo que tiene la relación original.
	 * @param string $relationName Nombre de la relación original.
	 * @param array $relationship Array con las propiedades de la relación original.
	 * @return boolean True si se ha añadido una nueva relación, false en otro caso.
	 * */
	protected static function addInverseRelationship($model, $relationName, $relationship){
		// Si la relación es una relación inversa
		if(isset($relationship["inverse_of"])){
			return false;
		}
		// Relaciones originales
		if($relationship["type"] == "ManyToMany"){
			static::addInverseManyToManyRelationship($model, $relationName, $relationship);
		}elseif($relationship["type"] == "ForeignKey" or $relationship["type"] == "ManyToOne"){
			static::addInverseForeignKeyRelationship($model, $relationName, $relationship);
		}elseif($relationship["type"] == "OneToMany"){
			static::addInverseOneToManyRelationship($model, $relationName, $relationship);
		}else{
			// Por si hemos introducido una relación de un tipo no reconocido 
			throw new UnexpectedValueException("La relación {$relationName} tiene un tipo no reconocido");
		}
		return true;
	}
	
	/** Comprueba si ya se han calculado las relaciones inversas para cada uno de los modelos */
	protected static $INVERSE_RELATIONSHIPS_ACTIVATED = [];
	
	
	/**
	 * Inicializa las relaciones inversas.
	 * Una relación inversa es una relación que se inserta automáticamente
	 * en un modelo debido a las relaciones que tienen con otros modelos.
	 * */
	protected static function initInvertedRelationships(){
		// Si las relaciones inversas ya han sido activadas para este clase,
		// no hagas nada
		if(isset(static::$INVERSE_RELATIONSHIPS_ACTIVATED[static::CLASS_NAME])){
			return false;
		}
		// Se asume que todo modelo puede estar relacionado consigo mismo
		$relatedModels = array_merge(static::$RELATED_MODELS, [static::CLASS_NAME]);
		// Para cada clase relacionada, vamos a ver todas sus relaciones
		foreach($relatedModels as $model){
			// Para cada relación de un modelo relacionado
			// (y de él consigo mismo), vamos a añadir una relación
			// inversa de esta clase con ese modelo
			$relationships = $model::$RELATIONSHIPS;
			foreach($relationships as $name=>$properties){
				// Comprobamos que existe el atributo "model"
				// en la relación
				if(!isset($properties["model"])){
					throw new InvalidArgumentException("No se ha definido el modelo para la relación {$name}");
				}
				// Modelo remoto
				$rModel = $properties["model"];
				// Añadimos las relaciones inversas del modelo
				// con el modelo actual
				if($rModel == static::CLASS_NAME){
					static::addInverseRelationship($model, $name, $properties);
				}
			}
		}
		// Marcamos que ya se han creado las relaciones inversas
		static::$INVERSE_RELATIONSHIPS_ACTIVATED[static::CLASS_NAME] = true;
		return true;
	}
	
	
	/***************** FIN DE LAS RELACIONES INVERSAS *****************/
	/******************************************************************/
	/******************************************************************/
	
	/******************************************************************/
	/******************************************************************/
	/************************** INICIALIZACIÓN ************************/
	
	/**
	 * Inicializa los atributos del modelo.
	 * Sólo es obligatorio llamarlo antes de trabajar con los modelos
	 * en uno de los siguientes casos:
	 * - Cuando queremos crear las relaciones inversas.
	 * */
	public static function init(){
		// Inicializa las relaciones directas (de tipo ForeignKey)
		// que están descritas como un atributo
		static::initDirectRelationships();
		// Inicializa las relaciones inversas
		static::initInvertedRelationships();
	}
	
	/********************** FIN INICIALIZACIÓN ************************/
	/******************************************************************/
	/******************************************************************/
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/*************** CUENTA Y EXISTENCIA DE ELEMENTOS *****************/
	
	
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
		$count = $db::count(static::TABLE_NAME, $finalCondition);
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
		$count = $db::count(static::TABLE_NAME, $finalCondition);
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
		return static::arrayFactoryFromRows($db::getDisjunctiveAll(static::TABLE_NAME, $columnsStr, $condition));
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
			return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAllWithExtraConditions(static::TABLE_NAME, $columnsStr, $likeCondition, $condition, $order, $limit));
		}
		return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAll(static::TABLE_NAME, $columnsStr, $likeCondition, $order, $limit));
	}
	
	
	/**
	* Informa de los objetos que contienen en alguno de los campos (pasados como claves de los elementos del array) un valor determinado (valor del elemento del array).
	* @param array $search Campos y cadenas de búsqueda..
	* @return array Array con los objetos que cumplen la condición dada por el array $search.
	*/ 
	public static function dbSearchIn($search){
		$columnsStr = static::getSelectColumnExpressionSQL();
		$dbHelper = static::DB;
		return static::arrayFactoryFromRows($dbHelper::getDisjunctiveAll(static::TABLE_NAME, $columnsStr, $search));
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
	
	
	/**
	 * Obtiene el nombre de fichero de un blob.
	 * @param $blobName Nombre del blob.
	 * @return Nombre del fichero asociado al blob.
	 * */
	public function getBlobFileName($blobName){
		if(array_key_exists("{$blobName}_filename", static::$ATTRIBUTES)){
			return $this->getAttribute("{$blobName}_filename");
		}
		return $this->getAttribute($blobName);
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
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******************* Representaciones del objeto ******************/
	
	/**
	 * Representación como cadena.
	 * @return string Representación del objeto como cadena.
	 * */
	public function str(){
		// Obtenemos el nombre humano de la entidad
		$humanName = ucfirst(static::$META["verbose_name"]);
		// Sacamos su clave primaria
		$strPk = $this->getStrPk();
		// Concatenamos todo eso y lo devolvemos
		return "{$humanName}({$strPk})";
	}
	
	
	/**
	 * Representación como cadena de la clave primaria.
	 * @return string Representación de la clave primaria del objeto como cadena.
	 * */
	public function getStrPk(){
		$strPk = implode("-", $this->getPk());
		return $strPk;
	}
	
	
	/**
	 * Conversión de una cadena de clave primaria a array.
	 * @param string $strPk Conversión de la clave primara como string a array.
	 * @return array Representación de la clave primaria de un objeto como array.
	 * */
	protected static function strToPk($strPk){
		//////////////////////////////////
		// La clave primaria como cadena es cada uno de los valores de 
		// los atributos de la clave primaria, en el mismo orden en el que
		// se definan.
		$pkValues = explode("-", $strPk);
		// Nombres de la clave primaria
		$pkNames = static::metaGetPkAttributeNames();
		// Comprobamos si la representación de la clave primaria como
		// cadena es correcta o no
		if(count($pkNames) != count($pkValues)){
			throw new InvalidArgumentException("El formato de '{$strPk}' como clave primaria no es válido");
		}
		//////////////////////////////////
		/// Obtención de la clave primaria
		$pk = []; // Contendrá la PK como array
		$countPkValues = count($pkValues);
		for($i=0; $i<$countPkValues; $i++){
			// Nombre del atributo de la PK
			$name = $pkNames[$i];
			// Valor del atributo de la PK
			$value = $pkValues[$i];
			// Pareja (<atributo> => <valor>) de la PK
			$pk[$name] = $value;
		}
		return $pk;
	}
	
	
	/**
	 * Representación del objeto como cadena.
	 * @return string Representación del objeto como cadena.
	 * */
	public function __toString(){
		return $this->str();
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
