<?php

spl_autoload_register("load_test_models");

function load_test_models($class_name){
	
	$class_file_name = strtolower($class_name);
	if(strpos($class_name, "\\") === false){
		$root_path = LULO_DIR."/tests/models";
		$class_path = "{$root_path}/{$class_file_name}.class.php";
	}else{
		$root_path = PARENT_LULO_DIR;
		$class_file_name = str_replace("\\", "/",  strtolower($class_name));
		$class_path = "{$root_path}/{$class_file_name}.class.php";
	}	
	if(file_exists($class_path)){
		require_once $class_path;
		return true;
	}
	return false;
}
