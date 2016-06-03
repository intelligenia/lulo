<?php

namespace lulo\tests\models;

use \lulo\tests\utils\MimetypeFinder as MimetypeFinder;

/**
 * Each one of the photographs of an user in a social-network-like website.
 * @author Diego J. Romero López at intelligenia.
 * */
class Photo extends \lulo\models\LuloModel{
	/******************************************************************/
	/******************************************************************/
	/***************** ATRIBUTOS QUE SE SOBRESCRIBREN *****************/
	
	/** Tabla en la que se basa esta clase */
	const TABLE_NAME = "photo";
	
	/** Nombre de la clase */
	const CLASS_NAME = "lulo\\tests\models\Photo";
	
	/**
	 * Metainformación sobre la clase, se usa para mostrar en los listados
	 * y formularios de creación y edición de objetos.
	 * */
	public static $META = [
		// Descripción legible de qué representa el modelo
		"model_description" => "Fotos de usuarios de ejemplo. Estos modelos son de ejemplo y sólo sirven como ejemplo a los desarrolladores",
		// Nombre legible por humanos (en singular)
		"verbose_name" => "fotografía de usuario de ejemplo de LULO",
		// Nombre legible por humanos (en plural)
		"verbose_name_plural" => "fotografías de usuarios de ejemplo de LULO",
		// Género de los nombres legibles ('m' para masculino, 'f' para femenino)
		"gender" => "f",
		// Orden en los listados (en estilo ["campo1"=>"ASC|DESC", "campo2"=>"ASC|DESC", ...])
		"order" => ["order_in_gallery"=>"ASC"]
	];
	
	/**
	 * Atributos de este modelo.
	*/
	protected static $ATTRIBUTES = [
		////////////////////////// Clave primaria
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Stack del que depende el usuario", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Identificador único del usuario", "auto"=>true],
		///////////////////////// Campos propiamente dichos
		// Atributo que nos sirve como clave externa
		"user_id" => [
			"type"=>"int", "subtype"=>"ForeignKey", "name"=>"user", "on"=>"lulo\\tests\models\User.id", "related_name"=>"photos",
			// A partir de aquí abajo todo son parámetros opcionales
			"verbose_name"=>"Propietario de la foto",
			"related_verbose_name" => "Fotografías del usuario",
			"nullable" => false,  "readonly"=>false,
			"on_master_deletion" => "delete"
		],
		// Atributos con datos
		"order_in_gallery" => ["type"=>"int", "verbose_name"=>"Orden de la fotografía"],
		"photo" => ["type"=>"blob", "verbose_name"=>"Fotografía"],
		"photo_mimetype" => ["type"=>"string", "verbose_name"=>"Mimetype de la fotografía", "default" =>"application/octet-stream"],
		"photo_filename" => ["type"=>"string", "max_length"=>64, "verbose_name"=>"Nombre del fichero", "default"=>null, "null"=>true, "auto"=>true],
		// Campos de fecha
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de última actualización", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de creación", "auto"=>true],
	];
	
	
	/** Listado de atributos que forman la clave primaria */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Clases con las que tiene alguna relación
	 * */
	protected static $RELATED_MODELS = ["lulo\\tests\models\User"];
	
	
	/** Relaciones con otros modelos */
	
	/**
	 * Relaciones del modelo Tag
	 * */
	protected static $RELATIONSHIPS = [];

	/************* FIN DE ATRIBUTOS QUE SE SOBRESCRIBREN **************/
	/******************************************************************/
	/******************************************************************/
	
	/******************************************************************/
	/******************************************************************/
	/******************* MÉTODOS QUE SE SOBRESCRIBREN *****************/
	
	/**
	 * Representación de este objeto como cadena.
	 * Útil para listados y administración.
	 * @return Representación de este objeto como cadena
	 * */
	public function str(){
		return $this->photo_filename;
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
		/// Posibles valores para el campo de propietario de este Tag
		if($formFieldName == "user"){
			return \lulo\tests\models\User::dbLoadAllAsCollection();
		}
		return null;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/************************** VALIDACIÓN ****************************/
	
	/**
	 * Método que valida el orden de la fotografía.
	 * @param array $data Array con los datos del formulario
	 * */
	public static function validateOrder($data){
		$order = $data["order_in_gallery"];
		if(!is_numeric($order) or $order<=0){
			return false;
		}
		// Sólo puede haber un orden en las fotos de cada usuario
		return !Photo::dbExists(["stack"=>TEST_STACK, "user_id"=>$data["user"], "order_in_gallery"=>$order]);
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
			"function" => __CLASS__."::validateOrder",
			"error_message" => "El orden no es un entero o ya existe",
			"error_fields" => ["order_in_gallery"]
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
		$data["photo_filename"] = $data["photo"]["name"];
		$data["photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($data["photo"]["name"]);
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
		// Si se modifica la foto
		if(array_key_exists("photo", $data) and !is_null($data["photo"]["dbblobreader"])){
			$data["photo_filename"] = $data["photo"]["name"];
			$data["photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($data["photo"]["name"]);
		}
		// Actualización de la fecha de última actualización
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
	
	-- Tabla para userphoto
	  CREATE TABLE `photo` (
	    `stack` varchar(128) CHARACTER SET ascii NOT NULL,
	    `id` int(11) NOT NULL AUTO_INCREMENT,
	    `user_id` int(11) NOT NULL,
	    `order_in_gallery` int(11) NOT NULL,
	    `photo` longblob NOT NULL,
	    `photo_mimetype` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
	    `photo_filename` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
	    `creation_datetime` datetime NOT NULL,
	    `last_update_datetime` datetime NOT NULL,
	    PRIMARY KEY (`id`)
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Imágenes de los usuarios'

	   
	-- END SQL
	*/
}

/*
 * Inicialización obligatoria, se ha de llamar a este método estático
 * una vez declarada la clase para poder iniciar (entre otras cosas, las
 * relaciones inversas.
 * */
Photo::init();

?>
