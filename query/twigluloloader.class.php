<?php

namespace lulo\query;

/**
 * Cargador específico de plantillas Twig para intelliweb
 * */
class TwigLuloLoader implements \Twig_LoaderInterface
{
	/** Bandera de depuración */
	public $debug = false;

	/** Ruta de la plantilla Twig */
	public $path = null;

    /**
     * Gets the source code of a template, given its name.
     * @param  string $name string The name of the template to load
     * @return string The template source code
     */	
	public function getSource($name)
	{
		$db_engine = \lulo\db\DB::ENGINE;
		$file_path = __DIR__."/templates/$db_engine/$name";
		if (file_exists($file_path)){
			$final_path = $file_path;
		}else if(file_exists(__DIR__."/templates/_default/$name")){
			$final_path = __DIR__."/templates/_default/$name";
		}else{
			throw new \InvalidArgumentException("$name twig template does not exists");
		}
		
		$twigString = file_get_contents($final_path);
		if(is_string($twigString))
		{
			$this->path = $final_path;
			if ($this->debug){
				var_dump($this->path);
			}
			return $twigString;
		}
		
		throw new \InvalidArgumentException("$name twig template does not exists");
	}

    /**
     * Gets the cache key to use for the cache for a given template name.
     * @param  string $name string The name of the template to load
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
		return $name."-".md5($name)."-".md5($this->getSource($name));
	}

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
		return false;
	}
}

?>