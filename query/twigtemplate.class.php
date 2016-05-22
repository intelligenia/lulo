<?php

namespace lulo\query;

require_once LULO_DIR__DEPENDENCES__VENDOR.'/twig/twig/lib/Twig/Autoloader.php';
require_once __DIR__.'/twigluloloader.class.php';

/**
 * Clase que actúa como una envoltura del sistema de plantillas Twig.
 * Twig es un sistema que sigue la sintaxis del sistema de plantillas Django (o Jinja2)
 * Para ver qué es Twig y las opciones que tiene vea primero la web y la documentación.
 * Para más información visite <a href="http://twig.sensiolabs.org/documentation">esta página</a>.
 * */
class TwigTemplate
{
	/** Instancia de Smarty a la que sirve de envoltura la clase */
	public $twig;

	/** Almacena la ruta completa de la plantilla que usará la instancia de Twig */
	protected $resource;

	/**
	* Nombre de la clase actual.
	*/
	const CLASS_NAME = "TwigTemplate";
	
	const CACHE_PATH = '/tmp/twig/cache';

	/**
	* Método mágico: devuelve lo que devuelva la llamada a un método que no se encuentra en el objeto actual pero sí en el objeto Smarty.
	* @param string $methodName Nombre del método llamado.
	* @param array $args Array con los atributos con los que se llama el método.
	* @return mixed Valor devuelto por la llamada del método en el objeto guardado en el array de atributos dinámicos o null si no existe.
	*/
	public function __call($methodName, $args)
	{
		if (is_callable(array($this->twig, $methodName))){
			if(!empty($args) and count($args)>0){
				return $this->twig->$methodName($args);
			}
			return $this->twig->$methodName();
		}
		// Avisamos de que ha ejecutado un método que no existe ni en él ni en el objeto Smarty interno
		trigger_error("El método $methodName no existe en la clase ".self::CLASS_NAME." ni en el objeto Twig interno", E_USER_WARNING);
		return null;
	}

	/**
	 * Construye objetos Twig.
	 * Método protegido, no se ha de llamar nunca desde fuera.
	 * @return object Objeto Twig para inicializar el atributo $twig de la clase.
	 * */
	protected static function twigFactory($debug_mode=false)
	{
		\Twig_Autoloader::register();
		$loader = new \lulo\query\TwigLuloLoader('');
		$twig = new \Twig_Environment($loader, array(
			"cache" => static::CACHE_PATH,
			"debug" => $debug_mode
		));
		////////////////////////////////////////////////////////////////////
		// ¿Estamos en el modo de depuración?
		if($debug_mode){
			$twig->addExtension(new Twig_Extension_Debug());
        }
		
		////////////////////////////////////////////////////////////////////
		// Devolvemos el objeto twig
		return $twig;
	}

	/**
	 * Construye objetos Twig a partir de cadenas.
	 * Método protegido, no se ha de llamar nunca desde fuera.
	 * @return object Objeto Twig para inicializar el atributo $twig de la clase.
	 * */
	protected static function twigFactoryFromString($debug_mode=false)
	{
		global $_ewconfig;
		\Twig_Autoloader::register();

		$loader = new \Twig_Loader_String();
		$twig = new \Twig_Environment($loader, array(
			"cache" => static::CACHE_PATH,
			"debug" => $debug_mode
		));
		if($debug_mode){
			$twig->addExtension(new \Twig_Extension_Debug());
		}
		return $twig;
	}

	/**
	 * Factoría a partir de la ruta de un recurso.
	 * @param string $resource Ruta de la plantilla que queremos cargar.
	 * @return object Objeto TwigTemplate con la plantilla $resource cargada.
	 * */
	public static function factoryResource($resource, $debug_mode=false)
	{
		$twigTemplate = new TwigTemplate();
		$twigTemplate->twig = static::twigFactory($debug_mode);
		$twigTemplate->resource = $resource;
		return $twigTemplate;
	}

	/**
	 * Alias de la función factoryResource($resource)
	 * */
	public static function factoryHtmlResource($resource, $debug_mode=false)
	{
		return static::factoryResource($resource, $debug_mode);
	}

	/**
	 * Fábrica de cadenas.
	 * */
	public static function factoryString($twigCode, $debug_mode=false)
	{
		$twigTemplate = new TwigTemplate();
		$twigTemplate->twig = static::twigFactoryFromString($twigCode, $debug_mode);
		$twigTemplate->resource = $twigCode;
		return $twigTemplate;
	}

	/**
	 * Renderiza la plantilla
	 * @param array $replacements Array de reemplazos de la  plantila.
	 * @return string Cadena con el contenido de la plantilla renderizada.
	 * */
	public function render($replacements=array())
	{
		return $this->twig->render($this->resource, $replacements);
	}

	/**
	 * Renderiza el código Twig directamente.
	 * @param string $twigCode Código TWIG que se desea interpretar.
	 * @param array $replacements Array con los reemplazos que se han de realizar.
	 * @param boolean $debug_mode Indica si el modo de depuración está activado.
	 * */
	public static function renderString($twigCode, $replacements=array(), $debug_mode=false)
	{
		$twigTemplate = static::factoryString($twigCode, $debug_mode);
		return $twigTemplate->render($replacements);
	}
	
	/**
	 * Renderiza la plantilla y la devuelve como un objeto Template.
	 * @param array $replacements Array de reemplazos de la  plantila.
	 * @return object Objeto Template con el contenido de la plantilla renderizada.
	 * */
	/*public function renderOnTemplate($replacements=array())
	{
		return Template::factoryString($this->render($replacements));
	}*/
	
	
	/**
	 * Renderiza la plantilla y la devuelve como un objeto Template.
	 * Alias de renderOnTemplate
	 * 
	 * @param array $replacements Array de reemplazos de la  plantila.
	 * @return object Objeto Template con el contenido de la plantilla renderizada.
	 * */
	/*public function renderToTemplate($replacements=array()){
		return Template::factoryString($this->render($replacements));
	}*/

	/********************************************************************/
	/********************************************************************/
	/********************************************************************/
	/* Filtros extra para Twig */
	
	/*public static function _filter_var_dump($data)
	{
		
		var_dump($data);die;
		return true;
	}
	
	
	public static function staticCall($class, $function, $args = array())
	{
		if (class_exists($class) && method_exists($class, $function)){
                    return call_user_func_array(array($class, $function), $args);
                }
		return null;
	}*/


}

?>
