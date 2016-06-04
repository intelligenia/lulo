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
	
	/** Table alias */
	public $table_alias;

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
	
	/**
	 * Raw filter. That is a string with a condition in plain SQL.
	 * 	 */
	public $raw_filter = null;

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
	
	/* Public API */

	/**
	 * Construct a Query for a model
	 * @param string $model Model that will be queried.
	 * */
	public function __construct($model) {
		// Using the same database connector than the model uses
		$this->db = $model::DB;
		// Model to query
		$this->model = $model;
		// Model table
		$this->model_table = $model::getTableName();
		$this->table_alias = $model::TABLE_ALIAS;
		
		$this->filters = [];
		$this->relationships = [];
		$this->order = null;
		$this->limit = null;
		
		// All non-blob model fields are selected
		$selectableFields = $model::metaGetSelectableAttributes();
		$this->select($selectableFields);
	}

	
	/**
	 * Allo selecting only some fields.
	 * 
	 * @param array $fields Array with the names of the fields to select.
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	protected function select($fields) {
		$this->selected_fields = $fields;
		return $this;
	}

	
	/**
	 * FILTER operation. Filter the results based on conditions that are included
	 * as paremeters.
	 * 
	 * Conditions are in Disjunctive Normal Form. That is, parameters for this
	 * operation are OR conditions that will be joined by AND operators.
	 * 
	 * Examples:
	 * - filter(["A"=>1, "B"=>2], ["B"=>3, "C"=>4]):
	 *     (A=1 AND B=2) OR (B=3 AND C=4)
	 * - filter(["A"=>1], ["B"=>3, "C"=>4])->filter(["X"=>2, "Y"=>3], ["W"=>5]):
	 *     ( (A=1) OR (B=3 AND C=4) ) AND ( (X=2 AND Y=3) OR W=5 )
	 * 
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	public function filter() {
		// If there is no arguments, return $this
		$numArgs = func_num_args();
		if($numArgs == 0){
			return $this;
		}
		
		// If only a lone array is passed use it as parameters
		$firstArgument = func_get_arg(0);
		if ($numArgs == 1 and isset($firstArgument[0]) and is_array($firstArgument[0])) {
			return $this->_f(true, $firstArgument);
		}

		// General case of filtering: arguments are conditions
		return $this->_f(true, func_get_args());
	}

	
	/**
	 * EXCLUDE operations.
	 * 
	 * Conditions are in Disjunctive Normal Form. That is, parameters for this
	 * operation are OR conditions that will be joined by AND operators.
	 * 
	 * Examples:
	 * - filter(["A"=>1, "B"=>2], ["B"=>3, "C"=>4])->exclude(["X"=>5, "Y"=>6]):
	 *     ((A=1 AND B=2) OR (B=3 AND C=4)) AND NOT(X=5 AND Y=6)
	 *
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	public function exclude() {
		// If there is no arguments, return $this
		$numArgs = func_num_args();
		if($numArgs == 0){ return $this; }
		
		// If only a lone array is passed use it as parameters
		$firstArgument = func_get_arg(0);
		if ($numArgs == 1 and isset($firstArgument[0]) and is_array($firstArgument[0])) {
			return $this->_f(false, $firstArgument);
		}

		// General case of filtering: arguments are conditions
		return $this->_f(false, func_get_args());
	}

	
	/**
	 * Apply a limit to this query.
	 * @param int $start Start position.
	 * @param int $size Number of elements to get. If null, $start is the size.
	 * @return object Reference to $this to enable chaining of methods.
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
	 * Get the first element of the Query.
	 * 
	 * If there is no elements, throw an exception
	 * 
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	public function get() {
		$numArgs = func_num_args();
		$results = null;
		if($numArgs == 0){
			$results = $this->limit(1);
		}else{
			$results = $this->filter(func_get_args())->limit(1);
		}
		
		// Return the first element of the query, if there is no elements
		// an OutOfRangeException exception is raised
		try{
			return $results[0];
		}catch(\OutOfRangeException $e){
			throw new \OutOfRangeException("Unable to get object in this query. There are no objects that comply with your filter.");
		}
	}

	
	/**
	 * Should DISTINCT be applied to the query?
	 * @param bool $distinct If true, a SELECT DISTINCT will be executed.
	 * If false, a SELECT will be executed.
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	public function distinct($distinct = true) {
		$this->distinct = $distinct;
	}

	/* ORDER */

	/**
	 * Apply an order to the query
	 * @param array $order Order of the form: ["<field_1>"=>"ASC"|"DESC", ..., "<field_N>"=>"ASC"|"DESC"]
	 * 
	 * Examples:
	 * - ["users::last_name"=>"ASC", "users::first_name"=>"ASC", "id"=>"DESC"]
	 * - ["creation_datetime"=>"ASC", "users::last_name"=>"ASC"]
	 * 
	 * @return object Reference to $this to enable chaining of methods.
	 * */
	public function order($order) {
		$model = $this->model;
		// For each order field, it is added as an OrderField object
		foreach ($order as $field => $fieldOrder) {
			$this->order[] = new \lulo\query\OrderField($this, $model, $field, $fieldOrder);
		}
		return $this;
	}
	
	/* Raw filter SQL code */
	
	public function raw_filter($sql_condition){
		$this->raw_filter = $sql_condition;
		return $this;
	}
	
	/* Aggregation */

	/**
	 * 
	 * Execute an aggregation for some fields.
	 * 
	 * If you want to get a number for this table, execute select_aggregate.
	 * 
	 * @param array Array with aggregations with this form, <aggregate function> => [field1, field2, ...]
	 * 	 */
	protected function init_aggregations($aggregations) {
		$model = $this->model;

		// Deletion of former aggregations
		$this->aggregations = [];
		$this->has_aggregation_group_by = false;
		
		// Check if aggregate functions and fields are right
		$hasFieldsInAnyAggregation = false;
		foreach ($aggregations as $aggregation) {
			if (!is_null($aggregation->fields) and count($aggregation->fields) > 0) {
				$hasFieldsInAnyAggregation = true;
			}
			// Model that will be used by the aggregation
			$aggregation->init($model);
		}

		$this->aggregations = array_merge($this->aggregations, $aggregations);
		$this->has_aggregation_group_by = $hasFieldsInAnyAggregation;
	}

	
	/**
	 * Execute an aggregation for some fields.
	 * 
	 * If you want to get a number for this table, execute select_aggregate.
	 * 
	 * @param array Array with aggregations with this form, <aggregate function> => [field1, field2, ...]
	 * @return object Reference to $this to enable chaining of methods.
	 * 	 */
	public function aggregate($aggregations) {
		$this->init_aggregations($aggregations);
		return $this;
	}

	
	/**
	 * Select a lone aggregation function.
	 * 
	 * @param array Array with aggregations with this form, <aggregate function> => [field1, field2, ...]
	 * @return mixed Results of applying the aggregation.
	 * */
	public function select_aggregate($aggregations) {
		$sql = $this->select(null)->aggregate($aggregations)->sql();
		$db = $this->db;
		$results = (array) $db::execute($sql);
		return $results["fields"];
	}

	/* SELECT FOR UPDATE */
	
	/**
	 * Mark this query to be executed with SELECT FOR UPDATE.
	 * @return object Reference to $this to enable chaining of methods.
	 * 	 */
	public function for_update(){
		$this->for_update = true;
		return $this;
	}
	
	/* Transaction */
	
	/**
	 * Wrap operation in a transaction.
	 * @return object Reference to $this to enable chaining of methods.
	 * 	 */
	public function trans(){
		$this->is_transaction = true;
		return $this;
	}

	/* Count and existence of elements */
	
	/**
	 * Count number of elements in this query.
	 * 
	 * @return integer Number of elements of this query.
	 * 	 */
	public function count(){
		$this->initRecordSet();
		return $this->recordSetSize;
	}	
	
	
	/**
	 * Check if exists any element in this query.
	 * 
	 * @return boolean true if exists any element in this query, false otherwise.
	 * 	 */
	public function exists(){
		return ($this->count() > 0);
	}	
	
	
	/* Check if model can be written */

	/**
	 * Is the model writable?
	 * 
	 * @return boolean true if model is writable, false otherwise.
	 * 	 */
	protected function modelIsWritable() {
		// Check if model has method dbUpdate
		$model = $this->model;
		return method_exists($model, "dbUpdate");
	}

	
	/**
	 * Is the model writable?
	 * @return boolean true if model is writable, false otherwise.
	 * 	 */
	protected function assertModelIsWritable() {
		if (!$this->modelIsWritable()) {
			$model = $this->model;
			throw new \Exception("Model {$model} does not allow writing only reading");
		}
	}

	/* UPDATE */

	/**
	 * Clean fields for UPDATE.
	 * 
	 * The aim is avoid SQL injections.
	 * 
	 * @param array $fieldsToUpdate Fields of the update.
	 * @return array Array of pairs <table attribute> => <new value> that will be used to update data.
	 * */
	protected function cleanFieldsToUpdate($fieldsToUpdate) {
		$model = $this->model;
		$db = $this->db;
		
		// Array that will contain "attribute" => <new value>
		$_fieldsToUpdate = [];
		foreach ($fieldsToUpdate as $field => $value) {
			
			// If it is a TupleValue and not a constant or variable, it
			// references to the current value of the tuple.
			if (is_object($value) and get_class($value)=="lulo\query\TupleValue") {
				$column = $value->f();
				if($value->isRaw()){
					$value = $column;
				}
				// Check model has $column attribute
				elseif($model::metaHasAttribute($column)){
					$escapedColumn = substr($db::qstr($column), 1, -1);
					$value = "{$this->table_alias}.{$escapedColumn}";
				}
				// Otherwise, throw exception
				else{
					throw new \Exception("'{$column}' is not an attribute of {$model}");
				}				
			}
			// If what we pass is a variable and not a reference to a field
			// it has to be escaped
			else {
				$value = $db::qstr($value);
			}
			
			// Add the field to the fields to update with its new value
			$_fieldsToUpdate[$field] = $value;
		}
		
		// Updating array
		return $_fieldsToUpdate;
	}

	
	/**
	 * Get UPDATE statement SQL code.
	 * 
	 * @return string UPDATE statement SQL code.
	 * */
	public function sql_for_update($fieldsToUpdate) {
		// Checking and escaping of fields
		$cleanedFieldsToUpdate = $this->cleanFieldsToUpdate($fieldsToUpdate);

		// UPDATE statement
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/update/query.twig.sql");
		$sql = $sqlT->render(["query" => $this, "fieldsToUpdate" => $cleanedFieldsToUpdate]);

		// Return of UPDATE statement SQL code
		return $sql;
	}

	
	/**
	 * Update statement
	 * @param array $fieldsToUpdate Array that contains pairs <attribute>=><newValue>
	 * to execute a update in the table of this model.
	 * */
	public function update($fieldsToUpdate) {
		// Check if model is writable
		$this->assertModelIsWritable();

		// Database
		$db = $this->db;

		// UPDATE statement code
		$sql = $this->sql_for_update($fieldsToUpdate);

		// Return UPDATE SQL code
		return $db::execute($sql);
	}

	/* Delete */

	/**
	 * Get SQL code for DELETE statement.
	 * 
	 * @return string SQL code for DELETE statement.
	 * */
	public function sql_for_delete() {
		// Check if model is writable
		$this->assertModelIsWritable();

		// Destination model
		$model = $this->model;

		// Cascade deletion calculation for model $model
		foreach ($model::metaGetRelationships() as $relationshipName => $relationship) {
			// Nexii tuples and children tuple deletion
			if (
				($relationship["type"] == "OneToMany" or $relationship["type"] == "ManyToMany") and
				isset($relationship["on_master_deletion"]) and
				$relationship["on_master_deletion"] == "delete"
			) {
				$this->addRelatedModel($relationshipName);
			}
		}

		// SQL code generation
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/delete/query.twig.sql");
		$sql = $sqlT->render(["query" => $this]);

		// Return DELETE statement SQL code
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

	/* SQL code or this query */

	/**
	 * Get SQL for SELECT statement.
	 * @return string SELECT statement for this query.
	 * 	 */
	public function sql() {
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/select/query.twig.sql");
		return $sqlT->render(["query" => $this]);
	}

	
	/**
	 * Get pretty SQL code for SELECT statement.
	 * @return string SELECT statement for this query.
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
