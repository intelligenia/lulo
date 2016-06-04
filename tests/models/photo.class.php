<?php

namespace lulo\tests\models;

use \lulo\tests\utils\MimetypeFinder as MimetypeFinder;

/**
 * Each one of the photographs of an user in a social-network-like website.
 * @author Diego J. Romero López at intelligenia.
 * */
class Photo extends \lulo\models\LuloModel{
	
	/** Table name */
	const TABLE_NAME = "photo";
	
	/** Class name */
	const CLASS_NAME = "lulo\\tests\models\Photo";
	
	/**
	 * Class metainformation.
	 * */
	public static $META = [
		// Model description
		"model_description" => "User photos.",
		// Model description that can be read by humans
		"verbose_name" => "user photo for lulo examples",
		// Model description that can be read by humans (plural)
		"verbose_name_plural" => "user photos for lulo examples",
		// Gender ('m' for male, 'f' for female)
		"gender" => "f",
		// Management list order
		"order" => ["order_in_gallery"=>"ASC"]
	];
	
	/**
	 * Model attributes.
	*/
	protected static $ATTRIBUTES = [
		// Primary key
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Web site this photo depends on", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Unique identifier", "auto"=>true],
		// Foreign key
		"user_id" => [
			"type"=>"int", "subtype"=>"ForeignKey", "name"=>"user", "on"=>"lulo\\tests\models\User.id", "related_name"=>"photos",
			// A partir de aquí abajo todo son parámetros opcionales
			"verbose_name"=>"Photo owner",
			"related_verbose_name" => "User photos",
			"nullable" => false,  "readonly"=>false,
			"on_master_deletion" => "delete"
		],
		// Data
		"order_in_gallery" => ["type"=>"int", "verbose_name"=>"Photo order"],
		"photo" => ["type"=>"blob", "verbose_name"=>"Photo"],
		"photo_mimetype" => ["type"=>"string", "verbose_name"=>"Photo image Mimetype", "default" =>"application/octet-stream"],
		"photo_filename" => ["type"=>"string", "max_length"=>64, "verbose_name"=>"Filename", "default"=>null, "null"=>true, "auto"=>true],
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
	
	protected static $RELATIONSHIPS = [];

	/**
	 * Representation of this object as a string.
	 * @return string Filename of the photography.
	 * */
	public function str(){
		return $this->photo_filename;
	}
	
	
	/**
	 * Values for foreign key fields
	 * */
	public static function formValues($formFieldName, $object=null){
		$values = parent::formValues($formFieldName, $object);
		if(!is_null($values)){
			return $values;
		}
		
		// Creation form
		if(is_null($object)){
			return null;
		}
		
		// Users for this object
		if($formFieldName == "user"){
			return \lulo\tests\models\User::dbLoadAll();
		}
		return null;
	}

	
	/**
	 * Clean creation data.
	 * */
	public static function cleanCreation($data){
		$cleaned_data = parent::cleanCreation($data);
		$cleaned_data["stack"] = TEST_STACK;
		$cleaned_data["photo_filename"] = $cleaned_data["photo"]["name"];
		$cleaned_data["photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($cleaned_data["photo"]["name"]);
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$cleaned_data["creation_datetime"] = $now;
		$cleaned_data["last_update_datetime"] = $now;
		return $cleaned_data;
	}
	
	
	/**
	 * Clean edition data.
	 * */
	public static function cleanEdition($data){
		$cleaned_data = parent::cleanEdition($data);
		// Si se modifica la foto
		if(array_key_exists("photo", $cleaned_data) and !is_null($cleaned_data["photo"]["dbblobreader"])){
			$cleaned_data["photo_filename"] = $cleaned_data["photo"]["name"];
			$cleaned_data["photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($cleaned_data["photo"]["name"]);
		}
		// Actualización de la fecha de última actualización
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$cleaned_data["last_update_datetime"] = $now;
		return $cleaned_data;
	}
	
	
	/**
	 * Implicit condition.
	 * @return array Array con la condición implícita.
	 * */
	public static function implicitBaseCondition(){
		return [
			"stack" => TEST_STACK,
		];
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/*
	-- SQL for this table:
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
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Lulo example table for photos'

	   
	-- END SQL
	*/
}

/*
 * Mandatory initialization.
 * */
Photo::init();

?>
