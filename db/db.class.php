<?php

namespace lulo\db;

require LULO_DIR__DEPENDENCES__VENDOR . "/autoload.php";


/**
 * Database abstraction layer.
 * Contains deprecated operations like 
 */
class DB {

	/** AdoDB database connection */
	protected static $db_connection = null;
        
    /** Database name */
    protected static $database_name = null;

	/** Database engine */
	const ENGINE = "mssql2008";

	/** Driver used to make the connection */
	const DRIVER = "mssqlnative";
	
	/** Blob max length */
	const BLOB_MAX_PACKET_LENGTH = 52428800;

	/**
	 * Create database connection.
	 * Initializes $db_connection attribute.
	 * @param string $server Server address.
	 * @param string $user Username.
	 * @param string $password Password used to authenticate user $user.
	 * @param string $database Database to connect.
	 * @param boolean $debug Should we use debug mode?
	 * */
	public static function connect($server, $user, $password, $database=null, $debug=false) {

		try {
			$error = error_reporting(E_ERROR);
                        static::$database_name = $database;

			switch (static::DRIVER) {
				case "oci8":
				case "oci8po":
					static::$db_connection = NewADOConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->Connect($server, $user, $password);
					break;

				case "mysql":
					static::$db_connection = ADONewConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					mysql_set_charset('utf8');
					break;

				case "mysqli":
					static::$db_connection = ADONewConnection(static::DRIVER);
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					mysqli_set_charset(static::$db_connection->_connectionID, 'utf8');
					mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
					break;

				case "mssql2008":
				case "mssql2012":
				case "mssqlnative":
					static::$db_connection = ADONewConnection("mssqlnative");
					static::$db_connection->charSet = 'utf8';
					static::$db_connection->PConnect($server, $user, $password, $database);
					static::$db_connection->Execute("USE " . $database);
					break;
                                
                                case "mssql2008_linux":
                                case "mssql2012_linux":
                                case "mssql_linux":
				case "mssqllinux":
                                    require_once(LULO_DIR__DEPENDENCES__VENDOR."/adodb/adodb-php/drivers/adodb-pdo.inc.php");
                                    static::$db_connection = & ADONewConnection('pdo_mssql');
                                    $dsnString= "host={$server};dbname={$database};charset=utf8";
                                    static::$db_connection->connect('dblib:' . $dsnString, $user, $password);
      
                                    static::$db_connection->execute('SET QUOTED_IDENTIFIER ON');
                                    static::$db_connection->setConnectionParameter('characterSet','UTF-8');
                                    static::$db_connection->execute('SET ANSI_WARNINGS ON');
                                    static::$db_connection->execute('SET ANSI_PADDING ON');
                                    static::$db_connecion->execute('SET ANSI_NULLS ON');
                                    static::$db_connection->execute('SET CONCAT_NULL_YIELDS_NULL ON');
                                    static::$db_connection->execute("USE " . $database);
                                    print static::$db_connection->ErrorMsg();
                                    
                                    break;

				default:
					trigger_error("Not valid database connection ".static::DRIVER, E_USER_ERROR);
					break;
			}

			error_reporting($error);

			// Fetch data mode
			static::$db_connection->SetFetchMode(ADODB_FETCH_ASSOC);
		
			// Debug mode
			static::$db_connection->debug = $debug;
		} catch (Exception $e) {
			// If there is some exception show 503 error
			require_once __DIR__ . "error503.php";
			die();
		}
	}

        /**
         * Get the name of the database.
         * @return Return the name of the database is connected otherwise return null.
         */
        public static function getDatabaseName(){
            return static::$database_name;
        }
        
	/**
	 * Describe table structure.
	 * @param string $table Database table.
	 * @return array with the information of the table. It depends on the DBMS.
	 * For example, for MySQL is:
	 * - Field: field name
	 * - Type: field type
	 * - Null: NO or YES,
	 * - Key: PRI if primary key, UNI if unique, MUL if multikey
	 * - Default: default value
	 * - Extra: if it is AUTOINCREMENT and more options.
	 */
	public static function describe($table) {
		return static::executeSqlTemplate("describe/query.twig.sql", $table);
	}
	
	/**
	 * Clear query cache.
	 * @return array Results of clearing the cache
	 * 	 */
	public static function clearQueryCache() {
		return static::executeSqlTemplate("cache/clear/query.twig.sql");
	}
	
	/**
	 * Executes a SQL statement loaded from a path.
	 * @param string $path Path of the SQL template.
	 * @param string $table Table name the statement will be execute.
	 * @param array $replacements Extra replacements needed for getting the SQL.
	 * @return array Array with the results of the statement execution.
	 * */
	protected static function executeSqlTemplate($path, $table=null, $replacements=[]){
		$sql = static::getSqlFromSqlTemplate($path, $table, $replacements);
		return static::$db_connection->execute($sql);
	}
	
	/**
	 * Gets the SQL of a SQL statement loaded from a path.
	 * @param string $path Path of the SQL template.
	 * @param string $table Table name the statement will be returned.
	 * @param array $replacements Extra replacements needed for getting the SQL.
	 * @return string SQL code with the replacements for table and other variables done.
	 * */
	protected static function getSqlFromSqlTemplate($path, $table=null, $replacements=[]){
		// Statement
		$sqlT = \lulo\twig\TwigTemplate::factoryHtmlResource($path);
		if(!is_null($table)){
			$replacements["table"] = $table;
		}
		$sql = $sqlT->render($replacements);
		return $sql;
	}
	
	
	/*
	 * Escape a variable to be included in a SQL statement..
	 * @param string $str String to escape.
	 * @return string Escaped $str string.
	 * */
	public static function qstr($str) {
		return static::$db_connection->qstr($str);
	}

	
	/**
	 * 	Get information about table indices.
	 *
	 * @param string $table Table anem
	 *
	 * @return Object ADORecordSet with the information of the index of the table.
	 * Note thata the information depends on the DBMS used.
	 * 
	 * For example, we show the MySQL returned fields:
	 * 			'Table' => string Table name
	 * 			'Non_unique' => 0 if can't contain duplicate values, 1 otherwise.
	 * 			'Key_name' => index name.
	 * 			'Seq_in_index' => string order in the column of index, starting from 1
	 * 			'Column_name' => string column name
	 * 			'Collation' => string 'A' (ascending order) o NULL (without order).
	 * 			'Cardinality' => string number of unique values of the index. Null if
	 *			there are no unique values.
	 * 			'Sub_part' => string indexed character number that are really indexed
	 *			useful for knowing if indexing is done partially. Null if full column is indexed.
	 * 			'Packed' => key is packed by this method, null if key is not packed.
	 * 			'Null' => string 'YES' if column can contain NULL; 'NO' otherwise.
	 * 			'Index_type' => index type ('BTREE', 'FULLTEXT', 'HASH', 'RTREE').
	 * 			'Comment' => comments about index column
	 *
	 */
	public static function showIndex($table) {
		return static::executeSqlTemplate("index/show/query.twig.sql", $table, $replacements=[]);
	}

	/**
	 * Get the last ID inserted in DB.
	 * Unique for session.
	 * Works in all DBMS that implements this
	 * Read http://phplens.com/adodb/reference.functions.insert_id.html for explanation
	 * @return last inserted id in database.
	 * */
	public static function getLastInsertId() {
		return static::$db_connection->Insert_ID();
	}

	/**
	* Return the last connection error of ADOdb object.
	* @return string Message with the last connection error.
	*/
	public static function getLastError(){
		return static::$db_connection->ErrorMsg();
	}
	

	/**
	 * 	Insert a longblob
	 *  Used to avoid fully storing the blob in memroy
	 * 	@param string $tname table name.
	 * 	@param string $field name of the LONGBLOB field.
	 * 	@param string $value LONGBLOB value. MODIFIED.
	 * 	@param string $where_cond condition to get tuple.
	 */
	public static function UpdateBlob($tname, $field, &$value, $where_cond) {
		$max_packet = static::BLOB_MAX_PACKET_LENGTH;
		$actual = 0;
		while ($actual < strlen($value)) {
			$packet = substr($value, $actual, $max_packet);
			$actual+=$max_packet;
			$data = static::$db_connection->qstr($packet);
			static::$db_connection->Execute("UPDATE $tname SET $field=concat($field,$data) WHERE $where_cond");
		}
	}

	
	/**
	 * Convert an array with new data to its SQL equivalent string.
	 * Basically, escape each one of the values of $new_data and prepare a
	 * VALUES part of INSERT SQL statement.
	 * @param array $new_data Array with data to use in an SQL INSERT.
	 * @return string SQL string with the values ready to be used.
	 */
	public static function convertDataArrayToSQLModificationString($new_data) {
		// Conversion for each value if it is needed
		foreach ($new_data as $nkey => &$nvalue) {
			if (is_string($nvalue)){
				$nvalue = static::$db_connection->qstr($nvalue);
			}
			elseif (is_null($nvalue)){
				$nvalue = "NULL";
			}elseif (is_bool($nvalue)){
				$nvalue = $nvalue ? 1 : 0;
			}elseif (is_float($nvalue)){
				$nvalue = str_replace(",", ".", $nvalue);
			}
		}
		// Construction of the SQL VALUES string
		$new_data_sql = "(" . implode(",", $new_data) . ")";
		return $new_data_sql;
	}

	/**
	 * Insertion of data. This function try to insert BLOBs in a non-memory exhausting operation.
	 * This method escape all values, don't worry about the data.
	 * @param string $tname Table name to make the insertion.
	 * @param array $data Associative array of the form <field name> => <value>
	 * @param mixed $blobfields List of columns that are blobs.
	 * @param mixed $clobfields List of columns that are clobs.
	 * @return boolean true if insertion went right, false otherwise.
	 */
	public static function insert($tname, &$data, $blobfields = array(), $clobfields = array()) {
		if (is_string($blobfields)) {
			$blobfields = preg_split('/ +/', $blobfields);
		}

		if (is_string($clobfields)) {
			$clobfields = preg_split('/ +/', $clobfields);
		}

		// Max size of blobs
		$num_blobs = count($blobfields) + count($clobfields);
		if ($num_blobs > 0) {
			$max_packet = static::BLOB_MAX_PACKET_LENGTH / $num_blobs;
		}

		// Standard attributes and NULLs
		foreach ($data as $field => $value) {
			if (in_array($field, $blobfields) || in_array($field, $clobfields)) {
				$new_data[$field] = "";
				if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
					// Assuming memory control is only needed in MySQL
					if (strlen($value) < $max_packet) {
						// If blob is small enough, insert it in the query
						$new_data[$field] = $value;
						unset($blobfields[array_search($field, $blobfields)]);
						unset($clobfields[array_search($field, $clobfields)]);
					}
				}
			} else {
				$new_data[$field] = $value;
			}
		}
		
		/// INSERT of non-blob attributes
		$data_keys = array_keys($new_data);
		$data_keys_sql = "(" . implode(",", $data_keys) . ")";
		// Escape of the values
		$new_data_sql = self::convertDataArrayToSQLModificationString($new_data);
		// SQL code for the insertion
		$insertSQL = "INSERT INTO $tname $data_keys_sql VALUES $new_data_sql";
		$ok = static::$db_connection->Execute($insertSQL);
		
		/// BLOBs & CLOBs
		if ($ok) {
			foreach ($new_data as $field => $value) {
				if ($value != ""){
					$where[] = $field . self::strEq($value);
				}
			}

			$where_cond = implode(" AND ", $where);

			foreach ($blobfields as $field) {
				if ($ok !== false && isset($data[$field])){
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateBlob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
				}
			}

			foreach ($clobfields as $field){
				if ($ok !== false && isset($data[$field])){
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateClob($tname, $field, $data[$field], $where_cond);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
				}
			}
		}

		return $ok !== false;
	}


	/**
	 * Convert an array with new data to its SQL equivalent string.
	 * Basically, escape each one of the values of $new_data and prepare a
	 * VALUES part of UPDATE SQL statement.
	 * @param array $new_data Array with data to use in an SQL UPDATE.
	 * @return string SQL string with the values ready to be used.
	 */
	public static function convertDataArrayToSQLUpdateString($new_data) {
		// Creation of pairs of '<field>'=<value> escaping when needed
		$new_data_sql = "";
		foreach ($new_data as $nkey => $nvalue) {
			if (is_null($nvalue)){
				$new_data_sql .= "$nkey=NULL";
			}elseif (is_bool($nvalue)){
				$new_data_sql .= "$nkey=" . ($nvalue ? "1" : "0");
			}elseif (is_float($nvalue)){
				$new_data_sql .= "$nkey=" . str_replace(",", ".", $nvalue);
			}else{
				$new_data_sql .= $nkey . DB::strEq($nvalue);
			}
			$new_data_sql .= ",";
		}
		// Deletion of last comma
		$new_data_sql = substr($new_data_sql, 0, strlen($new_data_sql) - 1);
		return $new_data_sql;
	}

	
	/** Updating of data (UPDATE).
	 *
	 * En $data no es necesario que aparezcan todos los campos de la tupla, tan sólo aquellos que
	 * se desea actualizar.
	 *
	 * @param string $tname Table name to make the insertion.
	 * @param array $data array Associative array of the form <field name> => <value>
	 * @param string $where WHERE clause for the update.
	 * @param mixed $blobfields List of columns that are blobs.
	 * @param mixed $clobfields List of columns that are clobs.
	 * @return boolean true if query went right, false otherwise.
	 */
	public static function update($tname, &$data, $where = "", $blobfields = array(), $clobfields = array()) {

		if (is_string($blobfields))
			$blobfields = preg_split('/ +/', $blobfields);

		if (is_string($clobfields))
			$clobfields = preg_split('/ +/', $clobfields);

		// Max size of blobs
		$num_blobs = count($blobfields) + count($clobfields);
		if ($num_blobs > 0)
			$max_packet = static::BLOB_MAX_PACKET_LENGTH / $num_blobs;

		// Convert empty strings to IS NULL
		$where = preg_replace('/=\s*((\'\')|(""))/', " IS NULL ", $where);
		$where = preg_replace('/((!=)|(<>))\s*((\'\')|(""))/', " IS NOT NULL ", $where);

		// Non-blob attributes
		foreach ($data as $field => $value) {
			if (in_array($field, $blobfields) || in_array($field, $clobfields)) {
				$new_data[$field] = "";
				if (!in_array(static::DRIVER, array("oci8", "oci8po"))) {
					// Only MySQL needs blob management
					if (strlen($value) < $max_packet) {
						// If blobs are small enough, updated them directly
						$new_data[$field] = $value;
						unset($blobfields[array_search($field, $blobfields)]);
						unset($clobfields[array_search($field, $clobfields)]);
					}
				}
			} else {
				$new_data[$field] = $value;
			}
		}

		/// Update non-blob fields
		// Escape of values
		$new_data_sql = self::convertDataArrayToSQLUpdateString($new_data);
		// SQL generation
		$updateSQL = "UPDATE $tname SET $new_data_sql WHERE $where";
		// SQL execution
		$ok = static::$db_connection->Execute($updateSQL);
		// BLOBs & CLOBs update
		if ($ok) {
			foreach ($blobfields as $field) {
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateBlob($tname, $field, $data[$field], $where);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where);
					}
			}

			foreach ($clobfields as $field)
				if ($ok !== false && isset($data[$field]))
					if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
						$ok = static::$db_connection->UpdateClob($tname, $field, $data[$field], $where);
					} else {
						self::UpdateBlob($tname, $field, $data[$field], $where_cond);
					}
		}
		return $ok !== false;
	}

	
	/**
	 * Test if a field is equal to a value.
	 * Oracle needs some tweaking.
	 *
	 * Equallity comparisons are different in Oracle:
	 *
	 * For example, in MySQL this statement is right:
	 * <code>SELECT * FROM table WHERE field=""</code>
	 *
	 * Oracle needs some changes:
	 * <code>SELECT * FROM table WHERE field IS NULL</code>
	 *
	 * This method hides this strange behavior to the developer
	 * <code>$sql="SELECT * FROM table WHERE field" . DB::strEq($value);</code>
	 *
	 * If $value is empty and the DBMS is Oracle, the comparison is replaced by "IS NULL"
	 *
	 * @see strNotEq
	 * @param string $value Value to compare the field
	 * @return string Adapted string comparison to the particularities of some DBMS.
	 */
	public static function strEq($value) {
		if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
			if ((is_string($value) && empty($value)) || is_null($value)){
				return " IS NULL ";
			}
		}
		else {
			if (is_null($value)){
				return " IS NULL ";
			}
		}

		return "=" . static::$db_connection->qstr($value) . " ";
	}

	
	/**
	 * Test if a field is different to a value.
	 * Oracle needs some tweaking.
	 *
	 * Equallity comparisons are different in Oracle:
	 *
	 * For example, in MySQL this statement is right:
	 * <code>SELECT * FROM table WHERE field<>""</code>
	 *
	 * Oracle needs some changes:
	 * <code>SELECT * FROM table WHERE field IS NOT NULL</code>
	 *
	 * This method hides this strange behavior to the developer
	 * <code>$sql="SELECT * FROM table WHERE field" . DB::strEq($value);</code>
	 *
	 * If $value is empty and the DBMS is Oracle, the comparison is replaced by "IS NULL"
	 *
	 * @see strEq
	 * @param string $value Value to compare the field
	 * @return string Adapted string comparison to the particularities of some DBMS.
	 */
	public static function strNotEq($value) {
		//Comento esta linea porque IS NULL / IS NOT NULL también funcionan y vienen bien en MySQL.
		if (in_array(static::DRIVER, array("oci8", "oci8po"))) {
			if ((is_string($value) && empty($value)) || is_null($value))
				return " IS NOT NULL ";
		}
		else {
			if (is_null($value))
				return " IS NOT NULL ";
		}
		return "<>" . static::$db_connection->qstr($value) . " ";
	}

	/* DB operations */

	/**
	 * Count the number of tuples that comply with a condition in a table.
	 * @param string $table Table to be queried.
	 * @param array|string $condition SQL condition as an array or as a string.
	 * @return integer Number of tuples that comply the condition $condition in table $table.
	 */
	public static function count($table, $condition) {
		$sql = "SELECT COUNT(*) as _number_of_tuples FROM $table " . self::makeWhereCondition($condition);
		$res = static::$db_connection->getRow($sql);
		return $res["_number_of_tuples"];
	}

	
	/**
	 * Max value of a field.
	 * @param string $table Table to be queried.
	 * @param string $field Field whose max will be queried.
	 * @param array|string $condition SQL condition as an array or as a string.
	 * @return integer Max value of field $field for the tuples that comply the condition $condition in table $table.
	 */
	public static function max($table, $field, $condition=null) {
		$sql = "SELECT MAX($field) FROM $table " . self::makeWhereCondition($condition);
		$max_value = static::$db_connection->getOne($sql);
		return $max_value;
	}


	/**
	 * Delete all the tuples of the table that comply with a condition.
	 * @param string $table Name of the table which tuples will be deleted.
	 * @param array|string $condition SQL condition as an array or as a string.
	 * @return boolean true si todo ha ido bien, false en otro caso.
	 */
	public static function delete($table, $condition) {
		$sql = "DELETE FROM $table " . self::makeWhereCondition($condition);
		return static::$db_connection->execute($sql);
	}

	
	/**
	 * Create a join condition used by join operation.
	 * @param array $joinCondition Array with the corespondence of columns.
	 * @param string $table1 First table name.
	 * @param string $table2 Second table name.
	 * @return string SQL with the where condition.
	 * */
	protected static function makeJoinCondition($joinCondition, $table1, $table2) {
		$sql = "";
		foreach ($joinCondition as $field1 => $field2){
			$sql .= "$table1.$field1=$table2.$field2 AND ";
		}
		$sql = substr($sql, 0, strlen($sql) - 4);
		return $sql;
	}

	
	/**
	 * Create field selection string from a list of fields.
	 * @param array|string $fields List of fields. If a string is passed, it will be returned unchanged.
	 * @return string String that contains the columns of the table that will be selected (separated by commas).
	 */
	protected static function makeFieldsToSelect($fields, $table = null) {
		if ($table == null) {
			$table = "";
			if (is_array($fields)){
				return implode(",", $fields);
			}
		} else {
			$table = $table . ".";
		}

		if ($fields == "*"){
			return $fields;
		}
		
		$sqlFields = "";
		if (is_string($fields)) {
			if (strpos($fields, ",") !== false) {
				if (strpos($fields, ",") != (strlen($fields) - 1)){
					$fields = explode(",", $fields);
				}else{
					$fields = substr($fields, 0, strlen($fields) - 1);
				}
			} else{
				return $fields;
			}
		}
		foreach ($fields as $field){
			$sqlFields .= $table . $field . ",";
		}
		
		$sqlFields = substr($sqlFields, 0, strlen($sqlFields) - 1);
		return $sqlFields;
	}

	
	/**
	 * Creates a LIMIT array ready to be used in AdoDD SelectLimit operation.
	 * @param mixed $mylimit Limit to be applied.
	 * @return array Array of [offset, size] that will be used in AdoDB SelectLimit method.
	 * 	 */
	protected static function makeLimit($mylimit=null)
	{
		// Limit must start at 0 because that's the behavior of SelectLimit
		$limit = array(0,-1);

		// If only a number is passed, assuming that's the number
		// of elements to get in the query
		if(is_numeric($mylimit)){
			$limit = array(0,$mylimit);
		}
		
		elseif(is_string($mylimit)){
			// If the developer has passed a LIMIT clause (you shouldn't)
			// Get with pregmatch the offset and the size.
			$matches = [];
			preg_match("#[^\d]*(\d+)[^\d]*(\d+)?#", $mylimit, $matches);
			$limit[0] = $matches[1];
			if(isset($matches[2])){
				$limit[1] = $matches[2];
			}

		// If is an array, it could be 
		}elseif(is_array($mylimit)){
			// of the form [offset, size]
			if(isset($mylimit[1])){
					$limit = $mylimit;
			
			// of the form [size]
			} else {
					$limit[1] = $mylimit[0];
			}
		}

		// Return the limit as an array to be taken by AdoDB SelectLimit
		return $limit;
	}

	
	/**
	 * Creates ORDER BY clausule.
	 * @param array $order Array of pairs <field>=>"asc|desc"
	 * @return string String with the ORDER BY clausule.
	 */
	public static function makeOrderBy($order = null) {
		$st = "";
		if ($order != null and is_array($order)) {
			$st = "order by ";
			foreach ($order as $k => $v) {
				// if the pair $k, $v is  <0-9>.<atributo>, include it as table_<0-9>.<atributo>
				if (strpos($k, ".") !== false and preg_match("/^(\d+)\.(.+)$/", $k, $matches) > 0){
					$k = "table_" . $matches[1] . "." . $matches[2];
				}
				// Update total order
				$st .= $k . " " . $v . ",";
			}
			$st = substr($st, 0, strlen($st) - 1); // Delete last comma (",")
		}
		return $st;
	}

	
	/**
	 * Create a WHERE condition to be used in SELECT, UPDATE and DELETE statements.
	 * 
	 * 
	 * $fields is an array of keys and values. Keys are the fields and values are
	 * an array with <operator> => <matching value>.
	 * 
	 * But it could be that we want a complex condition, so there are two special
	 * keys: "OR" and "AND". If index is "AND" or "OR", the condition will be
	 * taken as a "AND" or "OR" of its value arrays (recursively executed).
	 *
	 * EXAMPLE:
	 *
	 * array("OR"=> array('field1' => array ("<>" => "hola mundo"), 'field2' => array ("<>" => "hola mundo")))
	 *   --> Would be
	 * 				array('field1' => array ("<>" => "hola mundo"), 'field2' => array ("<>" => "hola mundo"))
	 * 		with logicalNexus = OR would be
	 * 				(field1 <> "hola mundo" OR field2 <> "hola mundo")
	 *
	 * EQUALITY COMPARISONS
	 *
	 * Where the value of the field is not an array but a PHP type (string, numeric, boolean)
	 * that is:
	 *	   array("field" => $value)
	 * would be:
	 *     WHERE field='$value'
	 *
	 *
	 * SPECIAL CASES FOR SIMPLE VALUES
	 * 
	 * The above equality comparison will be ignored if the value is one of them:
	 *
	 *   (string) "self::<other_field>"
	 *       Would make a comparison 'field = <other_field>'.
	 *
	 *   (string) "value1[or]value2[or]value3[or]..."
	 *       Would make a comparison '(field = "value1" OR field = "value2" OR ...)'.
	 * 
	 *		Note that [or] must have no spaces between it and the values, otherwise:
	 *
	 *          field => 'hola [or] mundo'
	 *
	 *       would be:
	 *
	 *          ... (field="hola " OR field=" mundo") ...
	 *
	 *       '[or]' token must be lowercase.
	 *
	 *   (boolean) TRUE / FALSE
	 *       Would make a comparison 'field=TRUE' o 'field=FALSE'.
	 *
	 *   NULL
	 *       Would make a comparison 'field IS NULL'. To make a comparison 'IS NOT NULL',
	 *       use operator "ISNOTNULL" (see below).
	 *
	 *
	 * OPERATORS
	 *
	 * If $value is an associative array condition will be interpreted as $operator => $argument.
	 * Operator is any of the legal SQL operators:
	 *
	 *    'field' => array ("<>" => "hola mundo")
	 *
	 * will be the following SQL code:
	 *
	 *    ... field<>'hola mundo' ...
	 *
	 * Some operators are special and will be explained later.
	 *
	 * $argument can be any PHP value or the following special values:
	 *
	 *   (object) DateTime
	 *       If is a datatime will be converted to YYYY-MM-DD HH:mm:SS and will do
	 *       comparison 'field $operator "YYYY-MM-DD HH:mm:SS"'.
	 *
	 *   (string) "self::other_field"
	 *       will be a comparison of type 'field $operator other_field'.
	 *
	 *   (boolean) TRUE / FALSE
	 *       will be a comparison of type 'field $operator TRUE' or 'field $operator FALSE'.
	 *
	 *   NULL
	 *       will be a comparison 'field $operator NULL'.
	 *
	 *
	 * SPECIAL OPERATORS
	 * NOTE: operators are must be written in capital letters.
	 *
	 *   %LIKE%
	 *       would make the comparison 'field LIKE %$value%', escaping $value characters to avoid
	 *		 having problems with % and _..
	 *
	 *   %NOTLIKE%
	 *       would make the comparison 'field NOT LIKE %$value%', escaping $value characters to avoid
	 *		 having problems with % and _..
	 *   LIKE
	 *       would make the comparison 'field LIKE $value', without escaping $value characters to avoid
	 *		 having problems with % and _..
	 *
	 *   NOTLIKE
	 *       would make the comparison 'field NOT LIKE $value', without escaping $value characters to avoid
	 *		 having problems with % and _..
	 *
	 *   ISNOTNULL
	 *       would make the comparison 'field IS NOT NULL' ignoring the value.
	 *
	 *   OR
	 *       OR union comaprison. For example:
	 *
	 *           'field' => array('OR' => array('a','b','c', NULL))
	 *
	 *       would be:
	 *
	 *            ... (field='a' OR field='b' OR field='c' OR field IS NULL) ...

	 *
	 * 			If you want to make another type of comparison:
	 *
	 *           'field' => array('OR' => array('>'=>'a','b', '!='=>'c', NULL))
	 *       would be:
	 *
	 *            ... (field > 'a' OR field='b' OR field != 'c' OR field IS NULL) ...
	 * 
	 * 			If they have the same operator, yo can do "operator"=>array(values,values, values);
	 *
	 *           'field' => array('OR' => array('like'=>array('a','b'), '!='=>'c', NULL))
	 *
	 *       would be:
	 *
	 *            ... (field like 'a' OR field like 'b' OR field != 'c' OR field IS NULL) ...
	 *
	 *   NOT_IN
	 *       AND operator for the different than operator:
	 *
	 *           'field' => array('NOT_IN' => array('a','b','c', NULL))
	 *
	 *       would be:
	 *
	 *            ... (campo<>'a' AND campo<>'b' AND campo<>'c' AND campo IS NOT NULL) ...
	 *
	 *   IN
	 *       IN operator for the included values:
	 *
	 *           'field' => array('IN' => array('a','b','c', NULL, 'ISNOTNULL'))
	 *
	 *       would be:
	 *
	 *            ... field IS NULL or field IS NOT NULL OR field in ('a','b','c')    ...
	 *
	 *
	 * @param mixed array $fields Associative array were the keys are the fields of the table
	 *				and the values are arrays with operators and the values we want
	 *				to match when executing the SQL statement.
	 *
	 *              string WHERE condition.
	 *
	 * @param bool  $includeWhereKeyword Booleano Should we need to include where
	 *				in the final WHERE condition? 
	 *
	 * @param string $table Table name used in the condition generation. By default is NULL.
	 *				If $includeWhereKeyword is a string, $table will be that value.
	 *
	 * @param string $logicalNexus Logical operator that will be used. By default is "AND".
	 *
	 * @return string Return a string with the WHERE condition.
	 */
	public static function makeWhereCondition($fields, $includeWhereKeyword = true, $table = null, $logicalNexus = "AND") {
		// if the second parameter is a string, we suppose that the developer doesn't
		// want to include WHERE but for one specific table
		if (is_string($includeWhereKeyword)) {
			$table = $includeWhereKeyword;
			$includeWhereKeyword = false;
		}

		$where = "";

		if ($fields == null or count($fields) == 0){
			return "";
		}

		if (is_array($fields) and count($fields) > 0) {
			$sqlTable = "";

			// Conditions to be joined with logical nexus conditions
			$whereConditions = array();

			if ($table != null){
				$sqlTable = $table . ".";
			}

			foreach ($fields as $f => $v) {
				// Nested conditions by OR or AND
				if ($f == "OR" || $f == "AND") {
					$nested_sql_condition = "(" . static::makeWhereCondition($v, false, $table, $f) . ")";
					$whereConditions[] = $nested_sql_condition;
					continue;
				}
				if (is_array($v)) {
					// If $v is na array ($op=>$value)
					foreach ($v as $op => $value) {
						// Special values for $value
						if (is_object($value) && ($value instanceof DateTime)) {
							$sql_value = static::$db_connection->qstr($value->format("Y-m-d H:i:s"));
						} elseif (is_string($value) && preg_match("/^(self::)(.+)/", $value, $matches)) {
							$sql_value = $matches[2];
						} elseif (is_null($value)) {
							$sql_value = "NULL";
						} elseif ($value === FALSE) {
							$sql_value = "FALSE";
						} elseif ($value === TRUE) {
							$sql_value = "TRUE";
						} elseif (!is_array($value)) {
							$sql_value = static::$db_connection->qstr($value);
						}

						// Specials operator (in $op)
						$upper_operation = strtoupper($op);
						if ($upper_operation == "%LIKE%") {
							$sql_value = addcslashes($sql_value, "%_");
							$sql_value = static::cleanValueForLike($sql_value);
							$whereConditions[] = "{$sqlTable}{$f} LIKE '%{$sql_value}%'";
						}
						elseif ($upper_operation == "%NOTLIKE%") {
							$sql_value = addcslashes($sql_value, "%_");
							$sql_value = static::cleanValueForLike($sql_value);
							$whereConditions[] = "{$sqlTable}{$f} NOT LIKE '%{$sql_value}%'";
						}
						elseif ($upper_operation == "LIKE") {
							$sql_value = static::cleanValueForLike($sql_value);
							$whereConditions[] = "{$sqlTable}{$f} LIKE '{$sql_value}'";
						}
						elseif ($upper_operation == "NOTLIKE") {
							$sql_value = static::cleanValueForLike($sql_value);
							$whereConditions[] = "{$sqlTable}{$f} NOT LIKE '{$sql_value}'";
						}
						elseif ($upper_operation == "IS NOT" and is_null($value)) {
							$whereConditions[] = "{$sqlTable}{$f} IS NOT NULL";
						} elseif ($upper_operation == "ISNOTNULL") {
							$whereConditions[] = "{$sqlTable}{$f} IS NOT NULL";
						} elseif ($upper_operation == "OR") {
							if (!is_array($value)){
								throw new \InvalidArgumentException("An array was expected for OR operator");
							}

							if (!empty($value)) {
								$conditionParts = array();
								foreach ($value as $o => $val) {
									if (is_numeric($o)){
										$o = "=";
									}
									if (strtoupper($o) == "ISNOTNULL" || (!is_array($val) && strtoupper($val) == "ISNOTNULL")){
										$conditionParts[] = "{$sqlTable}{$f} IS NOT NULL";
									}elseif (is_null($o) || is_null($val)){
										$conditionParts[] = "{$sqlTable}{$f} IS NULL";
									}else {
										if (!is_array($val)){
											$conditionParts[] = "{$sqlTable}{$f} {$o} " . static::$db_connection->qstr($val);
										}else{
											foreach ($val as $iter){
												$conditionParts[] = "{$sqlTable}{$f} {$op} " . static::$db_connection->qstr($iter);
											}
										}
									}
								}

								$whereConditions[] = "(" . implode(" OR ", $conditionParts) . ")";
							}
						}elseif ($upper_operation == "NOT_IN") {
							if (!is_array($value)){
								throw new \InvalidArgumentException("An array was expected for NOT_IN operator");
							}

							if (!empty($value)) {
								$conditionParts = array();
								foreach ($value as $val) {
									$conditionParts[] = "{$sqlTable}{$f}" . self::strNotEq($val);
								}

								$whereConditions[] = "(" . implode(" AND ", $conditionParts) . ")";
							}
						} elseif ($upper_operation == "IN") {
							if (!is_array($value)){
								throw new \InvalidArgumentException("An array was expected for IN operator");
							}

							if (!empty($value)) {
								$conditionParts = "";
								$extraConditionPart = "";
								foreach ($value as $val) {
									// If value is NULL, condition is special
									if ($val == null){
										$extraConditionPart = "{$sqlTable}{$f} IS NULL OR";
									}else if (strtoupper($val) == "ISNOTNULL"){
										$extraConditionPart .= " {$sqlTable}{$f} IS NOT NULL OR";
									}else{
										$conditionParts[] = static::$db_connection->qstr($val);
									}
								}

								if (empty($conditionParts)){
									$whereConditions[] = substr($extraConditionPart, 0, -3);
								}else{
									$whereConditions[] = " ( $extraConditionPart {$sqlTable}{$f} IN ( " . implode(", ", $conditionParts) . ")  )";
								}
							}
						} else {
							$whereConditions[] = "{$sqlTable}{$f} {$op} {$sql_value}";
						}
					}
				} else {

					// Special case of OR values: [or]
					if (strpos($v, "[or]") !== false) {
						$vs = explode("[or]", $v);

						$orConditionParts = array();
						foreach ($vs as $val) {
							$orConditionParts[] = "$sqlTable$f" . self::strEq($val);
						}

						$orCondition = "(" . implode(" OR ", $orConditionParts) . ")";
						$whereConditions[] = "{$orCondition}";

						// pasar a la siguiente iteración del bucle de condiciones
						continue;
					}

					// Other simple cases
					if (preg_match("/^(self::)(.+)/", $v, $matches)) {
						$v = "=" . $matches[2];
					} elseif ($v === FALSE) {
						$v = "=FALSE";
					} elseif ($v === TRUE) {
						$v = "=TRUE";
					} elseif (strtoupper($v) == "ISNOTNULL") {
						$v = " IS NOT NULL";
					} else {
						$v = self::strEq($v);
					}

					$whereConditions[] = "{$sqlTable}{$f}{$v}";
				}
			}

			// if the inclusion of WHERE is needed 
			if ($includeWhereKeyword) {
				$where .= " WHERE";
			}

			$where .= " " . implode(" {$logicalNexus} ", $whereConditions);
		} else {
			// if $fields is not an array, it is assumed that is a string.
			// Insertion of WHERE if needed
			if (!preg_match("/^where/i", $fields)) {
				$where .= " WHERE";
			}

			$where .= " " . $fields;
		}

		return $where;
	}

	
	/**
	 * Clean value for like.
	 * @param string $value Posibly a escaped string that we must clean to use with LIKE operator.
	 * @return string Clean value for use in a LIKE condition.
	 * 	 */
	protected static function cleanValueForLike($value){
		// Delete first quote if exists
		if ($value[0] == "'"){
			$value = substr($value, 1);
		}
		// Delete last quote if exists
		if ($value[strlen($value) - 1] == "'"){
			$value = substr($value, 0, strlen($value) - 1);
		}
		// Return value ready to use in a LIKE condition
		return $value;
	}
	
	
	/**
	 * Return a tuple as an array.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return array Array that represents a tuple, so its keys are the names of the columns of the table.
	 */
	public static function getRow($table, $fields="*", $condition = null, $order = null) {
		$selected_fields = self::makeFieldsToSelect($fields);
		return static::$db_connection->getRow("SELECT $selected_fields FROM $table " . self::makeWhereCondition($condition) . " " . self::makeOrderBy($order) . " LIMIT 1");
	}

	
	/**
	 * Return one value of a tuple.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $selectedField Column which value we want to return.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @return string Value of selected field in table $table with condition $condition.
	 * 	 */
	public static function getOne($table, $selectedField, $condition=null) {
		// This query contains an implicit LIMIT 1, so only the value of the first
		// tuple will be returned
		return static::$db_connection->getOne("SELECT $selectedField FROM $table " . self::makeWhereCondition($condition));
	}

	
	/**
	 * Get array of arrays to loop to the results of a getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return array Array of arrays where each contained array represents a tuple.
	 * 	 */
	public static function getAll($table, $fields = "*", $condition = null, $order = null, $limit = null) {
		$rs = static::getAllAsRecordSet($table, $fields, $condition, $order, $limit);
		return $rs->getArray();
	}

	
	/**
	 * Get recordset to loop to the results of a getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return object RecordSet object to loop through the results.
	 * 	 */
	public static function getAllAsRecordSet($table, $fields="*", $condition=null, $order=null, $limit=null)
	{
		$limit_interval = static::makeLimit($limit);
		$sql = static::generateSQLForGetAll($table, $fields, $condition, $order);
		$rs = static::$db_connection->SelectLimit($sql, $limit_interval[1], $limit_interval[0]);
		return $rs;
	}
	
	
	/**
	 * Get SQL for getAll operation.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $oder Order to be applied to the results. With the form: <field>=>"ASC|DESC".
	 * @return string SQL code of the selection statement.
	 * 	 */
	private static function generateSQLForGetAll($table, $fields="*", $condition=null, $order=null){
		$fields_to_select = static::makeFieldsToSelect($fields);
		return "SELECT {$fields_to_select} FROM {$table} ".static::makeWhereCondition($condition)." ".static::makeOrderBy($order);
	}
	
	
	/**
	 * Return random tuples of a table.
	 * @param string $table Table to get its random tuples.
	 * @param array|string $fields Fields to select. An array of columns or "*" if all columns must be selected.
	 * @param array|string $condition Condición SQL. Associative array or string like "WHERE field1='' and field2...". If null no condition will be used.
	 * @param array|string $limit Limit of tuples. If null, no limit will be enforced.
	 */
	public static function getRandom($table, $fields = "*", $condition = null, $limit = null) {

		$field_selection = self::makeFieldsToSelect($fields);
		$where_condition_clause = self::makeWhereCondition($condition);

		$order_clause = "ORDER BY RAND()";
		$limit_clause = self::makeLimit($limit);

		$sql = "SELECT $field_selection FROM $table $where_condition_clause $order_clause $limit_clause";

		$res = static::$db_connection->getAll($sql);
		return $res;
	}


	/**
	 * Delete the table $table.
	 * @param string $table Table to be deleted.
	 */
	public static function dropTable($table) {
		return static::executeSqlTemplate("drop/query.twig.sql", $table);
	}


	/**
	 * Create an unique uuid.
	 * @param string $prefix UUID prefix.
	 * @return string UUID with 2 random chars [a-z] + 32 chars [0-9a-f] (based on md5)
	 */
	public static function uuid($prefix = "") {
		$uuid = uniqid($prefix . $_SERVER["SERVER_NAME"] . rand(0, 1000), true);
		$chars = "abcdefghijklmnopqrstuvwxyz";
		$rand1 = mt_rand(0, strlen($chars) - 1);
		$rand2 = mt_rand(0, strlen($chars) - 1);
		$uuid = $prefix . $chars[$rand1] . $chars[$rand2] . md5($uuid);
		return $uuid;
	}


	/**
	 * Show tables in the database.
	 * @param string $database Database name.
	 * @return array Array with the tables of $database database.
	 * */
	public static function showTables($database) {
		return static::executeSqlTemplate("index/show_tables/query.twig.sql", $table=null, $replacements=["database"=>$database]);
	}


	/**
	 * Check if table exists in current database.
	 * @param $table Table name.
	 * @return boolean true if table exists, false otherwise.
	 * */
	public static function existsTableInCurrentDatabase($table) {
		$tableList = self::showTables();
		return in_array($table, $tableList);
	}

	
	/**
	 * Check if table exists in current database.
	 * @param $table Table name.
	 * @return boolean true if table exists, false otherwise.
	 * */
	public static function existsTable($table) {
		return self::existsTableInCurrentDatabase($table);
	}


	/**
	 * Executes arbitrary SQL code
	 * @param string $sqlCode SQL code to execute.
	 * @return mixed What the execution returns. Usually an AdoDB object.
	 * */
	public static function execute($sqlCode) {
		$results = static::$db_connection->execute($sqlCode);
		return $results;
	}

	
	/**
	 * Return create table SQL code.
	 * @params string $tableName Table name whose create table statement is needed.
	 * @return string SQL code with the creation of table $tableName.
	 */
	public static function getCreateTable($tableName) {
		return static::executeSqlTemplate("create/get_from_table.twig.sql", $tableName);
	}
		

	/**
	 * Create a table in the DBMS
	 * @param string $tableName Table name.
	 * @param array $tableFields Table fields.
	 * @return boolean true if creation went right, false otherwise.
	 * */
	public function createTable($tableName, $tableFields) {
		return static::$db_connection->CreateTableSQL($tableName, $tableFields);
	}

	
	/**
	 * Return the field length of a tuple.
	 * @param string $table Table name.
	 * @param string $field Field name.
	 * @param array $condition Condition of the selected tuple.
	 * @return integer Bytes of the field of the selected tuple.
	 * */
	private static function fieldLength($table, $field, $condition=null) {
		$lengthField = "_length_" . $field;

		$sql = "SELECT " . static::$db_connection->length . "($field) AS $lengthField FROM $table " . self::makeWhereCondition($condition);
		$res = static::$db_connection->getRow($sql);
		if (count($res) == 0 or $res == null or ! isset($res[$lengthField])){
			return null;
		}
		return (int) ($res[$lengthField]);
	}

	
	/**
	 * Check if value in a field is NULL.
	 * @param string $table Table name.
	 * @param string $field Field name.
	 * @param array $condition Condition of the selected tuple.
	 * @return boolean true if field value is NULL, false otherwise.
	 * */
	public static function fieldIsNull($table, $field, $condition = null) {
		$length = self::fieldLength($table, $field, $condition);
		if ($length == null){
			return true;
		}
		return false;
	}

	const TABLE_FIELD_SEPARATOR = ".";
	const TABLE_ALIAS_PREFIX = "table_";

	protected static $I_TABLE = 0;

	const UNIQUE_PREFIX_NUMBER_SUFFIX = "@@@###@@@";

	protected static function asUniqueTableName($tableName) {
		self::$I_TABLE++;
		return $tableName . self::UNIQUE_PREFIX_NUMBER_SUFFIX . self::$I_TABLE;
	}

	protected static function asTableName($tableName) {
		$pos = strpos($tableName, self::UNIQUE_PREFIX_NUMBER_SUFFIX);
		if ($pos === false){
			return $tableName;
		}
		return substr($tableName, 0, $pos);
	}

	
	/**
	 * Natural join between several tables.
	 * Implements INNER JOIN between several tables.
	 * - First key in $tables is main table
	 * - The other tables are joined to main table.
	 * 
	 * @param array $tables Array Hash with the tables as the keys. The values are the fields to be selected of each table. If "*", all fields will be selected.
	 * @param array $onConditions Conditions for each table. For each table (table_i), can contains two keys:
	 * - "on" => conditions that link fields of different tables "tabla_i-1.field"=>"tabla_i.field"
	 * - "extra" => extra conditions  on table_i
	 * @param Array $whereConditions Conditions on main table (first table in $tables).
	 * @param Array $params extra options to custom INNER JOIN results:
	 * 	"order" => order results "table_name"=>array("field"=>ORDER, "field"=>ORDER)
	 * 	"limit" => limit results
	 * 	"debug" => show generated SQL and stops
	 * 	"distinct" => should the results be distinct?
	 * 	"explain" => adds EXPLAIN keyword to obtain metainformation about performance.
	 * 	"straight_join" => adds STRAIGHT_JOIN in SELECT.
	 * @return array Array of arrays with the values of the selected fields.
	 * */
	public static function join($tables, $onConditions=null,  $whereConditions=null, $params=null)
	{
		$rs = static::joinAsRecordSet($tables, $onConditions,  $whereConditions, $params);
		return $rs->getArray();
	}

	
	/**
	 * Natural join between several tables.
	 * Implements INNER JOIN between several tables.
	 * - First key in $tables is main table
	 * - The other tables are joined to main table.
	 * 
	 * @param array $tables Array Hash with the tables as the keys. The values are the fields to be selected of each table. If "*", all fields will be selected.
	 * @param array $onConditions Conditions for each table. For each table (table_i), can contains two keys:
	 * - "on" => conditions that link fields of different tables "tabla_i-1.field"=>"tabla_i.field"
	 * - "extra" => extra conditions  on table_i
	 * @param Array $whereConditions Conditions on main table (first table in $tables).
	 * @param Array $params extra options to custom INNER JOIN results:
	 * 	"order" => order results "table_name"=>array("field"=>ORDER, "field"=>ORDER)
	 * 	"limit" => limit results
	 * 	"debug" => show generated SQL and stops
	 * 	"distinct" => should the results be distinct?
	 * 	"explain" => adds EXPLAIN keyword to obtain metainformation about performance.
	 * 	"straight_join" => adds STRAIGHT_JOIN in SELECT.
	 * @return recordset Recordset with the values of the selected fields.
	 * */	
	public static function joinAsRecordSet($tables, $onConditions=null,  $whereConditions=null, $params=null)
	{
		$tablesDict = array();
		$tablesOrig = array();

		// Fields to select, if not array, take all (*)
		$fieldSQL = "";
		$i = 0;
		foreach($tables as $table_name=>$fields){
			$tablesOrig[] = $table_name;
			if(!is_array($fields) && !is_null($fields)){
				$table_name = $fields;
				$fieldSQL .= "tabla_$i.*,";
			}
			else{
				if(!empty($fields)){
					foreach($fields as $f){
						$fieldSQL .= "tabla_$i.$f,";
					}
				}

			}
			
			$table_name = preg_replace("#-alias-$#", "",$table_name);
			$tablesDict[] = $table_name;
			$i++;
		}

		// Erasing the last comma
		$fieldSQL = substr($fieldSQL, 0, strlen($fieldSQL)-1);

		// Getting ON conditions
		$i = 1;
		$innerSql = "";
		
		$join = "INNER";
		if(isset($params["join"])){
			$join = strtoupper($params["join"]);
		}
		
		$extraWhereCond = array();	
		foreach($onConditions as $oC){
			if(!isset($oC["on"]))
				$on = array_pop($oC);
			else
				$on = $oC["on"];
			
			$joinCond = static::makeJoinCondition($on, "tabla_".($i-1), "tabla_$i");
			$extraJoin = "";
			if(isset($oC["extra"]) && !empty($oC["extra"])){
				$extraJoinCond = array();
				foreach($oC["extra"] as $field=>$cond){
					if($field != "OR" and $field != "AND"){
							$extraJoinCond["tabla_$i.".$field] = $cond;
					}else{
						foreach($cond as $f=>$c)
							$extraJoinCond[$field]["tabla_$i.".$field] = $cond;
					}
				}
				$extraJoin = " AND ".static::makeWhereCondition($extraJoinCond, false);
			}
			
			// Conditions 
			if(isset($oC["where"]) && !empty($oC["where"])){
				$extraJoinCond = array();
				foreach($oC["where"] as $field=>$cond){
					if($field != "OR" and $field != "AND"){
							$extraWhereCond["tabla_$i.".$field] = $cond;
					}else{
						foreach($cond as $f=>$c)
							$extraWhereCond[$field]["tabla_$i.".$field] = $cond;
					}
				}
			}
			
			$innerSql .= "$join JOIN {$tablesDict[$i]} tabla_$i ON $joinCond $extraJoin \n";
			$i++;
		}
		
		// Table 0 conditions in WHERE clause
		if(!is_null($whereConditions)){
			
			foreach($whereConditions as $field=>$cond){
				if($field != "OR" and $field != "AND"){
						$extraWhereCond["tabla_0.".$field] = $cond;
				}else{
					foreach($cond as $f=>$c)
						$extraWhereCond[$field]["tabla_0.".$field] = $cond;
				}
			}
		}
		$where = static::makeWhereCondition($extraWhereCond);
		
		// SQL code generation
		$sqlDistinct = "DISTINCT";
		if(!isset($params["distinct"]) || !$params["distinct"]){
			$sqlDistinct = "";
		}
		
		$sqlExplain = "EXPLAIN";
		if(!isset($params["explain"]) || !$params["explain"]){
			$sqlExplain = "";
		}
			
		$sqlStraight = "STRAIGHT_JOIN";
		if(!isset($params["straight_join"]) || !$params["straight_join"]){
			$sqlStraight = "";
		}

		$sql = "$sqlExplain SELECT $sqlStraight $sqlDistinct $fieldSQL \nFROM {$tablesDict[0]} tabla_0 \n $innerSql \n$where ";
		
		// ORDER of the query
		if(isset($params["order"]) && $params["order"] != null){
			$ordenFinal = array();
			foreach($params["order"] as $table_name=>$fields){
				foreach($fields as $f=>$order){
					$pos = array_search($table_name, $tablesOrig);
					$ordenFinal["tabla_$pos.".$f] = $order;
				}
			}
			$sql .= static::makeOrderBy($ordenFinal)."\n";
		}
		
		// Limit of the query
		if(isset($params["limit"]) && $params["limit"] != null){
			$limit = $params["limit"];
		}else{
			$limit = null;
		}
		
		$limit = static::makeLimit($limit);
		
		// Results
		if(isset($params["debug"])){
			print $sql."<br/><br/>";die;
		}

		$rs = static::$db_connection->SelectLimit($sql,$limit[1],$limit[0]);
		
		return $rs;
	}


	/**
	 * Update a table of our current database.
	 * @param string $table Name of the table to update.
	 * @param array $newValues Associative array with pairs of <field>=><new value>.
	 * @param array|string SQL condition. One associative array or a string "WHERE field1='' and field2..."
	 */
	public static function updateFields($table, $newValues, $condition) {
		return self::update($table, $newValues, self::makeWhereCondition($condition, false));
	}

	
	/* Lock tables.
	 *
	 * LOCK TABLES
	  tbl_name [[AS] alias] lock_type
	  [, tbl_name [[AS] alias] lock_type] ...

	  lock_type:
	  READ [LOCAL]
	  | [LOW_PRIORITY] WRITE
	 *
	 * 	Valid formats:
	 * lockTables(array(
	 * 	TABLA => array("lock_type"),
	 * 	TABLA => array("lock_type", "alias"),
	 * 	TABLA => array(array("lock_type"), array("lock_type", "alias1"), ...)
	 * 	TABLA => array(array("lock_type", "alias1"), array("lock_type", "alias2"), ...)
	 * ))
	 * 
	 * @param $tablas Array. Tables that we want to be locked are index of the array. Values are the type lock and the alias.
	 */
	public static function lockTables($tablas) {

		$sql = "";
		foreach ($tablas as $tabla => $opciones) {
			if ($sql != "")
				$sql .= ", ";

			// Legacy compatibility.
			// Format TABLA => array("READ") o TABLA => array("WRITE") ...
			if (count($opciones) == 1 && is_string($opciones[0])) {
				$sql .= " " . $tabla . " " . $opciones[0];
			}
			// Format TABLA => array("READ", "alias") o TABLA => array("WRITE, "alias") ...
			elseif (count($opciones) == 2 && is_string($opciones[0]) && is_string($opciones[1])) {
				$sql .= " " . $tabla . " AS " . $opciones[1] . " " . $opciones[0];
			}
			// http://dev.mysql.com/doc/refman/5.5/en/lock-tables.html
			// You cannot refer to a locked table multiple times in a single query
			// using the same name. Use aliases instead, and obtain a separate lock
			// for the table and each alias.
			// Format TABLA => array(array("READ"), array("READ", "alias1"), array("WRITE", "alias2"), ...)
			// or
			// TABLA => array(array("READ", "alias1"), array("READ", "alias2"), array("WRITE", "alias3"), ...)
			else {
				$sql2 = "";
				foreach ($opciones as $opcion) {
					if ($sql2 != "")
						$sql2 .= ", ";
					//var_dump($sql2);
					// Damos por hecho que son arrays con dos valores
					if (count($opcion) == 1 && is_string($opcion[0])) {
						$sql2 .= " " . $tabla . " " . $opcion[0];
					}
					// Formato TABLA => array("READ", "alias") o TABLA => array("WRITE, "alias") ...
					elseif (count($opcion) == 2 && is_string($opcion[0]) && is_string($opcion[1])) {
						$sql2 .= " " . $tabla . " AS " . $opcion[1] . " " . $opcion[0];
					}
				}
				$sql .= $sql2;
			}
		}
		$sql = "LOCK TABLES " . $sql;
		//print $sql; die();


		static::$db_connection->execute($sql);
	}

	
	/* 
	 * Unlock tables of a previous lock.
	 */
	public static function unlockTables() {

		static::$db_connection->execute("UNLOCK TABLES");
	}

	
	// Transaction functions
	// USE:
	// DBHelper::desactivateAutocommit(); //si usamos START TRANSACTION no hace falta
	// DBHelper::startTransaction();
	// 	SQL OPERATIONS
	// In case of error:
	//   DBHelper::rollback();
	// If everything went right:
	//   DBHelper::commit();
	//   DBHelper::activateAutocommit();//si usamos START TRANSACTION no hace falta
	//
	
	
	private static function activateAutocommit() {
		static::$db_connection->execute("SET autocommit = 1");
	}

	
	private static function desactivateAutocommit() {

		static::$db_connection->execute("SET autocommit = 0");
	}

	
	/*
	 *  work -> STRING indica el punto sobre el que hacer el commit
	 *  chain -> 0|1 -> indica si hace chain o no
	 *  release -> 0|1 -> si se hace release o no 
	 * 
	 * */
	public static function commit($work = null, $chain = null, $release = null) {
		$commit_sql = "";

		if ($work !== NULL)
			$commit_sql = $work;

		if ($chain !== NULL)
			if ($chain == 1)
				$commit_sql .= " AND CHAIN";
			else
				$commit_sql .= " AND NO CHAIN";

		if ($release !== NULL)
			if ($release == 1)
				$commit_sql .= " AND RELEASE";
			else
				$commit_sql .= " AND NO RELEASE";



		static::$db_connection->execute("commit $commit_sql");
	}

	
	public static function rollback($work = null, $chain = null, $release = null) {

		$sql = "ROLLBACK";
		if (!is_null($work))
			$sql = "ROLLBACK WORK";

		if ($chain !== NULL)
			if ($chain == 1)
				$sql .= " AND CHAIN";
			else
				$sql .= " AND NO CHAIN";

		if ($release !== NULL)
			if ($release == 1)
				$sql .= " AND RELEASE";
			else
				$sql .= " AND NO RELEASE";


		static::$db_connection->execute($sql);
	}

	
	/*
	 * transaction_characteristic:
	 *     WITH CONSISTENT SNAPSHOT
	 *   | READ WRITE
	 *   | READ ONLY
	 * 
	 * */
	public static function startTransaction($type = "begin", $work = "", $characteristics = []) {

		// If using BEGIN, deactivate AUTOCOMMIT
		if ($type === "begin")
			$sql = "BEGIN {$work};";
		else {
			$sql = "START TRANSACTION";
			if (!empty($characteristics)) {
				foreach ($characteristics as $c)
					$sql .= " {$c},";
				//quitamos la última coma que se incluyó
				$sql = substr($sql, 0, -1);
			}

			$sql .= ";";
		}

		static::$db_connection->execute($sql);
	}

	
	/**
	 * Creates an unique slug.
	 * @param string $text Text that will be converted to slug.
	 * @param integer $limit Maximum size of the slug.
	 * @param string $tableName Table that will store the slug.
	 * @param string $field Field that will store the slug.
	 * @param array $extraCondition Extra condition.
	 * @return string Unique slug based on $text.
	 */
	public static function dbMakeUniqueLargeSlug($text, $limit, $tableName, $field, $extraCondition=null ) {
		if ($extraCondition == null){
			$extraCondition = array();
		}

		$slug = static::makeLargeSlug($text, $limit);
		if (empty($slug)){
			$slug = "xyzzy";
		}

		$condition = array($field => $slug);
		if ($extraCondition != null and is_array($extraCondition) and count($extraCondition) > 0){
			$condition = array_merge($condition, $extraCondition);
		}
		$i = 2;
		$res = static::getOne($tableName, $field, $condition);
		$existe = is_string($res);
		while ($existe) {
			$slugAux = $slug;
			// Suffix to make slug unique in the table
			$suffix = "-" . $i;
			// Cut the slug to obey limit size
			if (strlen($slug) == $limit) {
				$slugAux = substr($slug, 0, $limit - strlen($suffix));
			}
			$condition[$field] = $slugAux . $suffix;

			$res = static::getOne($tableName, $field, $condition);
			$existe = is_string($res);
			$i++;
		}
		return $condition[$field];
	}
	
	
	/**
	 * Convert $text to a slug of maximum size $limit.
	 * @param string $text Text that will be converted to slug.
	 * @param integer $limit Maximum size of the generated slug.
	 * 	 */
	protected static function makeLargeSlug($text, $limit){
		$text = trim($text);
		$text = mb_strtolower( $text, "UTF-8"); // lowercase		
		$text = preg_replace('/á|à|ä|â|æ|ª/','a', $text);
		$text = preg_replace('/é|è|ë|ê|€/','e', $text);
		$text = preg_replace('/í|ì|ï|î/','i', $text);
		$text = preg_replace('/ó|ò|ö|ô|ø|º/','o', $text);
		$text = preg_replace('/ú|ù|ü|û/','u', $text);
		$text = preg_replace('/ñ|ń/','n', $text);
		$text = preg_replace( '/[^a-z0-9- ]/', '', $text ); // delete all non-ascii chars
		$text = preg_replace( '/\s+/', '-', $text ); // replace spaces with dashes
		$text = preg_replace( '/(\-+)/', '-', $text ); // delete multiple dashes
		$text = preg_replace( '/(\_+)/', '-', $text ); // delete _
		if(is_integer($limit) and $limit>0){
			$text = substr($text, 0, $limit);
		}
		return $text;
	}
}

?>
