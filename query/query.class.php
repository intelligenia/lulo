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

	/** SQL templates parent path */
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

	/** Related models needed for this query */
	public $related_models = [];

	/** Related tables needed for this query */
	public $related_tables = [];
	
	/** Relationships needed for this query */
	public $relationships = [];

	/** Aggregations for this query */
	public $aggregations = [];

	/** Has GROUP BY? */
	public $has_group_by = false;

	/**
	 * Should this query be wrapped in a transaction?
	 */
	public $is_transaction = false;
	
	/**
	 * Each one of the condition group. Is a disjunction of AND-conditions.
	 * A list of objects ConditionConjunction.
	 */
	public $filters = [];

	/** Order of the returned objects */
	public $order = null;

	/** Limit of returned results */
	public $limit = null;
	
	/** Is it a SELECT FOR UPDATE statement? */
	public $for_update = false;

	/**
	 * Current position. Used in operations of Iterator interface.
	 * */
	protected $currentIndex = 0;

	/** Database cursor in the form of RecordSet for this query */
	protected $recordSet = null;

	/** RecordSet size */
	protected $recordSetSize = null;

	/**
	 * Return the name of the table of the model passed as paremeter or the
	 * main modell if null.
	 * @param string $model Model to get its table name.
	 * @return string table name of $model or if $model is null, $this->model
	 * 	 */
	public function getTable($model=null) {
		if (is_null($model)) {
			$model = $this->model;
		}
		return $model::getTableName();
	}

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Private API */

	/**
	 * Add a model to the list of models neede to this query.
	 * Called from ConditionConjunction and Condition
	 * @param string $relationshipName Relationship whose model we want to add.
	 * */
	public function addRelatedModel($relationshipName) {
		$model = $this->model;
		$relationship = $model::metaGetRelationship($relationshipName);

		// Related model
		$relatedModel = $relationship["model"];

		// Adding related model to related model list
		$this->relatedModels[] = $relatedModel;
		
		// Adding related model table to related table list
		$this->relatedTables[] = $relatedModel::getTableName();
		
		// Adding relationship $relationshipName of $relatedModel
		// to the relationship list
		$this->relationships[$relationshipName] = [
			"model" => $relatedModel,
			"table" => $relatedModel::getTableName(),
			"name" => $relationshipName,
			"attributes" => $relationship
		];

		$this->relationships[$relationshipName]["attributes"]["table"] = $relatedModel::getTableName();
	}

	
	/**
	 * Filter or exclusion.
	 * @param bool $isPositive Is the filter positive? A positive filter is an including filter. A negative filter is a excluding filter.
	 * @param array $parameters Filter parameters.
	 * @return object Reference to current object (this).
	 * */
	protected function _f($isPositive, $parameters) {
		// If there is no paremeters, end returning reference to this
		if (count($parameters) == 0) {
			return $this;
		}

		// Is a positive (including) or negative (excluding) filter
		if ($isPositive) {
			$type = "positive";
		} else {
			$type = "negative";
		}

		// Generation of condition groups
		$conditionGroups = [];
		foreach ($parameters as $conditionGroup) {
			$conditionGroups[] = new \lulo\query\ConditionConjunction($this, $conditionGroup);
		}
		// Adding a new filter specifying if the filter is positive or negative
		$this->filters[] = ["type" => $type, "conditionGroups" => $conditionGroups];
		return $this;
	}
	
	/* END of Private API */
	/*	 * *************************************************************** */
	/*	 * *************************************************************** */

	/*	 * *************************************************************** */
	/*	 * *************************************************************** */
	/* Public API */

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
		$this->model_table = $model::getTableName();
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
		}catch(\OutOfRangeException $e){
			throw new \OutOfRangeException("Error en get. No hay un objeto que concuerde con la selección");
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
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/update/query.twig.sql");
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
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/delete/query.twig.sql");
		$sql = $sqlT->render(["query" => $this]);

		// Devolvemos el código SQL de la consulta de eliminación
		return $sql;
	}

	
	/**
	 * Delete the objects that comply the filter of this query.
	 * */
	public function delete() {

		// Database
		$db = $this->db;

		// SQL code for deletion
		$sql = $this->sql_for_delete();

		// Deletion execution
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
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/select/query.twig.sql");
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
	 * Get the size of the query, that is, the number of objects of this query.
	 * @return integer Size of the query.
	 **/
  public function size(){
		return $this->recordSetSize;
	}
        
	/* RecordSet methods */

	/**
	 * Init the recordset getting
	 * */
	protected function initRecordSet() {
		$db = $this->db;
		// If the RecordSet has been initialized, don't do anything
		if (!is_null($this->recordSet)) {
			return false;
		}
		// Otherwise, execute the stored SQL and get the size of the RecordSet
		$sql = $this->sql();
		$this->recordSet = $db::execute($sql);
		$this->recordSetSize = $this->recordSet->RecordCount();
	}

	/* Collection methods */

	/**
	 * Get a collection with all the objects of this query.
	 * @return object Collection that contains all the object of this query.
	 * */
	public function collection() {
       	$db = $this->db;
		$sql = $this->sql();
		$rows = $db::execute($sql);
		$model = $this->model;
		return new \Collection($model::arrayFactoryFromRows($rows));
	}

	/* ArrayAccess interface */

	/**
	 * Does the position $offset exists in the query?
	 * @param integer $offset Position to test.
	 * @return boolean true if there is an object in that position, false otherwise.
	 * */
	public function offsetExists($offset) {
		// Is the offset legal?
		if (!is_numeric($offset) or $offset < 0) {
			return false;
		}

		// Init RecordSet if needed
		$this->initRecordSet();

		// Return if the offset is less than the size of the query
		return ( $offset < $this->recordSetSize );
	}

	
	/**
	 * Get element at position $offset.
	 * @param integer $offset Index of the element we want to get.
	 * @return object Object in position $offset.
	 * */
	public function offsetGet($offset) {
		// Is the offset legal?
		if (!is_numeric($offset) or $offset < 0) {
			return false;
		}
		// Init RecordSet if needed
		$this->initRecordSet();

		// If position is greater than the recorset throw exception
		// informing that to developer
		if ($offset >= $this->recordSetSize) {
			throw new \OutOfRangeException("{$offset} position is greater than the size of this Query");
		}

		// If $offset is current position, get current position row
		if ($this->currentIndex == $offset) {
			$newRow = $this->recordSet->fields;
		}
		// Otherwise, move recordset to offset and get new position row
		else {
			$this->recordSet->Move($offset);
			$newRow = $this->recordSet->fields;
			$this->currentIndex = $offset + 1;
		}
		// Create the object from the tuple $newRow
		$model = $this->model;
		$newObject = $model::factoryFromRow($newRow);

		// Return that object
		return $newObject;
	}

	
	public function offsetSet($offset, $value) {
		throw new \BadFunctionCallException("Queries are read-only. Operation not allowed.");
	}

	
	public function offsetUnset($offset) {
		throw new \BadFunctionCallException("Queries are read-only. Operation not allowed.");
	}

	/* Iterator interface */

	/**
	 * Return current element.
	 * @return object Element at current position.
	 * 	 */
	public function current() {
		return $this->offsetGet($this->currentIndex);
	}

	
	/**
	 * Return current position.
	 * @return int Current position.
	 * 	 */	
	public function key() {
		// Devuelve la posición actual
		return $this->currentIndex;
	}

	
	/**
	 * Move foward.
	 * 	 */	
	public function next() {
		$this->recordSet->MoveNext();
		$this->currentIndex++;
	}

	
	/**
	 * Move to the first position.
	 * 	 */	
	public function rewind() {
		// Init the recordset if needed.
		$this->initRecordSet();
		// Move to first element
		$this->recordSet->MoveFirst();
		// Current index is the first index
		$this->currentIndex = 0;
	}

	
	/**
	 * Is current position valid?
	 * @return boolean true if current position exists in Query, false otherwise.
	 * 	 */	
	public function valid() {
		// Init the recordset if needed.
		$this->initRecordSet();
		// Is current position valid?
		return ((!$this->recordSet->EOF) and $this->currentIndex < $this->recordSet->RecordCount() );
	}

	/* Magic methods */

	/**
	 * Magic method that is called when the Query object is treated like
	 * a function
	 * 
	 * Return the object at the $index position.
	 * 
	 * @param int $index Index of the object that will be returned.
	 * @return object Object at position $index in the Query.
	 * 
	 * */
	public function __invoke($index) {
		return $this->offsetGet($index);
	}

}

?>
