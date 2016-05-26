<?php

namespace lulo\models\traits;

/**
 * Saving methods for LuloModel models.
 * @author Diego J. Romero López
 */
trait Save {
	
	/**
	 * Guarda el objeto en base de datos asumiendo que no existe otro
	 * con la misma clave primaria que éste.
	 * */
	protected function _dbSaveNewObject($blobs=[]){
		$db = static::DB;
		
		$id_attribute_name = static::ID_ATTRIBUTE_NAME;
		
		// Guarda en BD del propio objeto
		$_model_data = $this->getNonBlobAttributes();
		
		// Eliminamos el campo id, según la especificación de LULO,
		// el campo id es autoincrementado
		// y algunos SGBD no les gusta que tenga valor nulo
		if(array_key_exists($id_attribute_name, $_model_data)){
			unset($_model_data[$id_attribute_name]);
		}
		
		// Para cada blob, comprobamos si es una cadena o si es un objeto
		// DBBlobReader
		foreach($blobs as $blobName=>$blobObject){
			static::_dbReadBlob($blobName, $blobObject, $_model_data);
		}
		
		// Inserta una nueva tupla con los datos
		$ok = $db::insert(static::TABLE_NAME, $_model_data);
		
		// Nota: esta sentencia obtiene el último id insertado en esta sesión.
		// REPITO, ES ÚNICO POR SESIÓN, por lo que funciona correctamente.
		// Ver http://dev.mysql.com/doc/refman/5.5/en/information-functions.html#function_last-insert-id
		// para más información
		$this->$id_attribute_name = $db::getLastInsertId();
		// Devuelve si todo ha funcionado correctamente
		return $ok;
	}
	
	
	/**
	 * Actualiza un objeto antiguo que ya estuviera en base de datos.
	 * */
	protected function _dbSaveOldObject($blobs=[]){
		$db = static::DB;
		
		// Clave primaria del objeto
		$pkValue = $this->getPk();
		
		// Obtención de los atributos de objeto
		$values = $this->getNonBlobAttributes();
		
		// Adición de los campos blob al listado de atributos del objeto
		foreach($blobs as $blobName => $blobObject){
			static::_dbReadBlob($blobName, $blobObject, $values);
		}
		
		// Actualización de los campos del objeto
		$ok = $db::updateFields(static::TABLE_NAME, $values, $pkValue);
		return $ok;
	}
	
	
	/**
	 * Comprueba si el objeto actual es un objeto nuevo creado en memoria y no
	 * tiene un objeto asociado en la base de datos.
	 * @return boolean Informa si el objeto es nuevo (true) o no (false).
	 * */
	protected function _dbIsNewObject(){
		// Si estamos ante un objeto que no tiene el id establecido
		// o el id es nulo, no tiene clave primaria única
		if( isset(static::$ATTRIBUTES["id"]) and (!isset($this->id) or is_null($this->id)) ){
			return true;
		}
		// En otro caso, comprobamos que el objeto no existe en BD
		$db = static::DB;
		// Comprobamos no existe objeto en BD
		// con la misma clave primaria
		$pkValue = $this->getPk();
		$alreadyExists = ($db::count(static::TABLE_NAME, $pkValue) > 0);
		return !$alreadyExists;
	}

	
	/**
	 * Limpia los atributos antes de guardar el objeto en BD.
	 * 
	 * Es decir, comprueba que todos los atributos, que no sean blobs, obedecen sus restricciones.
	 */
	protected function cleanAttributes(){
		foreach($this->getNonBlobAttributes() as $name=>$v){
			$properties = static::$ATTRIBUTES[$name];
			// Comprobación de nulidad de cada atributo
			// Nótese que no compruebo los atributos automáticos, porque asumimos
			// que están fuera del control del desarrollador
			if($properties["type"]!= "blob" and (!isset($properties["auto"]) or !$properties["auto"]) and is_null($this->$name) and (!isset($properties["null"]) or !$properties["null"])){
				throw new \UnexpectedValueException("El atributo {$name} no puede ser null");
			}
		}
	}
	
	
	/**
	 * Valida un objeto que ya existe en base de datos.
	 * Es sobrescribible.
	 * @param array $blobs Array con los objetos Blob.
	 * */
	public function cleanOld($blobs){
		// Comprobamos que los atributos son correctos
		$this->cleanAttributes();
		
		// Si se encuentra aquí con una condición que no se permite
		// al lanzar una excepción con un mensaje se evita
		// la edición del objeto.
	}
	
	
	/**
	 * Valida un objeto que NO existe en base de datos.
	 * Es sobrescribible.
	 * @param array $blobs Array con los objetos Blob.
	 * */
	public function cleanNew($blobs){
		// Comprobamos que los atributos son correctos
		$this->cleanAttributes();		

		// Si se encuentra aquí con una condición que no se permite
		// al lanzar una excepción con un mensaje se evita
		// la edición del objeto.
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
	 * Añade todas las relaciones a partir de los atributos dinámicos
	 * del objeto $this.
	 * @param boolean $objectExisted Informa si el objeto existía ya (es una edición) o es una creación.
	 * En el caso de que sea una edición, se eliminarán todos sus objetos relacionados y se reemplazarán por los que se pasan como parámetro.
	 * */
	protected function _dbAddM2MFromForm($objectExisted=true){
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			if($properties["type"] == "ManyToMany"){
				if(isset($this->$relationName) and is_array($this->$relationName) and count($this->$relationName)>0){
					$strPks = $this->$relationName;
					if($objectExisted){
						$this->dbDeleteRelation($relationName);
					}
					$this->dbAddRelation($relationName, $strPks);
				}
			}
		}
	}
	
	/**
	 * Guarda este objeto en base de datos, validando antes si el objeto
	 * cumple las condiciones para guardarlo en base de datos.
	 * ATENCIÓN: o lo crea como una nueva tupla o edita el que ya
	 * existiera con esa clave primaria de forma automática.
	 * */
	public function dbSave($blobs=[]){
		// Comprobación de que el valor del objeto a guardar es válido
		// con respecto a lo que existe en la base de datos
		$isNewObject = $this->_dbIsNewObject();
		
		// Validamos el objeto
		$this->clean($blobs, $isNewObject);
		
		// Si existe, sólo tenemos que actualizarlo
		if(!$isNewObject){
			$ok = $this->_dbSaveOldObject($blobs);
		}
		// Si no existe, creamos un objeto nuevo
		else{
			$ok = $this->_dbSaveNewObject($blobs);
		}
		
		// Añadimos las relaciones muchos a muchos de nuevas
		$ok = ( $ok and $this->_dbAddM2MFromForm(!$isNewObject) );
		
		// Devuelve si la inserción o actualización de tupla y
		// las inserciones de objetos relacionados han ido correctamente
		return $ok;
	}
	
	
}
