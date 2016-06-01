<?php

namespace lulo\models\traits;

/**
 * Query operations in a LuloModel.
 */
trait Query {
	
	/**
	 * Creation of a Query object for this model's objects.
	 * @return object Query with model objects.
	 * */
	public static function objects(){
		// Query creation for this model
		$query = new \lulo\query\Query(static::CLASS_NAME);
		// If there is an implicit condition, set it as default condition
		$current_class = get_class();
		if(method_exists($current_class, "implicitBaseCondition")){
			$implicitBaseCondition = static::implicitBaseCondition();
			if(is_array($implicitBaseCondition) and count($implicitBaseCondition)>0){
				$query = $query->filter($implicitBaseCondition);
			}
		}
		// Returns a Query to chain filters and other actions
		return $query;
	}
	
}

