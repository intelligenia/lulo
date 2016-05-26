<?php

namespace lulo\query;

/**
 * Contains each one of the conditions for one field specified by the user.
 * 
 * That is, for example, each one of these:
 * - name="James"
 * - height >= 180
 * - surname like '%-Mesa'
 * 
 *  */
class Condition{
	
	/** Condition group this condition belongs to */
	protected $conditionConjunction;
	
	/** Model condition field belongs tod */
	protected $model;
	
	/** Model table */
	public $table;
	
	/** Table alias */
	public $table_alias;
	
	/** Field name */
	protected $field;
	
	/** Operation to apply to field $field */
	protected $operator;
	
	/** Value which this field compare to */
	protected $value;

	/** Character used to escape in LIKE operator */
	const LIKE_ESCAPE_CHARACTER = "|";
	
	/**************************************************************************/
	
	/**
	 * Condition constructor.
	 * 
	 * @param object $conditionConjunction Parent condition conjuntion.
	 * @param string $field Field to apply condition.
	 * @param string $value Value to apply operation.
	 */
	public function __construct($conditionConjunction, $field, $value) {
		$this->conditionConjunction = $conditionConjunction;
		$model = $conditionConjunction->luloquery->model;
		$matches = [];
		
		// if field is an extern field
		if(strpos($field, "::")!==false and preg_match("#^(.+)::(.+)$#", $field, $matches)>0){
			// Getting the related model and the relation
			$relationshipName = $matches[1];
			$relationship = $model::metaGetRelationship($relationshipName);
			$relatedModel = $relationship["model"];
			$this->model = $relatedModel;
			$this->table = $relatedModel::TABLE_NAME;
			$this->table_alias = $relationshipName;
			$this->field = $matches[2];
			// Adding to relations that will be used in the query
			$conditionConjunction->luloquery->addRelatedModel($relationshipName);
			$this->operator = "=";
		
		// Local field
		}else{
			$this->model = $model;
			$this->table = $model::TABLE_NAME;
			$this->table_alias = "main_table";
			$this->field = $field;
			$this->operator = "=";
		}
		$this->value = $value;

		// If field has __ as suffix we have a special operator, so
		// we need to adjust field name and operator
		if(strpos($this->field, "__")!==false){
			list($field, $operator) = explode("__", $this->field);
			$this->field = $field;
			$this->operator = $operator;
		}
	}
	
	
	/**
	 * Get condition model.
	 * 
	 * @return string String with model name used in this condition.
	 */
	public function getModel(){
		return $this->model;
	}

	
	/**
	 * Gets the SQL code of the operation.
	 * @return string SQL representation of the operation.
	 */
	protected function getSqlOperator(){
		if(is_null($this->value)){
			return "IS";
		}
		if($this->operator == "contains"){
			return "LIKE";
		}
		if($this->operator == "notcontains"){
			return "NOT LIKE";
		}
		if($this->operator == "startswith"){
			return "LIKE";
		}
		if($this->operator == "endswith"){
			return "LIKE";
		}
		if($this->operator == "in"){
			return "IN";
		}
		if($this->operator == "range"){
			return "BETWEEN";
		}
		if($this->operator == "eq" or $this->operator == "="){
			return "=";
		}
		if($this->operator == "noteq"){
			return "<>";
		}
		if($this->operator == "lt"){
			return "<";
		}
		if($this->operator == "lte"){
			return "<=";
		}
		if($this->operator == "gt"){
			return ">";
		}
		if($this->operator == "gte"){
			return ">=";
		}
		throw new \UnexpectedValueException("Operator {$this->operator} is not recognized");
	}
	
	
	/**
	 * Get the field name.
	 * @return string Field name prepared to be used in the query.
	 */
	protected function getSqlField(){
		return $this->field;
	}
	
	
	/**
	 * Get SQL-escaped value for LIKE operation.
	 * Remember we have defined a escape character in this class.
	 * 
	 * @param $sqlValue Value that will be escaped. Before wrapping it in % symbols.
	 * @return string Value with % and _ escaped.
	 */
	protected static function getSqlValueForLike($sqlValue){
		$e = static::LIKE_ESCAPE_CHARACTER;
		$escapedSqlValue = str_replace(array($e, '_', '%'), array($e.$e, $e.'_', $e.'%'), $sqlValue);
		return $escapedSqlValue;
	}
	
	/**
	 * Make implicit conversions for a field before making the query.
	 * 
	 * @return string $sqlValue Converted value.
	 * */
	protected static function implicitSqlValueConversion($sqlValue){
		// If the value is an object, we try to convert according to its class
		if(is_object($sqlValue)){
			$sqlValueClass = get_class($sqlValue);
			
			// If the value is a DateTime, it is converted
			// to YYYY-MM-DD HH:II:SS format.
			if($sqlValueClass == "DateTime"){
				$sqlValue = $sqlValue->format("Y-m-d H:i:s");
			}
			
			// If it has id attribute, return it
			elseif(isset($sqlValue->id)){
				$sqlValue = $sqlValue->id;
			}
			
			// // If it has a method that returns the primary key
			elseif(method_exists($sqlValue, "getStrPk")){
				$sqlValue = $sqlValue->getStrPk();
			}
		}
		return $sqlValue;
	}
	
	
	/**
	 * Return the SQL representation of the value.
	 * 
	 * @return string Value that will be used in the condition.
	 */
	protected function getSqlValue(){
		// Implicit conversions according to type of value
		$sqlValue = static::implicitSqlValueConversion($this->value);

		////// Value conversions according to operator
		// Conversions for LIKE
		if($this->operator == "contains" or $this->operator == "notcontains" or
			$this->operator == "startswith" or $this->operator == "endswith"){
			$sqlValue = static::getSqlValueForLike($sqlValue);
			
			// Must be wrapped by % if operator is contains
			if($this->operator == "contains" or $this->operator == "notcontains"){
				$sqlValue = \lulo\db\DB::qstr("%{$sqlValue}%");
			}
			// Must have % as suffix if operator is starswith
			elseif($this->operator == "startswith"){
				$sqlValue = \lulo\db\DB::qstr("{$sqlValue}%");
			}
			// Must have % as prefix if operator is endswith
			elseif($this->operator == "endswith"){
				$sqlValue = \lulo\db\DB::qstr("%{$sqlValue}");
			}
			$escapedValue = "{$sqlValue} ESCAPE '".static::LIKE_ESCAPE_CHARACTER."'";
		}
		
		// Conversions for IN
		elseif($this->operator == "in"){
			// Testing if the value is an array
			if(!is_array($this->value)){
				throw new \InvalidArgumentException("Operator IN requires the value to be an array");
			}
			// Creation of a string like IN (item1, item2, ..., itemN)
			$numValues = count($this->value);
			$escapedValue = "(";
			$i=0;
			foreach($this->value as $valueItem){
				$escapedValue .= \lulo\db\DB::qstr(static::implicitSqlValueConversion($valueItem));
				if($i < $numValues-1){
					$escapedValue .= ", ";
				}
				$i++;
			}
			$escapedValue .= ")";
		}
		
		// Conversions for RANGE
		elseif($this->operator == "range"){
			// An range is composed by two values
			if(!is_array($this->value) or count($this->value)!=2){
				throw new \InvalidArgumentException("RANGE operator needs a pair of elements");
			}
			// Interval limits
			$item1 = \lulo\db\DB::qstr(static::implicitSqlValueConversion($this->value[0]));
			$item2 = \lulo\db\DB::qstr(static::implicitSqlValueConversion($this->value[1]));
			$escapedValue = "{$item1} AND {$item2}";
		}
		
		// General case, must be escaped as usual
		else{
			$escapedValue = \lulo\db\DB::qstr($sqlValue);
		}
		
		// Devolvemos el valor
		return $escapedValue;
	}
	
	
	/**
	 * Get SQL code for this condition.
	 * 
	 * @return string String that contains the SQL code for this condition.
	 */
	public function sql(){
		$field = $this->getSqlField();
		$sqlOperator = $this->getSqlOperator();
		$sqlValue = $this->getSqlValue();
		$table = $this->table;
		if(!is_null($this->table_alias)){
			$table = $this->table_alias;
		}
		$conditionStr = "{$table}.{$field} {$sqlOperator} {$sqlValue}";
		return $conditionStr;
	}
	
}

?>
