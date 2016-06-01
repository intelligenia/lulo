<?php

namespace lulo\query;

/**
 * Represents one database aggregation in a query.
 *  */
class Aggregation{
	
	/** Aggregate function name */
	public $functionName;
	
	/** Aggregation result alias */
	public $alias;
	
	/** Fields that have a role in this aggregation */
	public $fields;
	
	/** Main model */
	public $model;
	
	
	/**
	 * Available aggregate functions.
	 * 	 */
	protected static $AGGREGATE_FUNCTION_NAMES = [
		"AVG", "COUNT", "MAX", "MIN", "STD", "STDDEV", "SUM", "VARIANCE"
	];
	
	
	/**
	 * Creates a new aggregation.
	 * 
	 * @param string $functionName Aggregation function name.
	 * @param string $alias Alias for the result of this aggregration.
	 * @param array Field names that will suffer the aggregation. If null, all fields are aggregated.
	 * 	 */
	public function __construct($functionName, $alias, $fields=null) {
		// Is the aggregation function one of the available aggregation functions?
		if(!in_array(strtoupper($functionName), static::$AGGREGATE_FUNCTION_NAMES)){
			throw new \Exception("Aggregation function {$functionName} is not right or is not available at the moment");
		}
		// Fields must be a list of fields or null, if fields are null it will
		// we regarded as all fields.
		if(!is_array($fields) and !is_null($fields)){
			throw new \Exception("No le has pasado campos a la función de agregación {$functionName}");
		}
		
		$this->functionName = $functionName;
		$this->alias = $alias;
		$this->fields = $fields;
	}
	
	
	/**
	 * Aggregation needs to know what model must be aggregated.
	 * 
	 * @param string $model Model name to use.
	 * 	 */
	public function init($model){
		$this->model = $model;
		// Test if aggregated fields belongs to this model
		if(is_array($this->fields)){
			foreach($this->fields as $field){
				if(!$model::metaHasAttribute($field)){
					throw new \Exception("{$field} is not an attribute of model {$model}");	
				}
			}
		}
	}
	
}
