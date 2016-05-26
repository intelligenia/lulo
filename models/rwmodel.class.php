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
	
	use \lulo\models\traits\Delete;
	use \lulo\models\traits\Save;
	use \lulo\models\traits\Update;
	
	/**
	 * Indica si se han de actualizar las relaciones al eliminar
	 * (ejecutar el método dbDelete). Nótese que cada modelo que herede
	 * de RWModel (o de LuloModel) puede sobrescribir este valor.
	 **/
	const UPDATE_RELATIONS_ON_OBJECT_DELETION_BY_DEFAULT = true;
	
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
			throw new \InvalidArgumentException("El blob {$blobName} no es un objeto con el método toString, ni una cadena, ni un descriptor de fichero, ni un array con clave 'path' que identifica a un fichero, es un {$blobType}");
		}
		
		// Asignación de atributo blob
		$_model_data[$blobName] = $blobString;
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
	
}
?>
