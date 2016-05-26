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
	 * Update all objects that match with the condition.
	 * @param $condition Update condition.
	 * @param $values Update values. Array of pairs field => new_value.
	 * @return true if it the update was successful, false otherwise.
	 * */
	public static function dbUpdateAll($condition, $values){
		$db = static::DB;
		$ok = $db::updateFields(static::TABLE_NAME, $values, $condition);
		return $ok;
	}
	
	
	/**
	 * Update current object.
	 * @param $values Update values. Array of pairs field => new_value.
	 * @return true if it the update was successfull, false otherwise.
	 * */
	public function dbUpdate($values){
		$db = static::DB;
		// Primary key of this object
		$pkValue = $this->getPk();
		// Updating the object
		$this->setAttributes($values);
		// Update object in table
		$ok = $db::updateFields(static::TABLE_NAME, $values, $pkValue);
		return $ok;
	}
	
	
	/**
	 * Stores the blob $blobName.
	 * @param $blobName Name of blob attribute.
	 * @param $mixed Blob object, several formats allowed.
	 * @return true if it the update was successfull, false otherwise.
	 * */
	public function dbBlobUpdate($blobName, $blob){
		$db = static::DB;
		
		// Primary key of this object
		$condition = $this->getPk();
		
		// 1. If $blob is a string, take it as a string
		if(is_string($blob)){
			$blobResource = $blob;
		}
		// 2. If $blob is an array and has the key "path",
		// $blob["path"] is the path of the file whose contents
		// we must store for $blobName
		elseif(is_array($blob)){
			if(isset($blob["path"])){
				$blobResource = file_get_contents($blob["path"]);
			}else{
				throw new \UnexpectedValueException("Blob {$blobName} needs key 'path'.");	
			}
		}
		// 3. Is NULL
		elseif(is_null($blob)){
			// If attribute is not nullable, thrown an exception
			if(!isset(static::$ATTRIBUTES[$blobName]["null"]) or !static::$ATTRIBUTES[$blobName]["null"]){
				throw new \UnexpectedValueException("Blob {$blobName} is not nullable.");
			}
			$blobResource = null;
		}
		// 4. File descriptor
		elseif(get_resource_type($blob)==="stream"){
			$blobResource = $blob;
		}
		// Otherwise, throw exception
		else{
			$blobType = gettype($blob);
			throw new \UnexpectedValueException("Blob {$blobName} is a {$blobType}, and that type is not allowed. Pass blob contents as a string, file path or stream");
		}
		
		// Writting the blob
		$newValues = [$blobName => $blobResource];
		$ok = $db::updateFields(static::TABLE_NAME, $newValues, $condition);
		return $ok;
	}
	
}
