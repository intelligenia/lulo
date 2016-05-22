<?php

namespace lulo\models;

require_once __DIR__."/romodel.class.php";

/**
 * Clase padre de los modelos que pueden escribir en base de datos.
 * Hereda de ROModel, porque asumimos que todo modelo que puede
 * escribir, puede leer.
 * @author Diego J. Romero López en intelligenia.
 * */
abstract class RWModel extends ROModel{
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******** OBTENCIÓN DE VALORES PREDETERMINADOS DE FORMULARIO ******/
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** CLEAN *****************************************/
	
	/**
	 * Añade los campos de relación para una relación de tipo
	 * ForeignKey (ManyToOne), o bien OneToOne.
	 * @param array $data Array con los datos del formulario.
	 * @param string $relationName Nombre de la relación.
	 * @param array $properties Propiedades de la relación.
	 * @return array Array con los datos del formulario más los campos de la relación $relationName necesarios.
	 * */
	protected static function cleanToOneRelationship($data, $relationName, $properties){
		// Nombre del modelo relacionado
		$relatedModelName = $properties["model"];
		// Tipo de la relación
		$relationType = $properties["type"];
		// Condición de la relación (al estilo <atributo_local> => <atributo_remoto>)
		$relationCondition = $properties["condition"];
		
		// Si existe el atributo y no es vacío, cargamos el objeto y le
		// asignamos de forma automática los atributos de la relación
		if(isset($data[$relationName]) and $data[$relationName]!=""){
			// Si le hemos pasado un objeto, y del tipo adecuado no tenemos necesidad de convertirlo
			// a clave primaria
			if(is_object($data[$relationName])){
				if(get_class($data[$relationName])!=$relatedModelName){
					throw new InvalidArgumentException("El objeto {$data[$relationName]} no es de tipo {$relatedModelName}");
				}
				$relatedObject = $data[$relationName];
			}else{
				$relatedObject = $relatedModelName::dbLoadFromStrPk($data[$relationName]);
			}
			// Obviamente, sólo asignamos los atributos de un
			// objeto remoto que existe
			if(is_object($relatedObject)){
				foreach($relationCondition as $localAttribute=>$remoteAttribute){
					$data[$localAttribute] = $relatedObject->getAttribute($remoteAttribute);
				}
				return $data;
			}
		}
		
		// Si el objeto remoto no existe, o se ha eliminado, tratamos
		// de poner como nulos todos los atributos de la relación
		// En caso de que no sean nulables, esto no debería ocurrir
		foreach($relationCondition as $localAttribute=>$remoteAttribute){
			if(!isset($data[$localAttribute]) or is_null($data[$localAttribute])){
				$data[$localAttribute] = null;
			}
		}
		
		return $data;
	}
	
	
	/**
	 * Añade los campos de relaciones necesarios de forma automática a
	 * partir de un array proveniente de un formulario.
	 * @param array $data Array con los datos del formulario.
	 * @return array Array con los datos del formulario más los campos de relación necesarios.
	 * */
	protected static function cleanRelationships($data){
		
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			$relatedModelName = $properties["model"];
			$relationType = $properties["type"];
			// Relación a uno, tenemos que cargar el objeto relacionado y
			// asociarlo a los atributos del objeto actual
			if($relationType == "ForeignKey" or $relationType == "ManyToOne" or $relationType == "OneToOne"){
				$data = static::cleanToOneRelationship($data, $relationName, $properties);
			}
			// Relación muchos a muchos, NO tenemos que hacer nada
			// de eso se encarga ya el ModelForm
			elseif($relationType == "ManyToMany"){
				// No hay que hacer nada
			}
			// Relación uno a muchos, NO tenemos que hacer nada
			// normalmente será una relación inversa
			elseif($relationType == "OneToMany"){
				// No hay que hacer nada
			}
			// El tipo de la relación no se conoce
			else{
				throw new UnexpectedValueException("La relación {$relationName} tiene como tipo {$relationType}, que no es un tipo de relación válido.");
			}
			
		}
		
		// Devolvemos los datos del formulario con el añadido de las
		// relaciones
		return $data;
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de CREAR
	 * un objeto de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return array Array con los datos convertidos para ser tomados por class::factoryFromArray
	 * */
	public static function cleanCreation($data){
		$cleanedData = static::cleanRelationships($data);
		return $cleanedData;
	}
	
	
	/**
	 * Comprueba que los campos son correctos a la hora de EDITAR
	 * un objeto existente de este modelo.
	 * @param array $data Array con los campos recibidos por formulario.
	 * @return array Array con los datos convertidos para ser tomados por $object->setFromArray
	 * */
	public static function cleanEdition($data){
		$cleanedData = static::cleanRelationships($data);
		return $cleanedData;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/************* FORMULARIOS ****************************************/
	
	/**
	 * Este método devuelve el valor por defecto de un campo para el formulario de creación o de edición
	 * (en función de si no le pasamos un objeto o sí se lo pasamos, respectivamente).
	 * Útil para definir los valores por defecto en las relaciones.
	 * @param string $formFieldName Nombre del campo predeterminado.
	 * @param object $object Objeto que indica si se ha de obtener el valor del campo para el formulario de edición.
	 * @return mixed Valor predeterminado del campo para la creación/edición de objetos de ese modelo mediante un formulario. Si es null, se asume que no hay valor predeterminado para ese campo.
	 * */
	public static function defaultFormValue($formFieldName, $object=null){
		// Si no hay objeto, no hay valor predeterminado (por defecto)
		if(is_null($object)){
			return null;
		}
		// Hay un objeto, por lo que hay valor predeterminado por defecto
		if(isset(static::$RELATIONSHIPS[$formFieldName])){
			$relationType = static::$RELATIONSHIPS[$formFieldName]["type"];
			// Si es una relación de muchos a muchos es devolver
			// directamente la colección con los objetos asociados
			if($relationType == "ManyToMany"){
				$relatedObjects = $object->dbLoadRelated($formFieldName);
				return $relatedObjects;
			}
			
			// Es una relación ToOne (a-uno), esto es,
			// ForeignKey (ManyToOne), o bien, OneToOne
			$relatedObject = $object->dbLoadRelated($formFieldName);
			if(is_null($relatedObject)){
				return "";
			}
			return $relatedObject;
		}
		
		// Si devolvemos null, indicamos que no hay valor especial
		// predeterminado
		return null;
	}
	
	/**
	 * Valores de los campos que son de tipo select y multiselect del
	 * formulario de creación y edición para este modelo.
	 * @param string $formFieldName Nombre del campo predeterminado.
	 * @param object $object Objeto que indica si se ha de obtener el valor del campo para el formulario de edición.
	 * @return mixed Array de valores con  . Si es null, se asume que no hay valor predeterminado para ese campo.
	 * */
	public static function formValues($formFieldName, $object=null){
		return null;
	}
	
	
	/**
	 * Validación del formulario.
	 * Este método tiene las llamadas a los métodos de validación de cada uno.
	 * Se le pasa el objeto $object que se desea editar ANTES de habérsele
	 * asignado los valores que vienen del formulario.
	 * @param object $object Indica si estamos antes una edición o una creación.
	 * @return array Array con las validaciones de formulario.
	 * */
	public static function formValidation($object=null){
		return [];
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** BLOBS *****************************************/
	
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
				throw new UnexpectedValueException("El blob {$blobName} es un array pero no tiene 'path' como clave.");	
			}
		}
		// 3. Es NULL
		elseif(is_null($blob)){
			// Lo primero es comprobar si el blob es nullable, si no es nullable
			// lanzamos una excepción
			if(!isset(static::$ATTRIBUTES[$blobName]["null"]) or !static::$ATTRIBUTES[$blobName]["null"]){
				throw new UnexpectedValueException("El blob {$blobName} no es nullable.");	
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
			throw new UnexpectedValueException("El blob {$blobName} no es una cadena binaria, ni un array con 'path' como clave y la ruta del fichero, ni un descriptor de fichero. Es un {$blobType} que no sirve");
		}
		
		// Escritura del blob
		$newValues = [$blobName => $blobResource];
		$ok = $db::updateFields(static::TABLE_NAME, $newValues, $condition);
		// Devolvemos si es correcto o no
		return $ok;
	}
	
	
	/**
	 * Lee un blob, lo obtiene como cadena y lo inserta en $_model_data
	 * @param $blob Blob en muchos formatos posibles
	 * @param $_model_data Array con los datos del objeto.
	 * */
	protected static function _dbReadBlob($blobName, $blobObject, &$_model_data){
		$blobType = gettype($blobObject);
		$blobString = null;
		// Casos de lo que puede ser un blob
		
		// 1. Es un objeto con método toString
		if($blobType=="object" and is_callable([$blobObject, "toString"])){
			$blobString = $blobObject->toString();
		
		// 2. Es una cadena
		}elseif($blobType=="string"){
			$blobString = $blobObject;
		
		// 3. Es un array y tiene el atributo path
		}elseif(is_array($blobObject) and isset($blobObject["path"])){ 
			$blobString = file_get_contents($blobObject);
		
		// 4. Es un descriptor de fichero
		}elseif(get_resource_type($blobObject)==="stream"){
			$blobString = stream_get_contents($blobObject);
		
		// 5. No se reconoce (y da un error)
		}else{
			throw new InvalidArgumentException("El blob {$blobName} no es un objeto con el método toString, ni una cadena, ni un descriptor de fichero, ni un array con clave 'path' que identifica a un fichero, es un {$blobType}");
		}
		
		// Asignación de atributo blob
		$_model_data[$blobName] = $blobString;
	}
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/***************** DELETE *****************************************/
	
	/**
	 * Indica si se han de actualizar las relaciones al eliminar
	 * (ejecutar el método dbDelete). Nótese que cada modelo que herede
	 * de RWModel (o de LuloModel) puede sobrescribir este valor.
	 **/
	const UPDATE_RELATIONS_ON_OBJECT_DELETION_BY_DEFAULT = true;
	
	
	/**
	 * Método que eliminar el objeto actual.
	 * Se permite su sobrescritura en las clases hijas, de manera que
	 * se puede implementar de forma sencilla un borrado lógico mediante
	 * un atributo estableciéndolo aquí a 0 e incluyendo que no se cargue
	 * nunca un objeto borrado lógicamente en implicitBaseCondition.
	 * @return boolean true si la eliminación ha sido correcta, false en otro caso.
	 * */
	protected function dbDeleteAction(){
		$db = static::DB;
		// Clave primaria del objeto a borrar
		$condition = $this->getPk();
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalDeletionCondition = static::getBaseCondition($condition);
		// Lo primero es eliminar el objeto actual
		$ok = $db::delete(static::TABLE_NAME, $finalDeletionCondition);
		// Informamos de si ha ido bien todo
		return $ok;
	}
	
	
	/**
	 * Elimina el objeto actual de la base de datos.
	 * @param boolean $updateRelations Indica si ha de actualizar las
	 * relaciones o los atributos de los objetos relacionados. Por defecto es null, lo que indica
	 * que se actualizarán las relaciones en función de la constante UPDATE_RELATIONS_ON_DELETE.
	 * @return true si la eliminación se ha efectuado sin problemas.
	 * En otro caso, false.
	 * */
	public function dbDelete($updateRelations=null){
		// ¿Se ha de actualizar las relaciones en la eliminación
		// de un objeto? Si es null, se toma la decisión por defecto
		// Si es un booleano, se toma la decisión que haya querido el
		// desarrollador en ese momento.
		if(is_null($updateRelations)){
			$updateRelations = static::UPDATE_RELATIONS_ON_OBJECT_DELETION_BY_DEFAULT;
		}
		// Acción de eliminar el objeto actual
		$ok = $this->dbDeleteAction();
		// Si hemos de actualizar las relaciones
		if($updateRelations){
			// Elimina TODAS mis relaciones, es decir, relaciones en las que
			// este modelo es el actor principal (si es OneToMany)
			// o el actor secundario (si es ManyToMany).
			$ok = ( $ok and $this->dbDeleteRelations() );
		}
		return $ok;
	}
	
	
	/**
	 * Elimina todos los objetos que cumplan una condición en la base de datos.
	 * ATENCIÓN: esta eliminación NO elimina objetos relacionados.
	 * @param $condition Condición de eliminación de los objetos.
	 * @return true si la eliminación se ha efectuado sin problemas.
	 * En otro caso, false.
	 * */
	public static function dbDeleteAll($condition){
		$db = static::DB;
		// Obtención de las condiciones finales (añadiendo las condiciones implícitas)
		$finalDeletionCondition = static::getBaseCondition($condition);
		// Se eliminan todos los objetos que cumplan una condición
		$ok = $db::delete(static::TABLE_NAME, $finalDeletionCondition);
		return $ok;
	}
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/******************* SAVE *****************************************/
	
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
		var_dump($ok);
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
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/***************** UPDATE *****************************************/
	
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
	public static function dbUpdate($condition, $values){
		$db = static::DB;
		// Clave primaria del objeto
		$pkValue = $this->getPk();
		// Actualización del objeto
		$this->setAttributes($values);
		// Actualización de los campos del objeto
		$ok = $db::updateFields(static::TABLE_NAME, $values, $pkValue);
		return $ok;
	}
	
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/***************** RELACIONES *************************************/

	/**
	 * Comprueba que la relación permite la escritura.
	 * @param string $relationName Nombre de la relación.
	 * @param array $relationship Relación de la que se desea comprobar si es editable.
	 * */
	protected static function assertRWRelationship($relationName, $relationship){
		// La relación es de sólo lectura
		if(isset($relationship["readonly"]) and $relationship["readonly"]){
			throw new UnexpectedValueException("La relación {$relationName} es de sólo lectura (y no se permite edición)");
		}
	}

	/******************************************************************/
	/******************************************************************/
	/*************************** ADD **********************************/

	/**
	 * Obtiene los valores del nexo $junction entre $this y $object.
	 * */
	protected function getJunctionValues($conditions, $junction, $object=null){
		// Claves primarias de cada uno de los extremos de la relación
		$sourceAttributes = $this->getPk();
		
		// Condición desde el modelo al nexo
		$condition0 = $conditions[0];
		foreach($condition0 as $sourceAttribute=>$junctionAttribute){
			$junctionData[$junctionAttribute] = $sourceAttributes[$sourceAttribute];
		}
		
		// Condición desde el nexo al destino
		if(is_object($object)){
			$objectAttributes = $object->getPk();
			$condition1 = $conditions[1];
			foreach($condition1 as $junctionAttribute=>$nextAttribute){
				$junctionData[$junctionAttribute] = $objectAttributes[$nextAttribute];
			}
		}
		return $junctionData;
	}
	
	
	/**
	 * Añade un objeto concreto a una relación ManyToMany.
	 * Ten en cuenta que se supone que esta relación sólo va a tener un nexo, no están implementadas todavía las relaciones con varios nexos.
	 * @param array $relationship Array con las propiedades de la relación.
	 * @param object $object Objeto con el que se desea establecer la relación.
	 * @return boolean true si todo ha ido bien, false en otro caso.
	 * */
	protected function _dbAddObjectToManyToManyRelation($relationship, $object){
		$db = static::DB;
		
		// Propiedades como atributos para mayor comodidad
		$model = $relationship["model"];
		$junctions = $relationship["junctions"];
		$conditions = $relationship["conditions"];
		
		// Como hemos dicho, se espera sólo 1 nexo, por ahora no se ha
		// implementado la inserción en cadena porque es muy costosa
		// tanto en tiempo de desarrollo como en tiempo de ejecución
		$junction = $junctions[0];
		if(count($junctions)>1){
			throw new UnexpectedValueException("Operación no soportada. No se puede añadir una relación M2M en tablas múltiples, lo siento, haberlo pensado mejor");
		}
		
		$junctionValues = $this->getJunctionValues($conditions, $junction, $object);
		
		// Inserción en BD
		$ok = true;
		$ok = ($ok and $db::insert($junction, $junctionValues));

		return $ok;
	}
	
	
	/**
	 * Añade una relación.
	 * @param string $relationName Nombre de la relación.
	 * @param mixed $value Un objeto relacionado o una colección de objetos relacionados.
	 * */
	public function dbAddRelation($relationName, $value){
		// Relación definida en __CLASS__
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Comprueba que se puede editar la relación
		// (en este caso añadiendo un nuevo objeto remoto)
		static::assertRWRelationship($relationName, $relationship);
		
		// Modelo que hemos de cargar
		$foreignClass = $relationship["model"];
		
		// Si no hay un modelo que cargar, lo creamos nosotros WOW
		if(is_null($foreignClass)){
			throw new UnexpectedValueException("La relación {$relationName} es de tipo table y en éstas no se permite edición");
		}
		
		// Tipo de relación (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		////////////////////////////////////////////////////////////////
		// Clave externa o clave externa invertida
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne" or $relationshipType == "OneToMany"){
			$object = $value;
			$condition = $relationship["condition"];
			// Espera que se le pase un objeto de tipo $foreignClass
			if(is_object($object) and get_class($object)==$foreignClass){
				foreach($condition as $localAttribute=>$remoteAttribute){
					$this->$localAttribute = $object->$remoteAttribute;
				}
				return $this->dbSave();
			}
			throw new InvalidArgumentException("La relación {$relationName} requiere un objeto de tipo {$foreignClass}");
		}
		
		////////////////////////////////////////////////////////////////
		// Muchos a muchos
		if($relationshipType == "ManyToMany"){
			$values = $value;
			// Si le pasamos un array, asumimos que los elementos son las
			// claves en formato strPk u objetos completos
			if(is_array($values)){
				$values = new Collection();
				foreach($value as $strPk){
					if(is_string($strPk)){
						$object = $foreignClass::dbLoadFromStrPk($strPk);
					}elseif(is_object($strPk)){
						$object = $strPk;
					}
					$values->add($object);
				}
			}
			// Si le pasamos una cadena, se asume que es una clave
			// primaria en formato strPk
			elseif(is_string($value)){
				$value = $foreignClass::dbLoadFromStrPk($value);
				$values = new Collection();
				$values->add($value);
			}
			// Si se le pasa un objeto de tipo $foreignClass,
			// construimos una colección de un solo elemento
			elseif(is_object($value) and get_class($value)==$foreignClass){
				$values = new Collection();
				$values->add($value);
			}
			// Espera que se le pase un objeto de tipo Collection
			if(is_object($values) and get_class($values)=="Collection"){
				$ok = true;
				foreach($values as $object){
					$ok = ($ok and static::_dbAddObjectToManyToManyRelation($relationship, $object));
				}
				return $ok;
			}
			return false;
		}
		// Si la relación es de otro tipo, damos un error
		throw new InvalidArgumentException("La relación {$relationName} no permite la adición de objetos");
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
	
	
	/******************************************************************/
	/******************************************************************/
	/************************* DELETE *********************************/
	
	/**
	 * Elimina las relaciones muchos a muchos de este modelo.
	 * Si se le pasa el parámetro $object, se asume que se desea eliminar la relación con ese objeto.
	 * @param array $relationship Relación que se desea eliminar.
	 * @param object $object Objeto del que queremos eliminar la relación. Si es null, se asume que se desean eliminar toda la relación $relationship.
	 * */
	protected function _dbDeleteManyToManyRelation($relationship, $object=null){
		$db = static::DB;
		// Propiedades como atributos para mayor comodidad
		$model = $relationship["model"];
		$junctions = $relationship["junctions"];
		$conditions = $relationship["conditions"];
		
		// Como hemos dicho, se espera sólo 1 nexo, por ahora no se ha
		// implementado la inserción en cadena porque es muy costosa
		// tanto en tiempo de desarrollo como en tiempo de ejecución
		$junction = $junctions[0];
		if(count($junctions)>1){
			throw new UnexpectedValueException("Operación no soportada. No se puede eliminar una relación M2M en tablas múltiples, lo siento, esa funcionalidad está pendiente");
		}
		
		$junctionValues = $this->getJunctionValues($conditions, $junction, $object);
		
		// Inserción en BD
		$ok = $db::delete($junction, $junctionValues);

		return $ok;
	}
	
	
	/**
	 * Elimina una relación
	 * @param string $relationName Nombre de la relación.
	 * @param object $object Objeto del que queremos eliminar de la relación.
	 * Si es null, se asume que se desean eliminar la relación $relationName completa (todos sus objetos asociados).
	 * @return boolean True si la eliminación ha sido un éxito, false en otro caso.
	 * */
	public function dbDeleteRelation($relationName, $object=null){
		// Relación definida en __CLASS__
		$relationship = static::$RELATIONSHIPS[$relationName];
		
		// Modelo que hemos de cargar
		$foreignClass = $relationship["model"];
		
		// Comprueba que se puede editar la relación
		static::assertRWRelationship($relationName, $relationship);
		
		// Si no hay un modelo que cargar, lo creamos nosotros WOW
		if(is_null($foreignClass)){
			throw new UnexpectedValueException("La relación {$relationName} es de tipo table y en éstas no se permite edición");
		}
		
		// Tipo de relación (ForignKey, OneToMany o ManyToMany)
		$relationshipType = $relationship["type"];
		
		////////////////////////////////////////////////////////////////
		// Clave externa
		if($relationshipType == "ForeignKey" or $relationshipType == "ManyToOne"){
			throw new InvalidArgumentException("La relación {$relationName} es de tipo ForeignKey y no se pueden poner a nulo así como así sus atributos. Prueba a establecer los que tú estimes adecuados y a llamar a dbSave");
		}
		////////////////////////////////////////////////////////////////
		// Uno a muchos
		elseif($relationshipType == "OneToMany"){
			if(isset($relationship["on_delete"])){
				// Eliminación en cascada
				if($relationship["on_delete"] == "delete" or $relationship["on_delete"] == "cascade"){
					$relatedObjects = $this->dbLoadRelated($relationName);
					foreach($relatedObjects as $relatedObject){
						$relatedObject->dbDelete();
					}
					return true;
				}
				// Establecimiento de un valor en los atributos al eliminar el objeto padre
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
		////////////////////////////////////////////////////////////////
		// Muchos a muchos
		elseif($relationshipType == "ManyToMany"){
			return $this->_dbDeleteManyToManyRelation($relationship, $object);
		}
	}
	
	
	/**
	 * Elimina todas las relaciones de un objeto en el que él es el principal.
	 * Es decir, un modelo puede ser el actor principal de una relación o el
	 * secundario. El actor principal es el modelo en el que se definen las
	 * relaciones. El actor secundario es el modelo que está asociado.
	 * @return boolean True si todo ha ido bien, false en otro caso.
	 * */
	public function dbDeleteDirectRelations(){
		$ok = true;
		foreach(static::$RELATIONSHIPS as $relationName=>$properties){
			if($properties["type"] == "ManyToMany" or $properties["type"] == "OneToMany"){
				// Sólo eliminamos las relaciones que no son "readonly"
				if(!isset($properties["readonly"]) or !$properties["readonly"]){
					// Estas relaciones son las relaciones directas
					$ok = ($ok and $this->dbDeleteRelation($relationName));
				}
			}
		}
		return $ok;
	}
	
	
	/**
	 * Elimina todas las relaciones de este objeto con cualquier otro objeto.
	 * @return boolean True si todo funciona correctamente, false en otro caso.
	 * */
	public function dbDeleteRelations(){
		// Relaciones directas
		$okDirect = $this->dbDeleteDirectRelations();
		// Informamos si todo ha ido bien
		return $okDirect;
	}


}
?>
