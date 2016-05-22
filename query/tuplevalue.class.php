<?php

namespace lulo\query;

/**
 * Representa una referencia al valor actual que tenga la tupla en ese momento.
 * 
 * Se usa en actualiaciones (UPDATE) y en las condiciones de selección (SELECT).
 * 
 * Equivalente a los objetos F de Django.
 * 
 *  */
class TupleValue{
	
	/** Nombre de la columna a la que se hace referencia */
	protected $fieldName;
	
	/** Indica si se ha de comprobar que lo que se pasa es un  */
	protected $raw = false;
	
	/**
	 * Construye un TupleValue a partir de un nombre de un campo
	 * @param string $fieldName Nombre de la columna a la que hace referencia.
	 */
	public function __construct($fieldName, $raw=false) {
		$this->fieldName = $fieldName;
		$this->raw = $raw;
	}
	
	
	/**
	 * Factoría que construye un TupleValue a partir de un nombre de un campo
	 * @param string $fieldName Nombre de la columna a la que hace referencia.
	 * @return object Objeto TupleValue para $fieldName.
	 */
	public static function n($fieldName, $raw=false){
		return new TupleValue($fieldName, $raw);
	}
	
	
	/**
	 * Obtiene el nombre de la columna a la que hace referncia.
	 * @return string Nombre de la columna.
	 * 	 */
	public function f(){
		return $this->fieldName;
	}
	
	
	/**
	 * Obtiene el nombre de la columna a la que hace referencia.
	 * @return string Indica si se ha de dejar el nombre del campo tal cual.
	 * 	 */
	public function isRaw(){
		return $this->raw;
	}
}

