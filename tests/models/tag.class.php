<?php

namespace lulo\tests\models;

/**
 * Tag example class that shows how to use LuloModel models.
 * @author Diego J. Romero López at intelligenia.
 * */
class Tag extends \lulo\models\LuloModel{
	
	/** Table name */
	const TABLE_NAME = "tag";
		
	/** Class name */
	const CLASS_NAME = "lulo\\tests\models\Tag";
	
	/**
	 * Class metainformation.
	 * */
	public static $META = [
		"model_description" => "User tag for lulo examples",
		"verbose_name" => "user tag",
		"verbose_name_plural" => "user tags",
		"gender" => "f",
		"order" => ["name"=>"ASC"]
	];
	
	/**
	 * Attributes.
	 * 	 */
	protected static $ATTRIBUTES = [
		// Primary key
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Tag web site", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Tag unique identifier", "auto"=>true],
		// Fields
		"name" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Name"],
		"description" => ["type"=>"string", "subtype"=>"doku", "verbose_name"=>"Tag description"],
		"parent_id" => ["type"=>"int", "verbose_name"=>"Parent tag", "null"=>true, "default"=>null, "relationship"=>"parent_tag"],
		// Datetime fields
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Last time this object was updated", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Creation datetime", "auto"=>true],
	];
	
	
	/** Primary key */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Related models
	 * */
	protected static $RELATED_MODELS = ["lulo\\tests\models\User"];
	
	
	/** Relationships with other models */
	/**
	 * Relationships of Tag
	 * */
	protected static $RELATIONSHIPS = [
		// Relationship with user
		"users" => [
			"type" => "ManyToMany",
			"model" => "lulo\\tests\models\User",
			// Human-readable name of the relationship
			"verbose_name" => "Users with this tags",
			// Inverse relationship name
			"related_name" => "tags",
			// Inverse relationship human readable-name
			"related_verbose_name" => "Etiquetas de un usuario",
			// Nexii tables
			// (if there are more than one nexus, this relationships will be read only)
			"junctions" => ["user_tag"],
			// Conditions
			"conditions" => [
				// Relationship between this table and the nexus
				["stack"=>"stack", "id"=>"usertag_id"],
				// Relationships between the nexus and the User table
				["stack"=>"stack", "user_id"=>"id"],
			],
			// This relationship is not readonly
			"readonly" => false,
			// The relationship will be deleted on User or Tag deletion
			"on_master_deletion" => "delete",
			// The relationship is unique, it has no sense to have one user
			// double tagged with the same tag
			"unique" => true
		],
		////////////////////////////////////////////////////////////////
		// Relationship wiht itself
		"parent_tag" => [
			"type" => "ForeignKey",
			"model" => "lulo\\tests\models\Tag",
			// Human-readable name of the relationship
			"verbose_name" => "Etiqueta padre",
			// Inverse relationship name
			"related_name" => "children_tags",
			// Inverse relationship human readable-name
			"related_verbose_name" => "Etiquetas hijas de la actual",
			// Link between a tag and its parent
			"condition" => ["parent_id"=>"id"],
			// This relationship can be nullable, that is, a tag without a parent
			"nullable" => true,
			// This relationship can be edited
			"readonly" => false,
			// When the parent is deleted, make all children tag orphans
			"on_master_deletion" => ["set" => ["parent_id"=>null]]  // "delete"
		]
	];

	
	/**
	 * Representation of this object as string.
	 * */
	public function str(){
		return $this->name;
	}
	
	
	/**
	 * Valores de los campos que son de tipo select y multiselect del
	 * formulario de creación y edición para este modelo.
	 * Si es null, no tienen ningún valor asociado.
	 * @param string $formFieldName Nombre del campo predeterminado.
	 * @param object $object Objeto que indica si se ha de obtener el valor del campo para el formulario de edición.
	 * @return mixed Array de valores con  . Si es null, se asume que no hay valor predeterminado para ese campo.
	 * */
	public static function formValues($formFieldName, $object=null){
		// Lo primero es ver si hay una implementación en el padre
		// en cuyo caso, eso me vale
		$values = parent::formValues($formFieldName, $object);
		if(!is_null($values)){
			return $values;
		}
		
		////////////////////////
		// Para formulario de creación
		if(is_null($object)){
			return null;
		}
		////////////////////////
		// Para formulario de edición
		/// Padre de este Tag
		if($formFieldName == "parent_tag"){
			return Tag::dbLoadAll(["id"=>["<>"=>$object->id]])->functionHash("getStrPk", "str");
		}
		return null;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/************************** VALIDACIÓN ****************************/
	
	/**
	 * Método que valida la descripción.
	 * @param array $data Array con los datos del formulario
	 * */
	public static function validateDescription($data){
		$description_length = mb_strlen($data["description"]);
		return ($description_length <= 50);
	}
	
	
	/**
	 * Validación del formulario.
	 * Este método tiene las llamadas a los métodos de validación de cada uno.
	 * Se le pasa el objeto $object que se desea editar ANTES de habérsele
	 * asignado los valores que vienen del formulario.
	 * @param object $object Indica si estamos antes una edición o una creación.
	 * @return array Array con las validaciones de formulario.
	 * */
	public static function formValidation($object=null){
		// Validación de ejemplo: la descripción ha de tener
		// menos de 50 caracteres
		$validateDescription = [
			"function" => __CLASS__."::validateDescription",
			"error_message" => "La descripción ha de ser menor de 50 caracteres",
			"error_fields" => ["description"]
		];
		
		// Devolvemos todos los validadores
		return [$validateDescription, ];
		
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de CREAR
	 * un objeto de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * */
	public static function cleanCreation($data){
		$data = parent::cleanCreation($data);
		$data["stack"] = TEST_STACK;
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$data["creation_datetime"] = $now;
		$data["last_update_datetime"] = $now;
		return $data;
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de EDITAR
	 * un objeto existente de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * */
	public static function cleanEdition($data){
		$data = parent::cleanEdition($data);
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$data["last_update_datetime"] = $now;
		return $data;
	}
	
	/**
	 * Condición implícita de carga/edición/eliminación.
	 * Todos los objetos con los que trabaje este modelo deberán cumplirla.
	 * @return array Array con la condición implícita.
	 * */
	public static function implicitBaseCondition(){
		return [
			"stack" => TEST_STACK,
		];
	}
	
	/*************** FIN DE MÉTODOS QUE SE SOBRESCRIBREN **************/
	/******************************************************************/
	/******************************************************************/
	
	
	/******************************************************************/
	/******************************************************************/
	/*
	-- SQL de esta tabla:
	-- START SQL
	
	-- Tabla para usertag
	  CREATE TABLE `tag` (
	    `stack` varchar(256) CHARACTER SET ascii NOT NULL,
	    `id` int(11) NOT NULL AUTO_INCREMENT,
	    `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
	    `description` longtext COLLATE utf8_unicode_ci NOT NULL,
	    `parent_id` int(11) DEFAULT NULL,
	    `creation_datetime` datetime NOT NULL,
	    `last_update_datetime` datetime NOT NULL,
	    PRIMARY KEY (`id`) 
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Lulo example table for tags';
	   
	-- Relación entre user y usertag
	  CREATE TABLE `user_tag` (
	    `stack` varchar(256) CHARACTER SET ascii NOT NULL,
	    `user_id` int(11) NOT NULL,
	    `usertag_id` int(11) NOT NULL,
	    PRIMARY KEY (`stack`,`user_id`,`usertag_id`) 
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Lulo example table for user-tag relationship';
	   
	-- END SQL
	*/

}

/*
 * Mandatory initialization.
 * */
Tag::init();

?>
