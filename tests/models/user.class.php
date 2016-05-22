<?php

/**
 * Clase de usuario de ejemplo.
 * Clase de ejemplo para ilustrar cómo usar los modelos de Lulo.
 * La herencia es la siguiente:
 * - Model: vamos a usar lectura y escritura de la BD de UniWeb/IntelliWeb
 * - RWModel: vamos a usar lectura y escritura de la BD del CSIRC (u otra BD con un DBHelper específico)
 * - ROModelo: vamos a usar sólo lectura de una BD que use un DBHelper específico.
 * @author Diego J. Romero López en intelligenia.
 * */
class User extends \lulo\models\LuloModel{
	
	/******************************************************************/
	/******************************************************************/
	/***************** ATRIBUTOS QUE SE SOBRESCRIBREN *****************/
	
	/** Tabla en la que se basa esta clase */
	const TABLE_NAME = "user";
	
	/** Nombre de la clase */
	const CLASS_NAME = __CLASS__;
	
	/**
	 * Metainformación sobre la clase, se usa para mostrar en los listados
	 * y formularios de creación y edición de objetos.
	 * */
	public static $META = [
		// Descripción legible de qué representa el modelo
		"model_description" => "Usuario del sistema Lulo. Estos modelos son de ejemplo y sólo sirven como ejemplo a los desarrolladores",
		// Nombre legible por humanos (en singular)
		"verbose_name" => "usuario de ejemplo de LULO",
		// Nombre legible por humanos (en plural)
		"verbose_name_plural" => "usuarios de ejemplo de LULO",
		// Género de los nombres legibles ('m' para masculino, 'f' para femenino)
		"gender" => "m",
		// Orden en los listados (en estilo ["campo1"=>"ASC|DESC", "campo2"=>"ASC|DESC", ...])
		"order" => ["username"=>"ASC"]
	];
	
	/**
	 * Atributos de este modelo.
	 * Cada elemento representa a un atributo. Cada atributo estará asociado a un campo de la base de datos.
	 * Cada atributo tiene los siguientes metatributos:
	 * - type: tipo de dato. Puede tomar cualquiera de los valores siguientes: string, blob, float, int. NOTA: el tipo "blob" identifica a cadenas que representan ficheros.
	 * - subtype: tipo semántico. Si no existe, se asume que no hay ninguna restricción. Valores posibles: 
	 *   - phone: teléfono.
	 *   - email: correo electrónico. 
	 *   - ddmmyyyy: fecha en formato dd/mm/yyyy.
	 *   - doku: texto largo en formato doku.
	 * - default: valor por defecto. Opcional.
	 * - null: indica si el atributo puede tener el valor nulo. Opcional.
	 * - verbose_name: descripción del atributo. Obligatorio.
	 * - length: si el campo es una cadena, se puede incluir la longitud de éste. Opcional.
	 * - auto: el campo se rellena de forma automática. Bien porque tiene un valor por defecto en BD o por otro motivo.
	 * 
	 * Todo atributo que no tenga un valor por defecto o no pueda ser nulo, se asume obligatorio.
	 * 
	 * Notemos que aquí los atributos pueden llamarse como el desarrollador quiera
	 * no se tienen que respetar las convenciones de _stack es el atributo del stack
	 * y el id es el identificador único. Eso lo definiremos en la aplicación que haga
	 * uso de este modelo.
	 * */
	protected static $ATTRIBUTES = [
		// Clave primaria
		"stack"=>["type"=>"string", "default"=>TEST_STACK, "verbose_name"=>"Stack del que depende el usuario", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Identificador único del usuario", "auto"=>true],
		// Campos propiamente dichos
		"first_name" => ["type"=>"string", "verbose_name"=>"Nombre", "access"=>"rw"],
		"last_name" => ["type"=>"string", "verbose_name"=>"Apellidos"],
		"email" => ["type"=>"string", "subtype"=>"email", "verbose_name"=>"Correo electrónico"],
		"phone" => ["type"=>"string", "subtype"=>"phone", "verbose_name"=>"Teléfono del usuario", "null"=>true, "default"=>null],
		"username" => ["type"=>"string", "subtype"=>"username", "verbose_name"=>"Nombre del usuario"],
		"sha1_password" => ["type"=>"string", "verbose_name"=>"Password"],
		// Campo de blob y sus atributos relacionados (mimetype y filename)
		"main_photo" => ["type"=>"blob", "verbose_name"=>"Foto principal del usuario", "null"=>true, "default"=>null],
		"main_photo_mimetype" => ["type"=>"string", "verbose_name"=>"Mimetype del campo main_photo", "default" =>"application/octet-stream"],
		"main_photo_filename" => ["type"=>"string", "verbose_name"=>"Nombre del fichero que contiene el campo main_photo", "null"=>true, "default"=>null],
		// Campos de fecha
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de última actualización", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de creación", "auto"=>true],
	];
	
	
	/** Listado de atributos que forman la clave primaria */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Clases con las que tiene alguna relación.
	 * */
	protected static $RELATED_MODELS = ["Tag", "Photo", "Post"];
	
	
	/** Relaciones con otros modelos (ver Tag para ejemplos y descripción) */
	protected static $RELATIONSHIPS = [
	
	];

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
		return $this->username." (".$this->first_name." ".$this->last_name.")";
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de CREAR
	 * un objeto de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return array Array con los datos convertidos para ser tomados por class::factoryFromArray
	 * */
	public static function cleanCreation($data){
		$data = parent::cleanCreation($data);
		$data["stack"] = TEST_STACK;
		$data["main_photo_filename"] = $data["main_photo"]["name"];
		$data["main_photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($data["main_photo"]["name"]);
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		if(isset($data["password"])){
			$data["sha1_password"] = sha1($data["password"]);
		}
		$data["creation_datetime"] = $now;
		$data["last_update_datetime"] = $now;
		return $data;
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de EDITAR
	 * un objeto existente de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return array Array con los datos convertidos para ser tomados por $object->setFromArray
	 * */
	public static function cleanEdition($data){
		$data = parent::cleanEdition($data);
		// Para la edición
		if(array_key_exists("main_photo", $data) and !is_null($data["main_photo"]["dbblobreader"])){
			$data["main_photo_filename"] = $data["main_photo"]["name"];
			if(isset($data["main_photo"]["name"])){
				$data["main_photo_mimetype"] = MimeTypeFinder::getMimetypeFromFileName($data["main_photo"]["name"]);
			}
		}
		if(isset($data["password"])){
			$data["sha1_password"] = sha1($data["password"]);
		}
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$data["last_update_datetime"] = $now;
		return $data;
	}
	
	
	/**
	 * Método que valida al primer nombre del usuario.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return boolean True si el campo es válido, false en otro caso.
	 * */
	public static function validateName($data){

		$name_length = mb_strlen($data["first_name"]);
		return ($name_length <= 25);
		
	}
	
	
	/**
	 * Método que valida al apellido del usuario.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return boolean True si el campo es válido, false en otro caso.
	 * */
	public static function validateSurname($data){

		$surname_length = mb_strlen($data["last_name"]);
		return ($surname_length <= 25);
		
	}
	
	
	/**
	 * Método que valida al correo electrónico del usuario.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return boolean True si el campo es válido, false en otro caso.
	 * */
	public static function validateEmail($data){
		
		$ok=false;
		$email = $data["email"];
		$expresion='/^([a-zA-Z0-9._]+)@([a-zA-Z0-9.-]+).([a-zA-Z]{2,4})$/';
		if (preg_match($expresion,$email)) $ok=true;
		return $ok;
	}
	
	
	/**
	 * Método que valida el teléfono del usuario.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return boolean True si el campo es válido, false en otro caso.
	 * */
	public static function validatePhone($data){
		$phone = $data["phone"];
		$expresion = '/^[9|6|7][0-9]{8}$/'; 
		if (preg_match($expresion, $phone)>0){
			return true;
		}
		return $false;
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
		// Validación del nombre (más de 25 caracteres)
		$validateName = [
			"function" => __CLASS__."::validateName",
			"error_message" => "El nombre  ha de ser menor de 25 caracteres",
			"error_fields" => ["first_name"]
		];
		// Validación del apellido (más de 25 caracteres)
		$validateSurname = [
			"function" => __CLASS__."::validateSurname",
			"error_message" => "El nombre  ha de ser menor de 25 caracteres",
			"error_fields" => ["last_name"]
		];
		// Validación del formato del email
		$validateEmail = [
			"function" => __CLASS__."::validateEmail",
			"error_message" => "Debe respetar el formato de una dirección email",
			"error_fields" => ["email"]
		];
		// Validación del formato del teléfono
		$validatePhone = [
			"function" => __CLASS__."::validatePhone",
			"error_message" => "Debe respetar el formato de un telefono",
			"error_fields" => ["phone"]
		];
		
		// Devolvemos todos los validadores
		return [$validateName, $validateSurname, $validateEmail, $validatePhone ];
		
	}
	
	
	/**
	 * Condición implícita de carga. Todos los objetos que cargue este modelo
	 * deberán cumplirla. Útil para atributos como "is_erased" que indican
	 * estados "sumidero".
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
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Tabla de ejemplo para LULO';
	-- END SQL
	*/

}

/*
 * Inicialización obligatoria, se ha de llamar a este método estático
 * una vez declarada la clase para poder iniciar (entre otras cosas, las
 * relaciones inversas.
 * */
User::init();

?>
