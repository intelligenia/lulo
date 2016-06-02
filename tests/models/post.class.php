<?php

namespace lulo\tests\models;

/**
 * Clase de usuario de ejemplo.
 * @author Diego J. Romero López at intelligenia.
 * */
class Post extends \lulo\models\LuloModel{

	/******************************************************************/
	/******************************************************************/
	/***************** ATRIBUTOS QUE SE SOBRESCRIBREN *****************/
	
	/** Tabla en la que se basa esta clase */
	const TABLE_NAME = "post";
	
	/** Nombre de la clase */
	const CLASS_NAME = "lulo\\tests\models\Post";
	
	/**
	 * Metainformación sobre la clase, se usa para mostrar en los listados
	 * y formularios de creación y edición de objetos.
	 * */
	public static $META = [
		// Descripción legible de qué representa el modelo
		"model_description" => "Entradas de blog de cada uno de los usuarios de ejemplo del sistema Lulo. Estos modelos son de ejemplo y sólo sirven como ejemplo a los desarrolladores",
		// Nombre legible por humanos (en singular)
		"verbose_name" => "entrada de blog de usuario de ejemplo de LULO",
		// Nombre legible por humanos (en plural)
		"verbose_name_plural" => "entradas de blog de usuarios de ejemplo de LULO",
		// Género de los nombres legibles ('m' para masculino, 'f' para femenino)
		"gender" => "f",
		// Orden en los listados (en estilo ["campo1"=>"ASC|DESC", "campo2"=>"ASC|DESC", ...])
		"order" => ["title"=>"ASC"]
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
	 * - relationship: el campo depende de la relación indicada como valor de este atributo.
	 * - translatable: el campo es traducible.
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
		"title" => ["type"=>"string", "max_length"=>64, "verbose_name"=>"Título"],
		"title_slug" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Slug del título", "auto"=>true],
		"content" => ["type"=>"string", "subtype"=>"doku", "verbose_name"=>"Contenido de la entrada"],
		"owner_id" => ["type"=>"int", "relationship"=>"owner", "verbose_name"=>"Identificador del usuario propietario"],
		// Campos de fecha
		"last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de última actualización", "auto"=>true],
		"creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Fecha de creación", "auto"=>true],
	];
	
	
	/** Listado de atributos que forman la clave primaria */
	protected static $PK_ATTRIBUTES = ["stack", "id"];
	
	
	/**
	 * Clases con las que tiene alguna relación.
	 * */
	protected static $RELATED_MODELS = ["lulo\\tests\models\User"];
	
	
	/** Relaciones con otros modelos (ver Tag para ejemplos y descripción) */
	/**
	 * Relaciones del modelo Post
	 * */
	protected static $RELATIONSHIPS = [
		////////////////////////////////////////////////////////////////
		// Relación con User
		"owner" => [
			"type" => "ForeignKey",
			"model" => "lulo\\tests\models\User",
			// Nombre legible de la relación
			"verbose_name" => "Propietario de la entrada",
			// Nombre de la relación inversa
			"related_name" => "posts",
			// Nombre legible de la relación inversa (opcional)
			"related_verbose_name" => "Entradas del usuario",
			// Atributos relacionados
			"condition" => ["owner_id"=>"id"],
			// Nullable indica que permite el valor "ninguno"
			"nullable" => false,
			// Una relación readonly sólo permite consulta
			"readonly" => false,
			// Indica qué ocurre cuando se elimina el objeto padre
			// Se han de eliminar las fotos en cascada
			"on_master_deletion" => "delete"
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
		return $this->title;
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
		/// Posibles valores para el campo de propietario de este Post
		if($formFieldName == "owner"){
			return \lulo\tests\models\User::dbLoadAllAsCollection();
		}
		return null;
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de CREAR
	 * un objeto de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * */
	public static function cleanCreation($data){
		$data = parent::cleanCreation($data);
		$data["stack"] = TEST_STACK;
		$data["title_slug"] = \DB::dbMakeUniqueLargeSlug($data["title"], 64, static::getTableName(), "title");
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
	
	
	/*
	-- SQL de esta tabla:
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
	) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Entradas de blog de los usuarios'
	-- END SQL
	*/
	
}

/*
 * Inicialización obligatoria, se ha de llamar a este método estático
 * una vez declarada la clase para poder iniciar (entre otras cosas, las
 * relaciones inversas.
 * */
Post::init();
