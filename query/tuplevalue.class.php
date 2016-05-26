<?php

namespace lulo\query;

/**
 * Reference to current value for a tuple.
 * 
 * Used in UPDATE and SELECT statements.
 * 
 * Similar to F objects in Django (https://docs.djangoproject.com/en/1.7/ref/models/queries/#f-expressions).
 * 
 *  */
class TupleValue{
	
	/** Column name to be referenced */
	protected $fieldName;
	
	/**
	 * Creates a TupleValue for a field.
	 * @param string $fieldName Referenced field.
	 */
	public function __construct($fieldName) {
		$this->fieldName = $fieldName;
	}
	
	
	/**
	 * Creates a new TupleValue object given its reference field.
	 * @param string $fieldName Referenced field.
	 * @return object TupleValue objects for $fieldName.
	 */
	public static function n($fieldName){
		return new TupleValue($fieldName);
	}
	
	
	/**
	 * Gets referenced field.
	 * @return string Column name referenced for this object.
	 * 	 */
	public function f(){
		return $this->fieldName;
	}
	
	/**
	 * Informs if the field value contains some kind of transformation.
	 * 
	 * Not yet implemented.
	 * 	 */
	public function isRaw(){
		return true;
	}
	
}

