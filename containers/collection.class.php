<?php

namespace lulo\containers;

/**
 * Model object collection.
 * Please use Queries better that Collections when reading data
 * from the database. It is more efficient.
 * Only use collections to operate with data in main memory.
 * @author Diego J. Romero López <diego@intelligenia.com>
 * */
class Collection implements \Countable, \Iterator, \ArrayAccess
{
	/** Collection class name */
	const CLASS_NAME = "Collection";

	/// Collection attributes
	
	/** Size of the collection*/
	protected $count = 0;
	
	/** Contents of the collection */
	protected $positionIndex = array();
	
	/** Dynamic attributes of the collection */
	protected $dynamicAttributes = array();
	
	/// Magic methods
	
	/**
	* Call this method for each objects of the collection.
	* @param string $name Function name.
	* @param array $arguments Function arguments.
	*/
	public function _call($name, $arguments)
	{
		$this->each(call_user_func_array($name, $arguments));
	}
	
	
	/**
	* Check if a dynamic attribute exists in the collection.
	* @param string $name Name of the dynamic attribute.
	* @return true if a dynamic attribute with the name $name exists, false otherwise.
	*/
	public function __isset($name){
		return isset($this->dynamicAttributes[$name]);
	}
	
	
	/**
	* Get the value of a dynamic attribute of the collection.
	* @param string $name Name of the dynamic attribute.
	* @return mixed value of the dynamic attribute, null otherwise.
	*/
	public function __get($name){
		if(isset($this->dynamicAttributes[$name])){
			return $this->dynamicAttributes[$name];
		}
		return null;
	}
	
	
	/**
	* Get the value of a dynamic attribute of the collection.
	* @param string $name Name of the dynamic attribute.
	* @param mixed $value Value to asssign to the $name dynamic attribute.
	*/
	public function __set($name, $value){
		$this->dynamicAttributes[$name] = $value;
	}
	
	
	/**
	* Unset the value of a dynamic attribute of the collection.
	* @param string $name Name of the dynamic attribute.
	*/
	public function __unset($name){
		unset($this->dynamicAttributes[$name]);
	}

	
	/**
	* Return the dynamic attribute array.
	* @return array Array with the dynamic attributes.
	*/
	public function getDynamicAttributes(){
		return $this->dynamicAttributes;
	}
	
	
	/**
	 * String representation of a collection.
	 * @return string String representation of a collection.
	 * 	 */
	public function __toString()
	{
		return json_encode($this);
	}

	
	/**
	 * Check if object is of type Collection.
	 * @param object $object Object to check its type.
	 * @return boolean true if $object is of class Collection, false otherwise.
	 * */
	public static function isInstanceOf($object)
	{
		return (is_object($object) and get_class($object)==Collection::CLASS_NAME);
	}
	
	/**
	 * Return object signatura.
	 * @param object $object Object whose signature we need
	 * 	 */
	protected static function getObjectSignature($object)
	{
		return $object->getStrPk();
	}

	
	/**
	* Collection constructor.
	* @param mixed $objects a Collection, an object, or an array of objects.
	* */
	public function __construct($objects=null)
	{
		// If there are no objects, create the empty collection
		if(is_null($objects))
		{
			$this->positionIndex = array();
			$this->count = 0;
			return $this;
		}
		// Copy constructor
		if(is_object($objects) and get_class($objects)==static::CLASS_NAME)
		{
			$this->positionIndex = $objects->positionIndex;
			$this->count = $objects->count;
			return $this;
		}
		// Array
		if(is_array($objects))
		{
			$this->positionIndex = $objects;
			$this->count = count($objects);
			return $this;
		}
		// Model object
		if(is_object($objects) and is_callable(array($objects,"dbSave")))
		{
			$this->positionIndex = array($objects);
			$this->count = 1;
			return $this;
		}
		// Anything
		$this->positionIndex = array($objects);
		$this->count = 1;
		return $this;
	}

	
	/**
	* Construct an object.
	* @param mixed $objects a Collection, an object, or an array of objects.
	* @return object Collection created from $objects.
	* */	
	public static function factory($objects=null)
	{
		return new Collection($objects);
	}
  
  
	/* Return the size of the collection */
	public function count(){
		return $this->count;
	}
	
	public function length(){
		return $this->count;
	}
	
	public function size(){
		return $this->count;
	}
	
	/* Check if collection is empty */
	public function isEmpty(){
		return ($this->count==0);
		
	}
	
	/* Check if collection is not empty */
	public function isNotEmpty(){
		return ($this->count>0);
	}
	

	/**
	* Return field values of the contained objects as an array.
	* @param string $field Attribute of the model objects.
	* @return array Array that contains the attribute $field for each object.
	*/
	public function pi($field)
	{
		$fields = array();
		foreach($this->positionIndex as $object){
			$fields[ ] = $object->getAttribute($field);
		}
		return $fields;
	}
	
	
	/**
	* Return the different field values of the contained objects as an array.
	* @param string $field Attribute of the model objects.
	* @return array Array that contains the attribute $field for each object.
	*/
	public function distinctPi($field)
	{
		$fields = array();
		foreach($this->positionIndex as $object){
			$fields[$object->getAttribute($field)] = true;
		}
		return array_keys($fields);
	}
	
	
	/**
	* Return field values of the contained objects as an array.
	 * Alias of pi.
	* @param string $field Attribute of the model objects.
	* @return array Array that contains the attribute $field for each object.
	*/
	public function getField($field)
	{
		return $this->pi($field);
	}
	
	
	/**
	 * Reduce the values of the collection.
	 * @param function Function that will be executed for all objects of the collection.
	 * @param mixed $initAccumulatorValue Init accumulator value
	 * @return mixed Result of reducing all objects of this collection.
	 * 	 */
	public function reduce($operator, $initAccumulatorValue=0)
	{
		$accumulator = $initAccumulatorValue;
		foreach($this->positionIndex as $object){
			$accumulator = $operator($accumulator, $object);
		}
		return $accumulator;
	}
	
	
	/**
	 * Diff between two collections
	 * @param object $collection Collection two.
	 * @return object $collection Collection with the elements of this collection
	 * that are not in the collection $collection.
	 * */
	public function difference($collection)
	{
		$difference = new Collection();
		foreach($this->positionIndex as $object)
		{
			$selector = function($o)use($object){
				return (Collection::getObjectSignature($object) == Collection::getObjectSignature($object));
			};
			if(!$collection->findObject($selector)){
				$difference->add($object);
			}
		}
		return $difference;
	}
	

	/**
	 * Intersection
	 * @param object $collection Collection two.
	 * @return object $collection Collection with the elements on this collection
	 * that are also in the collection $collection.
	 * */
	public function intersection($collection)
	{
		$intersection = new Collection();
		foreach($this->positionIndex as $object)
		{
			$selector = function($o)use($object){
				return (Collection::getObjectSignature($o) === Collection::getObjectSignature($object));
			};
			if($collection->findObject($selector)){
				$intersection->add($object);
			}
		}
		// Collection with objects of both collections
		return $intersection;
	}
	
	
	/**
	* Add one object to the collection
	* @param object $object Object to be added.
	* @return integer Position of new object.
	*/
	public function add($object)
	{
		// Add the object
		$this->positionIndex[] = $object;
		// Position of new element
		$positionOfNewElement = $this->count;
		// Increment the size of the collection
		$this->count += 1;
		// Position of new element
		return $positionOfNewElement;
	}
	
	
	/**
	* Add all objects of a collection to this collection.
	* @param object $collection Collection that will have all its objects added to this collection as well.
	* @return object Reference to this.
	* */
	public function addAll($collection)
	{
		// If the paremeter is an array, convert it to a collection
		if(is_array($collection)){
			$collection = new Collection($collection);
		}
		// Add each element of the collection to this collection
		foreach($collection->positionIndex as $object){
			$this->positionIndex[] = $object;
		}
		// Update size of the collection
		$this->count += $collection->count;
		// Allow method chaining
		return $this;
	}
	
	
	/**
	* Add one object to the collection if is not null.
	* @param mixed $object Object to be added or null.
	* @return integer Position of new object or false if $object was null.
	*/
	public function addIfNotNull($object)
	{
		if(!is_null($object)){
			return $this->add($object);
		}
		return false;
	}
	
	
	/**
	* Add one object to the collection if is really an object. Its class can also be specified.
	* @param mixed $object Object to be added or null.
	* @param mixed $className Class that must have the object to be added.
	* @return integer Position of new object or false if $object was not an object.
	*/
	public function addIfIsObject($object, $className=null)
	{
		// If $object is not an object, don't add it and return false
		if(!is_object($object)){
			return false;
		}
		// If there is no className, don't check its class
		if(is_null($className)){
			return $this->add($object);
		}
		// Check object class
		if(is_string($className) and get_class($object)==$className){
			return $this->add($object);
		}
		// Otherwise, $object was not right, return false
		return false;
	}
	
	
	/**
	* Add one object to the collection if verifies a predicate.
	* @param mixed $object Object to be added or null.
	* @param mixed $booleanPredicate Boolean predicated that $object must comply.
	* @return integer Position of new object or false if $object was not an object.
	*/
	public function addIf($object, $booleanPredicate)
	{
		// Check predicate
		if(call_user_func($booleanPredicate,$object))
		{
			return $this->add($object);
		}
		return false;    
	}
  
	
	/**
	* Get the object at position $index.
	 * If the $index is greater than this collection size, thrown an exception.
	* @param integer $index Index position.
	* @return object Object at position $index.
	*/
	public function get($index)
	{
		// Accept negative indexes
		if($index < 0){
			$index = ($this->count+$index);
		}
		// If we are in the limits of the collection
		if($index < $this->count){
			return $this->positionIndex[$index];
		}
		throw new \OutOfBoundsException("{$index} is out of collection limits");
	}
	
	
	/**
	* Get all object that verify a predicate.
	* @param function $booleanSelectionPredicate Function that return true or false for each object.
	* @return object Objeto de tipo Collection with a partition of the current collection based on the objects that comply the predicate.
	*/
	public function getAll($booleanSelectionPredicate=null)
	{
		// If there is no predicate, copy the collection
		if($booleanSelectionPredicate==null){
			return new Collection($this);
		}
		$partition = new Collection();
		foreach($this->positionIndex as $object){
			if($booleanSelectionPredicate($object)){
				$partition->add($object);
			}
		}
		return $partition;
	}
	
	
	/**
	* Get all object that verify a predicate.
	* Alias of getAll.
	* @param function $booleanSelectionPredicate Function that return true or false for each object.
	* @return object Objeto de tipo Collection with a partition of the current collection based on the objects that comply the predicate.
	*/
	public function getAllIf($booleanSelectionPredicate=null)
	{
		return $this->getAll($booleanSelectionPredicate);
	}
	
	
	/**
	* Return the first collection element.
	* Throws an exception if collection is empty.
	* @return object First element of the collection.
	*/
	public function getFirstElement(){
		return $this->get(0);
	}
	
	
	/**
	* Return the first collection element.
	* Throws an exception if collection is empty.
	* @return object First element of the collection.
	*/
	public function getFirst(){
		return $this->getFirstElement();
	}
	
	
	/**
	* Return the last collection element.
	* Throws an exception if collection is empty.
	* @return object Last element of the collection.
	*/
	public function getLastElement(){
		return $this->get(-1);
	}
	
	
	/**
	* Return the last collection element.
	* Alias of getLastElement
	* Throws an exception if collection is empty.
	* @return object Last element of the collection.
	*/
	public function getLast(){
		return $this->getLastElement();
	}
	
	
	/**
	* Return a range of the collection.
	* @param array $limit Selection limit.
	* @return object Collection formed by all the selected elements.
	*/
	public function getRange($limit)
	{
		$collection = new Collection();
		// If $limit is not an array, suppose it represents the size of the
		// selection
		if(!is_array($limit)){
			$limit = array(0, $limit);
		}
		// Take elements that are in the limit
		for($i=$limit[0]; $i<$limit[1]; $i++){
			$collection->add( $this->get($i) );
		}
		return $collection;
	}
	
	
	/**
	* Return a range of the collection.
	* Alias of getRange.
	* @param array $limit Selection limit.
	* @return object Collection formed by all the selected elements.
	*/
	public function getLimit($limit){
		return $this->getRange($limit);
	}


	/**
	 * Reassign positions after an element of the collection has been removed.
	 * Computes also the size of the collection (count)
	 * 	 */
	protected function reassignPositions(){
		$newPositionIndex = [];
		for($i=0; $i<$this->count; $i++){
			if(isset($this->positionIndex[$i])){
				$newPositionIndex[] = $this->positionIndex[$i];
			}
		}
		$this->positionIndex = $newPositionIndex;
		$this->count = count($this->positionIndex);
	}
  
	
	/**
	* Remove an object of a collection.
	* @param object $object Object to delete of the collection.
	* @param function Comparison function. If null, == comparison will be used.
	* @return integer|false Former index of the object or false if it didn't exist in the collection.
	*/
	public function removeElement($object, $objectAreEqual=null)
	{
		// Collection is empty, $object is not present
		if($this->count==0){
			return false;
		}
		if(is_null($objectAreEqual)){
			$objectAreEqual = function($a,$b){ return $a == $b; };
		}
		// Loop through all elements until we find what we are looking for
		foreach($this->positionIndex as $index=>$element)
		{
			if($objectAreEqual($element,$object))
			{
				unset($this->positionIndex[$index]);
				$this->reassignPositions();
				return $index;
			}
		}
		return false;
	}
	
	
	/**
	* Remove one element of the collection.
	* @param mixed $index Index or object to delete of the collection.
	* @param boolean $reassignPositions Should we reassign the positions of the collection? True by default.
	* @return object|integer|false Si se pasa un entero, y existe como índice se devuelve el objeto eliminado. Si se pasa un objeto y existe un objeto con ese índice se devuelve el objeto. Si no se encuentra el objeto con el índice o el objeto se devuelve false.
	*/
	public function remove($index, $reassignPositions=true)
	{
		// Collection is empty, $object is not present
		if($this->count==0){
			return false;
		}
		// Delete all elements contained in the collection
		if(is_object($index) and get_class($index)==self::CLASS_NAME){
			return $this->removeAll($index);
		}
		// Delete the object
		if(is_object($index)){
			return $this->removeElement($index);
		}
		// Delete element at $index
		if(isset($this->positionIndex[$index]))
		{
			$element= $this->positionIndex[$index];
			unset($this->positionIndex[$index]);
			if($reassignPositions){
				$this->reassignPositions();
			}
			return $element;
		}
		// Element has not been found, return false
		return false;
	}
  
	
	/**
	* Delete elements of collection in a range.
	* @param integer $first Offset of the interval.
	* @param integer $size Size of the interval.
	* @return integer New size of the collection.
	*/
	public function removeRange($first, $size)
	{
		$min_size = min($this->count, $size);
		for($i=$first; $i<$min_size; $i++){
			unset( $this->positionIndex[ $i ] );
		}
		$this->reassignPositions();
		return $this->count;
	}
	
	
	/**
	* Delete all elements that verify a boolean logic predicate and return them in a new collection.
	* @param function $booleanPredicate Lambda function that will return true or false for each object of this collection.
	* @return object Collection that will contain only the elements that verify the predicate.
	*/	
	public function removeAllIf($booleanPredicate)
	{
		$selected = new Collection();
		foreach($this->positionIndex as $index=>$object)
		{
			// Check if we need to remove the element
			if(call_user_func($booleanPredicate,$object))
			{
				$selected->add($object);
				$this->remove($index, false);				
			}
		}
		$this->reassignPositions();
		return $selected;
	}
	
	
	/**
	 * Delete all elements in $collection
 	 * @param object $collection Collections to find and delete in $this.
 	 * @return object Reference to this.
	 * */
	public function removeAll($collection)
	{
		foreach($collection as $element){
			$this->remove($element);
		}
		return $this;
	}
	
	
	/**
	* Select the unique values of the collection.
	* @return object Objeto Collection with unique values.
	*/
	public function uniqueValues()
	{
		$newCollection = new Collection($this);
		// Use a hash table to delete repeated elements
		$hashIndex = array();
		foreach($newCollection->positionIndex as $object){
			$hashIndex[self::getObjectSignature($object)] = $object;
		}
		// Return a new collection with unique values
		$newCollection->positionIndex = array_values($hashIndex);
		$newCollection->count = count($newCollection->positionIndex);
		return $newCollection;
	}

	
	/**
	* Select the unique values of the collection.
	* Alias of uniqueValues.
	* @return object Objeto Collection with unique values.
	*/
	public function unique(){
		return $this->uniqueValues();
	}
	
	
	/**
	* Return an array with the elements of the collection.
	* @return array Array with the elements of the collection.
	*/	
	public function toArray()
	{
		return $this->positionIndex;
	}
	

	/**
	* Join two collections in a new one
	* @param object $collection Collection to be joined with the current one.
	* @return object New collection formed by current collection and $collection.
	*/
	public function join($collection)
	{
		$joined = new Collection($this);
		foreach($collection as $element)
		{
			$joined->positionIndex[] = $element;
			$joined->count++;
		}
		return $joined;
	}
	
	
	/**
	* Execute a function to all elements of the collection.
	* @param function $function Lambda function. Must return an object.
	* @return object New collection object.
	*/
	public function each($function)
	{
		$newCollection = new Collection();
		foreach($newCollection->positionIndex as $key=>$element){
			$newCollection->add($function($element));
		}
		return $newCollection;
	}
	
	
	/**
	* Find an object in the collection.
	* @param function lambda function that return true if the object is the one we are looking for.
	* @return mixed Object if element found, false otherwise.
	*/
	public function findObject($lambdaSelector)
	{
		foreach($this->positionIndex as $pk=>$object)
		{
			if($lambdaSelector($object)){
				return $object;
			}
		}
		return false;		
	}
	
	
	/**
	* Return a new sorted collection according to the predicate $isLessThan
	* @param function $isLessThan Comparison predicate. Given $idLessThan = function(a,b){ if($a < $b){ return -1; }; if($a == $b){ return 0; }; return 1; }
	* @return New sorted collection.
	*/
	public function sort($isLessThan)
	{
		$newPositionIndex = $this->positionIndex;
		uasort($newPositionIndex, $isLessThan);
		$sortedCollection = new Collection();
		$sortedCollection->positionIndex = $newPositionIndex;
		$sortedCollection->count = $this->count;
		return $sortedCollection;
	}
	
			
	/************************************************************************************/
	/* Iterator interface */
	public function rewind()
	{
		reset($this->positionIndex);
	}
  
	public function current()
	{
		$var = current($this->positionIndex);
		return $var;
	}
  
	public function key() 
	{
		$var = key($this->positionIndex);
		return $var;
	}
  
	public function next() 
	{
		$var = next($this->positionIndex);
		return $var;
	}
  
	public function valid()
	{
		$key = key($this->positionIndex);
		$var = ($key !== null && $key !== false);
		return $var;
	}
	
	/* ArrayAccess interface */
	
	/**
	 * Check if $offset position exists.
	 */
	public function offsetExists($offset){
		return isset($this->positionIndex[$offset]);
	}
	
	
	/**
	 * Get object in $offset position.
	 */
	public function offsetGet($offset){
		return $this->get($offset);
	}

	
	/**
	 * Assign an object at $offset position
	 * @param integer $offset Index of the element that will be assigned to $value. 
	 * @param object $value Object to assign to position $offset.
	 */
	public function offsetSet($offset, $value){
		$this->positionIndex[$offset] = $value;
	}
	
	
	/**
	 * Delete an element given its position
	 * @param integer $offset Index of the element that will be deleted. 
	 */
	public function offsetUnset($offset){
		unset($this->positionIndex[$offset]);
		$this->reassignPositions();
	}
	
}
?>
