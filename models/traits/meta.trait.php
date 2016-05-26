<?php

namespace lulo\models\traits;


trait Meta{
	
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
}

?>
