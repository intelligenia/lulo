<?php

namespace lulo\query;

/**
 * Logic proposition.
 * Useful for creating complex conditions.
 * 
 * DO NOT USE, currently under development.
 */

class Proposition{
	
	protected $clause = null;
	
	public function __invoke($and_clause)
    {
        return new Proposition($and_clause);
    }
	
	public function __construct($and_clause_array) {
		$this->clause = [$and_clause_array];
	}
	
	public function a($and_clause_array){
		$this->clause = array_merge($this->clause, $and_clause_array);
		
	}
	
	public function o($and_clause_array){
		$this->clause = [$this->clause, $and_clause_array];
	}
	
	public function toArray(){
		return $this->clause;
	}
	
}
