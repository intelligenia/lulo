<?php
namespace lulo\management;

use lulo\twig\TwigTemplate as TwigTemplate;

/**
 * Drop model associated tables.
 *  */
class DBDropper{
	
	private $model;
	
	public function __construct($model){
		$this->model = $model;
	}
	
	public function execute(){
		$this->dropMainTable();
		$this->dropNexiiTables();
	}
	
	private function dropMainTable(){
		$sql = $this->getMainTableDropSqlCode();
		return \lulo\db\DB::execute($sql);
	}
	
	private function getMainTableDropSqlCode(){
		$model = $this->model;
		$sqlT = TwigTemplate::factoryHtmlResource("drop/query.twig.sql");
		$replacements = ["table" => $model::getTableName()];
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
	private function dropNexiiTables(){
		$model = $this->model;
		// Only the Many-to-many relationships have nexii tables
		$relationships = $model::metaGetRelationships();
		foreach($relationships as $relationshipName=>$relationshipProperties){
			// Only direct relationships, inverse relationship tables are ignored
			if($relationshipProperties["type"] == "ManyToMany" and !isset($relationshipProperties["inverse_of"])){
				$this->dropNexusTables($relationshipName);
			}
		}
	}
	
	private function dropNexusTables($relationshipName){
		$model = $this->model;
		$relationship = $model::metaGetRelationship($relationshipName);
		if(count($relationship["junctions"])>1){
			throw new \BadMethodCallException("Table creation for relationships with multiple nexii is not implemented");
		}
		
		$table = $relationship["junctions"][0];
		
		$sql = $this->getNexusTableDropSqlCode($table);
		return \lulo\db\DB::execute($sql);
	}
	
	private function getNexusTableDropSqlCode($table){
		$sqlT = TwigTemplate::factoryHtmlResource("drop/query.twig.sql");
		$replacements = ["table" => $table];
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
}

?>

