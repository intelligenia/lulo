<?php

namespace lulo\query;

/**
 * Campo de la tabla (o alguna de sus relacionadas) que se usa en la ordenación de la consulta.
 * */
class OrderField{

	/** Modelo del campo */
	public $model;
	
	/** Tabla a la que pertenece el campo */
	public $table;
	
	/** Alias usado en la consulta de la tabla a la que pertenece el campo */
	public $tableAlias;
	
	/** Campo que se desea ordenar */
	public $field;
	
	/** Valor de ordenación */
	public $orderValue;

	/**
	 * Construye un nuevo campo de la base de datos
	 * 
	 * @param object $luloquery LuloQuery del que depende.
	 * @param string $model Nombre del modelo del que depende el orden.
	 * */
	public function __construct($luloquery, $model, $field, $orderValue){
		
		// Asignamos el LuloQuery
		$this->luloquery = $luloquery;
		
		// Comprobamos que el orden asignado es ASC o DESC
		if(!(strtolower($orderValue)=="asc" or strtolower($orderValue)=="desc")){
			throw new \Exception("El campo {$field} no tiene un orden correcto. Tiene {$orderValue} y debe ser asc o desc");
		}
		
		// Si el campo contiene una referencia externa, asumimos que se va a ordenar
		// por un campo externo. Eso implica que habrá que hacer un JOIN en la
		// consulta por esa tabla, por lo que necesitamos añadir la relación
		// con el modelo (si no estaba ya).
		$matches = [];
		if(strpos($field, "::")!==false and preg_match("#^(.+)::(.+)$#", $field, $matches)>0){
			$relationshipName = $matches[1];
			if($model::metaHasRelationship($relationshipName)){
				// Le añadimos la relación con el modelo
				$this->luloquery->addRelatedModel($relationshipName, $relatedModel=null);
				$this->model = $model;
				$this->table = $model::TABLE_NAME;
				$this->tableAlias = $relationshipName;
				$this->field = $matches[2];
				$this->orderValue = strtoupper($orderValue);
			}else{
				throw new \Exception("El modelo {$model} no tiene la relación {$relationshipName}");
			}
		}
		
		// Si el campo es del modelo, simplemente lo añaidmos y sin problemas
		elseif($model::metaHasAttribute($field)){
			$this->model = $model;
			$this->table = $model::TABLE_NAME;
			$this->tableAlias = "main_table";
			$this->field = $field;
			$this->orderValue = strtoupper($orderValue);
		}
		else{
			throw new \Exception("El modelo {$model} no tiene el campo {$field}");
		}
		

		
		
	}
	
}
?>
