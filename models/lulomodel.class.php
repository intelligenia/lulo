<?php

namespace lulo\models;

require_once __DIR__."/romodel.class.php";
require_once __DIR__."/rwmodel.class.php";

/**
 * Clase padre de los modelos que pueden escribir sólo en la
 * base de datos de intelliweb (UniWeb para algunos).
 * Hereda de RWModel, porque asumimos que todo modelo que puede
 * escribir, puede leer y porque extiende RWModel añadiendo
 * funcionalidad de DBBlobReader y DBBlobWriter.
 * @author Diego J. Romero López en intelligenia.
 * */
abstract class LuloModel extends RWModel{
	
	/** Conexión usada */
	const DB = "DB";
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** BLOBS *****************************************/
	
	/**
	 * Escribe un blob cuyo nombre de atributo es $blobName.
	 * Se espera en el parámetro $blob una de la siguientes opciones:
	 * 1. Un objeto DBBlobReader,
	 * 2. Una cadena, que es el binario del fichero
	 * 3. Un array, y tiene la clave "path" que es la ruta de un fichero
	 * 4. Un descriptor de fichero, cuyo contenido se volcará en el campo $blobName
	 * @param $blobName Nombre del blob a actualizar.
	 * @param $blob Objeto blob o bien como DBlobReader, como cadena o como fichero.
	 * @return boolean True si la actualización ha sido un éxito.
	 * */
	public function dbBlobUpdate($blobName, $blob){
		$condition = $this->getPk();
		
		// 1. Si es un array y tiene la clave dbblobreader y ésta no es null
		// lo asignamos a al blob y asumimos que es un objeto de tipo DBBlobReader
		if(is_array($blob) and isset($blob["dbblobreader"]) and !is_null($blob["dbblobreader"])){
			$blob = $blob["dbblobreader"];
		}

		// 3. Si es una cadena, es el binario del fichero
		// 4. Si es una array, y tiene la clave "path",
		// es la ruta de un fichero 
		// 5. Es un descriptor de fichero
		return parent::dbBlobUpdate($blobName, $blob);
	}

}
?>
