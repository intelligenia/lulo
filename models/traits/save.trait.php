<?php

namespace lulo\models\traits;

/**
 * Saving methods for LuloModel models.
 * @author Diego J. Romero López
 */
trait Save {
	
	/**
	 * Saves object in database assuming there is no repeated primary key.
	 * */
	protected function _dbSaveNewObject($blobs=[]){
		$db = static::DB;
		
		$id_attribute_name = static::ID_ATTRIBUTE_NAME;
		
		// Get non-blob attributes and their values
		$_model_data = $this->getNonBlobAttributes();
		
		// Delete of id attribute. We assume the DB engine assigns it with a
		// legal value
		if(array_key_exists($id_attribute_name, $_model_data)){
			unset($_model_data[$id_attribute_name]);
		}
		
		// For each blob we read it and prepara for the insertion
		foreach($blobs as $blobName=>$blobObject){
			static::_dbReadBlob($blobName, $blobObject, $_model_data);
		}
		
		// Insertion of a new tuple
		$ok = $db::insert(static::TABLE_NAME, $_model_data);
		
		// Get a new id by session.
		// View http://dev.mysql.com/doc/refman/5.5/en/information-functions.html#function_last-insert-id
		// for the case of MySQL. Other DB engines are supposed to work fine.
		$this->$id_attribute_name = $db::getLastInsertId();
		return $ok;
	}
	
	
	/**
	 * Update an existing object in the database.
	 * */
	protected function _dbSaveOldObject($blobs=[]){
		$db = static::DB;
		
		// Primary key
		$pkValue = $this->getPk();
		
		// Get non-blob attributes and their values
		$values = $this->getNonBlobAttributes();
		
		// For each blob we read it and prepara for the insertion
		foreach($blobs as $blobName => $blobObject){
			static::_dbReadBlob($blobName, $blobObject, $values);
		}
		
		// Update of object fields
		$ok = $db::updateFields(static::TABLE_NAME, $values, $pkValue);
		return $ok;
	}
	
	
	/**
	 * Test if the object is a new object.
	 * @return boolean true if the object is not in the database, false otherwise.
	 * */
	protected function _dbIsNewObject(){
		$id_attribute_name = static::ID_ATTRIBUTE_NAME;
		// If the object has not an id, is a new object
		if( isset(static::$ATTRIBUTES[$id_attribute_name]) and (!isset($this->$id_attribute_name) or is_null($this->$id_attribute_name)) ){
			return true;
		}
		// Test if object exists in BD
		$db = static::DB;
		// Using its primary key
		$pkValue = $this->getPk();
		$alreadyExists = ($db::count(static::TABLE_NAME, $pkValue) > 0);
		return !$alreadyExists;
	}

	
	/**
	 * Clean attributes before saving the object in database.
	 * 
	 */
	protected function cleanAttributes(){
		foreach($this->getNonBlobAttributes() as $name=>$v){
			$properties = static::$ATTRIBUTES[$name];
			// Nullability test
			if($properties["type"]!= "blob" and (!isset($properties["auto"]) or !$properties["auto"]) and is_null($this->$name) and (!isset($properties["null"]) or !$properties["null"])){
				throw new \UnexpectedValueException("Attribute {$name} can't be null");
			}
		}
	}
	
	
	/**
	 * Validation of an existing object.
	 * Must be overwritten.
	 * @param array $blobs Blob array.
	 * */
	public function cleanOld($blobs){
		$this->cleanAttributes();
		
		// Throw exceptions here to stop saving
	}
	
	
	/**
	 * Validate a new object (that doesn't exist in database).
	 * Must be overwritten.
	 * @param array $blobs Blob array.
	 * */
	public function cleanNew($blobs){
		$this->cleanAttributes();		

		// Throw exceptions here to stop saving
	}
	
	
	/**
	 * Valida el objeto $object en función de el resto de objetos que
	 * ya existen en la base de datos.
	 * Es sobrescribible.
	 * @param array $blobs Array con los objetos Blob.
	 * */
	public function clean($blobs){
		// ¿Es un objeto nuevo?
		$isNewObject = $this->_dbIsNewObject();
		// Validar la creación
		if($isNewObject){
			//print "valida la creación";
			$this->cleanNew($blobs);
		// Validar la edición
		}else{
			//print "valida la edición";
			$this->cleanOld($blobs);
		}
	}
	
		
	/**
	 * Add all the relationships from dynamic attributes of $this object.
	 * @param boolean $objectExisted Did the object exist?
	 * */
	protected function _dbAddM2MFromForm($objectExisted=true){
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			if($properties["type"] == "ManyToMany"){
				// If it has dynamic attributes with the name of a relationship
				// we have to update this relationship
				if(isset($this->$relationName) and is_array($this->$relationName) and count($this->$relationName)>0){
					$strPks = $this->$relationName;
					// If object existed, delete all its relationships
					// to prepare them to create all again
					if($objectExisted){
						$this->dbDeleteRelation($relationName);
					}
					$this->dbAddRelation($relationName, $strPks);
				}
			}
		}
	}
	
	/**
	 * Save this object in its model table in the database.
	 * 
	 * Note that if the object existed, it is modified accordingly.
	 * */
	public function dbSave($blobs=[]){
		// Does the object exist?
		$isNewObject = $this->_dbIsNewObject();
		
		// Is it valid?
		$this->clean($blobs, $isNewObject);
		
		// If it existed, update it
		if(!$isNewObject){
			$ok = $this->_dbSaveOldObject($blobs);
		}
		// Otherwise, create it
		else{
			$ok = $this->_dbSaveNewObject($blobs);
		}
		
		// Add relationships if they exists as dynamic attributes
		$ok = ( $ok and $this->_dbAddM2MFromForm(!$isNewObject) );
		
		// Everything went ok?
		return $ok;
	}
	
	
}
