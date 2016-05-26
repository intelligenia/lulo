<?php

namespace lulo\models\traits;

/**
 * Deletion operations for models.
 * 
 * This operations will be deprected and replace by Query operations. Use carefully.
 * 
 * @author Diego J. Romero López
 */
trait Delete {

	/**
	 * Delete object.
	 * 
	 * This method is encouraged to be overwritten to implement logic deletion.
	 * Overwritting this method and implicitBaseCondition (to ignore objects
	 * marked as "deleted") is the right way to do it.
	 * 
	 * @return boolean true if deletion was right, false otherwise.
	 * */
	protected function dbDeleteAction(){
		$db = static::DB;
		// Primary key of object to delete
		$condition = $this->getPk();
		// Addition of base conditions
		$finalDeletionCondition = static::getBaseCondition($condition);
		// Deletion of object
		$ok = $db::delete(static::getTableName(), $finalDeletionCondition);
		return $ok;
	}
	
	
	/**
	 * Delete current object.
	 * 
	 * @param boolean $updateRelations should relationships be updated? If null,
	 * this decision will be taken according to UPDATE_RELATIONS_ON_DELETE constant.
	 * 
	 * @return true if deletion is right, false otherwise.
	 * */
	public function dbDelete($updateRelations=null){
		// Should relationships need to be updated?
		if(is_null($updateRelations)){
			$updateRelations = static::UPDATE_RELATIONS_ON_OBJECT_DELETION_BY_DEFAULT;
		}
		// Delete the object
		$ok = $this->dbDeleteAction();
		// If relationships need to be updated
		if($updateRelations){
			// Delete all my relationships. That is:
			// - Relationships where I'm main actor (if relation is OneToMany)
			// - Relationships where I'm part of a ManyToMany relationship
			$ok = ( $ok and $this->dbDeleteRelations() );
		}
		return $ok;
	}
	
	
	/**
	 * Delete all the objects that comply with a condition.
	 * 
	 * WARNING: this deletion does not delete related objects.
	 * 
	 * @param $condition Deletion condition.
	 * 
	 * @return true if deletion is right, false otherwise.
	 * */
	public static function dbDeleteAll($condition){
		$db = static::DB;
		// Final conditions (adding base conditions)
		$finalDeletionCondition = static::getBaseCondition($condition);
		// Deletion of all the objects that comply with that conditions
		$ok = $db::delete(static::getTableName(), $finalDeletionCondition);
		return $ok;
	}
	
	
	/******************************************************************/
	/************************* DELETE RELATED OBJECTS *****************/
	
	/**
	 * Delete a relationship.
	 * 
	 * If $object is not null, it assumes only relationships between $this
	 * and $object need to be deleted.
	 * 
	 * @param array $relationship Array with metainformation of the relationship to delete.
	 * @param object $object Object whose relationship we want to delete. If null,
	 * relationships with all objects will be deleted.
	 * @return true if deletion is right, false otherwise.
	 * */
	protected function _dbDeleteManyToManyRelation($relationship, $object=null){
		$db = static::DB;
		
		$model = $relationship["model"];
		$junctions = $relationship["junctions"];
		$conditions = $relationship["conditions"];
		
		// Only relationships with a nexus are considered
		$junction = $junctions[0];
		if(count($junctions)>1){
			throw new \UnexpectedValueException("Not supported operation. Unable to delete many-to-many relationship when dealing with several nexus tables.");
		}
		
		$junctionValues = $this->getJunctionValues($conditions, $junction, $object);
		
		// Deletion
		$ok = $db::delete($junction, $junctionValues);

		return $ok;
	}
	
	
	/**
	 * Delete a relationship.
	 * 
	 * If $object is not null, it assumes only relationships between $this
	 * and $object need to be deleted.
	 * 
	 * @param string $relationName Relationship to delete.
	 * @param object $object Object whose relationship we want to delete. If null,
	 * relationships with all objects will be deleted.
	 * @return true if deletion is right, false otherwise.
	 * */
	public function dbDeleteRelation($relationName, $object=null){
		// Relationship
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Remote model
		$foreignClass = $relationship["model"];
		
		// Is this relationship deletable?
		static::assertRWRelationship($relationName, $relationship);
		
		// Relationships without a model can't be deleted
		if(is_null($foreignClass)){
			throw new \UnexpectedValueException("Relationship {$relationName} can't be deleted because is not of model type.");
		}
		
		// Relationship type (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		// ForeignKey
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne"){
			throw new \InvalidArgumentException("Relationship {$relationName} is a ForeignKey relationship and can't be nullified. Set its attributes to NULL by hand and call to dbSave");
		}
		
		// OneToMany
		elseif($relationshipType == "OneToMany"){
			if(isset($relationship["on_delete"])){
				// On cascade deletion
				if($relationship["on_delete"] == "delete" or $relationship["on_delete"] == "cascade"){
					$relatedObjects = $this->dbLoadRelated($relationName);
					foreach($relatedObjects as $relatedObject){
						$relatedObject->dbDelete();
					}
					return true;
				}
				// On cascade set value
				if(isset($relationship["on_delete"]["set"])){
					$remoteAttributeChanges = $relationship["on_delete"]["set"];
					$relatedObjects = $this->dbLoadRelated($relationName);
					foreach($relatedObjects as $relatedObject){
						$relatedObject->setAttributes($remoteAttributeChanges);
						$relatedObject->dbSave();
					}
					return true;
				}
			}
		}
		
		// ManyToMany
		elseif($relationshipType == "ManyToMany"){
			return $this->_dbDeleteManyToManyRelation($relationship, $object);
		}
	}
	
	
	/**
	 * Delete all relationshihps of an object where it is the main object.
	 * 
	 * @return boolean true if everything was right, false otherwise.
	 * */
	public function dbDeleteDirectRelations(){
		$ok = true;
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			if($properties["type"] == "ManyToMany" or $properties["type"] == "OneToMany"){
				// Deletion of relationships that are not readonly
				if(!isset($properties["readonly"]) or !$properties["readonly"]){
					// Direct relationships deletion
					$ok = ($ok and $this->dbDeleteRelation($relationName));
				}
			}
		}
		return $ok;
	}
	
	
	/**
	 * Delete all relationshihps of an object where it is the main object.
	 * Alias of dbDeleteDirectRelations.
	 * 
	 * @return boolean true if everything was right, false otherwise.
	 * */
	public function dbDeleteRelations(){
		// Relaciones directas
		$okDirect = $this->dbDeleteDirectRelations();
		// Informamos si todo ha ido bien
		return $okDirect;
	}
	
}
