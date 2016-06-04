<?php

namespace lulo\tests\models;

use \lulo\tests\utils\MimetypeFinder as MimetypeFinder;

/**
 * Example user class for a social-network-like system.
 * @author Diego J. Romero López at intelligenia.
 * */
class User extends \lulo\models\LuloModel{
	
	/** Table name */
	const TABLE_NAME = "site_user";
	
	/** Class name */
	const CLASS_NAME = "lulo\\tests\models\User";
	
	/**
	 * Class metainformation.
	 * */
	public static $META = [
		"model_description" => "Usuario del sistema Lulo. Estos modelos son de ejemplo y sólo sirven como ejemplo a los desarrolladores",
		"verbose_name" => "usuario de ejemplo de LULO",
		"verbose_name_plural" => "usuarios de ejemplo de LULO",
		"gender" => "m",
		"order" => ["username"=>"ASC"]
	];
	
	/**
	 * Attributes.
	 * */
	protected static $ATTRIBUTES = [
		// Primary key
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"User web site", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Unique identifier for this user", "auto"=>true],
		// Fields
		"first_name" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Name", "access"=>"rw"],
		"last_name" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Surname"],
		"email" => ["type"=>"string", "max_length"=>32, "subtype"=>"email", "verbose_name"=>"E-mail"],
		"phone" => ["type"=>"string", "max_length"=>32, "subtype"=>"phone", "verbose_name"=>"User telephone number", "null"=>true, "default"=>null],
		"username" => ["type"=>"string", "max_length"=>32, "subtype"=>"username", "verbose_name"=>"Username"],
		"sha1_password" => ["type"=>"string", "max_length"=>256, "verbose_name"=>"Password"],
		// Blob fields and associated fields
		"main_photo" => ["type"=>"blob", "verbose_name"=>"Avatar photo", "null"=>true, "default"=>null],
		"main_photo_mimetype" => ["type"=>"string", "verbose_name"=>"Photo mimetype", "default" =>"application/octet-stream"],
		"main_photo_filename" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Photo filename", "null"=>true, "default"=>null],
		// Datetime fields
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Last time this object was updated", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Creation datetime", "auto"=>true],
	];
	
	
	/** Primary key */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Related models
	 * */
	protected static $RELATED_MODELS = ["lulo\\tests\models\Tag", "lulo\\tests\models\Photo", "lulo\\tests\models\Post"];
	
	
	/** Direct relationships with other models */
	protected static $RELATIONSHIPS = [
	
	];


	/**
	 * Representation of this object as string.
	 * */
	public function str(){
		return $this->username." (".$this->first_name." ".$this->last_name.")";
	}
	
	
	/**
	 * Clean creation data to make it ready for use in factoryFromArray.
	 * */
	public static function cleanCreation($data){
		$cleaned_data = parent::cleanCreation($data);
		$cleaned_data["stack"] = TEST_STACK;
		$cleaned_data["main_photo_filename"] = $cleaned_data["main_photo"]["name"];
		$cleaned_data["main_photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($cleaned_data["main_photo"]["name"]);
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		if(isset($cleaned_data["password"])){
			$cleaned_data["sha1_password"] = sha1($cleaned_data["password"]);
		}
		$cleaned_data["creation_datetime"] = $now;
		$cleaned_data["last_update_datetime"] = $now;
		return $cleaned_data;
	}
	
	
	/**
	 * Clean edition data to make it ready for use in $object->setFromArray.
	 * */
	public static function cleanEdition($data){
		$cleaned_data = parent::cleanEdition($data);
		// Para la edición
		if(array_key_exists("main_photo", $cleaned_data) and !is_null($cleaned_data["main_photo"]["dbblobreader"])){
			$cleaned_data["main_photo_filename"] = $cleaned_data["main_photo"]["name"];
			if(isset($cleaned_data["main_photo"]["name"])){
				$cleaned_data["main_photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($cleaned_data["main_photo"]["name"]);
			}
		}
		if(isset($cleaned_data["password"])){
			$cleaned_data["sha1_password"] = sha1($cleaned_data["password"]);
		}
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$cleaned_data["last_update_datetime"] = $now;
		return $cleaned_data;
	}
	

	/**
	 * Validate user email.
	 * */
	public static function validateEmail($data){
		
		$ok = false;
		$email = $data["email"];
		$expresion='/^([a-zA-Z0-9._]+)@([a-zA-Z0-9.-]+).([a-zA-Z]{2,4})$/';
		if (preg_match($expresion,$email)){
			$ok = true;
		}
		return $ok;
	}
	
	
	/**
	 * Implicit condition.
	 * */
	public static function implicitBaseCondition(){
		return [
			"stack" => TEST_STACK,
		];
	}
	
	
	/*
	-- SQL code for this table:
	-- START SQL
	  CREATE TABLE `user` (
	    `stack` varchar(256) CHARACTER SET ascii NOT NULL,
	    `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	    `first_name` varchar(256) COLLATE utf8_spanish_ci NOT NULL,
	    `last_name` varchar(256) COLLATE utf8_spanish_ci NOT NULL,
	    `email` varchar(256) COLLATE utf8_spanish_ci NOT NULL,
	    `phone` varchar(256) COLLATE utf8_spanish_ci DEFAULT NULL,
	    `username` varchar(64) COLLATE utf8_spanish_ci NOT NULL,
	    `sha1_password` varchar(256) COLLATE utf8_spanish_ci NOT NULL,
	    `main_photo` longblob DEFAULT NULL,
	    `main_photo_mimetype` varchar(64) COLLATE utf8_spanish_ci DEFAULT NULL,
	    `main_photo_filename` varchar(64) COLLATE utf8_spanish_ci DEFAULT NULL,
	    `last_update_datetime` datetime NOT NULL,
	    `creation_datetime` datetime NOT NULL
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Lulo example table for users';
	-- END SQL
	*/

}

/*
 * Mandatory initialization.
 * */
User::init();

?>
