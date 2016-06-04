<?php

namespace lulo\tests\models;

/**
 * User post in our example package.
 * @author Diego J. Romero López at intelligenia.
 * */
class Post extends \lulo\models\LuloModel{

	/** Table name */
	const TABLE_NAME = "post";
	
	/** Class name */
	const CLASS_NAME = "lulo\\tests\models\Post";
	
	/**
	 * Class metainformation.
	 * */
	public static $META = [
		// Model description
		"model_description" => "Blog post for lulo examples",
		// Nombre legible por humanos (en singular)
		"verbose_name" => "blog post",
		// Model description that can be read by humans (plural)
		"verbose_name_plural" => "blog posts",
		// Gender ('m' for male, 'f' for female)
		"gender" => "f",
		// Management list order
		"order" => ["title"=>"ASC"]
	];
	
	/**
	 * Attributes
	 * 	 */
	protected static $ATTRIBUTES = [
		// Primary key
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Web site of this post", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Unique identifier", "auto"=>true],
		// Data
		"title" => ["type"=>"string", "max_length"=>64, "verbose_name"=>"Title"],
		"title_slug" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Slug", "auto"=>true],
		"content" => ["type"=>"string", "subtype"=>"doku", "verbose_name"=>"Post content"],
		"owner_id" => ["type"=>"int", "relationship"=>"owner", "verbose_name"=>"User owner of this post"],
		// Datetime fields
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Last time this object was updated", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Creation datetime", "auto"=>true],
	];
	
	
	/** Primary key */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Related models.
	 * */
	protected static $RELATED_MODELS = ["lulo\\tests\models\User"];
	
	
	/**
	 * Relationships of Post model
	 * */
	protected static $RELATIONSHIPS = [
		// Relationship with User
		"owner" => [
			"type" => "ForeignKey",
			"model" => "lulo\\tests\models\User",
			// Relationship human name
			"verbose_name" => "Owner of the post",
			// Inverse relationship name
			"related_name" => "posts",
			// Inverse relationship human name
			"related_verbose_name" => "User posts",
			// Link
			"condition" => ["owner_id"=>"id"],
			// It is not nullable
			"nullable" => false,
			// It is not readonly
			"readonly" => false,
			// On user deletion, the posts are also deleted
			"on_master_deletion" => "delete"
		]
	];

	
	/**
	 * Representation of this object as a string.
	 * @return string Title of the post.
	 * */
	public function str(){
		return $this->title;
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
		// Edition form allows selecting an user
		if($formFieldName == "owner"){
			return \lulo\tests\models\User::dbLoadAllAsCollection();
		}
		return null;
	}
	
	
	/**
	 * Clean creation fields
	 * */
	public static function cleanCreation($data){
		$cleaned_data = parent::cleanCreation($data);
		$cleaned_data["stack"] = TEST_STACK;
		$cleaned_data["title_slug"] = \lulo\db\DB::dbMakeUniqueLargeSlug($data["title"], 64, static::getTableName(), "title");
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$cleaned_data["creation_datetime"] = $now;
		$cleaned_data["last_update_datetime"] = $now;
		return $cleaned_data;
	}
	
	
	/**
	 * Clean edition fields
	 * */
	public static function cleanEdition($data){
		$cleaned_data = parent::cleanEdition($data);
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
	
	
	/*
	-- SQL for this table:
	-- START SQL
	CREATE TABLE `post` (
	 `stack` varchar(128) CHARACTER SET ascii NOT NULL,
	 `id` int(11) NOT NULL AUTO_INCREMENT,
	 `owner_id` int(11) NOT NULL,
	 `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
	 `title_slug` varchar(128) CHARACTER SET ascii NOT NULL,
	 `content` longtext COLLATE utf8_unicode_ci NOT NULL,
	 `creation_datetime` datetime NOT NULL,
	 `last_update_datetime` datetime NOT NULL,
	 PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Lulo example table for posts'
	-- END SQL
	*/
	
}

/*
 * Mandatory initialization.
 * */
Post::init();
