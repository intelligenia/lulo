<?php

namespace lulo\query;

/**
 * Condition conjunction. That is, each one of the condition groups created
 * for the user that are converted in an intermediate objects this class.
 * The aim of this approach is making easier dealing with them.
 * @author Diego J. Romero López
 */
class ConditionConjunction {
	
	/** Query object this condition depends on */
	public $luloquery;
	
	/** Original conjunction */
	protected $queryConjunction;
	
	/** Condition list */
	public $conditions;
	
	/** Conditions by model hash */
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

		// Keeping the original query conjunction
		$this->queryConjunction = $queryConjunction;
		
		// Intialization of the conditions
		$this->conditions = [];
		$this->conditionsByModel = [];
		
		foreach($this->queryConjunction as $field=>$value){
			
			// I-th condition
			$conditionI = new \lulo\query\Condition($this, $field, $value);
			
			// Ith-condition in conditions by model
			$model = $conditionI->getModel();
			if(!isset($this->conditionsByModel[$model])){
				$this->conditionsByModel[$model] = [];
			}
			$this->conditionsByModel[$model][] = $conditionI;
			
			// I-th condition in the condition list
			$this->conditions[] = $conditionI;
		}
	}
	
	
	/**
	 * Gets conditions grouped by model.
	 * @return array Array of the conditions grouped by model.
	 */
	public function getconditionsByModel(){
		return $this->conditionsByModel;
	}
	

}
?>
