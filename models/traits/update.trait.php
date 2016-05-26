<?php

namespace lulo\models\traits;

/**
 * Update operations
 * 
 * This operations will be deprected and replace by Query operations. Use carefully.
 *
 * @author Diego J. Romero López
 */
trait Update {
	
	/**
	 * Actualiza todos los objetos de la base de datos.
	 * @param $condition Condición de actualización.
	 * @param $values Valores a modificar. En formato campo=>valor.
	 * @return true si la actualización ha sido un éxito, false en otro caso.
	 * */
	public static function dbUpdateAll($condition, $values){
		$db = static::DB;
		// Actualización de los campos del objeto
		$ok = $db::updateFields(static::TABLE_NAME, $values, $condition);
		return $ok;
	}
	
	
	/**
	 * Actualiza el objeto actual en la base de datos.
	 * @param $values Valores a modificar. En formato campo=>valor.
	 * @return true si la actualización ha sido un éxito, false en otro caso.
	 * */
	public function dbUpdate($values){
		$db = static::DB;
		// Clave primaria del objeto
		$pkValue = $this->getPk();
		// Actualización del objeto
		$this->setAttributes($values);
		// Actualización de los campos del objeto
		$ok = $db::updateFields(static::TABLE_NAME, $values, $pkValue);
		return $ok;
	}
	
	
	/**
	 * Escribe un blob cuyo nombre de atributo es $blobName.
	 * @param $blobName Nombre del blob.
	 * @param $mixed Objeto blob en varios formatos.
	 * */
	public function dbBlobUpdate($blobName, $blob){
		$db = static::DB;
		
		// Condición de este objeto
		$condition = $this->getPk();
		
		// 1. Si es una cadena, es el binario del fichero
		if(is_string($blob)){
			$blobResource = $blob;
		}
		// 2. Si es una array, y tiene la clave "path",
		// es la ruta de un fichero 
		elseif(is_array($blob)){
			// Si tiene el atributo path es la ruta del binario subido,
			// seguramente en /tmp
			if(isset($blob["path"])){
				$blobResource = file_get_contents($blob["path"]);
			}else{
				throw new \UnexpectedValueException("El blob {$blobName} es un array pero no tiene 'path' como clave.");	
			}
		}
		// 3. Es NULL
		elseif(is_null($blob)){
			// Lo primero es comprobar si el blob es nullable, si no es nullable
			// lanzamos una excepción
			if(!isset(static::$ATTRIBUTES[$blobName]["null"]) or !static::$ATTRIBUTES[$blobName]["null"]){
				throw new \UnexpectedValueException("El blob {$blobName} no es nullable.");	
			}
			$blobResource = null;
		}
		// 4. Es un descriptor de fichero
		elseif(get_resource_type($blob)==="stream"){
			$blobResource = $blob;
		}
		// Si no es un tipo adecuado, da un error
		else{
			$blobType = gettype($blob);
			throw new \UnexpectedValueException("El blob {$blobName} no es una cadena binaria, ni un array con 'path' como clave y la ruta del fichero, ni un descriptor de fichero. Es un {$blobType} que no sirve");
		}
		
		// Escritura del blob
		$newValues = [$blobName => $blobResource];
		$ok = $db::updateFields(static::TABLE_NAME, $newValues, $condition);
		// Devolvemos si es correcto o no
		return $ok;
	}
	
}
