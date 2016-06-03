<?php

namespace lulo\tests\utils;

/**
* MimeTypeFinder class. Base utility for the simple example of the Social-Network classes.
*/
class MimeTypeFinder{

	protected static $MIMETYPES = [
		"pdf" => "application/pdf",
		"jpg" => "image/jpeg",
		"jpeg" => "image/jpeg",
		"png" => "image/p"
	];
	
	/**
	 * Returns the mimetype from the filename extension.
	 * @param string $filename File name.
	 * @return mixed string Mime type of the filename (based on its extension)
	 * or null if it is not recognized.
	 */
	public static function getMimetypeFromFileName($filename)
	{
		$matches = array();
		if(preg_match("/(.+)(\.)(.+)$/", $filename, $matches) == 0){
			return null;
		}

		$ext = strtolower($matches[3]);
		// Si existe el mimetype, lo devolvemos
		if(isset(static::$MIMETYPES[$ext])){	
			return static::$MIMETYPES[$ext];
		}
		return null;
	}
	
}

?>
