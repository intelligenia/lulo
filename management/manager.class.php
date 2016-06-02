<?php

namespace lulo\management;

/**
 * Manager class 
 * Executes management actions.
 */
class Manager {
	
	/**
	 * Create tables needed for model $model.
	 * 
	 * Create all tables needed for the model, including main table and nexii
	 * tables used in many-to-many relationships.
	 * 
	 * @param string $model Model whose tables will be created.
	 * 	 */
	public static function createTables($model){
		$creator = new \lulo\management\DBCreator($model);
		return $creator->execute();
	}


	/**
	 * Drop tables of model $model.
	 * 
	 * Drop all tables needed for the model, including main table and nexii
	 * tables used in many-to-many relationships.
	 * 
	 * @param string $model Model whose tables will be dropped.
	 * 	 */	
	public static function dropTables($model){
		$dropper = new \lulo\management\DBDropper($model);
		return $dropper->execute();
	}
	
}
