<?php

/**
* Query abstraction for use in LuloModel.
* @author Diego J. Romero
*/

namespace lulo\query;

use \lulo\query\Aggregation as L_AGREG ;
use \lulo\query\TupleValue as L_V ;

/**
 * Query creator for LuloModel objects.
 * */
class Query implements \ArrayAccess, \Iterator, \Countable {

	/** Ruta donde está la plantilla SQL */
	const PATH = "";

	/** Database connector of the class \lulo\db\DB */
	public $db;

	/** Should we include DISTINCT statement? */
	public $is_distinct;

	/** LuloModel model that will be queried */
	public $model;

	/** Main table of the model */
	public $model_table;

	/** Fields to select. By default, all that belongs to model $model */
	public $selected_fields = null;

	/** Almacén de modelos relacionados en función a la consulta realizada */
	public $related_models = [];

	/** Almacén de tablas relacionadas en función a la consulta realizada que han de estar presentes en el JOIN */
	public $related_tables = [];
	
	/** Almacén con las relaciones que han de estar presentes en el JOIN */
	public $relationships = [];

	/** Agregaciones en la consulta */
	public $aggregations = [];

	/** Indica si tiene cláusula GROUP BY o no */
	public $has_group_by = false;

	/**
	 * Indica si la acción de eliminación o actualización ha de ser envuelta por una transacción
	 */
	public $is_transaction = false;
	
	/**
	 * Cada uno de los grupos de condiciones. Es una disyunción de condiciones AND.
	 * Esto es, una lista de objetos ConjunctionCondition.
	 */
	public $filters = [];

	/** Orden de los objetos obtenidos */
	public $order = null;

	/** Límite de tuplas */
	public $limit = null;
	
	/** Indica si se trata de una consulta SELECT FOR UPDATE */
	public $for_update = false;

	/**
	 * Índice actual. Usado en las operaciones de la implementación
	 * de la interfaz Iterator
	 * */
	protected $currentIndex = 0;

	/** RecordSet de la consulta */
	protected $recordSet = null;

	/** Tamaño del RecordSet */
	protected $recordSetSize = null;

	public function getTable($model = null) {
		if (is_null($model)) {
			$model = $this->model;
		}
		return $model::TABLE_NAME;
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* API privada */

	/**
	 * Añade un modelo al listado de modelos que se usan en la consulta.
	 * Sólo debería llamarse desde ConditionConjunction y Condition
	 * @param string $relationshipName Nombre de la relación.
	 * @param string $relatedModel Nombre del modelo relacionado
	 * */
	public function addRelatedModel($relationshipName, $relatedModel = null) {
		$model = $this->model;
		$relationship = $model::metaGetRelationship($relationshipName);

		// Tomamos el modelo relacionado de la relación (si no se ha pasado)
		if (is_null($relatedModel)) {
			$relatedModel = $relationship["model"];
		}
		// Añadimos el modelo relacionado a la lista de modelos
		$this->relatedModels[] = $relatedModel;
		// Añadimos su tabla a la lista de tablas relacionadas
		$this->relatedTables[] = $relatedModel::TABLE_NAME;
		// Añadimos la relación $relationshipName de $relatedModel
		// a la lista de relaciones relacionadas
		$this->relationships[$relationshipName] = [
			"model" => $relatedModel,
			"table" => $relatedModel::TABLE_NAME,
			"name" => $relationshipName,
			"attributes" => $relationship
		];

		$this->relationships[$relationshipName]["attributes"]["table"] = $relatedModel::TABLE_NAME;
	}

	
	/**
	 * Filtro de adición o exclusión.
	 * @param bool $isPositive Indica si es positivo (filtro de inclusión) o negativo (filtro de exclusión).
	 * @param array $paramters Parámetros del filtro.
	 * @return object Referencia a este objeto (this).
	 * */
	protected function _f($isPositive, $parameters) {
		// Si no hay parámetros, no hacemos nada
		if (count($parameters) == 0) {
			return $this;
		}

		// ¿Es una consulta positiva (filter) o negativa (exclude)?
		if ($isPositive) {
			$type = "positive";
		} else {
			$type = "negative";
		}

		// Generación de los grupos de condiciones condiciones
		$conditionGroups = [];
		foreach ($parameters as $conditionGroup) {
			$conditionGroups[] = new \lulo\query\ConditionConjunction($this, $conditionGroup);
		}
		// Añadimos el nuevo filtro, con su tipo
		$this->filters[] = ["type" => $type, "conditionGroups" => $conditionGroups];
		return $this;
	}
	
	/* FIN de API privada */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* API pública */

	/**
	 * Construye un LuloQuery para un modelo determinado.
	 * @param string $model Modelo al que se le quiere hacer una consulta.
	 * */
	public function __construct($model) {
		// Se usa el mismo conector de base de datos que el que tuviera
		// el modelo que se va a consultar
		$this->db = $model::DB;
		// Modelo a consultar
		$this->model = $model;
		// Tabla del modelo (tabla principal)
		$this->model_table = $model::TABLE_NAME;
		$this->filters = [];
		
		$this->relationships = [];
		
		$this->order = null;
		$this->limit = null;
		
		// Por defecto, se seleccionan los cambos seleccionables del modelo
		// es decir, todos aquellos que no sean BLOBs
		$selectableFields = $model::metaGetSelectableAttributes();
		$this->select($selectableFields);
	}

	
	/**
	 * Permite seleccionar una serie de campos determinados.
	 * 
	 * @param array $fields Array con los campos que se han de seleccionar.
	 * @return object Referencia a este objeto (this).
	 * */
	protected function select($fields) {
		$this->selected_fields = $fields;
		return $this;
	}

	
	/**
	 * Operación FILTER. Filtra los resultados en función de las condiciones
	 * que se incluyan en los parámetros.
	 * 
	 * Las condiciones están en Forma Normal Disyuntiva. Esto es,
	 * los parámetros de esta operación son condiciones disyuntivas (OR)
	 * que se unirán con AND la conjunción global.
	 * 
	 * Ejemplos:
	 * - filter(["A"=>1, "B"=>2], ["B"=>3, "C"=>4]):
	 *     (A=1 AND B=2) OR (B=3 AND C=4)
	 * - filter(["A"=>1], ["B"=>3, "C"=>4])->filter(["X"=>2, "Y"=>3], ["W"=>5]):
	 *     ( (A=1) OR (B=3 AND C=4) ) AND ( (X=2 AND Y=3) OR W=5 )
	 * 
	 * @return object Referencia a este objeto (this).
	 * */
	public function filter() {
		// Si no tiene argumentos, no añadimos nuevas condiciones y devolvemos $this
		$numArgs = func_num_args();
		if($numArgs == 0){ return $this; }
		
		// Llamada del filtro a partir de parámetros pasados como un único array
		$firstArgument = func_get_arg(0);
		if ($numArgs == 1 and isset($firstArgument[0]) and is_array($firstArgument[0])) {
			return $this->_f(true, $firstArgument);
		}

		// Caso general, el filtro se lo hemos introducido como argumentos
		// a este método
		return $this->_f(true, func_get_args());
	}

	
	/**
	 * Operación EXCLUDE.
	 * 
	 * Las condiciones están en Forma Normal Disyuntiva. Esto es,
	 * los parámetros de esta operación son condiciones disyuntivas (OR)
	 * que se unirán con AND la conjunción global.
	 * 
	 * Ejemplos:
	 * - filter(["A"=>1, "B"=>2], ["B"=>3, "C"=>4])->exclude(["X"=>5, "Y"=>6]):
	 *     ((A=1 AND B=2) OR (B=3 AND C=4)) AND NOT(X=5 AND Y=6)
	 *
	 * @return object Referencia a este objeto (this).
	 * */
	public function exclude() {
		// Si no tiene argumentos, no añadimos nuevas condiciones y devolvemos $this
		$numArgs = func_num_args();
		if($numArgs == 0){ return $this; }
		
		// Llamada del filtro a partir de parámetros pasados como un único array
		$firstArgument = func_get_arg(0);
		if ($numArgs == 1 and isset($firstArgument[0]) and is_array($firstArgument[0])) {
			return $this->_f(false, $firstArgument);
		}

		// Caso general, el filtro se lo hemos introducido como argumentos
		// a este método
		return $this->_f(false, func_get_args());
	}

	
	/**
	 * Aplica un límite a la consulta.
	 * @param int $start Inicio del intervalo de objetos a obtener.
	 * @param int $size Número de objetos a obtener. Si es null, se asume que $start es el tamaño.
	 * @return object Referencia a este objeto (this).
	 * */
	public function limit($start, $size = null) {
		if (is_null($size)) {
			$this->limit = [0, $start];
		} else {
			$this->limit = [$start, $size];
		}
		return $this;
	}

	
	/**
	 * Aplica un límite de 1 elemento a la consulta.
	 * Lanza una excepción si el LuloQuery devuelve vacío.
	 * 
	 * @return object Referencia al primer objeto del LuloQuery.
	 * */
	public function get() {
		// Si no tiene argumentos, no añadimos nuevas condiciones y devolvemos $this
		$numArgs = func_num_args();
		$results = null;
		if($numArgs == 0){
			$results = $this->limit(1);
		}else{
			$results = $this->filter(func_get_args())->limit(1);
		}
		
		// Comprobamos si el LuloQuery está vacío o no.
		// Si está vacío el LuloQuery devolvemos una excepción.
		try{
			return $results[0];
		}catch(OutOfRangeException $e){
			throw new OutOfRangeException("Error en get. No hay un objeto que concuerde con la selección");
		}
	}

	
	/**
	 * Aplica distinct a la consulta.
	 * @param bool $distinct Indica si la consulta ha de comprobar unicidad de elementos (true) o no comprobarla (false).
	 * @return object Referencia a este objeto (this).
	 * */
	public function distinct($distinct = true) {
		$this->distinct = $distinct;
	}

	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* ORDEN */

	/**
	 * Aplica un orden a la consulta.
	 * @param array $order orden con formato: ["<field_1>"=>"ASC"|"DESC", ..., "<field_N>"=>"ASC"|"DESC"]
	 * 
	 * Ejemplos:
	 * - ["users::last_name"=>"ASC", "users::first_name"=>"ASC", "id"=>"DESC"]
	 * - ["creation_datetime"=>"ASC", "users::last_name"=>"ASC"]
	 * 
	 * @return object Referencia a este objeto (this).
	 * */
	public function order($order) {
		$model = $this->model;
		// Para cada campo de orden, lo añadimos como objeto OrderField
		foreach ($order as $field => $fieldOrder) {
			$this->order[] = new \lulo\query\OrderField($this, $model, $field, $fieldOrder);
		}
		return $this;
	}
	
	/* FIN DEL ORDEN */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Agrupación de elementos */

	/**
	 * Ejecuta una agregación junto a los campos que se quieren seleccionar.
	 * Nótese que si lo que quieres hacer es obtener un valor numérico de la tabla, ejecuta select_aggregate.
	 * 
	 * @param array Array con las agregaciones de la siguiente forma, <función de agregación> => [campo1, campo2, ...]
	 * @return string SQL con la sentencia de la agregación.
	 * 	 */
	protected function init_aggregations($aggregations) {
		$model = $this->model;
		$db = $this->db;

		// Borramos las agregaciones que se hubieran hecho antes,
		// las agregaciones no se han de acumular
		$this->aggregations = [];
		$this->has_aggregation_group_by = false;
		
		// Comprobaciones de que las funciones de agregación y sus campos
		// son correctos y si tiene campos en al menos una agregación
		$hasFieldsInAnyAggregation = false;
		foreach ($aggregations as $aggregation) {
			// Comprobamos si tiene campos en alguna agregación
			if (!is_null($aggregation->fields) and count($aggregation->fields) > 0) {
				$hasFieldsInAnyAggregation = true;
			}
			// Le indicamos a la agregación el modelo sobre el que se va a ejecutar
			$aggregation->init($model);
		}

		$this->aggregations = array_merge($this->aggregations, $aggregations);
		$this->has_aggregation_group_by = $hasFieldsInAnyAggregation;
	}

	
	/**
	 * Ejecuta una agregación junto a los campos que se quieren seleccionar.
	 * Nótese que si lo que quieres hacer es obtener un valor numérico de la tabla, ejecuta select_aggregate.
	 * 
	 * @param array Array con las agregaciones de la siguiente forma, <función de agregación> => [campo1, campo2, ...]
	 * @return mixed Resultado de realizar la agregación
	 * 	 */
	public function aggregate($aggregations) {
		$this->init_aggregations($aggregations);
		return $this;
	}

	
	/**
	 * Selecciona sólo una función de agregación
	 * 
	 * @param array Array con las agregaciones de la siguiente forma, <función de agregación> => [campo1, campo2, ...]
	 * @return mixed Resultado de realizar la agregación
	 * */
	public function select_aggregate($aggregations) {
		$sql = $this->select(null)->aggregate($aggregations)->sql();
		$db = $this->db;
		$results = (array) $db::execute($sql);
		return $results["fields"];
	}

	/* FIN DE LA AGRUPACIÓN */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* SELECT FOR UPDATE */
	
	/**
	 * Marca la consulta para ser ejecutada como un SELECT FOR UPDATE.
	 * @return object Referencia a $this para poder encadenarlo con otros métodos.
	 * 	 */
	public function for_update(){
		$this->for_update = true;
		return $this;
	}
	
	/* FIN DEL SELECT FOR UPDATE */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Transacción */
	
	/**
	 * Método que envuelve la operación en una transacción.
	 * @return object Referencia a $this para poder encadenarlo con otros métodos.
	 * 	 */
	public function trans(){
		$this->is_transaction = true;
		return $this;
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/** MÉTODOS QUE NO DEVUELVEN THIS * */
	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Cuenta de elementos */
	
	/**
	 * Cuenta el número de elementos que hay en el Query,
	 * 
	 * @return integer Número de elementos de esta consulta.
	 * 	 */
	public function count(){
		/*// Alias que uso para poder obtener la cuenta del COUNT(*)
		$aggretate_alias = "count_all";
		// Selección con el agregado
		$result = $this->select_aggregate([new \lulo\query\Aggregation("count", $aggretate_alias)]);
		// Devolvemos la cuenta de elementos
		return $result[$aggretate_alias];
		*/
		
		// Si no existe el recordset lo creamos.
		$this->initRecordSet();
		return $this->recordSetSize;
	}	
	
	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Comprobación de si el modelo puede editarse o no */

	/**
	 * Indica si el modelo es escribible o no.
	 * 
	 * Esto es, comprueba si el modelo tiene el método dbUpdate, que,
	 * normalmente lo heredará de la clase RWModel.
	 * 
	 * @return boolean Informa si el modelo es escribible o no.
	 * 	 */
	protected function modelIsWritable() {
		// Comprobamos si la clase actual tiene implementado el método dbUpdate
		$model = $this->model;
		return method_exists($model, "dbUpdate");
	}

	
	/**
	 * Indica si el modelo es escribible o no.
	 * 
	 * Esto es, comprueba si el modelo tiene el método dbUpdate, que,
	 * normalmente lo heredará de la clase RWModel y en caso de que no sea
	 * escrible, lanza una excepción.
	 * 
	 * 	 */
	protected function assertModelIsWritable() {
		if (!$this->modelIsWritable()) {
			$model = $this->model;
			throw new \Exception("El modelo {$model} no permite su escritura, sólo su lectura");
		}
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Actualización de elementos */

	/**
	 * Prepara los campos de la actualización para evitar inyecciones SQL.
	 * 
	 * @param array $fieldsToUpdate Campos a actualizar.
	 * @return array Array con pares <campo de la tabla> => <nuevo valor> que se usará en la actualización.
	 * */
	protected function cleanFieldsToUpdate($fieldsToUpdate) {
		$model = $this->model;
		$db = $this->db;
		
		// Hash que contendrá los pares "campo" => <nuevo valor>
		$_fieldsToUpdate = [];
		foreach ($fieldsToUpdate as $field => $value) {
			
			// Si el campo que se ha pasado es una referencia a una columna
			// (en forma de TupleValue)
			if (is_object($value) and get_class($value)=="lulo\query\TupleValue") {
				$column = $value->f();
				// Si tenemos que dejarlo tal cual, así se queda
				if($value->isRaw()){
					$value = $column;
				}
				// Comprobamos si el modelo tiene esa columna como atributo
				elseif($model::metaHasAttribute($column)){
					$escapedColumn = substr($db::qstr($column), 1, -1);
					$value = "main_table.{$escapedColumn}";
				}
				// Si el modelo no tiene $column como atributo, damos un error
				else{
					throw new \Exception("'{$column}' no es un atributo del modelo {$model}");
				}				
			}
			// En caso que no sea una TupleValue, asumimos que el valor que se
			// va a usar para actualizar es una cadena y por tanto sólo
			// debemos escaparla
			else {
				$value = $db::qstr($value);
			}
			
			// Añadimos el campo que vamos a actualizar al hash que vamos a
			// aplicar como actualización
			$_fieldsToUpdate[$field] = $value;
		}
		
		// Devolvemos el hash con la actualización
		return $_fieldsToUpdate;
	}

	
	/**
	 * Obtiene el SQL de la consulta de eliminación de objetos según el filtro de la consulta.
	 * 
	 * @return string Código SQL de la consulta de eliminación.
	 * */
	public function sql_for_update($fieldsToUpdate) {
		// Comprobamos que los campos son correctos y los escapamos
		$cleanedFieldsToUpdate = $this->cleanFieldsToUpdate($fieldsToUpdate);

		// Construimos la sentencia SQL de la actualización
		$sqlT = TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/update/query.twig.sql");
		$sql = $sqlT->render(["query" => $this, "fieldsToUpdate" => $cleanedFieldsToUpdate]);

		// Obtención del código SQL para la actualización.
		return $sql;
	}

	
	/**
	 * Actualiza todos los elementos del LuloQuery.
	 * @param array $fieldsToUpdate Array con los datos de actualización.
	 * */
	public function update($fieldsToUpdate) {
		// Lo primero es comprobar que el modelo es escribible.
		$this->assertModelIsWritable();

		// Base de datos sobre la que se ejecuta la consulta de UPDATE
		$db = $this->db;

		// Código SQL para la actualización
		$sql = $this->sql_for_update($fieldsToUpdate);

		// Ejecución del SQL de actualización
		return $db::execute($sql);
	}

	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Eliminación de elementos */

	/**
	 * Obtiene el SQL de la consulta de eliminación de objetos según el filtro de la consulta.
	 * 
	 * @return string Código SQL de la consulta de eliminación.
	 * */
	public function sql_for_delete() {
		// Lo primero es comprobar que el modelo es escribible.
		$this->assertModelIsWritable();

		// Modelo que se va a consultar
		$model = $this->model;

		// Lo primero es añadir los modelos relacionados hijos que tienen
		// eliminación en cascada
		foreach ($model::metaGetRelationships() as $relationshipName => $relationship) {
			// Se han de añadir aquellos modelos que son hijos de este modelo
			// y que han sido marcados como que se han de eliminar en el caso
			// de eliminación del modelo padre
			// También tendremos que eliminar las tuplas de los tablas nexo
			// para el caso de que haya eliminación en cascada
			if (
				($relationship["type"] == "OneToMany" or $relationship["type"] == "ManyToMany") and
				isset($relationship["on_master_deletion"]) and
				$relationship["on_master_deletion"] == "delete"
			) {
				$this->addRelatedModel($relationshipName);
			}
		}

		// Generamos el SQL de la consulta
		$sqlT = TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/delete/query.twig.sql");
		$sql = $sqlT->render(["query" => $this]);

		// Devolvemos el código SQL de la consulta de eliminación
		return $sql;
	}

	
	/**
	 * Elimina los objetos según el filtro de la consulta.
	 * */
	public function delete() {

		// Base de datos sobre la que se van a hacer las consultas
		$db = $this->db;

		// Obtención del código SQL de eliminación
		$sql = $this->sql_for_delete();

		// Ejecutamos el SQL de eliminación
		return $db::execute($sql);
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Generación SQL de la consulta */

	/**
	 * Obtener código SQL de la consulta de selección.
	 * 	 */
	public function sql() {
		// Si tiene agregationes, usamos un SQL 
		$sqlT = TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/select/query.twig.sql");
		return $sqlT->render(["query" => $this]);
	}

	
	/**
	 * Obtener código SQL con formato bonito de la consulta de selección.
	 * 	 */
	public function prettySql() {
		$sql = $this->sql();
		$sql = preg_replace("#([\w_\(\)]+)([ \t]+)([\w_\(\)]+)#s", "$1 $3", $sql);
		$sql = str_replace("    ", "  ", $sql);
		return $sql;
	}

        
	/**
	 * Tamaño de los resultados obtenidos.
	 * @return integer Tamaño del QueryResult obtenido.
	 **/
  public function size(){
		return $this->recordSetSize;
	}
        
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Métodos de RecordSet */

	/**
	 * Obtiene la colección de objetos del tipo indicado según la
	 * condición que haya seleccionado el usuario.
	 * */
	protected function initRecordSet() {
		$db = $this->db;
		// Comprueba si la colección ha sido iniciada, si ya estaba
		// no hace nada
		if (!is_null($this->recordSet)) {
			return false;
		}
		// Si no estaba iniciada, la inicia y crea los objetos
		$sql = $this->sql();
		$this->recordSet = $db::execute($sql);
		$this->recordSetSize = $this->recordSet->RecordCount();
	}

	
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Métodos de colección */

	/**
	 * Obtiene la colección completa.
	 * */
	public function collection() {
        	$db = $this->db;
		// Inicia la colección y crea los objetos
		$sql = $this->sql();
		$rows = $db::execute($sql);
		$model = $this->model;
		return new \Collection($model::arrayFactoryFromRows($rows));
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Interfaz de ArrayAccess */

	/**
	 * Indica si el elemento en la posición $offset existe.
	 * @param integer $offset Índice del elemento a comprobar.
	 * @return boolean true si el elemento existe, false en otro caso.
	 * */
	public function offsetExists($offset) {
		// Lo primero es comprobar el offset es legal
		if (!is_numeric($offset) or $offset < 0) {
			return false;
		}

		// Inicializamos el RecordSet si es necesario
		$this->initRecordSet();

		// No lo tenemos cargado en los resultados, obtenemos
		// el tamaño del RecordSet y comprobamos si el offset es
		// menor que éste
		$querySize = $this->recordSetSize;
		return ( $offset < $querySize );
	}

	
	/**
	 * Obtiene el elemento en la posición $offset.
	 * @param integer $offset Índice del elemento a obtener.
	 * @return object Objeto modelo en la posición $offset.
	 * */
	public function offsetGet($offset) {
		// Lo primero es comprobar el offset es legal
		if (!is_numeric($offset) or $offset < 0) {
			return false;
		}
		// Inicializamos el RecordSet si es necesario
		$this->initRecordSet();

		// No lo tenemos cargado en los resultados, obtenemos
		// el tamaño del RecordSet y comprobamos si el offset es
		// menor que éste. Si lo es, añadimos el elemento a results y lo
		// devolvemos
		$querySize = $this->recordSetSize;
		if ($offset >= $querySize) {
			// Si hemos pasado un offset incorrecto, devolvemos false.
			throw new \OutOfRangeException("La posición {$offset} es mayor que el tamaño del LuloQuery");
		}

		// Si la posición actual es la deseada, la fila que se usará para
		// construir el objeto es la actual
		if ($this->currentIndex == $offset) {
			$newRow = $this->recordSet->fields;
		}
		// Si la posición actual es otra distinta, nos movemos a la posición
		// y obtenemos la tupla en la posición actual
		else {
			// Avanzamos el puntero a la posición indicada
			$this->recordSet->Move($offset);
			// Obtenemos la fila de la posición actual
			$newRow = $this->recordSet->fields;
			$this->currentIndex = $offset + 1;
		}
		// Construimos el objeto a partir de la tupla
		$model = $this->model;
		$newObject = $model::factoryFromRow($newRow);

		// Devolvemos el nuevo objeto
		return $newObject;
	}

	
	public function offsetSet($offset, $value) {
		throw new \BadFunctionCallException("Los LuloQueries son de sólo lectura. Esta operación no se permite.");
	}

	
	public function offsetUnset($offset) {
		throw new \BadFunctionCallException("Los LuloQueries son de sólo lectura. Esta operación no se permite.");
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Interfaz de Iterator */

	public function current() {
		// Devuelve el elemento actual
		return $this->offsetGet($this->currentIndex);
	}

	
	public function key() {
		// Devuelve la posición actual
		return $this->currentIndex;
	}

	
	public function next() {
		// Avanza de posición
		$this->recordSet->MoveNext();
		$this->currentIndex++;
	}

	
	public function rewind() {
		// Si no existe el recordset lo creamos.
		$this->initRecordSet();
		// Pone el contador otra vez en la posición inicial
		$this->recordSet->MoveFirst();
		// El índice actual vuelve a ser el primero
		$this->currentIndex = 0;
	}

	public function valid() {
		// Inicializamos el RecordSet si es necesario
		$this->initRecordSet();
		// Informa si el puntero es válido
		return ((!$this->recordSet->EOF) and $this->currentIndex < $this->recordSet->RecordCount() );
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Métodos de mágicos */

	/**
	 * Método mágico que se llama cuando se llama al objeto como si se
	 * tratase de una función.
	 * 
	 * Devuelve un objeto del LuloQuery.
	 * 
	 * @param int $index Índice del objeto a devolver.
	 * @return object Objeto en la posición $index.
	 * 
	 * */
	public function __invoke($index) {
		return $this->offsetGet($index);
	}

}

?>
