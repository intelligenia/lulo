<?php

namespace lulo\management;

use lulo\twig\TwigTemplate as TwigTemplate;

/**
 * Create model associated tables.
 *  */
class DBCreator{
	
	private $model;
	
	public function __construct($model){
		$this->model = $model;
	}
	
	public function execute(){
		// Main table creation
		$this->createMainTable();
		$this->createNexiiTables();
		
	}
	
	private function createMainTable(){
		$sql = $this->getMainTableCreationSqlCode();
		return \lulo\db\DB::execute($sql);
	}
	
	private function getMainTableCreationSqlCode(){
		$model = $this->model;
		$sqlT = TwigTemplate::factoryHtmlResource("create/query.twig.sql");
		$replacements = [
			"model" => $model,
			"table" => $model::getTableName(),
			"id_attribute_name" => $model::ID_ATTRIBUTE_NAME,
			"attributes" => $model::metaGetAttributes(),
			// We will use the id of the model as a primary key
			// although in conditions we will use the primary key specified
			// in the attribute PRIMARY_KEY
			"primary_key" => [$model::ID_ATTRIBUTE_NAME],
		];
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
	private function createNexiiTables(){
		$model = $this->model;
		// Only the Many-to-many relationships have nexii tables
		$relationships = $model::metaGetRelationships();
		foreach($relationships as $relationshipName=>$relationshipProperties){
			// Only direct relationships, inverse relationship tables are ignored
			if($relationshipProperties["type"] == "ManyToMany" and !isset($relationshipProperties["inverse_of"])){
				$this->createNexusTables($relationshipName);
			}
		}
	}
	
	private function createNexusTables($relationshipName){
		$model = $this->model;
		$relationship = $model::metaGetRelationship($relationshipName);
		$remoteModel = $relationship["model"];
		if(count($relationship["junctions"])>1){
			throw new \BadMethodCallException("Table creation for relationships with multiple nexii is not implemented");
		}
		
		$table = $relationship["junctions"][0];
		$conditions = $relationship["conditions"];
		
		$nexus_attributes = [];
		
		// Get the type of each of the related attributes of the model
		// to assign it to its related nexus attribute
		$relationWithModel = $conditions[0];
		foreach($relationWithModel as $modelAttribute=>$nexusAttribute){
			$modelAttributeProperties = $model::metaGetAttribute($modelAttribute);
			$nexus_attributes[$nexusAttribute] = $modelAttributeProperties;
		}
		
		// Get the type of each one of the related attributes of the remote model
		// to assign it to the related nexus attribute
		$relationWithRemoteModel = $conditions[1];
		foreach($relationWithRemoteModel as $nexusAttribute=>$remoteModelAttribute){
			$remoteModelAttributeProperties = $remoteModel::metaGetAttribute($remoteModelAttribute);
			$nexus_attributes[$nexusAttribute] = $remoteModelAttributeProperties;
		}
		
		$sql = $this->getNexusTableCreationSqlCode($table, $nexus_attributes, $relationship);
		return \lulo\db\DB::execute($sql);
	}
	
	private function getNexusTableCreationSqlCode($table, $nexus_attributes, $relationship_properties){
		$sqlT = TwigTemplate::factoryHtmlResource("create/query.twig.sql");
		$replacements = [
			"table" => $table,
			"attributes" => $nexus_attributes,
			"unique" => (isset($relationship_properties["unique"]) and $relationship_properties["unique"]),
		];
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
}

