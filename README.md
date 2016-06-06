# lulo
A minimal ORM for PHP inspired by Django.

# What's this?
This is a small and easy to use ORM based on Django's ORM API for PHP.

# Requirements
PHP 5.4 and dependencies installed by [composer](https://getcomposer.org/) ([AdoDB](http://adodb.org/dokuwiki/doku.php) and [Twig template system](http://twig.sensiolabs.org/)).

# Documentation

## Local configuration
Create a configuration.local.php in your web server with the structure defined in configuration.local.example.php.

This file must contain access credential to your database as seen in the example:

```php
function get_db_settings(){
    $db_settings = [
        "server" => "<DB SERVER>",
        "user" => "<DB USER>",
        "password" => "<DB PASSWORD>",
        "database" => "<DATABASE>"
    ];
    return $db_settings;
}
```

This local configuration file will be loaded automatically from **configuration.php** and will be used to access database.

## API

- [Models](docs/models.md)
- [API](docs/model_api.md)
- [Queries](docs/queries.md)

Extended documents:
- [Lulo](docs/Lulo-ES.pdf)
- [LuloQuery](docs/LuloQuery-ES.pdf)


# Examples

There is a test module gives you several examples of using Lulo and its query system.

```php
/**
 * Example user class for a social-network-like system.
 * @author Diego J. Romero López at intelligenia.
 * */
class User extends \lulo\models\LuloModel{
	
	/** Model table */
	const TABLE_NAME = "user";
	
	/** Class name*/
	const CLASS_NAME = __CLASS__;
	
	/**
	 * Metainformation of the class
	 * */
	public static $META = [
		// Description of the model
		"model_description" => "Test user",
		// Verbose name
		"verbose_name" => "test user",
		// Verbose name (plural)
		"verbose_name_plural" => "test users",
		// Gender of the verbose name
		"gender" => "m",
		// Order in lists (["field1"=>"ASC|DESC", "field2"=>"ASC|DESC", ...])
		"order" => ["username"=>"ASC"]
	];
	
	/**
	 * Model attributes
	 * */
	protected static $ATTRIBUTES = [
		// Primary key
		"stack"=>["type"=>"string", "default"=>TEST_STACK, "verbose_name"=>"User dependant stack", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Identificador único del usuario", "auto"=>true],
		
        	// Proper user fields
		"first_name" => ["type"=>"string", "verbose_name"=>"Name", "access"=>"rw"],
		"last_name" => ["type"=>"string", "verbose_name"=>"Family name"],
		"email" => ["type"=>"string", "subtype"=>"email", "verbose_name"=>"E-Mail"],
		"phone" => ["type"=>"string", "subtype"=>"phone", "verbose_name"=>"User telepohone number", "null"=>true, "default"=>null],
		"username" => ["type"=>"string", "subtype"=>"username", "verbose_name"=>"Username"],
		"sha1_password" => ["type"=>"string", "verbose_name"=>"Password"],
		
        	// Photograph of the user. Stored as a blob in database
		"main_photo" => ["type"=>"blob", "verbose_name"=>"User photo", "null"=>true, "default"=>null],
		"main_photo_mimetype" => ["type"=>"string", "verbose_name"=>"Mimetype of main_photo", "default" =>"application/octet-stream"],
		"main_photo_filename" => ["type"=>"string", "verbose_name"=>"Filename of main_photo", "null"=>true, "default"=>null],
		
        	// Datetime fields
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Last update of this object", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Creation datetime of this object", "auto"=>true],
	];
	
	
	/** Primary key */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Related models.
	 * */
	protected static $RELATED_MODELS = ["Tag", "Photo", "Post"];
	
	
	/** Relationships */
	protected static $RELATIONSHIPS = [
	
	];
}
User::init();
```

# Extending Lulo

## Adding new DBMS SQL templates
Add a new folder in sql_templates with the name that will identify your DBMS (mssql2012, for example).

Overwrite the queries you need. Lulo template system will first load the templates you
specify here and if it doesn't find the template, it will try to load it from the _default
folder.


# TODO

- ~~Allow models to dynamically change of table.~~ It can be done by reimplementing **getTableName** method.
- End translation of code to English.
- Translate documents to English.
- ~~Reimplement Collection to use RecordSet.~~
- ~~Script for table creation from model.~~ Manager class in management namespace allows creation and deletion of model tables.
- Migrations.

# License
[MIT License](LICENSE).

# Authors
- Lulo and Lulo query created by the team leader and main developer of this project:
Diego J. Romero López at intelligenia (diegoREMOVETHIS@REMOVETHISintelligenia.com)
- DB abstraction layer created by several members of intelligenia team. Reviewed and extended by Gerardo Fernandez Rodríguez.
- QueryResult was done with Gerardo Fernandez Rodríguez.
- Several bugfixes and tests in MSSQL done by [Francisco Morales](https://github.com/moralesgea).
- Minor translation fixes done by [Brian Holsters](https://github.com/brian-holsters)
