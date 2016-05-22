<?php

/**
* Clase MimeTypeFinder. Encuentra un mimetype a partir de una extensión de un fichero.
* @package helper
*/
class MimeTypeFinder{

	protected static $MIMETYPES = [
		"pdf" => "application/pdf",
		"jpg" => "image/jpeg",
		"jpeg" => "image/jpeg",
		"png" => "image/p"
	];
	
	/**
	 * Devuelve el mimetype a partir del nombre de archivo.
	 * @param string $filename Nombre de archivo del que se desea sacar el mimetype.
	 * @pre El nombre de archivo ha de contener la extensión.
	 * @return mixed string Se devuelve el mimetype del archivo o si no existe, se devuelve NULL.
	 */
	public static function getMimetypeFromFileName($filename)
	{
		$matches = array();
		if(preg_match("/(.+)(\.)(.+)$/", $filename, $matches) == 0){
			var_dump($filename);
			print "sdfasdfsdf";
			return null;
		}

		$ext = strtolower($matches[3]);
		// Si existe el mimetype, lo devolvemos
		if(isset(static::$MIMETYPES[$ext])){	
			print "XXXXX";
			return static::$MIMETYPES[$ext];
		}
		print "YYY";
		return null;
	}
	
}

?>
