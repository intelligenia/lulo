<?php

namespace lulo\models\traits;

trait Repr{
	
	/******************************************************************/
	/******************* Representaciones del objeto ******************/
	
	/**
	 * Representación como cadena.
	 * @return string Representación del objeto como cadena.
	 * */
	public function str(){
		// Obtenemos el nombre humano de la entidad
		$humanName = ucfirst(static::$META["verbose_name"]);
		// Sacamos su clave primaria
		$strPk = $this->getStrPk();
		// Concatenamos todo eso y lo devolvemos
		return "{$humanName}({$strPk})";
	}
	
	
	/**
	 * Representación como cadena de la clave primaria.
	 * @return string Representación de la clave primaria del objeto como cadena.
	 * */
	public function getStrPk(){
		$strPk = implode("-", $this->getPk());
		return $strPk;
	}
	
	
	/**
	 * Conversión de una cadena de clave primaria a array.
	 * @param string $strPk Conversión de la clave primara como string a array.
	 * @return array Representación de la clave primaria de un objeto como array.
	 * */
	protected static function strToPk($strPk){
		//////////////////////////////////
		// La clave primaria como cadena es cada uno de los valores de 
		// los atributos de la clave primaria, en el mismo orden en el que
		// se definan.
		$pkValues = explode("-", $strPk);
		// Nombres de la clave primaria
		$pkNames = static::metaGetPkAttributeNames();
		// Comprobamos si la representación de la clave primaria como
		// cadena es correcta o no
		if(count($pkNames) != count($pkValues)){
			throw new InvalidArgumentException("El formato de '{$strPk}' como clave primaria no es válido");
		}
		//////////////////////////////////
		/// Obtención de la clave primaria
		$pk = []; // Contendrá la PK como array
		$countPkValues = count($pkValues);
		for($i=0; $i<$countPkValues; $i++){
			// Nombre del atributo de la PK
			$name = $pkNames[$i];
			// Valor del atributo de la PK
			$value = $pkValues[$i];
			// Pareja (<atributo> => <valor>) de la PK
			$pk[$name] = $value;
		}
		return $pk;
	}
	
	
	/**
	 * Representación del objeto como cadena.
	 * @return string Representación del objeto como cadena.
	 * */
	public function __toString(){
		return $this->str();
	}
	
}
