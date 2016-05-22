<?php

namespace lulo\containers;

/**
 * Colección de objectos generados por el generador de clases.
 * @author Diego J. Romero López <diego@intelligenia.com>
 * */
class Collection implements \Countable, \Iterator, \ArrayAccess
{
  /** Nombre de la clase colección */
  const CLASS_NAME = "Collection";
	/**
	* Devuelve el nombre de la clase a la que pertenece (el objetivo es la compatibilidad con PHP 5.2).
	* @return string Nombre de la clase de la que es el objeto.
 	*/
	public static function getClassName(){ return self::CLASS_NAME; }
	/////////////////////////////////////////////////////////////////////////////////////////////
	/// Atributos de la colección
	/** Tamaño de la colección */
	protected $count = 0;
	/** Contenido de la colección como tabla hash (como array) */
	//protected $uniqueIndex = array();
	/** Contenido de la colección como array de índices */
	protected $positionIndex = array();
	/** Propiedades comunes para la colección */
	protected $dynamicAttributes = array();
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	/// Métodos mágicos
	/**
	* Llamadas de forma dinámica para cada uno de los elementos de la colección.
	* @param string $name Nombre de la función.
	* @param array $arguments Argumentos de la función.
	*/
	public function _call($name, $arguments)
	{
		$this->each(call_user_func_array($name, $arguments));
	}
	/**
	* Método mágico: comprueba si está asignado el atributo en tiempo de ejecución.
	* @param string $name Nombre del atributo.
	* @return boolean true si está asignado como atributo dinámico, false en otro caso (comportamiento de isset).
	*/
	public function __isset($name){
		return isset($this->dynamicAttributes[$name]);
	}
	/**
	* Método mágico: devuelve un atributo asignado en tiempo de ejecución.
	* @param string $name Nombre del atributo.
	* @return mixed Valor del atributo.
	*/
	public function __get($name){
		if(isset($this->dynamicAttributes[$name]))
			return $this->dynamicAttributes[$name];
		return null;
	}
	/**
	* Método mágico: asigna un atributo dinámico en tiempo de ejecución.
	* @param string $name Nombre del atributo.
	* @param string $value Valor del atributo.
	*/
	public function __set($name, $value){
		$this->dynamicAttributes[$name] = $value;
	}
	/**
	* Método mágico: ejecuta unset sobre un atributo asignado en tiempo de ejecución.
	* @param $name Nombre del atributo.
	*/
	public function __unset($name){
		unset($this->dynamicAttributes[$name]);
	}

	/**
	* Devuelve los datos dinámicos del objeto como array.
	* @return array Array con los datos dinámicos del objeto como pares atributo=>valor.
	*/
	public function getDynamicAttributes(){
		return $this->dynamicAttributes;
	}
	/**
	* Devuelve los datos dinámicos del objeto como array. Sobrecarga de getDynamicAttributes.
	* @return array Array con los datos dinámicos del objeto como pares atributo=>valor.
	*/
	public function getDynamicAttributesAsArray(){
		$dynamicData = $this->getDynamicAttributes();
		if(is_null($dynamicData) or !is_array($dynamicData)) return array();
		return $dynamicData;
	}
	
	public function __toString()
	{
		return json_encode($this);
	}

	/**
	* Devuelve el atributo cuyo nombre se le pasa como parámetro al método.
	* @param string $name Nombre del atributo.
	* @return mixed|null Atributo $name del objeto.
	*/
	public function getAttribute($name){
		if(isset($this->$name)) return $this->$name;
		return null;
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	/// Métodos de clase
	/**
	 * Informa si el objeto que se le pasa como parámetro es una instancia de la clase Collection.
	 * @param object $object Objeto que se desea saber si es de la clase Collection.
	 * @return boolean true si es un objeto de la clase Collection, false en otro caso.
	 * */
	public static function isInstanceOf($object)
	{
		return (is_object($object) and get_class($object)==Collection::CLASS_NAME);
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	/// Métodos protegidos
  
	public static function getObjectSignature($object)
	{
		$pk = $object->getPKAsArray();
		$pk["className"] = $object->getClassName();
		$pk["stack"] = $object->getStack();
		$pk = serialize($pk);
		return $pk;
	}

	/**
	* Constructor de colecciones a partir de objetos.
	* @param mixed $objects Una colección, un objeto, o un array de objetos.
	* */
	public function __construct($objects=null)
	{
		////////////
		// Para cada tipo de dato, hemos de ir realizando conversiones
		////////////////// Es nulo
		if(is_null($objects))
		{
			$this->positionIndex = array();
			$this->count = 0;
			return $this;
		}
		////////////////// Collection
		if(is_object($objects) and get_class($objects)==Collection::CLASS_NAME)
		{
			$this->positionIndex = $objects->positionIndex;
			$this->count = $objects->count;
			return $this;
		}
		////////////////// Arrays
		if(is_array($objects))
		{
			$this->positionIndex = $objects;
			$this->count = count($objects);
			return $this;
		}
		////////////////// objeto generado por model creator
		if(is_object($objects) and is_callable(array($objects,"dbSave")))
		{
			$this->positionIndex = array($objects);
			$this->count = 1;
			return $this;
		}
		////////////////// cualquier otra cosa
		$this->positionIndex = array($objects);
		$this->count = 1;
		return $this;
	}

	/**
	* Constructor de colecciones a partir de objetos.
	* @param mixed $objects Una colección, un objeto, o un array de objetos.
	* @return object Objeto Collection cargado con los objetos.
	* */	
	public static function factory($objects=null)
	{
		return new Collection($objects);
	}
  
  
	/************************************************************************************/
	/* Métodos que consultan el tamaño de la colección */
	public function count(){  return $this->count;  }
	public function isEmpty(){  return ($this->count==0);  }
	public function isNotEmpty(){  return ($this->count>0);  }
	public function length(){  return $this->count;  }
	public function size(){  return $this->count;  }
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	* Devuelve la colección como una tabla hash de pares clave,valor donde la clave es $fieldKey y
	* el valor es el valor del atributo $fieldValue de cada objeto.
	* @param string $fieldKey Campo clave.
	* @param string $fieldValue Campo clave.
	* @return array Array de pares clave,valor según el campo seleccionado en este método.
	*/
	public function asSingleValueHash($fieldKey, $fieldValue)
	{
		if($this->isEmpty())
			return array();
		$hash = array();
		foreach($this->positionIndex as $object)
			$hash[ $object->getAttribute($fieldKey) ] = $object->getAttribute($fieldValue);
		return $hash;
	}
	
	/**
	* Aplica una función a un array.
	*/
	public static function applyFunctionToArray($function, $attributes)
	{
		$acc = 0;
		foreach($attributes as $attribute)
			$acc = $function($acc,$attribute);
		return $acc;
	}
	
	const ALL_ATTRIBUTES = true;
	
	/**
	* Devuelve la colección como una tabla hash de pares clave,valor donde la clave es $fieldKey y
	* el valor es un array con los valores de los campos $fieldValues (si éste es un array), o un
	* único valor si éste es un único valor.
	* @param string $fieldKey Campo clave.
	* @param string $fieldValue Campo clave.
	* @return array Array de pares clave,valor según el campo seleccionado en este método.
	*/
	public function asHash($fieldKey, $fieldValues=null, $operator=null)
	{
		////////////////////////////////////////////////////////////////////////////
		// Si la colección está vacía, devolvemos un array vacío
		if($this->isEmpty()) return array();
		////////////////////////////////////////////////////////////////////////////
		// Si el primer parámetro es una función, la clave se genera con
		// una función
		if(is_callable($fieldKey))
		{
			// Si son todos los atributos, cada objeto genera un elemento
			// en el array con todos los atributos
			if($fieldValues==self::ALL_ATTRIBUTES){
				foreach($this->positionIndex as $object){
					$hash[ $fieldKey($object) ] = $object->getAsArray();
				}
				return $hash;
			}
			// Si hemos pasado como claves a recuperar una función
			// para cada objeto, su valor es el resultado de esa función
			if(is_callable($fieldKey)){
				foreach($this->positionIndex as $object){
					$hash[ $fieldKey($object) ] = $fieldKey($object);
				}
				return $hash;
			}
			return null;
		}
		////////////////////////////////////////////////////////////////////////////
		// Si el parámetro fieldValues es una cadena, devolvemos un hash simple
		if(is_string($fieldValues))
			return $this->asSingleValueHash($fieldKey, $fieldValues);
		////////////////////////////////////////////////////////////////////////////	
		// Si no le pasamos campos, suponemos que queremos un hash de objetos
		if(is_null($fieldValues))
			return $this->objectHash($fieldKey);
		////////////////////////////////////////////////////////////////////////////	
		// Si le pasamos true, suponemos que queremos un hash de arrays con todos los atributos del objeto
		if(is_bool($fieldValues) and $fieldValues==self::ALL_ATTRIBUTES)
		{
			$hash = array();
			foreach($this->positionIndex as $object)
				$hash[ $object->getAttribute($fieldKey) ] = $object->getAsArray();
			return $hash;
		}
		////////////////////////////////////////////////////////////////////////////
		// Si hemos llegado aquí, tenemos que obtener un hash en el que la clave sea $fieldKey
		// y los valores sean $fieldValues
		$hash = array();
		if($operator==null)
		{
			foreach($this->positionIndex as $object)
				$hash[ $object->getAttribute($fieldKey) ] = $object->getAttributes($fieldValues);
			return $hash;
		}
		// En el caso de que el operador sea una cadena con un operador aritmético
		if($operator=="+") $operator = function($a,$b){ return ($a+$b); };
		elseif($operator=="-") $operator = function($a,$b){ return ($a-$b); };
		elseif($operator=="*") $operator = function($a,$b){ return ($a*$b); };
		elseif($operator==".") $operator = function($a,$b){ return ($a.$b); };
		elseif($operator=="and") $operator = function($a,$b){ return ($a and $b); };
		elseif($operator=="or") $operator = function($a,$b){ return ($a or $b); };
		elseif($operator=="xor") $operator = function($a,$b){ return ($a xor $b); };
		// Para cada atributo le aplicamos la operación
		foreach($this->positionIndex as $object)
			$hash[ $object->getAttribute($fieldKey) ] = self::applyFunctionToArray( $operator,  $object->getAttributes($fieldValues) );
		return $hash;
	}
	
	/**
	 * Devuelve un hash de arrays.
	 * */
	public function asArrayHash($fieldKey)
	{
		return $this->asHash($fieldKey, self::ALL_ATTRIBUTES);
	}
	
	/**
	* Sobrecarga de asHash. Ver asHash.
	* @param string $fieldKey Campo clave.
	* @param string $fieldValue Campo clave.
	* @return array Array de pares clave,valor según el campo seleccionado en este método.
	*/
	public function hash($fieldKey, $fieldValues=null, $operator=null)
	{
		return $this->asHash($fieldKey, $fieldValues, $operator);
	}
	
	
	/**
	 * Función que devuelve un hash en el que la generación de claves y la generación de valores
	 * se realiza mediante funciones lambda.
	 * */
	public function functionHash($keyGenerator, $valueGenerator=null){
		$hash = array();
		if(is_null($valueGenerator)){
			$valueGenerator = function($object){
				return $object->str();
			};
		}
		foreach($this->positionIndex as $object){
			$objectKey = $object->$keyGenerator();
			$objectValue = $object->$valueGenerator();
			$hash[ $objectKey ] = $objectValue;
		}
		return $hash;
	}
	
	
	/**
	* Genera un hash a partir de un campo (presumiblemente único), en el que los valores son objetos.
	* @param string $fieldKey Cadena con el nombre del atributo que será clave del hash.
	* @return array Array de pares clave,valor donde la clave serán los valores del atributo $fieldKey de cada objeto y los valores serán los propios objetos.
	*/
	public function objectHash($fieldKey)
	{
		$hash = array();
		foreach($this->positionIndex as $object)
			$hash[ $object->getAttribute($fieldKey) ] = $object;
		return $hash;
	}
	/**
	* Devuelve los valores de un campo como un array. El nombre del método viene del álgebra relacional donde la operación Pi
	* @param string $field Atributo que se desea devolver de la colección de objetos.
	*/
	public function pi($field)
	{
		$fields = array();
		foreach($this->positionIndex as $object)
			$fields[ ] = $object->getAttribute($field);
		return $fields;
	}
	/**
	* Devuelve los valores de un campo como un array. El nombre del método viene del álgebra relacional donde la operación Pi
	* @param string $field Atributo que se desea devolver de la colección de objetos.
	*/
	public function distinctPi($field)
	{
		$fields = array();
		foreach($this->positionIndex as $object)
			$fields[$object->getAttribute($field)] = true;
		return array_keys($fields);
	}
	/**
	* Devuelve los valores de un campo como un array. Sobrecarga del método pi.
	* @param string $field Atributo que se desea devolver de la colección de objetos.
	*/	

	public function getField($field)
	{
		return $this->pi($field);
	}
	
	public function reduce($operator, $initAccumulatorValue=0)
	{
		$accumulator = $initAccumulatorValue;
		foreach($this->positionIndex as $object)
			$accumulator = $operator($accumulator, $object);
		return $accumulator;
	}
	
	/**
	* Agrupa los elementos de la colección por un campo.
	*/
	public function groupByField($field)
	{
		// Caso 1: pasamos una cadena y agrupamos por un atributo
		if(is_string($field))
		{
			$group = array();
			foreach($this->positionIndex as $object)
				$group[ $object->getAttribute($field) ][] = $object;
			return $group;	
		}
		
		// Caso 2: pasamos un array y agrupamos por un atributo
		if(is_array($field))
		{
			$dynamicAttributeName = key($field);
			$dynamicAttributeValue = $field[$dynamicAttributeName];
			$group = array();
			foreach($this->positionIndex as $object)
			{
				$dynamicAttributes = $object->getAttribute($dynamicAttributeName);
				$group[ $dynamicAttributes[$dynamicAttributeValue] ][] = $object;
			}
			return $group;
		}
		
		return null;
	}
	
	/************************************************************************************/
	/* Diferencia entre dos colecciones */
	
	/**
	 * Ejecuta una diferencia entre dos colecciones.
	 * Es decir, devuelve una colección en la que los elementos son los mismos
	 * que los de la colección actual con los elementos de la colección
	 * parámetro ($collection) eliminandos.
	 * @param object $collection Objeto colección.
	 * @return object $collection Objeto colección con los elementos que
	 * están en la colección actual pero no en $collection.
	 * */
	public function difference($collection)
	{
		$difference = new Collection();
		foreach($this->positionIndex as $object)
		{
			$selector = function($o)use($object){
				return (Collection::getObjectSignature($object) == Collection::getObjectSignature($object));
			};
			if(!$collection->findObject($selector))
				$difference->add($object);
		}
		return $difference;
	}
	
	/************************************************************************************/
	/* Intersección entre dos colecciones */
	
	/**
	 * Intersección entre dos colecciones.
	 * @param object $collection Objeto Collection del que se quiere ver si tiene elementos comunes con la colección actual.
	 * @return object Objecto Collection con los objetos comunes entre las dos 
	 * */
	public function intersection($collection)
	{
		$intersection = new Collection();
		// Para cada objeto de la colección actual, vemos si
		// tenemos un objeto con el mismo valor en la colección que se
		// pasa como parámetro
		foreach($this->positionIndex as $object)
		{
			// Función de selección de cada objeto de la colección actual
			// en la colección parámetro
			$selector = function($o)use($object){
				return (Collection::getObjectSignature($o) === Collection::getObjectSignature($object));
			};
			// Llamada a la búsqueda del objeto
			if($collection->findObject($selector))
				$intersection->add($object);
		}
		// Devolvemos los objetos comunes entre ambas colecciones
		return $intersection;
	}
	
	/************************************************************************************/
	/* Métodos que añaden elementos a la colección */
	
	/**
	* Añade un objeto a la colección.
	* @param object $object Objeto
	* @return integer|false Se devuelve la posición del nuevo elemento insertado o false si no se ha añadido.
	*/
	public function add($object)
	{
		// Metemos el elemento en el índice de posiciones
		$this->positionIndex[] = $object;
		// Obtenemos la posición del nuevo elemento
		$positionOfNewElement = $this->count;
		// Añadimos 1 al número de elementos actuales
		$this->count += 1;
		// Devolvemos la posición del nuevo elemento
		return $positionOfNewElement;
	}
	/**
	* Añade todos los elementos de la colección a la colección actual.
	* @param object $collection Colección cuyos elementos se añadirán a la colección actual. También permite pasarle un array (que convertirá a un objeto Collection).
	* @return object Objeto actual, útil para encadenar.
	* */
	public function addAll($collection)
	{
		// Si lo que le pasamos es una colección, intenta convertirlo a objeto Colección
		if(is_array($collection))
			$collection = new Collection($collection);
		// Para cada elemento de la colección, lo añadimos a la colección actual
		foreach($collection->positionIndex as $object)
			$this->positionIndex[] = $object;
		// Actualizamos el número de elementos
		$this->count += $collection->count;
		// Permitimos encadenar este método.
		return $this;
	}
	/**
	* Añade un elemento a la colección si el elemento no es nulo.
	* @param object $object Objeto que se añadirá si no es nulo.
	* @return integer|false Se devuelve la posición del nuevo elemento insertado o false si no se ha añadido.
	*/
	public function addIfNotNull($object)
	{
		if(!is_null($object))
			return $this->add($object);
		return false;
	}
	/**
	* Añade un elemento a la colección si el elemento es un objeto (opcionalmente se puede restringir a objetos de una determinada clase).
	* @param object $object Objeto que se añadirá si es un objeto o (si $className no es null) si además es de la clase $className.
	* @param string $className Nombre de la clase de la que ha de ser el objeto $object para añadirse a la colección.
	* @return integer|false Se devuelve la posición del nuevo elemento insertado o false si no se ha añadido.
	*/
	public function addIfIsObject($object, $className=null)
	{
		// Si lo que le intentamos añadir no es un objeto, devolvemos false
		if(!is_object($object))
			return false;
		// Si no le pasamos un nombre de clase,
		// no tenemos que comprobar si es una instancia de esa clase
		if(is_null($className))
			return $this->add($object);
		// Si le indicamos el nombre de una clase y el objeto es instancia de esa
		// clase, lo añadimos a la colección
		if(is_string($className) and get_class($object)==$className)
			return $this->add($object);
		// En cualquier otro caso, por ejemplo,  si el nombre de la clase no es el
		// que esperamos, devolvemos false
		return false;
	}
	/**
	* Añade un elemento a la colección si cumple un predicado lógico pasado como función lambda.
	* @param object $object Objeto que se añadirá si cumple el predicado lógico $booleanPredicate.
	* @param object $booleanPredicate Objeto Closure que implementa el predicado lógico que ha de verificar $object para ser añadido.
	* @return integer|false Se devuelve la posición del nuevo elemento insertado o false si no se ha añadido.
	*/
	public function addIf($object, $booleanPredicate)
	{
		// Comprobamos mediante un predicado lógico si se ha de añadir el elemento
		if(call_user_func($booleanPredicate,$object))
		{
			return $this->add($object);
		}
		return false;    
	}
  
	/************************************************************************************/
	/* Obtención de elementos */
	
	/**
	* Obtiene un elemento de la colección que tenga el índice pasado como parámetro.
	* @param integer $index Índice del elemento en la colección.
	* @return object|null Objeto al que referencia el index $index en esta colección. Si no existe el index $index, se devuelve null.
	*/
	public function get($index)
	{
		// Si el índice es negativo, devolvemos el elemento $this->count+$index
		if($index<0)	$index = ($this->count+$index);
		// Si estamos dentro de los límites
		if($index < $this->count)
			return $this->positionIndex[$index];
		// Si estamos fuera de los límites lanzamos una excepción y un aviso al usuario
		$message = "Collection: $index ".tlt("se encuentra fuera de los límites del array");
		throw new Exception($message);
		trigger_error($message, E_USER_WARNING);
		return null;
	}
	/**
	* Devuelve una colección con una partición de la colección actual.
	* @param function $booleanSelectionPredicate Predicado booleano de selección de objetos. Si es null se ignora.
	* @return object Objeto de tipo Collection con una partición de la colección actual con elementos que cumplen el predicado.
	*/
	public function getAll($booleanSelectionPredicate=null)
	{
		// Si no hay predicado, devolvemos una copia de la colección actual
		if($booleanSelectionPredicate==null)
			return new Collection($this);
		// Eliminamos los elementos que NO cumplen el predicado de la colección actual
		return $this->removeAllIfNot($booleanSelectionPredicate);
	}
	/**
	* Devuelve una colección con una partición de la colección actual. Sobrecarga de getAll.
	* @param function $booleanSelectionPredicate Predicado booleano de selección de objetos. Si es null se ignora.
	* @return object Objeto de tipo Collection con una partición de la colección actual con elementos que cumplen el predicado.
	*/
	public function getAllIf($booleanSelectionPredicate=null)
	{
		return $this->getAll($booleanSelectionPredicate);
	}
	/**
	* Devuelve el primer elemento.
	* NOTA: tomamos el primer elemento como elemento 0.
	* @return object|null Primer elemento de la colección o null si éste no existe.
	*/
	public function getFirstElement(){
		reset($this->positionIndex);
		$first_key = key($this->positionIndex);
		return $this->positionIndex[$first_key];
	}
	/**
	* Devuelve el primer elemento. Sobrecarga de getFirstElement.
	* NOTA: tomamos el primer elemento como elemento 0.
	* @return object|null Primer elemento de la colección o null si éste no existe.
	*/
	public function getFirst(){	return $this->getFirstElement();	}
	/**
	* Devuelve el último elemento.
	* NOTA: tomamos el primer elemento como elemento -1.
	* @return object|null Último elemento de la colección o null si éste no existe.
	*/
	public function getLastElement(){	return $this->get(-1);	}
	/**
	* Devuelve el último elemento. Sobrecarga de getLastElement.
	* NOTA: tomamos el primer elemento como elemento -1.
	* @return object|null Último elemento de la colección o null si éste no existe.
	*/
	public function getLast(){	return $this->getLastElement();	}
	/**
	* Devuelve un rango de elementos de la colección como otra colección.
	* @param array $limit Límite de selección.
	* @return object Collection formada por los elementos seleccionados mediante el límite.
	*/
	public function getRange($limit)
	{
		$collection = new Collection();
		if(!is_array($limit))
			$limit = array(0, $limit);
		// Cogemos los elementos que están en el intervalo necesario
		// y los añadimos a otra colección
		for($i=$limit[0]; $i<$limit[1]; $i++)
			$collection->add( $this->get($i) );
		return $collection;
	}
	
	/**
	* Devuelve un rango de elementos de la colección como otra colección. Sobrecarga de getRange.
	* @param array $limit Límite de selección.
	* @return object Collection formada por los elementos seleccionados mediante el límite.
	*/
	public function getLimit($limit){ return $this->getRange($limit); }
	
	/************************************************************************************/
	/* Métodos que eliminan elemenetos de la colección */
  
	/**
	* Elimina un objeto de la colección.
	* @param object $object Objeto que se desea eliminar de la colección.
	* @return integer|false Índice que tenía el elemento en la colección (si existía). En otro caso, devuelve false.
	*/
	public function removeElement($object)
	{
		if($this->count==0)	return false;
		// Ciclamos por los elementos hasta que encontremos al elemento que es igual que el objeto.
		foreach($this->positionIndex as $index=>$element)
		{
			if($element == $object)
			{
				unset($this->positionIndex[$index]);
				$this->count--;
				return $index;
			}
		}
		return false;
	}
	/**
	* Elimina de la colección un elemento.
	* @param integer|object $index Índice del elemento a eliminar. U objeto a eliminar.
	* @return object|integer|false Si se pasa un entero, y existe como índice se devuelve el objeto eliminado. Si se pasa un objeto y existe un objeto con ese índice se devuelve el objeto. Si no se encuentra el objeto con el índice o el objeto se devuelve false.
	*/
	public function remove($index)
	{
		// Si la colección está vacía, devuelve false, ya que no se elimina
		// nada
		if($this->count==0)	return false;
		// Eliminación de todos los objetos que pertenezcan a la colección
		// que se pasa como parámetro
		if(is_object($index) and get_class($index)==self::CLASS_NAME)
			return $this->removeAll($index);
		// Eliminación según el objeto que sea
		if(is_object($index))
			return $this->removeElement($index);
		// Eliminación según la posición
		if(isset($this->positionIndex[$index]))
		{
			$element= $this->positionIndex[$index];
			unset($this->positionIndex[$index]);
			$this->count--;
			return $element;
		}
		return false;
	}
  
	/**
	* Elimina de la colección un rango de elementos.
	* @param integer $first Índice del primer elemento a eliminar.
	* @param integer $size Tamaño de la porción a eliminar desde el elemento con índice $first.
	* @return integer Tamaño actual de la colección.
	*/
	public function removeRange($first, $size)
	{
		$numberOfDeletedElements = 0;
		$min_size = min($this->count, $size);
		for($i=$first; $i<$min_size; $i++)
			unset( $this->positionIndex[ $i ] );
		$numberOfDeletedElements = ($size - $first);
		$this->count = $this->count - $numberOfDeletedElements;
		return $this->count;
	}
	/**
	* Elimina los elementos de la colección que cumplen un predicado lógico pasado como función lambda.
	* @param function $booleanPredicate Función lambda que ha de devolver true o false para cada elemento en función del predicado lógico contenido.
	* @param array|mixed $extraParametersAsArray Array con parámetros extras pasados en tiempo de llamada a esta función.
	* @return object Objeto colección que contendrá los elementos que cumplen el predicado lógico.
	*/	
	public function removeAllIf($booleanPredicate,$extraParametersAsArray=null)
	{
		$selected = new Collection($this);
		foreach($selected->positionIndex as $index=>$object)
		{
			// Comprobamos mediante un predicado lógico si se ha de eliminar el elemento
			// Si el predicado lógico es verdadero, eliminamos el elemento
			if(call_user_func($booleanPredicate,$object,$extraParametersAsArray))
			{
				$selected->remove($index);
			}
		}
		$selected->count = count($selected->positionIndex);
		return $selected;
	}
	
	/**
	 * Elimina todos los elementos que aparezcan
 	 * @param object $collection Objeto colección cuyos elementos se van a eliminar de la colección actual.
 	 * @return object Objeto actual sin los elementos.
	 * */
	public function removeAll($collection)
	{
		foreach($collection as $element)
			$this->remove($element);
		return $this;
	}
	
	/**
	* Elimina los elementos de la colección que NO cumplen un predicado lógico pasado como función lambda.
	* @param function $booleanPredicate Función lambda que ha de devolver true o false para cada elemento en función del predicado lógico contenido.
	* @param array|mixed $extraParametersAsArray Array con parámetros extras pasados en tiempo de llamada a esta función.
	* @return object Objeto colección que contendrá los elementos que NO cumplen el predicado lógico.
	*/		
	public function removeAllIfNot($booleanPredicate,$extraParametersAsArray=null)
	{
		$selected = new Collection($this);
		foreach($selected->positionIndex as $index=>$object)
		{
			// Comprobamos mediante un predicado lógico si se ha de eliminar el elemento
			// Si el predicado lógico es falso, eliminamos el elemento
			if(!call_user_func($booleanPredicate,$object,$extraParametersAsArray))
			{
				$selected->remove($index);
			}
		}
		$selected->count = count($selected->positionIndex);
		return $selected;
	}

	/************************************************************************************/
	/* Métodos que eliminan elemenetos de la colección */
	
	/**
	* Genera una nueva colección cuyos valores son únicos, eliminando objetos repetidos.
	* @return object Objeto Collection con los valores únicos.
	*/
	public function uniqueValues()
	{
		$newCollection = new Collection($this);
		// Mediante una tabla hash eliminamos todos los objetos repetidos
		$hashIndex = array();
		foreach($newCollection->positionIndex as $object)
			$hashIndex[self::getObjectSignature($object)] = $object;
		// Asignamos los nuevos valores de índice de posición y número de elementos
		$newCollection->positionIndex = array_values($hashIndex);
		$newCollection->count = count($newCollection->positionIndex);
		return $newCollection;
	}

	/**
	* Genera una nueva colección cuyos valores son únicos, eliminando objetos repetidos. Alias de uniqueValues.
	* @return object Objeto Collection con los valores únicos.
	*/
	public function unique(){ return $this->uniqueValues(); }
	
	/**
	* Genera una nueva colección cuyos valores son únicos, eliminando objetos repetidos.
	* Sobrecarga de uniqueValues.
	* @return object Objeto Collection con los valores únicos.
	*/	
	public function removeRepeated(){ return $this->uniqueValues(); }
	/**
	* Genera una nueva colección cuyos valores son únicos, eliminando objetos repetidos.
	* Sobrecarga de uniqueValues.
	* @return object Objeto Collection con los valores únicos.
	*/	
	public function removeRepeatedElements(){ return $this->uniqueValues(); }
	
	/************************************************************************************/
	/* Métodos profundos: ejecutan operaciones basándose no en las referencias de
	 * los objetos, sino en su contenido. */
	/**
	 * Hace que los 
	 * */
	 /*
	public function deepUniqueValues()
	{
		$newCollection = new Collection();
		foreach($this->positionIndex as $object)
			$newCollection->add($object);
		return $newCollection;
	}
	
	public function deepUnique()
	{
		return $this->deepUniqueValues();
	}*/
	
	/**
	* Devuelve un array con los elementos de la colección.
	* @return array Array con los elementos de la colección.
	*/	
	public function toArray($attributes=null)
	{
		// Si no le pasamos atributos, se devuelve el array con los elementos
		if($attributes==null or count($attributes)==0)
			return $this->positionIndex;
		// Si le pasamos una cadena, implica que queremos devolver un array cuyos elementos
		// sean cada uno de los atributos con ese nombre de cada objeto.
		if(is_string($attributes))
		{
			if($attributes[0]=="+")
			{
				$array = array();
				$attributes = str_replace("+","",$attributes);
				if(strpos($attributes,",")!==false)
					$attributes = preg_split("/\s*,\s*/", $attributes);
				else
					$attributes = [$attributes];
				foreach($this->positionIndex as $object)
				{
					$objectArray = $object->getAsArray();
					foreach($attributes as $attribute)
					{
						$attributeObject = $object->getAttribute($attribute);
						if(is_object($attributeObject) and Collection::isInstanceOf($attributeObject))
							$attributeObject = $attributeObject->toArray();
						$objectArray[$attribute] = $attributeObject;
					}
					$array[] = $objectArray;
				}
				return $array;
			}
			$attribute = $attributes;
			$array = array();
			foreach($this->positionIndex as $object)
				$array[] = $object->getAttribute($attribute);
			return $array;
		}
		// Si le pasamos una cadena, implica que queremos devolver un array cuyos elementos
		// sean arrays en los que se incluyan cada uno de los atributos (cuyos nombres se pasan en el array) de cada objeto.
		if(is_array($attributes) and count($attributes)>0)
		{
			$array = array();
			foreach($this->positionIndex as $object)
			{
				$objectArray = array();
				foreach($attributes as $attribute)
					$objectArray[$attribute] = $object->getAttribute($attribute);
				$array[] = $objectArray;
			}
			return $array;
		}
		
		// Si le pasamos una función, implica que queremos devolver un array cuyos elementos
		// sean obtenidos mediante la aplicación de esa función al objeto
		if(is_callable($attributes)){
			$array = array();
			foreach($this->positionIndex as $object)
			{
				$array[] = $attributes($object);
			}
			return $array;
		}
	}
	
	/************************************************************************************/
	/**
	* Une las dos colecciones en una nueva. No se preocupa de la inserción de elementos repetidos.
	* @param object $collection Otra colección que queremos unir a la actual para devolver una nueva.
	* @return object Nueva colección con los elementos de la actual y de la que se pasa como parámetro.
	*/
	public function join($collection)
	{
		if(!isset($this) or is_null($this))
			return self::joinCollections( func_get_arg(0), func_get_arg(1) );
		
		$joined = new Collection($this);
		foreach($collection as $element)
		{
			$joined->positionIndex[] = $element;
			$joined->count++;
		}
		return $joined;
	}
	/**
	* Une las dos colecciones y devuelve una nueva.
	* @param object $collection1 Primera colección a unir.
	* @param object $collection2 Segunda colección a unir.
	* @return object Collection formada por los elementos de $collection1 y $collection2.
	*/
	public static function joinCollections($collection1, $collection2)
	{
		return $collection1->join($collection2);
	}
	/**
	* Ejecuta una función lambda para cada elemento de la colección, cambiando cada uno de éstos por el resultado de la función lambda.
	* NOTA: se MODIFICA todos y cada uno de los elementos de la colección asignando a cada uno el resultado de la llamada de la función lambda con él como parámetro.
	* @param function $function Función lambda que se aplica a cada elemento.
	* @param mixed $extraParameters Parámetros extra de la función lambda.
	* @return object Objeto colección al que se le han modificado todos los elementos.
	*/
	public function each($function,$extraParameters=null)
	{
		$newCollection = new Collection($this);
		foreach($newCollection->positionIndex as $key=>&$element)
			call_user_func($function,$element,$extraParameters);
		return $newCollection;
	}
	/**
	* Encuentra un objeto dadas unas condiciones concretas
	* @param function Función lambda que contiene el selector del objeto de la colección.
	* @return mixed Objeto de la colección o false si no se encuentra en la colección ningún objeto que cuadre con el selector.
	*/
	public function findObject($lambdaSelector)
	{
		foreach($this->positionIndex as $pk=>$object)
		{
			if($lambdaSelector($object))
				return $object;
		}
		return false;		
	}
	/**
	* Ordenamos la colección según un predicado lógico de comparación de menor. La colección está modificada. ES MODIFICADO.
	* @param function $isLessThan Predicado de comparación entre elementos de la colección. Este predicado devuelve -1 (si el elemento de la izquierda es menor que el otro), 0 (si son iguales) y 1 (si el elemento de la derecha es menor que el de la izquierda).
	* @return @object Referencia al objeto ordenado.
	*/
	public function sort($isLessThan)
	{
		uasort($this->positionIndex, $isLessThan);
		return $this;
	}
	
	/**
	* Ordenamos la colección a la inversa de un predicado lógico de comparación de menor. La colección está modificada. ES MODIFICADO.
	* @param function $isLessThan Predicado de comparación entre elementos de la colección. Este predicado devuelve -1 (si el elemento de la izquierda es menor que el otro), 0 (si son iguales) y 1 (si el elemento de la derecha es menor que el de la izquierda).
	* @return @object Referencia al objeto ordenado.
	*/
	public function rsort($isLessThan)
	{
		$isGreaterThan = function($e1,$e2)use($isLessThan){
			return !($isLessThan($e1,$e2));
		};		
		return $this->sort($isGreaterThan);	
	}
	
	/**
	* Ordena según el valor de un campo. El ordenado por defecto hasta ahora era ASC y sigue manteniendose para la compatibilidad
	* 
	* @param string nombre del campo por el que quieres ordenar
	* @param string orden que quieres seguir puede ser ASC o DESC
	*/
	public function sortByField($field, $order="ASC")
	{
		return $this->sort(function($a,$b)use($field, $order){
			$aField = $a->getAttribute($field);
			$bField = $b->getAttribute($field);
			if($aField==$bField) return 0;
			if("ASC"==strtoupper($order)){
				if($aField<$bField) return -1;
				if($aField>$bField) return 1;
			}
			else{
				if($aField>$bField) return -1;
				if($aField<$bField) return 1;
			}
			return 0;
		});
	}
	
	public function transformEachElement($function)
	{
		$collection = array();
		foreach($this->positionIndex as $key=>$element)
			$collection[$key] = call_user_func($function,$element);
		return new Collection($collection);
	}
	
	public function transformAll($function)
	{
		return $this->transformEachElement($function);
	}
	
	public function asText($function, $separator=", ", $lastSeparator=" y ", $voidText="")
	{
		if($this->isEmpty())	return $voidText;
		if($this->count()==1)
			return call_user_func($function,$this->getFirstElement());
		$text = "";
		$values = array();
		foreach($this->positionIndex as $key=>$element)
			$values[] = call_user_func($function,$element);
		// Saca el último elemento que tiene un separador distinto
		$lastValue = array_pop($values);
		// Pegada de los elementos
		$text = implode($separator,$values);
		$text .= $lastSeparator.$lastValue;			
		return $text;		
	}
	/*
	public function isTrue($booleanPredictate,$booleanPredictateSelector,$extraParameters=null)
	{
		return $this->isTrueThat($booleanPredictate,$booleanPredictateSelector,$extraParameters);
	}
	
	public function isTrueThat($booleanPredictate,$booleanPredictateSelector,$extraParameters=null)
	{
		$selected = $this->getAll($booleanPredictateSelector);
	}
	*/
	
	
	/**
	 * Serializa la colección, devolviendo un array de arrays.
	 * @return array Array en el que cada elemento es un array que representa la serialización de cada objeto.
	 * */
	public function serialize($serializer=null){
		$serialized_data = [];
		if(is_null($serializer)){
			foreach($this->positionIndex as $object){
				$serialized_data[] = $object->serialize();
			}
		}else{
			foreach($this->positionIndex as $object){
				$serialized_data[] = $serializer($object);
			}
		}
		return $serialized_data;
	}
	
	/************************************************************************************/
	/* Implementación de la interfaz Iterator */
	public function rewind()
	{
		//echo "rewinding\n";
		reset($this->positionIndex);
	}
  
	public function current()
	{
		$var = current($this->positionIndex);
		//echo "current: $var\n";
		return $var;
	}
  
	public function key() 
	{
		$var = key($this->positionIndex);
		//echo "key: $var\n";
		return $var;
	}
  
	public function next() 
	{
		$var = next($this->positionIndex);
		//echo "next: $var\n";
		return $var;
	}
  
	public function valid()
	{
		$key = key($this->positionIndex);
		$var = ($key !== null && $key !== false);
		//var_dump("valid: ", $var, "porque key es ", $key);
		//var_dump($this->positionIndex);
		return $var;
	}
	
	/************************************************************************************/
	/* Implementación de la interfaz ArrayAccess */
	
	/**
	 * Comprueba si existe el objeto en la posición $offset.
	 * 
	 * Operación necesaria para la implementación de ArrayAccess.
	 * Evitar su uso siempre que se pueda.
	 * 
	 * @param integer $offset Índice cuyo elemento se desea asignar. 
	 * @return boolean True si existe un elemento en la posición $offset, False en otro caso.
	 */
	public function offsetExists($offset){
		return isset($this->positionIndex[$offset]);
	}
	
	
	/**
	 * Obtiene el objeto en la posición $offset.
	 * 
	 * Si el $offset es mayor que el tamaño de la colección,
	 * lanza una excepción.
	 * 
	 * Operación necesaria para la implementación de ArrayAccess.
	 * Evitar su uso siempre que se pueda.
	 * 
	 * @param integer $offset Índice cuyo elemento se desea asignar. 
	 * @return object Objeto de la colección en la posición $offset.
	 */
	public function offsetGet($offset){
		return $this->get($offset);
	}

	
	/**
	 * Asigna un objeto en la posición $offset.
	 * 
	 * Operación necesaria para la implementación de ArrayAccess.
	 * Evitar su uso siempre que se pueda.
	 * 
	 * @param integer $offset Índice cuyo elemento se desea asignar. 
	 * @param object $value Objeto a asignar en posición $offset.
	 */
	public function offsetSet($offset, $value){
		$this->positionIndex[$offset] = $value;
	}
	
	
	/**
	 * Elimina el elemento en la posición $offset.
	 * AVISO: no recalcula los índices del objeto Collection.
	 * 
	 * Operación necesaria para la implementación de ArrayAccess.
	 * Evitar su uso siempre que se pueda.
	 * 
	 * @param integer $offset Índice cuyo elemento se desea eliminar. 
	 */
	public function offsetUnset($offset){
		unset($this->positionIndex[$offset]);
	}
	
}
?>
