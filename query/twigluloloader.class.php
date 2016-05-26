<?php

namespace lulo\query;

/**
 * Twig template loader for Lulo.
 * */
class TwigLuloLoader implements \Twig_LoaderInterface
{
	/** Should we debug? */
	public $debug = false;

	/** Complete path of the found twig file */
	public $path = null;

    /**
     * Gets the source code of a template, given its name.
     * @param  string $name string The name of the template to load
     * @return string The template source code
     */	
	public function getSource($name)
	{
		// If we have specified a raw_path in templates, gets the raw path
		$matches = [];
		if(preg_match("/^(!raw_path:)(.+)$/", $name, $matches)){
			$final_path = __DIR__."/templates/_default/{$matches[2]}";
			if(!file_exists($final_path)){
				throw new \InvalidArgumentException("Path {$final_path} does not exist");
			}
			return $final_path;
		}
		// Load the SQL file for our DB engine
		$db_engine = \lulo\db\DB::ENGINE;
		
		// First path to try is the specific SQL templates for this DB engine
		$file_path = __DIR__."/templates/$db_engine/$name";
		if (file_exists($file_path)){
			$final_path = $file_path;
		
		// Second path to try is the default SQL templates for all DB engines
		// that respect the standard
		}else if(file_exists(__DIR__."/templates/_default/$name")){
			$final_path = __DIR__."/templates/_default/$name";
		}else{
			throw new \InvalidArgumentException("$name twig template does not exists ({$final_path} tested)");
		}
		
		// Load of the Twig template as a string
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