<?php

/**
 * Tag example class that shows how to use LuloModel models.
 * @author Diego J. Romero López at intelligenia.
 * */
class Tag extends \lulo\models\LuloModel{
	/******************************************************************/
	/******************************************************************/
	/***************** ATRIBUTOS QUE SE SOBRESCRIBREN *****************/
	
	/** Tabla en la que se basa esta clase */
	const TABLE_NAME = "tag";
	
	
	/** Nombre de la clase */
	const CLASS_NAME = __CLASS__;
	
	/**
	 * Metainformación sobre la clase, se usa para mostrar en los listados
	 * y formularios de creación y edición de objetos.
	 * */
	public static $META = [
		// Descripción legible de qué representa el modelo
		"model_description" => "Etiqueta de usuario del sistema Lulo. Estos modelos son de ejemplo y sólo sirven como ejemplo a los desarrolladores",
		// Nombre legible por humanos (en singular)
		"verbose_name" => "etiqueta de usuario de ejemplo de LULO",
		// Nombre legible por humanos (en plural)
		"verbose_name_plural" => "etiquetas de usuarios de ejemplo de LULO",
		// Género de los nombres legibles ('m' para masculino, 'f' para femenino)
		"gender" => "f",
		// Orden en los listados (en estilo ["campo1"=>"ASC|DESC", "campo2"=>"ASC|DESC", ...])
		"order" => ["name"=>"ASC"]
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
		"stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Stack del que depende el usuario", "auto"=>true],
		"id" => ["type"=>"int", "verbose_name"=>"Identificador único del usuario", "auto"=>true],
		// Campos propiamente dichos
		"name" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Nombre"],
		"description" => ["type"=>"string", "subtype"=>"doku", "verbose_name"=>"Descripción de la etiqueta"],
		"parent_id" => ["type"=>"int", "verbose_name"=>"Etiqueta padre", "null"=>true, "default"=>null, "relationship"=>"parent_tag"],
		// Campos de fecha
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de última actualización", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de creación", "auto"=>true],
	];
	
	
	/** Listado de atributos que forman la clave primaria */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Clases con las que tiene alguna relación
	 * */
	protected static $RELATED_MODELS = ["User"];
	
	
	/** Relaciones con otros modelos */
	/*
	$RELATIONSHIPS = [
			// Si es relación de muchos a muchos
			'<relationship_name>' => [
				// Tipo de la relación (muchos a muchos)
				"type"=>"ManyToMany",
				// Modelo con el que está relacionado
				"model"=>"<model_name>",
				// Tablas de nexo.
				// ATENCIÓN: sólo se permite la edición si existe una única tabla de nexo
				"junctions" => <array con la lista de las tablas intermedias>,
				// Lista con la lista de todas las relaciones entre nexos
				"conditions"=>[ 
					// Cada una de las relaciones entre tablas
					// Se asume que
					// 1.- La tabla 1 es la tabla origen (la tabla de este modelo)
					// 2.- Las tablas 2 hasta la N-1 (ambas incluidas) son las tablas de nexo (tablas listadas en el atributo "junctions" de esta relación)
					// 3.- La tabla N es la tabla remota (tabla del modelo descrito en el atributo "model" de esta relación)
					// Relaciones:
					// Relación entra tabla 1 y tabla 2 (M1 es el número de atributos a emparejar entre la tabla 1 y la tabla 2)
					[<table_1_attribute_1> => <table_2_attribute_1>, <table_1_attribute_2> => <table_2_attribute_2>, ..., <table_1_attribute_M1> => <table_2_attribute_M1>],
					// Relación entra tabla 2 y tabla 3 (M2 es el número de atributos a emparejar entre la tabla 2 y la tabla 3)
					[<table_2_attribute_1> => <table_3_attribute_1>, <table_2_attribute_2> => <table_3_attribute_2>, ..., <table_2_attribute_M2> => <table_3_attribute_M2>],
					...
					// Relación entra tabla N-1 y tabla N (Mn es el número de atributos a emparejar entre la tabla N-1 y la tabla N)
					[<table_N-1_attribute_1> => <table_N_attribute_1>, <table_N-1_attribute_2> => <table_N_attribute_2>, ..., <table_N-1_attribute_Mn> => <table_N_attribute_Mn>],
				],
				// Para determinar si la relación es de sólo lectura
				"readonly" => true|false,
			],
			
			// Si es relación de clave externa con otra modelo (tabla)
			'<relationship_name>' => [
				// Tipo de la relación (clave externa)
				'type'=>"ForeignKey",
				// Nombre del modelo con el que se relaciona
				'model'=>"<model_name>",
				// Condición de la relación, donde los atributos locales
				// son los del modelo actual, y los remotos los del modelo remoto
				"condition"=>[
					<local_attribute_1> => <remote_attribute_1>,
					<local_attribute_2> => <remote_attribute_2>,
					...
					<local_attribute_N> => <remote_attribute_N>,
				],
				// Para determinar si la relación es de sólo lectura
				"readonly" => true|false,
			],
		);
	*/
	
	/**
	 * Relaciones del modelo Tag
	 * */
	protected static $RELATIONSHIPS = [
		////////////////////////////////////////////////////////////////
		// Relación con User
		"users" => [
			"type" => "ManyToMany",
			"model" => "User",
			// Nombre legible de la relación
			"verbose_name" => "Usuarios con esta etiqueta",
			// Nombre de la relación inversa
			"related_name" => "tags",
			// Nombre legible de la relación inversa (opcional)
			"related_verbose_name" => "Etiquetas de un usuario",
			// Tablas nexo intermedias
			// (si tiene más de una tiene que ser relación de sólo lectura [readonly=true])
			"junctions" => ["user_tag"],
			// Condiciones de relación entre la tabla, el nexo
			// y la tabla destino (tabla del modelo a cargar)
			"conditions" => [
				// Relación entre esta tabla y el nexo
				["stack"=>"stack", "id"=>"usertag_id"],
				// Relación entre el nexo y la tabla del modelo User
				["stack"=>"stack", "user_id"=>"id"],
			],
			// Una relación readonly sólo permite consulta
			"readonly" => false,
			"on_master_deletion" => "delete"
		],
		////////////////////////////////////////////////////////////////
		// Relación con ella misma
		"parent_tag" => [
			"type" => "ForeignKey",
			"model" => "Tag",
			// Nombre legible de la relación
			"verbose_name" => "Etiqueta padre",
			// Nombre de la relación inversa
			"related_name" => "children_tags",
			// Nombre legible de la relación inversa (opcional)
			"related_verbose_name" => "Etiquetas hijas de la actual",
			// Atributos relacionados
			"condition" => ["parent_id"=>"id"],
			// Nullable indica que permite el valor "ninguno"
			"nullable" => true,
			// Una relación readonly sólo permite consulta
			"readonly" => false,
			// Indica qué ocurre cuando se elimina el objeto padre
			"on_master_deletion" => ["set" => ["parent_id"=>null]]  // "delete"
			// Otra opción sería hacer 
			// "on_master_deletion" => "delete" // esto eliminaría en cascada los objetos
		]
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
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Etiquetas para cada uno de los usuarios';
	   
	-- Relación entre user y usertag
	  CREATE TABLE `user_tag` (
	    `stack` varchar(256) CHARACTER SET ascii NOT NULL,
	    `user_id` int(11) NOT NULL,
	    `usertag_id` int(11) NOT NULL,
	    PRIMARY KEY (`stack`,`user_id`,`usertag_id`) 
	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Relación muchos a muchos entre user y usertag';
	   
	-- END SQL
	*/

}

/*
 * Inicialización obligatoria, se ha de llamar a este método estático
 * una vez declarada la clase para poder iniciar (entre otras cosas, las
 * relaciones inversas.
 * */
Tag::init();

?>
