<?php

namespace lulo\query;

/**
 * SQL transaction helper.
 * Work in progress. Please, contribute!
 *  */
class Transaction{
	
	/** DB connection to use in as a DB translation layer */
	const DB = "\lulo\db\DB";
	
	
	/**
	 * Creates the transaction.
	 */
	protected function __construct(){
	}

	
	/**
	 * Starts the transaction.
	 * Executes START TRANSACTION statement.
	 */
	protected function start(){
		$db = static::DB;
		$db::execute("START TRANSACTION");
	}
	
	
	/**
	 * Ends the transaction.
	 * Executes COMMIT statement.
	 */
	protected function end(){
		$db = static::DB;
		$db::execute("COMMIT");
	}

	
	/**
	 * Creates a new transaction.
	 * @param function $function Function to wrap in the transaction.
	 */
	public static function n($function){
		$transaction = new Transaction();
		$transaction->start();
		$result = $function();
		$transaction->end();
		return $result;
	}
	
}
