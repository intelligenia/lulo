<?php

namespace lulo\query;

/**
 * Conjunción de condiciones. Esto es, cada uno de los grupos de las condiciones
 * creadas por el usuario convertidos a un formato intermedio para facilitar
 * su tratamiento.
 * @author diegoj
 */
class ConditionConjunction {
	
	/** Objeto LuloQuery del que depende */
	public $luloquery;
	
	/** Conjunción original del que se han extraído la consulta */
	protected $queryConjunction;
	
	/** Listado con todas las condiciones independientemente de su entidad */
	public $conditions;
	
	/** Tabla hash en el que las condiciones están agrupadas por modelo */
	protected $conditionsByModel;
	
	/**
	 * Constructor: a partir de un listado de hashes con determinado número de
	 * atributos, construye un objeto ConditionGroup con varios objetos Condition
	 * asociados que encapsula de forma sencilla las consultas enviadas por el
	 * cliente.
	 * 
	 * @param object $luloquery LuloQuery del que dependen esta conjunción de condiciones.
	 * @param array $queryConjunction Consulta enviada por el cliente. En el formato de entrada.
	 * @param boolean $positive Indica si la conjunción es positiva o negativa en el sentido lógico (las proposiciones negativas son proposiciones negadas).
	 */
	public function __construct($luloquery, $queryConjunction, $positive=true){
		$this->luloquery = $luloquery;
		$this->positive = $positive;

		// Guardamos el listado de arrays con la consulta original
		$this->queryConjunction = $queryConjunction;
		
		// Guardamos en lugares separados las condiciones y las condiciones
		// por entidad. Es cierto que nos servirían con las condiciones por
		// entidad, pero bueno.
		$this->conditions = [];
		$this->conditionsByModel = [];
		
		foreach($this->queryConjunction as $field=>$value){
			
			// Construcción de la condición iésima
			$conditionI = new \lulo\query\Condition($this, $field, $value);
			
			// Guardamos las condiciones agrupadas por el modelo sobre el que
			// actúan. Esto nos permitirá saber qué modelos hay en juego
			// en esta consulta.
			$model = $conditionI->getModel();
			if(!isset($this->conditionsByModel[$model])){
				$this->conditionsByModel[$model] = [];
			}
			$this->conditionsByModel[$model][] = $conditionI;
			
			// Almacenamos la condición también en un listado de condiciones
			$this->conditions[] = $conditionI;
		}
	}
	
	
	/**
	 * Obtiene una array con las condiciones agrupadas por modelos.
	 * @return array Array con las condiciones agrupadas por modelos.
	 */
	public function getconditionsByModel(){
		return $this->conditionsByModel;
	}
	

}
?>
