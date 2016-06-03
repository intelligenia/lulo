<?php

namespace lulo\models;

require_once __DIR__."/romodel.class.php";
require_once __DIR__."/rwmodel.class.php";

/**
 * Parent class of all models.
 * @author Diego J. Romero López en intelligenia.
 * */
abstract class LuloModel extends RWModel{
	
	/** DB connection used */
	const DB = "\lulo\db\DB";
	
	/******************************************************************/
	/******************************************************************/
	/******************************************************************/
	/****************** BLOBS *****************************************/
	
	/**
	 * Write $blobName blob to database.
	 * $blob parameter can be:
	 * 1. A string that contains the binary representation of the blob.
	 * 2. An array with "dbblobreader" key, where $blob["dbblobreader"] is an object that contains the blob contents.
	 * 3. An array with "path" key, where $blob["path"] is the path of the filename to write.
	 * 4. A file descriptor which content will be written to $blobName field.
	 * @param $blobName Blob name.
	 * @param $blob mixed blob contents.
	 * @return boolean true if update was a success, false otherwise.
	 * */
	public function dbBlobUpdate($blobName, $blob){
		if(is_array($blob) and isset($blob["dbblobreader"]) and !is_null($blob["dbblobreader"])){
			$blob = $blob["dbblobreader"];
		}

		return parent::dbBlobUpdate($blobName, $blob);
	}

}
?>
