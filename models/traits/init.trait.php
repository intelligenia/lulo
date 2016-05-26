<?php

namespace lulo\models\traits;

/**
 * Initialize the LuloModel object.
 *  */
trait Init {
	
	/* Direct relationships*/
	
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
		if(preg_match("#(\w[\w\d]+)\.(\w[\w\d]+)#", $attributeProperties["on"], $matches)==0){
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
}
