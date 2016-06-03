<?php

namespace lulo\twig;

require_once LULO_DIR__DEPENDENCES__VENDOR.'/twig/twig/lib/Twig/Autoloader.php';
require_once __DIR__.'/twigluloloader.class.php';

/**
 * Wrapper class that hides the internals of twig to the rest of this system.
 * 
 * */
class TwigTemplate
{
	/** TWIG instance */
	private $twig;

	/** Complete path of the twig template that will be used */
	private $resource;

	/**
	* Class name.
	*/
	const CLASS_NAME = "TwigTemplate";
	
	/**
	* Cache path.
	*/
	const CACHE_PATH = '/tmp/twig/cache';

	/**
	* Magic method. Pass every non-recognized method call to the Twig Envinroment.
	* @param string $methodName Non-recognized called method.
	* @param array $args Array with method attributes.
	* @return mixed Result of the call of Twig_Environment::$methodName.
	*/
	public function __call($methodName, $args)
	{
		if (is_callable(array($this->twig, $methodName))){
			if(!empty($args) and count($args)>0){
				return $this->twig->$methodName($args);
			}
			return $this->twig->$methodName();
		}
		// This method does not exist in TwigTemplate nor in Twig_Environment
		throw new \BadFunctionCallException("Method $methodName does not exist ".self::CLASS_NAME." nor in Twig_Environment internal object");
	}

	/**
	 * Creates Twig_Environments objects.
	 * Internal method. Do not use.
	 * @param boolean $debug_mode Should we use debug_mode? Default to false.
	 * @return object Twig_Environment object used for initialize $twig attribute of this class.
	 * */
	protected static function twigFactory($debug_mode=false)
	{
		\Twig_Autoloader::register();
		$loader = new \lulo\twig\TwigLuloLoader('');
		$twig = new \Twig_Environment($loader, array(
			"cache" => static::CACHE_PATH,
			"debug" => $debug_mode
		));
		// Debug mode is activated?
		if($debug_mode){
			$twig->addExtension(new Twig_Extension_Debug());
        }
		
		// Return Twig Environment object
		return $twig;
	}

	/**
	 * Create Twig objects from string.
	 * Internal method. Do not use.
	 * @param boolean $debug_mode Should we use debug_mode? Default to false.
	 * @return object Twig_Environment object used for initialize $twig attribute of this class.
	 * */
	protected static function twigFactoryFromString($debug_mode=false)
	{
		\Twig_Autoloader::register();

		$loader = new \Twig_Loader_String();
		$twig = new \Twig_Environment($loader, array(
			"cache" => static::CACHE_PATH,
			"debug" => $debug_mode
		));
		// Debug mode is activated?
		if($debug_mode){
			$twig->addExtension(new \Twig_Extension_Debug());
		}
		// Return Twig Environment object
		return $twig;
	}

	/**
	 * Creates a new TwigTemplate from a template $path.
	 * @param string $resource Template path we want to load.
	 * @return object TwigTemplate object that contains $resource template file.
	 * */
	public static function factoryResource($resource, $debug_mode=false)
	{
		$twigTemplate = new \lulo\twig\TwigTemplate();
		$twigTemplate->twig = static::twigFactory($debug_mode);
		$twigTemplate->resource = $resource;
		return $twigTemplate;
	}

	/**
	 * Alias for factoryResource($resource)
	 * */
	public static function factoryHtmlResource($resource, $debug_mode=false)
	{
		return static::factoryResource($resource, $debug_mode);
	}

	/**
	 * Creates a new TwigTemplate from a Twig template in a string.
	 * @param string $twig_code String with Twig template code.
	 * @param boolean $debug_mode Should we use debug mode?
	 * @return object TwigTemplate object with the contents of $twig_code.
	 * */
	public static function factoryString($twig_code, $debug_mode=false)
	{
		$twigTemplate = new \lulo\twig\TwigTemplate();
		$twigTemplate->twig = static::twigFactoryFromString($twig_code, $debug_mode);
		$twigTemplate->resource = $twig_code;
		return $twigTemplate;
	}

	/**
	 * Renders the templtes
	 * @param array $replacements Replacements hash for the template.
	 * @return string String with the replacements applied to the the template.
	 * */
	public function render($replacements=array())
	{
		return $this->twig->render($this->resource, $replacements);
	}

	/**
	 * Renders Twig code.
	 * @param string $twig_code String with Twig template code.
	 * @param array $replacements Replacements hash for the template.
	 * @param boolean $debug_mode Is debug mode activade?
	 * */
	public static function renderString($twig_code, $replacements=array(), $debug_mode=false)
	{
		$twigTemplate = static::factoryString($twig_code, $debug_mode);
		return $twigTemplate->render($replacements);
	}

}

?>
