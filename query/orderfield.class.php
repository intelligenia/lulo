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
	public $table_alias;
	
	/** Field that will define order of query */
	public $field;
	
	/** Type of ordering ("asc" [ascending] or "desc" [descending]) */
	public $order_value;

	/**
	 * Creates a new field ordering.
	 * 
	 * @param object $luloquery query which will have this ordering applied.
	 * @param string $model Main model that will be the query destination.
	 * */
	public function __construct($luloquery, $model, $field, $order_value){
		
		$this->luloquery = $luloquery;
		
		// Ordering must be ASC or DESC
		if(!(strtolower($order_value)=="asc" or strtolower($order_value)=="desc")){
			throw new \Exception("Incorrect ordering value for field {$field}. It has {$order_value} and must be 'asc' o 'desc'");
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
				$this->table_alias = $relationshipName;
				$this->field = $matches[2];
				$this->order_value = strtoupper($order_value);
			}else{
				throw new \Exception("Model {$model} does not have relationship {$relationshipName}");
			}
		}
		
		// If field belongs to the model, we only have to include the order
		elseif($model::metaHasAttribute($field)){
			$this->model = $model;
			$this->table = $model::getTableName();
			$this->table_alias = $this->luloquery->table_alias;
			$this->field = $field;
			$this->order_value = strtoupper($order_value);
		}
		else{
			throw new \Exception("Model {$model} does not have field {$field}");
		}
		

		
		
	}
	
}
?>
