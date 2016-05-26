<?php

namespace lulo\query;

/**
 * Clase que contiene cada una de las condiciones establecidas por el usuario.
 *  */
class Condition{
	
	/** Entidad de la que depende esta condición, Contacto, Apunte, etc. */
	protected $conditionConjunction;
	
	/** Modelo sobre el que se ejecuta la condición */
	protected $model;
	
	/** Tabla sobre la que se ejecuta la condición */
	public $table;
	
	/** Alias de la tabla sobre la que se ejecuta la condición */
	public $table_alias;
	
	/** Campo de la condición cuyo SQL se quiere generar */
	protected $field;
	
	/** Operador de comparación que se le va a aplicar */
	protected $operator;
	
	/** Valor que se va a comparar con el campo */
	protected $value;

	/** Carácter de escape de las condicones LIKE */
	const LIKE_ESCAPE_CHARACTER = "|";
	
	/**************************************************************************/
	
	/**
	 * Constructor de la clase Condition.
	 * 
	 * @param object $conditionConjunction Conjunción de condiciones padre de la que depende esta condición.
	 * @param string $field Campo sobre el que se va ejecutar la condición.
	 * @param string $value Valor de la condición.
	 */
	public function __construct($conditionConjunction, $field, $value) {
		$this->conditionConjunction = $conditionConjunction;
		$model = $conditionConjunction->luloquery->model;
		$matches = [];
		
		// Si el campo es una referencia externa
		if(strpos($field, "::")!==false and preg_match("#^(.+)::(.+)$#", $field, $matches)>0){
			// Obtenemos el modelo relacionado a partir del nombre
			// de la relación
			$relationshipName = $matches[1];
			$relationship = $model::metaGetRelationship($relationshipName);
			$relatedModel = $relationship["model"];
			$this->model = $relatedModel;
			$this->table = $relatedModel::TABLE_NAME;
			$this->table_alias = $relationshipName;
			$this->field = $matches[2];
			// Le añadimos la relación con el modelo
			$conditionConjunction->luloquery->addRelatedModel($relationshipName);
			$this->operator = "=";
		
		// El campo es local al modelo, por lo que es una condición normal
		}else{
			$this->model = $model;
			$this->table = $model::TABLE_NAME;
			$this->table_alias = "main_table";
			$this->field = $field;
			$this->operator = "=";
		}
		$this->value = $value;
		// En el caso de que el campo final tenga la subcadena __
		// entonces estamos ante un operador especial y hemos de cambiar
		// el campo y el operador
		if(strpos($this->field, "__")!==false){
			list($field, $operator) = explode("__", $this->field);
			$this->field = $field;
			$this->operator = $operator;
		}
	}
	
	
	/**
	 * Obtiene el modelo al que pertenece la condición.
	 * 
	 * @return string Cadena con el nombre del modelo al que pertenece esta condición.
	 */
	public function getModel(){
		return $this->model;
	}

	
	/**
	 * Obtiene el SQL del operador.
	 * @return string Operador la operación de comparación que se usará en la consulta.
	 */
	protected function getSqlOperator(){
		if(is_null($this->value)){
			return "IS";
		}
		if($this->operator == "contains"){
			return "LIKE";
		}
		if($this->operator == "notcontains"){
			return "NOT LIKE";
		}
		if($this->operator == "startswith"){
			return "LIKE";
		}
		if($this->operator == "endswith"){
			return "LIKE";
		}
		if($this->operator == "in"){
			return "IN";
		}
		if($this->operator == "range"){
			return "BETWEEN";
		}
		if($this->operator == "eq" or $this->operator == "="){
			return "=";
		}
		if($this->operator == "noteq"){
			return "<>";
		}
		if($this->operator == "lt"){
			return "<";
		}
		if($this->operator == "lte"){
			return "<=";
		}
		if($this->operator == "gt"){
			return ">";
		}
		if($this->operator == "gte"){
			return ">=";
		}
		throw new \UnexpectedValueException("El operator {$this->operator} no se reconoce");
	}
	
	
	/**
	 * Obtiene el campo preparado para insertar en el SQL.
	 * @return string Nombre del campo de la operación de comparación que se usará en la consulta.
	 */
	protected function getSqlField(){
		return $this->field;
	}
	
	
	/**
	 * Obtiene el valor sql para el caso de que sea un Like.
	 * @param $sqlValue Valor a escapar. Antes de envolverlo entre los símbolos de %.
	 * @return Valor con los % y _ escapados.
	 */
	protected static function getSqlValueForLike($sqlValue){
		$e = static::LIKE_ESCAPE_CHARACTER;
		$escapedSqlValue = str_replace(array($e, '_', '%'), array($e.$e, $e.'_', $e.'%'), $sqlValue);
		return $escapedSqlValue;
	}
	
	/**
	 * Realiza conversiones implícitas de un campo para generar la consulta
	 * correctamente.
	 * 
	 * @return string $sqlValue Valor que se ha de convertir a cadena.
	 * */
	protected static function implicitSqlValueConversion($sqlValue){
		/////// Conversiones básicas del valor del campo
		// Si el campo es un objeto establecemos otras conversiones
		if(is_object($sqlValue)){
			$sqlValueClass = get_class($sqlValue);
			
			// Si es de tipo DateTime, se convierte a una cadena
			// con la fecha en formato MySQL
			if($sqlValueClass == "DateTime"){
				$sqlValue = $sqlValue->format("Y-m-d H:i:s");
			}
			
			// Si tiene el atributo id devolvemos el id
			elseif(isset($sqlValue->id)){
				$sqlValue = $sqlValue->id;
			}
			
			// Si tiene el método getStrPk devolvemos getStrPk
			elseif(method_exists($sqlValue, "getStrPk")){
				$sqlValue = $sqlValue->getStrPk();
			}
		}
		return $sqlValue;
	}
	
	
	/**
	 * Obtiene el valor preparado para insertar en el SQL.
	 * 
	 * @return string Valor de la operación de comparación que se usará en la consulta.
	 */
	protected function getSqlValue(){
		// Si el operador es de tipo LIKE, tenemos que introducir % delante
		// y detrás del valor
		$sqlValue = $this->value;
		
		// Conversiones implícitas según la naturaleza del valor
		$sqlValue = static::implicitSqlValueConversion($sqlValue);

		/////// Conversiones según los operadores
		// Operadores que usan el LIKE
		if($this->operator == "contains" or $this->operator == "notcontains" or
			$this->operator == "startswith" or $this->operator == "endswith"){
			$sqlValue = static::getSqlValueForLike($sqlValue);
			
			// En el caso de que se trate de un operador contains o notcontains,
			// el valor ha de estar envuelto por %
			if($this->operator == "contains" or $this->operator == "notcontains"){
				$sqlValue = \lulo\db\DB::qstr("%{$sqlValue}%");
			}
			// En el caso de que se trate de un operador startswith
			// el valor ha de estar precedido por %
			if($this->operator == "startswith"){
				$sqlValue = \lulo\db\DB::qstr("{$sqlValue}%");
			}
			// En el caso de que se trate de un operador endswith
			// el valor ha de estar precedido por %
			if($this->operator == "endswith"){
				$sqlValue = \lulo\db\DB::qstr("%{$sqlValue}");
			}
			$escapedValue = "{$sqlValue} ESCAPE '".static::LIKE_ESCAPE_CHARACTER."'";
		}
		
		// Operador IN
		elseif($this->operator == "in"){
			// Comprobación básica
			if(!is_array($this->value)){
				throw new \InvalidArgumentException("El operador in requiere un array como valor");
			}
			// Generamos una string con la forma: IN (item1, item2, ..., itemN)
			$numValues = count($this->value);
			$escapedValue = "(";
			$i=0;
			foreach($this->value as $valueItem){
				$escapedValue .= \lulo\db\DB::qstr(static::implicitSqlValueConversion($valueItem));
				if($i < $numValues-1){
					$escapedValue .= ", ";
				}
				$i++;
			}
			$escapedValue .= ")";
		}
		
		// Operador range
		elseif($this->operator == "range"){
			// Comprobación básica
			if(!is_array($this->value) or count($this->value)!=2){
				throw new \InvalidArgumentException("El operador range requiere un array como valor con dos elementos");
			}
			// Extremos del intervalo
			$item1 = \lulo\db\DB::qstr(static::implicitSqlValueConversion($this->value[0]));
			$item2 = \lulo\db\DB::qstr(static::implicitSqlValueConversion($this->value[1]));
			$escapedValue = "{$item1} AND {$item2}";
		}
		
		// En el caso general, el escapado es normal
		else{
				$escapedValue = \lulo\db\DB::qstr($sqlValue);
		}
		
		// Devolvemos el valor
		return $escapedValue;
	}
	
	
	/**
	 * Obtiene el SQL de la condición.
	 * 
	 * @return string Cadena con la condición SQL sobre la tabla "contacto".
	 */
	public function sql(){
		// Si no tiene correspondencia especial,
		// simplemente hemos de construir la condición
		$field = $this->getSqlField();
		$sqlOperator = $this->getSqlOperator();
		$sqlValue = $this->getSqlValue();
		$table = $this->table;
		if(!is_null($this->table_alias)){
			$table = $this->table_alias;
		}
		$conditionStr = "{$table}.{$field} {$sqlOperator} {$sqlValue}";
		return $conditionStr;
	}
	
}

?>
