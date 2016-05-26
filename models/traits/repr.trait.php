<?php

namespace lulo\models\traits;

/**
 * Representations of the object.
 */
trait Repr{
	
	/**
	 * Object as string.
	 * @return string Object representation as a string.
	 * */
	public function str(){
		// Human name of the model
		$humanName = ucfirst(static::$META["verbose_name"]);
		// Primary key of this object
		$strPk = $this->getStrPk();
		// Concate name of the model and primary key
		return "{$humanName}({$strPk})";
	}
	
	
	/**
	 * Primary key of the object as a string.
	 * @return string Representation of the primary key of the object as a string.
	 * */
	public function getStrPk(){
		$strPk = implode("-", $this->getPk());
		return $strPk;
	}
	
	
	/**
	 * String representation of the primary key to primary key (as array).
	 * @param string $strPk Primary key as string.
	 * @return array Primary key as array.
	 * */
	protected static function strToPk($strPk){
		// Conversion to array
		$pkValues = explode("-", $strPk);
		// Attributes that belong to primary key
		$pkNames = static::metaGetPkAttributeNames();
		// Is the representation or the primary key right?
		if(count($pkNames) != count($pkValues)){
			throw new \InvalidArgumentException("'{$strPk}' format as primary key is not valid");
		}
		/// Creation of primary key
		$pk = []; // Will contain primary key
		$countPkValues = count($pkValues);
		// For each attribute that belongs to primary key, in that order
		// assign its content from the string representation of the primary key
		for($i=0; $i<$countPkValues; $i++){
			$attribute_name = $pkNames[$i];
			$value = $pkValues[$i];
			$pk[$attribute_name] = $value;
		}
		return $pk;
	}
	
	
	/**
	 * Object as string.
	 * 
	 * Alias of str to provide automatic conversion of objects to string.
	 * 
	 * @return string Object representation as a string.
	 * */
	public function __toString(){
		return $this->str();
	}
	
}
