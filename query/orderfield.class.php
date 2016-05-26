<?php

namespace lulo\query;

/**
 * Field used in main table (or one of its related tables) when ordering
 * the SELECT query.
 * */
class OrderField{

	/** Model that contains the field */
	public $model;
	
	/** Table of that model */
	public $table;
	
	/** Alias of the table used in the statement  */
	public $tableAlias;
	
	/** Field that will define order of query */
	public $field;
	
	/** Type of ordering ("asc" [ascending] or "desc" [descending]) */
	public $orderValue;

	/**
	 * Creates a new field ordering.
	 * 
	 * @param object $luloquery query which will have this ordering applied.
	 * @param string $model Main model that will be the query destination.
	 * */
	public function __construct($luloquery, $model, $field, $orderValue){
		
		$this->luloquery = $luloquery;
		
		// Ordering must be ASC or DESC
		if(!(strtolower($orderValue)=="asc" or strtolower($orderValue)=="desc")){
			throw new \Exception("Incorrect ordering value for field {$field}. It has {$orderValue} and must be 'asc' o 'desc'");
		}
		
		// Field can have a reference to other extern table. That implies
		// making a JOIN in our query, so we will need to add the relationship
		// to the model (if it was not already there) in the query.
		$matches = [];
		if(strpos($field, "::")!==false and preg_match("#^(.+)::(.+)$#", $field, $matches)>0){
			$relationshipName = $matches[1];
			if($model::metaHasRelationship($relationshipName)){
				// Add of the relationship to the query object
				$this->luloquery->addRelatedModel($relationshipName, $relatedModel=null);
				$this->model = $model;
				$this->table = $model::getTableName();
				$this->tableAlias = $relationshipName;
				$this->field = $matches[2];
				$this->orderValue = strtoupper($orderValue);
			}else{
				throw new \Exception("Model {$model} does not have relationship {$relationshipName}");
			}
		}
		
		// If field belongs to the model, we only have to include the order
		elseif($model::metaHasAttribute($field)){
			$this->model = $model;
			$this->table = $model::getTableName();
			$this->tableAlias = "main_table";
			$this->field = $field;
			$this->orderValue = strtoupper($orderValue);
		}
		else{
			throw new \Exception("Model {$model} does not have field {$field}");
		}
		

		
		
	}
	
}
?>
