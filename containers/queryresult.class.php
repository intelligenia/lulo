<?php

namespace lulo\containers;

/**
* QueryResult container.
* 
* @author Gerardo Fernandez Rodríguez
* @author Diego J. Romero López
*/

/**
* QueryResult is a multiporpuses container for querying efficiently a database.
*/
class QueryResult implements \ArrayAccess, \Iterator, \Countable {
	
	/** RecordSet of the executed query. Contains internally a cursor to the results */
	protected $recordSet = null;
	
	/** RecordSet size*/
	protected $recordSetSize = null;
	
	/**
	 * Current index. Used in Iterator operations.
	 * */
	protected $currentIndex = 0;
	
	/**
	 * If is null, QueryResult will return arrays, if is a name of a class.
	 * It will call model's factoryFromArray static method for each access to each
	 * element of the recordset.
	 **/
	protected $model = null;
	
	/**
	 * Creates a QueryResult for a recordset and a model.
	 * @param string $recordSet Recordset de AdoDB.
	 * @param string $model Model that will be loaded with this recordset.
	 * If null, it will return arrays.
	 * */
	public function __construct($recordSet, $model=null){
		$this->recordSet = $recordSet;
		$this->recordSetSize = $this->recordSet->RecordCount();
		$this->model = $model;
	}	

	/******************************************************************/
	/******************************************************************/
	/* ArrayAccess interface */
	                
	/**
	 * Informs if $offset position exists.
	 * @param integer $offset Index to test.
	 * @return boolean true if there is an element in $offset position, false otherwise.
	 * */
	public function offsetExists($offset){
		// if offset is not legal
		if(!is_numeric($offset) or $offset < 0){
			return false;
		}
                
		// Test if offset is less than recordset size
		$querySize = $this->recordSetSize;
		return ( $offset < $querySize );
	}
	
	
	/**
	 * Gets an element at position $offset.
	 * @param integer $offset Index of the element to get.
	 * @return mixed Model object if $this->model is not null, array otherwise.
	 * */
	public function offsetGet ($offset){
		// if offset is not legal
		if(!is_numeric($offset) or $offset < 0){
			return false;
		}

		// Test if offset is less than recordset size
		$querySize = $this->recordSetSize;
		if ( $offset >= $querySize ){
			throw new \OutOfRangeException("Position {$offset} si greater than the size of this QueryResult");
		}
		
		// Move pointer to offset position if needed
		if($this->currentIndex != $offset)
		{
			$this->recordSet->Move($offset);
			$this->currentIndex = $offset + 1; 
		}

		// If there is no model specified, return an array
		if(is_null($this->model)){
			return $this->recordSet->fields;
		}
		
		// Otherwise, creates a new object of the model using factoryFromArray
		$model = $this->model;
		return $model::factoryFromArray($this->recordSet->fields);
	}
	
	
	public function offsetSet ($offset , $value){
		throw new \BadFunctionCallException("QueryResults are read-only. This operation is not allowed.");
	}
	
	public function offsetUnset($offset){
		throw new \BadFunctionCallException("QueryResults are read-only. This operation is not allowed.");
	}
	
		
	/******************************************************************/
	/******************************************************************/
	/* Iterator interface */
	
	/**
	 * Get current element.
	 * See offsetGet to understand what can return depending on $this->model value.
	 * 
	 * @return mixed Current element of QueryResult.
	 * 	 */
	public function current(){
		// Get current element
		return $this->offsetGet($this->currentIndex);
	}
	
	/**
	 * Get current position.
	 * @return integer Current position of QueryResult.
	 * 	 */
	public function key(){
		
		return $this->currentIndex;
	}

	/**
	 * Move forward to next element.
	 * 	 */
	public function next(){
		$this->recordSet->MoveNext();
		$this->currentIndex++;
	}
	
	/**
	 * Move cursor to first position.
	 * 	 */
	public function rewind(){
		$this->recordSet->MoveFirst();
		$this->currentIndex = 0;
	}
	
	/**
	 * Is the pointer valid?
	 * @return boolean true if the index is less than the size of the recordset.
	 * 	 */
	public function valid(){
		return ((!$this->recordSet->EOF) and $this->currentIndex < $this->recordSetSize );
	}
	
	/**
	 * Return the size of the recordset.
	 * @return integer Number of elements of the recordset.
	 * 	 */
	public function count(){
		return $this->recordSetSize;
	}
	
}

?>
