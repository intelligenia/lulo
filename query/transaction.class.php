<?php

namespace lulo\query;

/**
 * Transacción.
 *  */
class Transaction{
	
	/** DBHelper que se va a usar para realizar las transacciones */
	const DBHELPER = "DBHelper";
	
	
	/**
	 * Construye la transacción.
	 */
	protected function __construct(){
	}

	
	/**
	 * Inicia la transacción.
	 */
	protected function start(){
		$db = static::DBHELPER;
		$db::execute("START TRANSACTION");
	}
	
	
	/**
	 * Finaliza la transacción.
	 */
	protected function end(){
		$db = static::DBHELPER;
		$db::execute("COMMIT");
	}

	
	/**
	 * Factoría que crea una nueva transacción.
	 * @param lambda $function Función que se va a ejecutar en una transacción.
	 */
	public static function n($function){
		$transaction = new Transaction();
		$transaction->start();
		$result = $function();
		$transaction->end();
		return $result;
	}
	
}
