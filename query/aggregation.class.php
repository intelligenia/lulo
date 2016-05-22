<?php

namespace lulo\query;

/**
 * Representa una agregación en una consulta a la BD.
 *  */
class Aggregation{
	
	/** Nombre de la función de agregación a aplicar */
	public $functionName;
	
	/** Alias que le metemos al resultado de la agregación */
	public $alias;
	
	/** Campos implicados en la agregación */
	public $fields;
	
	/** Modelo que sufre la consulta */
	public $model;
	
	
	/**
	 * Nombres que se permiten para las funciones de agregación.
	 * 	 */
	protected static $AGGREGATE_FUNCTION_NAMES = [
		"AVG", "COUNT", "MAX", "MIN", "STD", "STDDEV", "SUM", "VARIANCE"
	];
	
	
	/**
	 * Construye una nueva agregación.
	 * 
	 * @param string $functionName Nombre de la función de agregación.
	 * @param string $alias Alias que le meteremos a la función de agregación en la consulta.
	 * @param array Array con los nombres de los campos que van a sufrir la agregación.
	 * 	 */
	public function __construct($functionName, $alias, $fields=null) {
		// Comprobación de que las funciones de agregación son válidas
		if(!in_array(strtoupper($functionName), static::$AGGREGATE_FUNCTION_NAMES)){
			throw new \Exception("La función {$functionName} no es un nombre de agregación correcto");
		}
		// Comprobamos si se han pasado los campos correctamente
		if(!is_array($fields) and !is_null($fields)){
			throw new \Exception("No le has pasado campos a la función de agregación {$functionName}");
		}
		
		$this->functionName = $functionName;
		$this->alias = $alias;
		$this->fields = $fields;
	}
	
	
	/**
	 * Indicamos a la agregación el modelo sobre el que se va a ejecutar.
	 * 
	 * @param string $model Nombre del modelo que se va a ejecutar.
	 * 
	 * 	 */
	public function init($model){
		$this->model = $model;
		// Comprobamos que los campos sean atributos del modelo
		if(is_array($this->fields)){
			foreach($this->fields as $field){
				if(!$model::metaHasAttribute($field)){
					throw new \Exception("{$field} no es un atributo del modelo {$model}");	
				}
			}
		}
	}
	
}
