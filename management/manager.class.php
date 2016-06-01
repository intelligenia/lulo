<?php

namespace lulo\management;

use lulo\query\TwigTemplate as TwigTemplate;

/**
 * Manager class 
 *
 */
class Manager {
	
	/**
	 * Get SQL code for table creation for model $model.
	 * 	 */
	public static function getCreateTableSqlCode($model){
		$sqlT = TwigTemplate::factoryHtmlResource(\lulo\query\Query::PATH . "/create/query.twig.sql");
		$replacements = [
			"model" => $model,
			"table" => $model::getTableName(),
			"attributes" => $model::metaGetAttributes(),
			"primary_key" => $model::metaGetPkAttributeNames(),
		];
		$sql = $sqlT->render($replacements);
		print($sql);
		return $sql;
	}
	
	/**
	 * Create table for model $model.
	 * 	 */
	public static function createTable($model){
		$sql = static::getCreateTableSqlCode($model);
		$res = \lulo\db\DB::execute($sql);
		var_dump($res);
	}
}
