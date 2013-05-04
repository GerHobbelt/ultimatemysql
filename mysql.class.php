<?php
/**
 * Ultimate MySQL Wrapper Class for PHP 5.x
 *
 *
 * Contributions from
 *   Frank P. Walentynowicz
 *   Larry Wakeman
 *   Nicola Abbiuso
 *   Douglas Gintz
 *   Emre Erkan
 *   Vincent van Daal
 *   Xander Groesbeek   (SQLValue:int quoting; QueryArray tweak)
 *   Ger Hobbelt
 *
 * <ul>
 * <li> Establish MySQL server connections easily
 * <li> Execute SQL queries
 * <li> Retrieve query results into objects or arrays
 * <li> Retrieve the last inserted ID
 * <li> Manage transactions (transaction processing)
 * <li> Retrieve the list tables of a database
 * <li> Retrieve the list fields of a table (or field comments)
 * <li> Retrieve the length or data type of a field
 * <li> Measure the time a query takes to execute
 * <li> Display query results in an HTML table
 * <li> Easy formatting for SQL parameters and values
 * <li> Generate SQL Selects, Inserts, Updates, and Deletes
 * <li> Error handling with error numbers and text
 * <li> And much more!
 * </ul>
 *
 * Feb 02, 2007 - Written by Jeff Williams (Initial Release)
 * Feb 11, 2007 - Contributions from Frank P. Walentynowicz
 * Feb 21, 2007 - Contribution from Larry Wakeman
 * Feb 21, 2007 - Bug Fixes and PHPDoc
 * Mar 09, 2007 - Contribution from Nicola Abbiuso
 * Mar 22, 2007 - Added array types to RecordsArray and RowArray
 * Jul 01, 2007 - Class name change, constructor values, static methods, fixed
 * Jul 16, 2007 - Bug fix, removed test, major improvements in error handling
 * Aug 11, 2007 - Added InsertRow() and UpdateRow() methods
 * Aug 19, 2007 - Added BuildSQL static functions, DeleteRows(), SelectRows(),
 *                IsConnected(), and ability to throw Exceptions on errors
 * Sep 07, 2007 - Enhancements to SQL SELECT (column aliases, sorting, limits)
 * Sep 09, 2007 - Updated SelectRows(), UpdateRow() and added SelectTable(),
 *                TruncateTable() and SQLVALUE constants for SQLValue()
 * Oct 23, 2007 - Added QueryArray(), QuerySingleRow(), QuerySingleRowArray(),
 *                QuerySingleValue(), HasRecords(), AutoInsertUpdate()
 * Oct 28, 2007 - Small bug fixes
 * Nov 28, 2007 - Contribution from Douglas Gintz
 * Jul 06, 2009 - GetXML() and GetJSON() contribution from Emre Erkan
 *                and ability to use a blank password if needed
 *
 * @example
 * include("mysql.class.php");
 *
 * $db = new MySQL();
 * $db = new MySQL(true, "database");
 * $db = new MySQL(true, "database", "localhost", "username", "password");
 *
 *
 * @category  Ultimate MySQL Wrapper Class
 * @package Ultimate MySQL Wrapper
 * @version 2.5.1
 * @author Jeff L. Williams
 * @copyright 2007-2012 Jeff L. Williams
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://www.phpclasses.org/ultimatemysql            Ultimate MySQL
 */




/**
 * Ultimate MySQL Wrapper Class
 */
class MySQL
{
	// SET THESE VALUES TO MATCH YOUR DATA CONNECTION

	/** server name */
	private $db_host             = 'localhost';
	/** user name */
	private $db_user             = '';
	/** password */
	private $db_pass             = '';
	/** database name */
	private $db_dbname           = '';
	/** optional character set (i.e. utf8) */
	private $db_charset          = 'utf8';
	/** optional character set collation (i.e. utf8_unicode_ci) */
	private $db_charsetcollation = 'utf8_unicode_ci';
	/** use persistent connection? */
	private $db_pcon             = false;

	// constants for SQLValue function

	const SQLVALUE_BIT      = 'bit';
	const SQLVALUE_BOOLEAN  = 'boolean';
	const SQLVALUE_DATE     = 'date';
	const SQLVALUE_DATETIME = 'datetime';
	const SQLVALUE_NUMBER   = 'number';
	const SQLVALUE_ENUMERATE = 'enum';
	const SQLVALUE_T_F      = 't-f';
	const SQLVALUE_TEXT     = 'text';
	const SQLVALUE_TIME     = 'time';
	const SQLVALUE_Y_N      = 'y-n';

	// class-internal variables - do not change

	/** current row */
	private $active_row     = -1;
	/** last mysql error string */
	private $error_desc     = '';
	/** last mysql error number */
	private $error_number   = 0;
	/** used for transactions */
	private $in_transaction = false;
	/** last id of record inserted */
	private $last_insert_id;
	/** last mysql query result */
	private $last_result;
	/** last mysql query */
	private $last_sql       = '';
	/** mysql link resource */
	private $mysql_link     = 0;
	/** holds the difference in time */
	private $time_diff      = 0;
	/** start time for the timer */
	private $time_start     = 0;
	/** tracks the number of queries executed through this instance */
	private $query_count    = 0;

	/**
	 * Determines if an error throws an exception
	 *
	 * @api
	 * @var boolean Set to true to throw error exceptions
	 */
	public $ThrowExceptions = false;

	/**
	 * Provide minimal or extended error information
	 *
	 * Determines if the code is running in a development or production environment: error diagnostics information
	 * is far more elaborate in a development environment setting to aid problem analysis and resolution.
	 *
	 * @api
	 * @var boolean Set to false to enable production environment behaviour (reduced info available for errors)
	 */
	public $InDevelopmentEnvironment = true;

	/**
	 * Constructor: Opens the connection to the database
	 *
	 * @param boolean $connect (Optional) Auto-connect when object is created
	 * @param string $database (Optional) Database name
	 * @param string $server   (Optional) Host address
	 * @param string $username (Optional) User name
	 * @param string $password (Optional) Password
	 * @param string $charset  (Optional) Character set
	 * @param string $collation (Optional) Character set collation
	 *
	 * @example
	 * $db = new MySQL();
	 * $db = new MySQL(true, "database");
	 * $db = new MySQL(true, "database", "localhost", "username", "password");
	 */
	public function __construct($connect = true, $database = null, $server = null,
	                            $username = null, $password = null, $charset = null,
	                            $collation = null)
	{
		if ($database  !== null) $this->db_dbname  = $database;
		if ($server    !== null) $this->db_host    = $server;
		if ($username  !== null) $this->db_user    = $username;
		if ($password  !== null) $this->db_pass    = $password;
		if ($charset   !== null) $this->db_charset = $charset;
		if ($collation !== null) $this->db_charsetcollation = $collation;

		if (strlen($this->db_host) > 0 &&
		    strlen($this->db_user) > 0)
		{
			if ($connect) $this->Open();
		}
	}

	/**
	 * Destructor: Closes the connection to the database
	 */
	public function __destruct()
	{
		$this->Close();
	}

	/**
	 * UPSERT a row
	 *
	 * Automatically does an INSERT or UPDATE depending on whether a record
	 * already exists in a table.
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, etc.)
	 * @param array|string $whereArray An associative array containing the column
	 *                           names as keys and values as data. The values
	 *                           must be SQL ready (i.e. quotes around strings,
	 *                           formatted dates, etc.).
	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is used verbatim for the WHERE
	 *                           clause of the query. This is useful when
	 *                           advanced queries are constructed.
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function AutoInsertUpdate($tableName, $valuesArray, $whereArray)
	{
		if (!$this->SelectRows($tableName, $whereArray))
		{
			return false;
		}
		else if ($this->HasRecords())
		{
			return $this->UpdateRow($tableName, $valuesArray, $whereArray);
		}
		else
		{
			return $this->InsertRow($tableName, $valuesArray);
		}
	}

	/**
	 * Test if the internal pointer is at the start of the record set
	 *
	 * Returns true if the internal pointer is at the beginning of the record set produced by the last query.
	 *
	 * @api
	 * @return boolean TRUE if at the first row or FALSE if not
	 *
	 * @example
	 * if ($db->BeginningOfSeek())
	 * {
	 *     echo "We are at the beginning of the record set";
	 * }
	 */
	public function BeginningOfSeek()
	{
		$this->ResetError();
		if ($this->IsConnected())
		{
			return ($this->active_row < 1);
		}
		else
		{
			return $this->SetError('No connection', -1);
		}
	}

	/**
	 * Builds a comma delimited list of columns for use with a SQL query
	 *
	 * This method can be used to construct a SELECT, FROM or SORT BY section of an SQL query.
	 *
	 * @api
	 * @param array|string $columns Either an array containing the column names
	 *                       or a string. The latter is used when, for example,
	 *                       constructing 'advanced' queries with SUM(*)
	 *                       or other expressions in the SELECT fieldset section.
	 * @param boolean $addQuotes (Optional) TRUE to add quotes
	 * @param boolean $showAlias (Optional) TRUE to show column alias
	 * @param boolean $withSortMarker (Optional) TRUE when the field list is meant
	 *                  for an ORDER BY clause; fields may be prefixed by a
	 *                  plus(+) or minus(-) to indicate sort order.
	 *                  Default is ASCending for each field.
	 * @return string Returns the constructed SQL column list on success or NULL on failure
	 */
	public function BuildSQLColumns($columns, $addQuotes = true, $showAlias = true, $withSortMarker = false)
	{
		switch (gettype($columns))
		{
		case 'array':
			$sql = '';
			foreach ($columns as $key => $value)
			{
				$asc = '';
				if ($withSortMarker)
				{
					switch ($value[0])
					{
					case '+':
						$asc = ' ASC';
						$value = substr($value, 1);
						break;

					case '-':
						$asc = ' DESC';
						$value = substr($value, 1);
						break;

					default:
						$asc = ' ASC';
						break;
					}
				}

				// Build the columns
				if (strlen($sql) != 0)
				{
					$sql .= ', ';
				}
				if ($addQuotes)
				{
					$sql .= self::SQLFixName($value);
				}
				else
				{
					$sql .= $value;
				}
				if ($showAlias && is_string($key) && (!empty($key)))
				{
					$sql .= ' AS ' . self::SQLFixName($key);
				}
				else if ($withSortMarker)
				{
					$sql .= $asc;
				}
			}
			return $sql;

		case 'string':
			return $columns;

		default:
			return false;
		}
	}

	/**
	 * Builds a SQL DELETE statement
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, etc.). If not specified
	 *                           then all values in the table are deleted.
	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is used verbatim for the WHERE
	 *                           clause of the query. This is useful when
	 *                           advanced queries are constructed.
	 * @return string Returns the SQL DELETE statement
	 *
	 * @example
	 * // Let's create an array for the example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $filter["ID"] = MySQL::SQLValue(7, MySQL::SQLVALUE_NUMBER);
	 * // Echo out the SQL statement
	 * echo MySQL::BuildSQLDelete("MyTable", $filter);
	 */
	public function BuildSQLDelete($tableName, $whereArray = null)
	{
		$sql = 'DELETE FROM ' . self::SQLFixName($tableName);
		if (!is_null($whereArray))
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= ' ' . $wh;
		}
		return $sql;
	}

	/**
	 * Builds a SQL INSERT statement
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, etc.)
	 * @return string Returns a SQL INSERT statement
	 *
	 * @example
	 * // Let's create an array for the example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $values["Name"] = MySQL::SQLValue("Violet");
	 * $values["Age"] = MySQL::SQLValue(777, MySQL::SQLVALUE_NUMBER);
	 * // Echo out the SQL statement
	 * echo MySQL::BuildSQLInsert("MyTable", $values);
	 */
	public function BuildSQLInsert($tableName, $valuesArray)
	{
		$columns = $this->BuildSQLColumns(array_keys($valuesArray), true, false);
		$values  = $this->BuildSQLColumns($valuesArray, false, false);
		if (empty($columns) || empty($values)) return false;
		$sql = 'INSERT INTO ' . self::SQLFixName($tableName) .
		       ' (' . $columns . ') VALUES (' . $values . ')';
		return $sql;
	}

	/**
	 * Builds a simple SQL SELECT statement
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @return string Returns a SQL SELECT statement
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 *
	 * @example
	 * // Let's create an array for the example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $values["Name"] = MySQL::SQLValue("Violet");
	 * $values["Age"] = MySQL::SQLValue(777, MySQL::SQLVALUE_NUMBER);
	 * // Echo out the SQL statement
	 * echo MySQL::BuildSQLSelect("MyTable", $values);
	 */
	public function BuildSQLSelect($tableName, $whereArray = null, $columns = null,
	                               $sortColumns = null, $limit = null)
	{
		if (!is_null($columns))
		{
			$sql = $this->BuildSQLColumns($columns);
			if (!is_string($sql)) return false;
			$sql = trim($sql);
		}
		if (empty($sql))
		{
			$sql = '*';
		}
		$sql = 'SELECT ' . $sql . ' FROM ' . $this->BuildSQLColumns($tableName);
		if (!is_null($whereArray))
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= ' ' . $wh;
		}
		if (!is_null($sortColumns))
		{
			$ordstr = $this->BuildSQLColumns($sortColumns, true, false, true);
			if (!is_string($ordstr)) return false;
			$ordstr = trim($ordstr);
			if (!empty($ordstr))
			{
				$sql .= ' ORDER BY ' . $ordstr;
			}
		}
		if (!is_null($limit))
		{
			if (1 == preg_match('/[^0-9 ,]/', $limit))
				return $this->SetError('ERROR: Invalid LIMIT clause specified in BuildSQLSelect method.', -1);
			$sql .= ' LIMIT ' . $limit;
		}
		return $sql;
	}

	/**
	 * Builds a SQL UPDATE statement
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, etc.)
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, etc.). If not specified
	 *                           then all values in the table are updated.
	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is used verbatim for the WHERE
	 *                           clause of the query. This is useful when
	 *                           advanced queries are constructed.
	 * @return string Returns a SQL UPDATE statement
	 *
	 * @example
	 * // Let's create two arrays for the example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $values["Name"] = MySQL::SQLValue("Violet");
	 * $values["Age"] = MySQL::SQLValue(777, MySQL::SQLVALUE_NUMBER);
	 * $filter["ID"] = MySQL::SQLValue(10, MySQL::SQLVALUE_NUMBER);
	 * // Echo out some SQL statements
	 * echo MySQL::BuildSQLUpdate("Test", $values, $filter)";
	 */
	public function BuildSQLUpdate($tableName, $valuesArray, $whereArray = null)
	{
		if (!is_array($valuesArray))
			return $this->SetError('ERROR: Invalid valuesArray type specified in BuildSQLUpdate method', -1);
		if (count($valuesArray) <= 0)
			return $this->SetError('ERROR: Invalid/Empty valuesArray array specified in BuildSQLUpdate method', -1);

		$sql = '';
		foreach ($valuesArray as $key => $value)
		{
			if (empty($value) && !is_integer($value))
				return $this->SetError('ERROR: Invalid value specified in BuildSQLUpdate method', -1);
			if (empty($key))
				return $this->SetError('ERROR: Invalid key specified in BuildSQLUpdate method', -1);

			if (strlen($sql) != 0)
				$sql .= ', ';
			$sql .= self::SQLFixName($key) . ' = ' . $value;
		}
		$sql = 'UPDATE ' . self::SQLFixName($tableName) . ' SET ' . $sql;

		if (!is_null($whereArray))
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= ' ' . $wh;
		}
		return $sql;
	}

	/**
	 * Construct a value string suitable for incorporation anywhere
	 * in a SQL query. This method invokes self::SQLValue() under the hood.
	 *
	 * @static
	 * @api
	 * @param arbitrary $value The value to be checked and processed.
	 *                         Usually this would be a string, but any other
	 *                         type which can be cast to a string is fine
	 *                         as well.
	 * @return string Returns a string containing the SQL query ready value.
	 */
	static public function BuildSQLValue($value)
	{
		return self::SQLValue($value, gettype($value));
	}

	/**
	 * Builds a SQL WHERE clause from an array.
	 *
	 * If a key is specified, the key is used at the field name and the value
	 * as a comparison. If a key is not used, the value is used as the clause.
	 *
	 * @api
	 * @param array|string $whereArray An associative array containing the column
	 *                           names as keys and values as data. The values
	 *                           must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, etc.).
	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is returned verbatim. This is useful
	 *                           when advanced queries are constructed and this
	 *                           method is invoked internally.
	 * @return string Returns a string containing the SQL WHERE clause
	 */
	public function BuildSQLWhereClause($whereArray)
	{
		switch (gettype($whereArray))
		{
		case 'array':
			$where = '';
			foreach ($whereArray as $key => $value)
			{
				if (strlen($where) == 0)
				{
					$where = 'WHERE ';
				}
				else
				{
					$where .= ' AND ';
				}

				if (is_string($key) && empty($key))
					return $this->SetError('ERROR: Invalid key specified in BuildSQLWhereClause method', -1);
				if (empty($value) && !is_integer($value))
					return $this->SetError('ERROR: Invalid value specified in BuildSQLWhereClause method for key ' . self::SQLFixName($key), -1);

				if (is_string($key))
				{
					$where .= self::SQLFixName($key) . ' = ' . $value;
				}
				else
				{
					$where .= $value;
				}
			}
			return $where;

		case 'string':
			return $whereArray;

		default:
			return $this->SetError('ERROR: Invalid key specified in BuildSQLWhereClause method', -1);
		}
	}

	/**
	 * Close current MySQL connection
	 *
	 * @api
	 * @return object Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $db->Close();
	 */
	public function Close()
	{
		$this->ResetError();
		$this->active_row = -1;
		$success = $this->Release();
		if ($success)
		{
			$success = @mysql_close($this->mysql_link);
			if (!$success)
			{
				return $this->SetError();
			}
			else
			{
				unset($this->last_sql);
				unset($this->last_result);
				unset($this->mysql_link);
			}
		}
		return $success;
	}

	/**
	 * Delete selected rows.
	 *
	 * Deletes rows in a table based on a WHERE filter
	 * (can be just one or many rows based on the filter).
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.). If not specified
	 *                          then all values in the table are deleted.
	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is used verbatim for the WHERE
	 *                           clause of the query. This is useful when
	 *                           advanced queries are constructed.
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $filter["ID"] = 7;
	 *
	 * // Execute the delete
	 * $result = $db->DeleteRows("MyTable", $filter);
	 *
	 * // If we have an error
	 * if (!$result)
	 * {
	 *     // Show the error and kill the script
	 *     $db->Kill();
	 * }
	 */
	public function DeleteRows($tableName, $whereArray = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$sql = self::BuildSQLDelete($tableName, $whereArray);
			if (!is_string($sql)) return false;
			// Execute the UPDATE
			return !!$this->Query($sql);
		}
	}

	/**
	 * Returns true if the internal pointer is at the end of the records
	 *
	 * @api
	 * @return boolean TRUE if at the last row or FALSE if not
	 *
	 * @example
	 * if ($db->EndOfSeek())
	 * {
	 *     echo "We are at the end of the record set";
	 * }
	 */
	public function EndOfSeek()
	{
		$this->ResetError();
		if ($this->IsConnected())
		{
			return ($this->active_row >= $this->RowCount());
		}
		else
		{
			return $this->SetError('No connection', -1);
		}
	}

	/**
	 * Return the last MySQL error as text
	 *
	 * @note
	 * The returned error description string is appended with the error number itself
	 * as ' (#<i>error_number</i>)'.
	 *
	 * @api
	 * @return string Error text from last known error
	 *
	 * @example
	 * if (!$db->Query("SELECT * FROM Table"))
	 * {
	 *     echo $db->Error();   // Shows the error
	 * }
	 *
	 * if ($db->Error()) $db->Kill();
	 */
	public function Error()
	{
		$error = $this->error_desc;
		if (empty($error))
		{
			if ($this->error_number <> 0)
			{
				$error = 'Unknown Error (#' . $this->error_number . ')';
			}
			else
			{
				$error = false;
			}
		}
		else
		{
			if ($this->error_number > 0 || $this->error_number < -1)
			{
				$error .= ' (#' . $this->error_number . ')';
			}
		}
		return $error;
	}

	/**
	 * Return the last MySQL error as a number
	 *
	 * @api
	 * @return integer Error number from last known error
	 *
	 * @example
	 * if ($db->ErrorNumber() <> 0)
	 * {
	 *     $db->Kill();   // show the error message
	 * }
	 */
	public function ErrorNumber()
	{
		if (strlen($this->error_desc) > 0)
		{
			if ($this->error_number <> 0)
			{
				return $this->error_number;
			}
			else
			{
				return -1;
			}
		}
		else
		{
			return $this->error_number;
		}
	}

	/**
	 * Convert any value of any datatype into boolean (true or false)
	 *
	 * @static
	 * @api
	 * @param mixed $value Value to analyze for TRUE or FALSE
	 * @return boolean Returns TRUE or FALSE
	 *
	 * @example
	 * echo (MySQL::GetBooleanValue("Y") ? "True" : "False");
	 * echo (MySQL::GetBooleanValue("no") ? "True" : "False");
	 * echo (MySQL::GetBooleanValue("TRUE") ? "True" : "False");
	 * echo (MySQL::GetBooleanValue(1) ? "True" : "False");
	 */
	static public function GetBooleanValue($value)
	{
		if (gettype($value) == 'boolean')
		{
			return ($value == true);
		}
		elseif (is_numeric($value))
		{
			return (intval($value) > 0);
		}
		else
		{
			$cleaned = strtoupper(trim($value));

			if ($cleaned == 'ON')
			{
				return true;
			}
			elseif ($cleaned == 'SELECTED' || $cleaned == 'CHECKED')
			{
				return true;
			}
			elseif ($cleaned == 'YES' || $cleaned == 'Y')
			{
				return true;
			}
			elseif ($cleaned == 'TRUE' || $cleaned == 'T')
			{
				return true;
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * Convert a string or integer value into a DateTime instance
	 *
	 * @static
	 * @api
	 * @param mixed $value Value to convert to a DateTime instance
	 * @return DateTime Returns the date/time encoded in the input $value on success; return a boolean FALSE on error.
	 *
	 * @example
	 * echo (MySQL::GetDateTimeValue("2010-01-31")->format('Y-m-d');
	 */
	static public function GetDateTimeValue($value)
	{
		if (gettype($value) == 'boolean')
		{
			return false;
		}
		elseif (is_numeric($value))
		{
			$date = new DateTime();
			$date = $date->setTimestamp(intval($value));
		}
		else
		{
			$cleaned = trim($value);
			$date = date_create($cleaned, new DateTimeZone('UTC'));
			// apply some common sense: MySQL can spit out dates such as 0000-00-00 00:00:00, which are nonsensical
			if (!empty($date) && $date->getTimestamp() === false)
			{
				return false;
			}
		}
		return $date;
	}

	/**
	 * Return the comments for fields in a table
	 *
	 * @api
	 * @param string $table Table name
	 * @return array An array that contains the column comments
	 *               or FALSE on error.
	 *
	 * @example
	 * $columns = $db->GetColumnComments("MyTable");
	 * foreach ($columns as $column => $comment)
	 * {
	 *     echo $column . " = " . $comment . "<br />\n";
	 * }
	 */
	public function GetColumnComments($table)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		$sql = 'SHOW FULL COLUMNS FROM ' . self::SQLFixName($table);
		$this->last_sql = $sql;
		$this->query_count++;
		$records = mysql_query($sql, $this->mysql_link);
		if (!$records)
		{
			return $this->SetError();
		}
		else
		{
			// Get the column names
			$columnNames = $this->GetColumnNames($table);
			if (!$columnNames)
			{
				return false;
			}
			else
			{
				$index = 0;
				$columns = array();
				// Fetches the array to be returned (column 8 is field comment):
				while ($array_data = mysql_fetch_array($records, MYSQL_NUM))
				{
					$columns[$index] = $array_data[8];
					$columns[$columnNames[$index++]] = $array_data[8];
				}
				return $columns;
			}
		}
	}

	/**
	 * Get the number of columns
	 *
	 * @api
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      column count is returned from the last query
	 * @return integer The total count of columns or FALSE on error
	 *
	 * @example
	 * echo "Total Columns: " . $db->GetColumnCount("MyTable");
	 */
	public function GetColumnCount($table = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		if (empty($table))
		{
			$result = mysql_num_fields($this->last_result);
			if (!$result) return $this->SetError();
		}
		else
		{
			$sql = 'SELECT * FROM ' . self::SQLFixName($table) . ' LIMIT 1';
			$this->last_sql = $sql;
			$this->query_count++;
			$records = mysql_query($sql, $this->mysql_link);
			if (!$records)
			{
				return $this->SetError();
			}
			else
			{
				$result = mysql_num_fields($records);
				if (!$result) return $this->SetError();
				$success = @mysql_free_result($records);
				if (!$success)
				{
					return $this->SetError();
				}
			}
		}
		return $result;
	}

	/**
	 * Return the data type for a specified column
	 *
	 * @api
	 * @param integer|string $column Column name or number (first column is 0)
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used
	 * @return string The MySQL data (field) type.  If the column does not
	 *                exist or no records exist, return FALSE.
	 *
	 * @example
	 * echo "Type: " . $db->GetColumnDataType("FirstName", "Customer");
	 */
	public function GetColumnDataType($column, $table = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		if (empty($table))
		{
			if ($this->RowCount() > 0)
			{
				if (is_numeric($column))
				{
					return mysql_field_type($this->last_result, $column);
				}
				else
				{
					return mysql_field_type($this->last_result, $this->GetColumnID($column));
				}
			}
			else
			{
				return false;
			}
		}
		else
		{
			if (is_numeric($column)) $column = $this->GetColumnName($column, $table);

			$sql = 'SELECT ' . self::SQLFixName($column) . ' FROM ' . self::SQLFixName($table) . ' LIMIT 1';
			$this->last_sql = $sql;
			$this->query_count++;
			$result = mysql_query($sql, $this->mysql_link);
			if (!$result)
			{
				return $this->SetError('The specified column or table does not exist, or no data was returned');
			}
			else
			{
				if (mysql_num_fields($result) > 0)
				{
					return mysql_field_type($result, 0);
				}
				else
				{
					return $this->SetError('The specified column or table does not exist, or no data was returned', -1);
				}
			}
		}
	}

	/**
	 * Return the position of a column
	 *
	 * @api
	 * @param string $column Column name
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return integer Column ID or FALSE on error.
	 *
	 * @example
	 * echo "Column Position: " . $db->GetColumnID("FirstName", "Customer");
	 */
	public function GetColumnID($column, $table = '')
	{
		$this->ResetError();
		$columnNames = $this->GetColumnNames($table);
		if (!$columnNames)
		{
			return false;
		}
		else
		{
			$index = 0;
			$found = false;
			foreach ($columnNames as $columnName)
			{
				if ($columnName == $column)
				{
					$found = true;
					break;
				}
				$index++;
			}
			if ($found)
			{
				return $index;
			}
			else
			{
				return $this->SetError('Column name not found', -1);
			}
		}
	}

	/**
	 * Return the field length
	 *
	 * @api
	 * @param string $column Column name
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return integer Field length or FALSE on error.
	 *
	 * @example
	 * echo "Length: " . $db->GetColumnLength("FirstName", "Customer");
	 */
	public function GetColumnLength($column, $table = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		if (empty($table))
		{
			if (is_numeric($column))
			{
				$columnID = intval($column);
			}
			else
			{
				$columnID = $this->GetColumnID($column);
			}
			if (!$columnID)
			{
				return false;
			}
			else
			{
				$result = mysql_field_len($this->last_result, $columnID);
				if (!$result)
				{
					return $this->SetError();
				}
				else
				{
					return $result;
				}
			}
		}
		else
		{
			$sql = 'SELECT ' . self::SQLFixName($column) . ' FROM ' . self::SQLFixName($table) . ' LIMIT 1';
			$this->last_sql = $sql;
			$this->query_count++;
			$records = mysql_query($sql, $this->mysql_link);
			if (!$records)
			{
				return $this->SetError();
			}
			$result = mysql_field_len($records, 0);
			if (!$result)
			{
				return $this->SetError();
			}
			else
			{
				return $result;
			}
		}
	}

	/**
	 * Return the field name for a specified column number
	 *
	 * @api
	 * @param string $columnID Column position (0 is the first column)
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return string The field name for a specified column number. If
	 *                the given column index number is invalid (does not exist)
	 *                or no records exist, return FALSE.
	 *
	 * @example
	 * echo "Column Name: " . $db->GetColumnName(0);
	 */
	public function GetColumnName($columnID, $table = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		if (empty($table))
		{
			if ($this->RowCount() > 0)
			{
				$result = mysql_field_name($this->last_result, $columnID);
				if (!$result) return $this->SetError();
			}
			else
			{
				return false;
			}
		}
		else
		{
			$sql = 'SELECT * FROM ' . self::SQLFixName($table) . ' LIMIT 1';
			$this->last_sql = $sql;
			$this->query_count++;
			$records = mysql_query($sql, $this->mysql_link);
			if (!$records)
			{
				return $this->SetError();
			}
			else
			{
				if (mysql_num_fields($records) > 0)
				{
					$result = mysql_field_name($records, $columnID);
					if (!$result) return $this->SetError();
				}
				else
				{
					return false;
				}
			}
		}
		return $result;
	}

	/**
	 * Return the field names in a table or query as an array
	 *
	 * @api
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used
	 * @return array An array that contains the column names or FALSE on error.
	 *
	 * @example
	 * $columns = $db->GetColumnNames("MyTable");
	 * foreach ($columns as $columnName)
	 * {
	 *     echo $columnName . "<br />\n";
	 * }
	 */
	public function GetColumnNames($table = null)
	{
		$this->ResetError();
		$columns = array();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		if (empty($table))
		{
			$columnCount = mysql_num_fields($this->last_result);
			if (!$columnCount)
			{
				return $this->SetError();
			}
			else
			{
				for ($column = 0; $column < $columnCount; $column++)
				{
					$columns[] = mysql_field_name($this->last_result, $column);
				}
			}
		}
		else
		{
			$sql = 'SHOW COLUMNS FROM ' . self::SQLFixName($table);
			$this->last_sql = $sql;
			$this->query_count++;
			$result = mysql_query($sql, $this->mysql_link);
			if (!$result)
			{
				return $this->SetError();
			}
			else
			{
				while ($array_data = mysql_fetch_array($result, MYSQL_NUM))
				{
					$columns[] = $array_data[0];
				}
			}
		}

		// Returns the array
		return $columns;
	}

	/**
	 * Return the last query as a HTML table
	 *
	 * @api
	 * @param boolean $showCount (Optional) TRUE if you want to show the row count,
	 *                           FALSE if you do not want to show the count
	 * @param string $styleTable (Optional) table tag attributes, e.g. styling
	 * @param string $styleHeader (Optional) header row tag attributes
	 * @param string $styleData (Optional) cell tag attributes
	 * @return string HTML containing a table with all records listed or FALSE on error
	 *
	 * @example
	 * $db->Query("SELECT * FROM Customer");
	 * echo $db->GetHTML();
	 */
	public function GetHTML($showCount = true, $styleTable = null, $styleHeader = null, $styleData = null)
	{
		if ($styleTable === null)
		{
			$tb = 'style="border-collapse:collapse;empty-cells:show;" cellpadding="2" cellspacing="2"';
		}
		else
		{
			$tb = $styleTable;
		}
		if ($styleHeader === null)
		{
			$th = 'style="border-width:1px;border-style:solid;background-color:navy;color:white;"';
		}
		else
		{
			$th = $styleHeader;
		}
		if ($styleData === null)
		{
			$td = 'style="border-width:1px;border-style:solid;"';
		}
		else
		{
			$td = $styleData;
		}

		if ($this->last_result)
		{
			if ($this->RowCount() > 0)
			{
				$html = '';
				if ($showCount) $html = 'Record Count: ' . $this->RowCount() . '<br />\n';
				$html .= '<table $tb>\n';
				$this->MoveFirst();
				$header = false;
				while ($member = mysql_fetch_object($this->last_result))
				{
					if (!$header)
					{
						$html .= "\t<tr>\n";
						foreach ($member as $key => $value)
						{
							$html .= "\t\t<th $th><strong>" . htmlspecialchars($key, ENT_COMPAT, 'UTF-8') . "</strong></th>\n";
						}
						$html .= "\t</tr>\n";
						$header = true;
					}
					$html .= "\t<tr>\n";
					foreach ($member as $key => $value)
					{
						$html .= "\t\t<td $td>" . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . "</td>\n";
					}
					$html .= "\t</tr>\n";
				}
				$this->MoveFirst();
				$html .= '</table>';
			}
			else
			{
				$html = 'No records were returned.';
			}
		}
		else
		{
			$this->active_row = -1;
			$html = false;
		}
		return $html;
	}

	/**
	 * Return the last query as a JSON document
	 *
	 * @api
	 * @return string JSON containing all records listed
	 */
	public function GetJSON()
	{
		if ($this->last_result)
		{
			if ($this->RowCount() > 0)
			{
				for ($i = 0, $il = mysql_num_fields($this->last_result); $i < $il; $i++)
				{
					$types[$i] = mysql_field_type($this->last_result, $i);
				}
				$json = '[';
				$this->MoveFirst();
				while ($member = mysql_fetch_object($this->last_result))
				{
					$json .= json_encode($member) . ',';
				}
				$json .= ']';
				$json = str_replace('},]', '}]', $json);
			}
			else
			{
				$json = 'null';
			}
		}
		else
		{
			$this->active_row = -1;
			$json = 'null';
		}
		return $json;
	}

	/**
	 * Return the last autonumber ID field from a previous INSERT query
	 *
	 * @api
	 * @return  integer ID number from previous INSERT query
	 *
	 * @example
	 * $sql = "INSERT INTO Employee (Name) Values ('Bob')";
	 * if (!$db->Query($sql))
	 * {
	 *     $db->Kill();
	 * }
	 * echo "Last ID inserted was: " . $db->GetLastInsertID();
	 */
	public function GetLastInsertID()
	{
		return $this->last_insert_id;
	}

	/**
	 * Return the last SQL statement executed
	 *
	 * @api
	 * @return string Current SQL query string
	 *
	 * @example
	 * $sql = "INSERT INTO Employee (Name) Values ('Bob')";
	 * if (!$db->Query($sql)) $db->Kill();
	 * echo $db->GetLastSQL();
	 */
	public function GetLastSQL()
	{
		return $this->last_sql;
	}

	/**
	 * Get all table names from the database
	 *
	 * @api
	 * @param string $filter [Optional] Comma separated list of acceptable table
	 *                       names: no other table will be listed in the results.
	 *                       Alternatively, when no filter is specified, all tables
	 *                       are listed. This is the default behaviour of this method.
	 * @return array An array that contains the table names. If the database
	 *               does not contain any tables, the returned value is FALSE.
	 *
	 * @example
	 * $tables = $db->GetTables();
	 * foreach ($tables as $table)
	 * {
	 *     echo $table . "<br />\n";
	 * }
	 */
	public function GetTables($filter = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}

		// Query to get the tables in the current database:
		$sql = 'SHOW TABLES';
		$this->last_sql = $sql;
		$this->query_count++;
		$records = mysql_query($sql, $this->mysql_link);
		if (!$records)
		{
			return $this->SetError();
		}
		else
		{
			// return an array with non-empty elements: see also http://nl.php.net/manual/en/function.explode.php#99830
			$accepted = array_filter(explode(',', $filter . ','));

			$tables = array();
			while ($array_data = mysql_fetch_array($records, MYSQL_NUM))
			{
				if (count($accepted) == 0 || in_array($array_data[0], $accepted))
				{
					$tables[] = $array_data[0];
				}
			}

			// Returns the array or NULL
			if (count($tables) > 0)
			{
				return $tables;
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * Return the last query as an XML Document
	 *
	 * @api
	 * @return string XML containing all records listed
	 */
	public function GetXML()
	{
		// Create a new XML document
		$doc = new DomDocument('1.0'); // ,'UTF-8');

		// Create the root node
		$root = $doc->createElement('root');
		$root = $doc->appendChild($root);

		// If there was a result set
		if (is_resource($this->last_result))
		{
			// Show the row count and query
			$root->setAttribute('rows',
			                    ($this->RowCount() ? $this->RowCount() : 0));
			$root->setAttribute('query', $this->last_sql);
			$root->setAttribute('error', '');

			// process one row at a time
			$rowCount = 0;
			while ($row = mysql_fetch_assoc($this->last_result))
			{
				// Keep the row count
				$rowCount = $rowCount + 1;

				// Add node for each row
				$element = $doc->createElement('row');
				$element = $root->appendChild($element);
				$element->setAttribute('index', $rowCount);

				// Add a child node for each field
				foreach ($row as $fieldname => $fieldvalue)
				{
					$child = $doc->createElement($fieldname);
					$child = $element->appendChild($child);

					// $fieldvalue = iconv('ISO-8859-1', 'UTF-8', $fieldvalue);
					$fieldvalue = htmlspecialchars($fieldvalue, ENT_COMPAT, 'UTF-8');
					$value = $doc->createTextNode($fieldvalue);
					$value = $child->appendChild($value);
				}
			}
		}
		else
		{
			// Process any errors
			$root->setAttribute('rows', 0);
			$root->setAttribute('query', $this->last_sql);
			if ($this->ErrorNumber())
			{
				$root->setAttribute('error', $this->Error());
			}
			else
			{
				$root->setAttribute('error', 'No query has been executed.');
			}
		}

		// Show the XML document
		return $doc->saveXML();
	}





	/**
	 * Dump the entire database as an SQL script
	 *
	 * Produces a SQL script representing the dump of the entire database (when no
	 * (optional, comma-separated set of) tables has been specified as a method
	 * argument) or just the specified (comma separated set of) tables.
	 * You may choose to have either the database/table structure or the records
	 * dumped. Or both, for a full-fledged database/table dump which can serve as
	 * a db/table backup/restore script later on.
	 *
	 * @api
	 * @param string $tables [Optional] Comma separated list of tables. When none
	 *                       are specified, the entire database is assumed (this
	 *                       is the default).
	 * @param boolean $with_sql_comments [Optional] Include SQL comments in the
	 *                       generated script (default: TRUE).
	 * @param boolean $with_structure [Optional] Whether to include the table
	 *                       structure creation (and tear-down) SQL statements in
	 *                       the generated script (default: TRUE).
	 * @param boolean $with_data [Optional] Whether to include the table rows (data)
	 *                       in the generated script (default: TRUE).
	 * @param boolean $with_drops_and_truncates [Optional] Whether to include the
	 *                       appropriate DROP TABLE and/or TRUNCATE TABLE statements
	 *                       in the generated script (default: TRUE).
	 * @param boolean $alter_database [Optional] Whether to include the appropriate
	 *                       ALTER DATABASE statement in the generated script to
	 *                       set the default database charset and collation (default: TRUE).
	 *
	 * @return string the generated SQL script, boolean FALSE when a query error occurred.
	 */
	public function Dump($tables = null, $with_sql_comments = true, $with_structure = true, $with_data = true, $with_drops_and_truncates = true, $alter_database = true)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}

		$value = '';
		if ($with_sql_comments)
		{
			$value .= '--' . "\r\n";
			$value .= '-- MySQL database dump' . (!empty($tables) ? ' for these tables: ' . $tables : '') . "\r\n";
			$value .= '-- Created for CompactCMS (www.compactcms.nl)' . "\r\n";
			$value .= '--' . "\r\n";
			$value .= '-- Host: ' . $this->db_host . "\r\n";
			$value .= '-- Generated: ' . date('M j, Y') . ' at ' . date('H:i') . "\r\n";
			$value .= '-- MySQL version: ' . mysql_get_server_info() . "\r\n";
			$value .= '-- PHP version: ' . phpversion() . "\r\n";
			if (!empty($this->db_dbname))
			{
				$value .= '--' . "\r\n";
				$value .= '-- Database: ' . self::SQLFixName($this->db_dbname) . "\r\n";
			}
			$value .= '--' . "\r\n" . "\r\n" . "\r\n";
		}

		$charset = null;    // or should we assume a default of UTF8 if nothing works below?
		if (!empty($this->db_dbname))
		{
			$tv = "\r\n" . "\r\n";

			if ($with_sql_comments)
			{
				$tv .= '-- ========================================================' . "\r\n";
				$tv .= "\r\n";
				$tv .= '--' . "\r\n";
				$tv .= '-- Create the database if it doesn\'t exist yet for database ' . self::SQLFixName($this->db_dbname) . "\r\n";
				$tv .= '--' . "\r\n" . "\r\n";
			}

			$sql = 'SHOW CREATE DATABASE ' . self::SQLFixName($this->db_dbname);
			$this->last_sql = $sql;
			$this->query_count++;
			$result = $this->QuerySingleRowArray($sql);
			if (!$result || empty($result['Create Database']))
			{
				return false;
			}
			$result = $result['Create Database'];
			$result = str_replace('CREATE DATABASE', 'CREATE DATABASE IF NOT EXISTS', $result);
			$tv .= $result . ' ; ' . "\r\n" . "\r\n";

			$tv .= 'USE ' . self::SQLFixName($this->db_dbname) . ';' . "\r\n" . "\r\n";

			// http://stackoverflow.com/questions/1049728/how-do-i-see-what-character-set-a-database-table-column-is-in-mysql
			$sql = 'SHOW VARIABLES LIKE "character_set_database"';
			$this->last_sql = $sql;
			$this->query_count++;
			$charset = $this->QuerySingleRow($sql);
			if (!$charset || empty($charset->Value))
			{
				return false;
			}
			$charset = $charset->Value;

			$sql = 'SHOW VARIABLES LIKE "collation_database"';
			$this->last_sql = $sql;
			$this->query_count++;
			$collation = $this->QuerySingleRow($sql);
			if (!$collation || empty($collation->Value))
			{
				return false;
			}
			$collation = $collation->Value;

			$result = 'ALTER DATABASE ' . self::SQLFixName($this->db_dbname) . ' DEFAULT CHARACTER SET `' . self::SQLFix($charset) . '` COLLATE `' . self::SQLFix($collation) . '`;';
			$tv .= $result . "\r\n" . "\r\n" . "\r\n";

			if ($with_structure && $alter_database)
			{
				$value .= $tv;
			}
		}

		if (!empty($this->db_charset))
		{
			$charset = $this->db_charset;
		}
		if (!empty($charset))
		{
			$result = 'SET CHARACTER SET `' . self::SQLFix($charset) . '`;';
			$value .= $result . "\r\n" . "\r\n" . "\r\n";
		}

		if (!($tbl = $this->GetTables($tables)))
		{
			if (!$this->ErrorNumber())
			{
				return $this->SetError('Database has no ' . (!empty($tables) ? 'matching tables for the set: ' . $tables : 'tables'), -1);
			}
			return false;
		}

		foreach ($tbl as $table)
		{
			$tv = "\r\n" . "\r\n";

			$sql = 'LOCK TABLES ' . self::SQLFixName($table) . ' WRITE';
			$this->last_sql = $sql;
			$this->query_count++;
			if (!mysql_query($sql, $this->mysql_link))
			{
				return $this->SetError();
			}
			if ($with_structure)
			{
				if ($with_sql_comments)
				{
					$tv .= '-- --------------------------------------------------------' . "\r\n";
					$tv .= "\r\n";
					$tv .= '--' . "\r\n";
					$tv .= '-- Table structure for table ' . self::SQLFixName($table) . "\r\n";
					$tv .= '--' . "\r\n" . "\r\n";
				}
				if ($with_drops_and_truncates)
				{
					$tv .= 'DROP TABLE IF EXISTS ' . self::SQLFixName($table) . ';' . "\r\n";
				}
				$sql = 'SHOW CREATE TABLE ' . self::SQLFixName($table);
				$this->last_sql = $sql;
				$this->query_count++;
				$result = mysql_query($sql, $this->mysql_link);
				if (!$result)
				{
					$this->SetError();
					mysql_query('UNLOCK TABLES', $this->mysql_link);
					return false;
				}
				$row = mysql_fetch_assoc($result);
				$tv .= str_replace("\n", "\r\n", str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $row['Create Table'])) . ';';
				$tv .= "\r\n" . "\r\n";
			}

			if ($with_data)
			{
				if ($with_sql_comments)
				{
					$tv .= '--' . "\r\n";
					$tv .= '-- Dumping data for table ' . self::SQLFixName($table) . "\r\n";
					$tv .= '--' . "\r\n" . "\r\n";
				}

				if ($with_drops_and_truncates /* && !$with_structure */ )
				{
					$tv .= 'TRUNCATE TABLE ' . self::SQLFixName($table) . ';' . "\r\n" . "\r\n";
				}

				if (!$this->SelectTable($table))
				{
					mysql_query('UNLOCK TABLES', $this->mysql_link);
					return false;
				}
				else if ($this->RowCount() > 0)
				{
					$members = array();

					for ($rows_dumped = 0; $row = $this->RowArray(null, MYSQL_ASSOC); $rows_dumped++)
					{
						$k = '';
						$d = '';
						foreach ($row as $key => $data)
						{
							$k .= self::SQLFixName($key) . ', ';
							// we cope with NULL-valued columns:
							if ($data === null)
							{
								$d .= 'NULL, ';
							}
							else
							{
								$d .= '\'' . addslashes($data) . '\', ';
							}
						}
						$k = substr($k, 0, -2);
						$d = substr($d, 0, -2);

						// one INSERT INTO statement per 100 rows:
						$marker = $rows_dumped % 100;
						if ($marker == 0)
						{
							if ($rows_dumped > 0)
							{
								$tv .= ";\r\n\r\n";
							}
							$tv .= 'INSERT INTO ' . self::SQLFixName($table) . ' (' . $k . ') VALUES' . "\r\n";
						}
						if ($rows_dumped > 0)
						{
							$tv .= ",\r\n";
						}
						$tv .= '(' . $d . ')';
					}

					if ($rows_dumped > 0)
					{
						$tv .= ";\r\n\r\n";
					}

					if ($this->ErrorNumber())
					{
						mysql_query('UNLOCK TABLES', $this->mysql_link);
						return false;
					}
				}
				else
				{
					// no data in table:
					if ($with_sql_comments)
					{
						$tv .= '-- table ' . self::SQLFixName($table) . ' has 0 records.' . "\r\n";
						$tv .= '--' . "\r\n" . "\r\n";
					}
				}
			}

			$sql = 'UNLOCK TABLES';
			$this->last_sql = $sql;
			$this->query_count++;
			if (!mysql_query($sql, $this->mysql_link))
			{
				return $this->SetError();
			}

			$value .= $tv;
		}

		$value .= "\r\n" . "\r\n";

		return $value;
	}



	/**
	 * Determines if a query contains any rows
	 *
	 * @api
	 * @param string $sql [Optional] If specified, the query is first executed
	 *                    Otherwise, the last query is used for comparison
	 * @return boolean TRUE if records exist, FALSE if not or query error
	 */
	public function HasRecords($sql = null)
	{
		if (!empty($sql))
		{
			if (!$this->Query($sql)) return false;
		}
		return ($this->RowCount() > 0);
	}

	/**
	 * Inserts a row into a table in the connected database
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, etc.)
	 * @return integer Returns last insert ID on success or FALSE on failure
	 *
	 * @example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $values["Name"] = MySQL::SQLValue("Violet");
	 * $values["Age"]  = MySQL::SQLValue(777, MySQL::SQLVALUE_NUMBER);
	 *
	 * // Execute the insert
	 * $result = $db->InsertRow("MyTable", $values);
	 *
	 * // If we have an error
	 * if (!$result)
	 * {
	 *     // Show the error and kill the script
	 *     $db->Kill();
	 * }
	 * else
	 * {
	 *     // No error, show the new record's ID
	 *     echo "The new record's ID is: " . $result;
	 * }
	 */
	public function InsertRow($tableName, $valuesArray)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			// Execute the query
			$sql = self::BuildSQLInsert($tableName, $valuesArray);
			if (!is_string($sql)) return false;
			if (!$this->Query($sql))
			{
				return false;
			}
			else
			{
				return $this->GetLastInsertID();
			}
		}
	}

	/**
	 * Determines if a valid connection to the database exists
	 *
	 * @api
	 * @return boolean TRUE if connected or FALSE if not connected
	 */
	public function IsConnected()
	{
		return (gettype($this->mysql_link) == 'resource');
	}

	/**
	 * Determines if a value of any data type is a date PHP can convert
	 *
	 * @static
	 * @api
	 * @param string $value
	 * @return boolean Returns TRUE if value is date or FALSE if not date
	 *
	 * @example
	 * if (MySQL::IsDate("January 1, 2000"))
	 * {
	 *     echo "valid date";
	 * }
	 */
	static public function IsDateStr($value)
	{
		$date = strtotime($value);
		/*
		time == 0 ~ 1970-01-01T00:00:00 is considered an INVALID date here,
		because it can easily result from parsing arbitrary input representing
		the date eqv. of zero(0)...

		time == -1 was the old error signaling return code (pre-PHP 5.1.0)
		*/
		return (is_int($date) && $date > 0);
	}

	/**
	 * Stop executing (die/exit) and show last MySQL error message
	 *
	 * @api
	 * @param string $message The message to display on exit
	 * @param boolean $prepend_message (Optional) Whether the message should
	 *                       be shown as-is (FALSE) or followed by the last
	 *                       error message/description (TRUE) (Default: TRUE)
	 *
	 * @example
	 * // Stop executing the script and show the last error
	 * $db->Kill();
	 */
	public function Kill($message = '', $prepend_message = true)
	{
		exit($this->MyDyingMessage($message, $prepend_message));
	}

	/**
	 * Get last error message as HTML string
	 *
	 * Return the error message ready for throwing back out to the client side
	 * while dying, a.k.a. Kill() without the death nor the echo'ing.
	 *
	 * @api
	 * @param string $message The message to display on exit
	 * @param boolean $prepend_message (Optional) Whether the message should
	 *                       be shown as-is (FALSE) or followed by the last
	 *                       error message/description (TRUE) (Default: TRUE)
	 * @return string Return the error message as a HTML string; when the
	 *         $this->InDevelopmentEnvironment configuration member has been
	 *         set, the offending query is included for enhanced diagnostics.
	 */
	public function MyDyingMessage($message = '', $prepend_message = true)
	{
		if (strlen($message) > 0)
		{
			if ($prepend_message)
			{
				$message .= ' ';
			}
			else
			{
				return $message;
			}
		}
		if ($this->InDevelopmentEnvironment)
		{
			$message .= '<h1>Offending SQL query</h1><p>' . htmlspecialchars($this->last_sql, ENT_COMPAT, 'UTF-8') . '</p><h2>Error Message</h2><p> ';
		}

		return $message . $this->Error();
	}

	/**
	 * Seeks to the beginning of the records
	 *
	 * @api
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $db->MoveFirst();
	 * while (!$db->EndOfSeek())
	 * {
	 *     $row = $db->Row();
	 *     echo $row->ColumnName1 . " " . $row->ColumnName2 . "\n";
	 * }
	 */
	public function MoveFirst()
	{
		$this->ResetError();
		if (!$this->Seek(0))
		{
			return $this->SetError();
		}
		else
		{
			$this->active_row = 0;
			return true;
		}
	}

	/**
	 * Seeks to the end of the records
	 *
	 * @api
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $db->MoveLast();
	 */
	public function MoveLast()
	{
		$this->ResetError();
		$this->active_row = $this->RowCount() - 1;
		if (!$this->ErrorNumber())
		{
			return !!$this->Seek($this->active_row);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Connect to specified MySQL server
	 *
	 * @api
	 * @param string $database (Optional) Database name
	 * @param string $server   (Optional) Host address
	 * @param string $username (Optional) User name
	 * @param string $password (Optional) Password
	 * @param string $charset  (Optional) Character set
	 * @param string $collation (Optional) Character set collation
	 * @param boolean $pcon    (Optional) Persistent connection
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * if (!$db->Open("MyDatabase", "localhost", "user", "password"))
	 * {
	 *     $db->Kill();
	 * }
	 */
	public function Open($database = null, $server = null, $username = null,
	                     $password = null, $charset = null, $collation = null, $pcon = false)
	{
		$this->ResetError();

		// Use defaults?
		if ($database !== null) $this->db_dbname  = $database;
		if ($server   !== null) $this->db_host    = $server;
		if ($username !== null) $this->db_user    = $username;
		if ($password !== null) $this->db_pass    = $password;
		if ($charset  !== null) $this->db_charset = $charset;
		if ($collation !== null) $this->db_charsetcollation = $collation;
		if (is_bool($pcon))      $this->db_pcon    = $pcon;

		$this->active_row = -1;

		// Open persistent or normal connection
		if ($pcon)
		{
			$this->mysql_link = @mysql_pconnect(
			        $this->db_host, $this->db_user, $this->db_pass);
		}
		else
		{
			$this->mysql_link = @mysql_connect(
			        $this->db_host, $this->db_user, $this->db_pass);
		}
		// Connect to mysql server failed?
		if (!$this->IsConnected())
		{
			return $this->SetError();
		}
		else
		{
			// Select a database (if specified)
			if (strlen($this->db_dbname) > 0)
			{
				if (empty($this->db_charset))
				{
					return !!$this->SelectDatabase($this->db_dbname);
				}
				else
				{
					return !!$this->SelectDatabase($this->db_dbname, $this->db_charset);
				}
			}
			else
			{
				return true;
			}
		}
	}

	/**
	 * Executes the given SQL query and returns the records
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP 'mysql result' resource object containing the records
	 *                on SELECT, SHOW, DESCRIBE or EXPLAIN queries and returns;
	 *                TRUE or FALSE for all others i.e. UPDATE, DELETE, DROP
	 *                AND FALSE on all errors (setting the local Error message).
	 *
	 * @example
	 * if (!$db->Query("SELECT * FROM Table")) echo $db->Kill();
	 */
	public function Query($sql)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		$this->last_sql = $sql;
		$this->query_count++;
		$this->last_result = @mysql_query($sql, $this->mysql_link);
		if (!$this->last_result)
		{
			$this->active_row = -1;
			return $this->SetError();
		}
		else
		{
			if (preg_match('/^\s*insert\b/i', $sql))
			{
				$this->last_insert_id = mysql_insert_id($this->mysql_link);
				if ($this->last_insert_id === false)
				{
					return $this->SetError();
				}
				else
				{
					$this->active_row = -1;
					return $this->last_result;
				}
			}
			else if (preg_match('/^\s*select\b/i', $sql) || preg_match('/^\s*show\b/i', $sql))
			{
				$numrows = mysql_num_rows($this->last_result);
				if ($numrows > 0)
				{
					$this->active_row = 0;
				}
				else
				{
					$this->active_row = -1;
				}
				$this->last_insert_id = 0;
				return $this->last_result;
			}
			else
			{
				return $this->last_result;
			}
		}
	}

	/**
	 * Executes the given SQL query and returns a multi-dimensional array
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array A multi-dimensional array containing all the data
	 *               returned from the query or FALSE on all errors
	 */
	public function QueryArray($sql, $resultType = MYSQL_ASSOC)
	{
		if (!$this->Query($sql))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RecordsArray($resultType);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Executes the given SQL query
	 *
	 * Executes the given SQL query and returns an array of objects, where
	 * each record is an object with the columns serving as object member variables.
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @return array An array of record objects containing all the data
	 *               returned from the query or FALSE on all errors
	 */
	public function QueryObjects($sql)
	{
		if (!$this->Query($sql))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RecordsObjects();
		}
		else
		{
			return array();
		}
	}


	/**
	 * Returns a multidimensional array of rows from a table based on a WHERE filter
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array A multi-dimensional array containing all the data
	 *               returned from the query or FALSE on all errors
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 */
	public function SelectArray($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null, $resultType = MYSQL_ASSOC)
	{
		if (!$this->SelectRows($tableName, $whereArray, $columns, $sortColumns, $limit))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RecordsArray($resultType);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Returns an array of row (= record) objects from a table based on a WHERE filter
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @return array An array of record objects containing all the data
	 *               returned from the query or FALSE on all errors
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 */
	public function SelectObjects($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null)
	{
		if (!$this->SelectRows($tableName, $whereArray, $columns, $sortColumns, $limit))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RecordsObjects();
		}
		else
		{
			return array();
		}
	}

	/**
	 * Executes the given SQL query and returns only one (the first) row
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP resource object containing the first row or
	 *                FALSE if no row is returned from the query
	 */
	public function QuerySingleRow($sql)
	{
		if (!$this->Query($sql))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->Row();
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns a single (first) row from a table based on a WHERE filter
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @return object PHP resource object containing the first row or
	 *                FALSE if no row is returned from the query
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 */
	public function SelectSingleRow($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null)
	{
		if (!$this->SelectRows($tableName, $whereArray, $columns, $sortColumns, $limit))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->Row();
		}
		else
		{
			return false;
		}
	}

	/**
	 * Executes the given SQL query and returns the first row as an array
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array An array containing the first row or FALSE if no row
	 *               is returned from the query
	 */
	public function QuerySingleRowArray($sql, $resultType = MYSQL_ASSOC)
	{
		if (!$this->Query($sql))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RowArray(null, $resultType);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns a single (first) row as an array from a table based on a WHERE filter
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array An array containing the first row or FALSE if no row
	 *               is returned from the query
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 */
	public function SelectSingleRowArray($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null, $resultType = MYSQL_ASSOC)
	{
		if (!$this->SelectRows($tableName, $whereArray, $columns, $sortColumns, $limit))
		{
			return false;
		}
		else if ($this->RowCount() > 0)
		{
			return $this->RowArray(null, $resultType);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Executes a query and returns a single value. If more than one row
	 * is returned, only the first value in the first column is returned.
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @return mixed The value returned or FALSE if no value
	 */
	public function QuerySingleValue($sql)
	{
		if (!$this->Query($sql))
		{
			return false;
		}
		else if ($this->RowCount() > 0 && $this->GetColumnCount() > 0)
		{
			$row = $this->RowArray(null, MYSQL_NUM);
			return $row[0];
		}
		else
		{
			return false;
		}
	}
	/**
	 * Returns a single value from the first row SELECTed from a table based on a
	 * WHERE filter.
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @return mixed The value returned or FALSE if no value
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 */
	public function SelectSingleValue($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null)
	{
		if (!$this->SelectRows($tableName, $whereArray, $columns, $sortColumns, $limit))
		{
			return false;
		}
		else if ($this->RowCount() > 0 && $this->GetColumnCount() > 0)
		{
			$row = $this->RowArray(null, MYSQL_NUM);
			return $row[0];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Executes the given SQL query, measures it, and saves the total duration
	 * in microseconds
	 *
	 * @api
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP 'mysql result' resource object containing the records
	 *                on SELECT, SHOW, DESCRIBE or EXPLAIN queries and returns
	 *                TRUE or FALSE for all others i.e. UPDATE, DELETE, DROP.
	 *
	 * @example
	 * $db->QueryTimed("SELECT * FROM MyTable");
	 * echo "Query took " . $db->TimerDuration() . " microseconds";
	 */
	public function QueryTimed($sql)
	{
		$this->TimerStart();
		$result = $this->Query($sql);
		$this->TimerStop();
		return $result;
	}

	/**
	 * Returns the records from the last query
	 *
	 * @api
	 * @return object PHP 'mysql result' resource object containing the records
	 *                for the last query executed
	 *
	 * @example
	 * $records = $db->Records();
	 */
	public function Records()
	{
		return $this->last_result;
	}

	/**
	 * Returns all records from the last query as array of arrays
	 *
	 * Returns all records from the last query and returns the contents as an array of records
	 * where each record is presented as an array of columns (fields).
	 *
	 * @api
	 * @param integer $resultType (Optional) The type of array representing one record
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH. (Default: MYSQL_ASSOC)
	 * @return array Records in array form or FALSE on error. May return an
	 *         EMPTY array when no records are available.
	 *
	 * @example
	 * $myArray = $db->RecordsArray(MYSQL_ASSOC);
	 */
	public function RecordsArray($resultType = MYSQL_ASSOC)
	{
		$this->ResetError();
		if ($this->last_result)
		{
			if (!mysql_data_seek($this->last_result, 0))
			{
				return $this->SetError();
			}
			else
			{
				$members = array();
				while ($member = mysql_fetch_array($this->last_result, $resultType))
				{
					$members[] = $member;
				}
				mysql_data_seek($this->last_result, 0);
				$this->active_row = 0;
				return $members;
			}
		}
		else
		{
			$this->active_row = -1;
			return $this->SetError('No query results exist', -1);
		}
	}

	/**
	 * Returns all records from the last query as array of objects
	 *
	 * Returns all records from the last query and returns the contents as an array of record objects
	 * (where each record is an object with each column as an attribute (data member variable)).
	 *
	 * @api
	 * @return array Records in object form or FALSE on error. May return an
	 *         EMPTY array when no records are available.
	 */
	public function RecordsObjects()
	{
		$this->ResetError();
		if ($this->last_result)
		{
			if (!mysql_data_seek($this->last_result, 0))
			{
				return $this->SetError();
			}
			else
			{
				$members = array();
				while($member = mysql_fetch_object($this->last_result))
				{
					$members[] = $member;
				}
				mysql_data_seek($this->last_result, 0);
				$this->active_row = 0;
				return $members;
			}
		}
		else
		{
			$this->active_row = -1;
			return $this->SetError('No query results exist', -1);
		}
	}

	/**
	 * Frees memory used by the query results and returns the query execution result.
	 *
	 * @warning It is an (non-fatal) error to Release() a query result
	 *          more than once.
	 *
	 * @api
	 * @param resource $result (Optional) the result originally returned
	 *                 by any previous SQL query.
	 * @return boolean Returns TRUE on success or FALSE on failure
	 *
	 * @example
	 * $db->Release();
	 */
	public function Release($result = null)
	{
		$this->ResetError();
		if (!is_resource($result))
		{
			$result = $this->last_result;
		}
		if (!$this->last_result)
		{
			$success = true;
		}
		else
		{
			$success = @mysql_free_result($this->last_result);
			if (!$success) $this->SetError();
		}
		return $success;
	}

	/**
	 * Clears the internal variables from any error information
	 *
	 * @api
	 */
	private function ResetError()
	{
		$this->error_desc = '';
		$this->error_number = 0;
	}

	/**
	 * Reads the current row and returns contents as a PHP object
	 *
	 * @api
	 * @param integer $optional_row_number (Optional) Use to specify a row
	 * @return object PHP object or FALSE on error
	 *
	 * @example
	 * $db->MoveFirst();
	 * while (!$db->EndOfSeek())
	 * {
	 *     $row = $db->Row();
	 *     echo $row->ColumnName1 . " " . $row->ColumnName2 . "\n";
	 * }
	 */
	public function Row($optional_row_number = null)
	{
		$this->ResetError();
		if (!$this->last_result)
		{
			return $this->SetError('No query results exist', -1);
		}
		elseif ($optional_row_number === null)
		{
			if (($this->active_row) > $this->RowCount())
			{
				return $this->SetError('Cannot read past the end of the records', -1);
			}
			else
			{
				$this->active_row++;
			}
		}
		else
		{
			if ($optional_row_number >= $this->RowCount())
			{
				return $this->SetError('Row number is greater than the total number of rows', -1);
			}
			else
			{
				$this->active_row = $optional_row_number;
				$this->Seek($optional_row_number);
			}
		}
		$row = mysql_fetch_object($this->last_result);
		if (!$row)
		{
			return $this->SetError();
		}
		else
		{
			return $row;
		}
	}

	/**
	 * Reads the current row and returns contents as an array
	 *
	 * @api
	 * @param integer $optional_row_number (Optional) Use to specify a row
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array Array that corresponds to the fetched row or FALSE on error or when no rows are available.
	 *
	 * @example
	 * for ($index = 0; $index < $db->RowCount(); $index++)
	 * {
	 *     $val = $db->RowArray($index);
	 * }
	 */
	public function RowArray($optional_row_number = null, $resultType = MYSQL_ASSOC)
	{
		$this->ResetError();
		if (!$this->last_result)
		{
			return $this->SetError('No query results exist', -1);
		}
		elseif ($optional_row_number === null)
		{
			if (($this->active_row) > $this->RowCount())
			{
				return $this->SetError('Cannot read past the end of the records', -1);
			}
			else
			{
				$this->active_row++;
			}
		}
		else
		{
			if ($optional_row_number >= $this->RowCount())
			{
				return $this->SetError('Row number is greater than the total number of rows', -1);
			}
			else
			{
				$this->active_row = $optional_row_number;
				$this->Seek($optional_row_number);
			}
		}
		$row = mysql_fetch_array($this->last_result, $resultType);
		if (!$row)
		{
			return $this->SetError();
		}
		else
		{
			return $row;
		}
	}

	/**
	 * Returns the last query row count
	 *
	 * @api
	 * @return integer Row count or FALSE on error
	 *
	 * @example
	 * $db->Query("SELECT * FROM Customer");
	 * echo "Row Count: " . $db->RowCount();
	 */
	public function RowCount()
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		elseif (!$this->last_result)
		{
			return $this->SetError('No query results exist', -1);
		}
		else
		{
			$result = @mysql_num_rows($this->last_result);
			if (!$result)
			{
				return $this->SetError();
			}
			else
			{
				return $result;
			}
		}
	}

	/**
	 * Sets the internal database pointer to the
	 * specified row number and returns the result
	 *
	 * @api
	 * @param integer $row_number Row number
	 * @return object Fetched row as PHP object on success or FALSE on error
	 *
	 * @example
	 * $db->Seek(0);   // Move to the first record
	 */
	public function Seek($row_number)
	{
		$this->ResetError();
		$row_count = $this->RowCount();
		if (!$row_count)
		{
			return false;
		}
		elseif ($row_number >= $row_count)
		{
			return $this->SetError('Seek parameter is greater than the total number of rows', -1);
		}
		else
		{
			$this->active_row = $row_number;
			$result = mysql_data_seek($this->last_result, $row_number);
			if (!$result)
			{
				return $this->SetError();
			}
			else
			{
				$record = mysql_fetch_row($this->last_result);
				if (!$record)
				{
					return $this->SetError();
				}
				else
				{
					// Go back to the record after grabbing it
					mysql_data_seek($this->last_result, $row_number);
					return $record;
				}
			}
		}
	}

	/**
	 * Returns the current cursor row location
	 *
	 * @api
	 * @return integer Current row number
	 *
	 * @example
	 * echo "Current Row Cursor : " . $db->GetSeekPosition();
	 */
	public function SeekPosition()
	{
		return $this->active_row;
	}

	/**
	 * Selects a different database and character set
	 *
	 * @api
	 * @param string $database Database name
	 * @param string $charset (Optional) Character set, e.g. 'utf8'. (Default: NULL)
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $db->SelectDatabase("DatabaseName");
	 */
	public function SelectDatabase($database, $charset = null)
	{
		if (!$charset) $charset = $this->db_charset;
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		$this->last_sql = '(SELECT DATABASE ' . $database . ')';
		$this->query_count++;
		if (!mysql_select_db($database, $this->mysql_link))
		{
			return $this->SetError();
		}
		else
		{
			if (!empty($charset))
			{
				if (!$this->Query('SET CHARACTER SET ' . self::SQLFixName($charset)))
				{
					return $this->SetError();
				}
			}
		}
		return true;
	}


	/**
	 * Creates a new database and sets up the root access for the database.
	 *
	 * @api
	 * @param string $database Database name
	 * @param string $charset (Optional) Character set (i.e. utf8)
	 * @param string $collation (Optional) Character set collation (i.e. utf8_unicode_ci)
	 * @param string $admin_user (Optional) Database admin user name
	 * @param string $admin_pass (Optional) Database admin password
	 *
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function CreateDatabase($database, $charset = null, $collation = null, $admin_user = null, $admin_pass = null)
	{
		if (!$charset) $charset = $this->db_charset;
		if (!$collation) $collation = $this->db_charsetcollation;
		if (!$admin_user) $admin_user = $this->db_user;
		if (!$admin_pass) $admin_pass = $this->db_pass;
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}

		$sql = 'CREATE DATABASE ' . self::SQLFixName($database);
		if (!empty($charset))
		{
			$sql .= ' DEFAULT CHARSET=' . self::SQLFixName($charset);

			if (!empty($collation))
			{
				$sql .= ' COLLATE=' . self::SQLFixName($collation);
			}
		}
		if (!$this->Query($sql))
		{
			return false;
		}

		$sql = 'GRANT ALL PRIVILEGES ON ' . $database . '.* TO \'' . self::SQLFix($admin_user) . '\'@\'' . $this->db_host . '\' IDENTIFIED BY \'' . self::SQLFix($admin_pass) . '\'';
		if (!$this->Query($sql))
		{
			return false;
		}

		$sql = 'FLUSH PRIVILEGES';
		if (!$this->Query($sql))
		{
			return false;
		}

		return true;
	}


	/**
	 * Gets rows in a table based on a WHERE filter
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, etc.)
	 * @param array|string $columns (Optional) The column or list of columns to select
	 * @param array|string $sortColumns (Optional) Column or list of columns to sort by.
	 *                          Column names may be prefixed by a plus(+) or minus(-)
	 *                          to indicate sort order.
	 *                          Default is ASCending for each column.
	 * @param integer|string $limit (Optional) The limit of rows to return
	 * @return boolean Returns records on success or FALSE on error
	 *
	 * @note Any of the parameters $whereArray, $columns, $sortColumns or $limit
	 *       may alternatively be a string, in which case these are used verbatim
	 *       in the query. This is useful when advanced queries are constructed.
	 *
	 * @example
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $filter["Color"] = MySQL::SQLValue("Violet");
	 * $filter["Age"]   = MySQL::SQLValue(777, MySQL::SQLVALUE_NUMBER);
	 *
	 * // Execute the select
	 * $result = $db->SelectRows("MyTable", $filter);
	 *
	 * // If we have an error
	 * if (!$result)
	 * {
	 *     // Show the error and kill the script
	 *     $db->Kill();
	 * }
	 */
	public function SelectRows($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$sql = $this->BuildSQLSelect($tableName, $whereArray,
					$columns, $sortColumns, $limit);
			if (!is_string($sql)) return false;
			// Execute the UPDATE
			if (!$this->Query($sql))
			{
				return false;
			}
			return $this->last_result;
		}
	}

	/**
	 * Retrieves all rows in a specified table
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @return boolean Returns an array of records
	 *         (each an object where the columns are individual object member variables)
	 *         on success or FALSE on error
	 */
	public function SelectTable($tableName)
	{
		return $this->SelectRows($tableName);
	}

	/**
	 * Sets the local variables with the first error information
	 *
	 * @api
	 * @param string $errorMessage The error description
	 * @param integer $errorNumber The error number
	 */
	private function SetError($errorMessage = '', $errorNumber = 0)
	{
		if (!$this->ErrorNumber())
		{
			try
			{
				if (strlen($errorMessage) > 0)
				{
					$this->error_desc = $errorMessage;
				}
				else
				{
					if ($this->IsConnected())
					{
						$this->error_desc = mysql_error($this->mysql_link);
					}
					else
					{
						$this->error_desc = mysql_error();
					}
				}
				if ($errorNumber <> 0)
				{
					$this->error_number = $errorNumber;
				}
				else
				{
					if ($this->IsConnected())
					{
						$this->error_number = @mysql_errno($this->mysql_link);
					}
					else
					{
						$this->error_number = @mysql_errno();
					}
				}
			}
			catch(Exception $e)
			{
				$this->error_desc = $e->getMessage();
				$this->error_number = -999;
			}
		}
		if ($this->ThrowExceptions)
		{
			if (isset($this->error_desc) && $this->error_desc != NULL)
			{
				throw new Exception($this->error_desc . ' (' . __LINE__ . ')');
			}
		}
		return false; // always return 'false' which is used as an error marker throughout.
	}

	/**
	 * Converts a boolean into a formatted TRUE or FALSE value of choice
	 *
	 * @static
	 * @api
	 * @param mixed $value value to analyze for TRUE or FALSE
	 * @param mixed $trueValue value to use if TRUE
	 * @param mixed $falseValue value to use if FALSE
	 * @param string $datatype Use SQLVALUE constants or the strings:
	 *                          string, text, varchar, char, boolean, bool,
	 *                          Y-N, T-F, bit, date, datetime, time, integer,
	 *                          int, number, double, float
	 * @return string SQL formatted value of the specified data type on success or FALSE on error
	 *
	 * @example
	 * echo MySQL::SQLBooleanValue(false, "1", "0", MySQL::SQLVALUE_NUMBER);
	 * echo MySQL::SQLBooleanValue($test, "Jan 1, 2007 ", "2007/06/01", MySQL::SQLVALUE_DATE);
	 * echo MySQL::SQLBooleanValue("ON", "Ya", "Nope");
	 * echo MySQL::SQLBooleanValue(1, '+', '-');
	 */
	static public function SQLBooleanValue($value, $trueValue = true, $falseValue = false, $datatype = self::SQLVALUE_TEXT)
	{
		if (self::GetBooleanValue($value))
		{
			 $return_value = self::SQLValue($trueValue, $datatype);
		}
		else
		{
			 $return_value = self::SQLValue($falseValue, $datatype);
		}
		return $return_value;
	}

	/**
	 * Returns string suitable for inclusion in a SQL query
	 *
	 * The returned string representing the $value will be properly escaped
	 * and filtered to use as part of a constructed SQL query.
	 *
	 * @static
	 * @api
	 * @param string $value
	 * @return string SQL formatted value
	 *
	 * @example
	 * $value = MySQL::SQLFix("\hello\ /world/");
	 * echo $value . "\n" . MySQL::SQLUnfix($value);
	 */
	static public function SQLFix($value)
	{
		return @mysql_real_escape_string($value);
	}

	/**
	 * Returns MySQL string as normal string
	 *
	 * @static
	 * @api
	 * @param string $value
	 * @return string
	 *
	 * @warning Do NOT use on columns returned by a database query: such data has already
	 *          been adequately processed by MySQL itself.
	 *          The only probable place where the SQLUnfix() method MAY be useful is when
	 *          DIRECTLY accessing strings produced by the SQLValue() method.
	 *
	 * @example
	 * $value = MySQL::SQLFix("\hello\ /world/");
	 * echo $value . "\n" . MySQL::SQLUnfix($value);
	 */
	static public function SQLUnfix($value)
	{
		return @stripslashes($value);
	}

	/**
	 * Returns string suitable for inclusion in a SQL query as a field, table or aliased reference
	 *
	 * The returned string representing the $value will be properly escaped
	 * and filtered to use as part of a constructed SQL query.
	 *
	 * @static
	 * @api
	 * @param string $value
	 * @return string SQL formatted value, will be surrounded by '`'-quotes when containing non-standard characters
	 *
	 * @example
	 * $value = MySQL::SQLFix("table.column_name");
	 * echo $value . "\n" . MySQL::SQLFixName($value);
	 */
	static public function SQLFixName($value)
	{
		$s = @mysql_real_escape_string($value);
		$e = explode('.', $s);
		foreach($e as &$fn)
		{
			// field and table names should never start with a digit and consist of alphanumerics only:
			$quoting = (strspn($fn, '0123456789') > 0 || strlen($fn) !== strspn($fn, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'));
			if ($quoting && $fn !== '*')
			{
				$fn = '`' . $fn . '`';
			}
		}
		$s = implode('.', $e);
		return $s;
	}

	/**
	 * Formats any value into a string suitable for SQL statements
	 *
	 * @note
	 * Also supports data types returned from the gettype function.
	 *
	 * @static
	 * @api
	 * @param mixed $value Any value of any type to be formatted to SQL
	 * @param string $datatype Use SQLVALUE constants or the strings:
	 *                          'string', 'text', 'varchar', 'char', 'boolean', 'bool',
	 *                          'Y-N', 'T-F', 'bit', 'date', 'datetime', 'time', 'integer',
	 *                          'int', 'number', 'double', 'float'
	 * @return string The properly quoted and escaped/filtered value as a string
	 *                which can be safely included in a generated SQL query.
	 *
	 * @example
	 * echo MySQL::SQLValue("it's a string", "text");
	 * $sql = "SELECT * FROM Table WHERE Field1 = " . MySQL::SQLValue("123", MySQL::SQLVALUE_NUMBER);
	 * $sql = "UPDATE Table SET Field1 = " . MySQL::SQLValue("July 4, 2007", MySQL::SQLVALUE_DATE);
	 */
	static public function SQLValue($value, $datatype = self::SQLVALUE_TEXT)
	{
		$return_value = '';

		switch (strtolower(trim($datatype)))
		{
		case 'text':
		case 'string':
		case 'varchar':
		case 'char':
			$strvalue = strval($value);
			if (strlen($strvalue) == 0)
			{
				/*
				Depending on original type, this is a NULL or an empty string!
				*/
				if (gettype($value) == 'string')
				{
					$return_value = "''";
				}
				else
				{
					$return_value = 'NULL';
				}
			}
			else
			{
				$return_value = "'" . self::SQLFix($strvalue) . "'";
			}
			break;

		case 'enum':
			if (is_numeric($value))
			{
				$return_value = "'" . intval($value) . "'"; // Very tricky to go without the quotes, particularly when feeding integers into enum fields
			}
			else if (!empty($value))
			{
				$return_value = "'" . self::SQLFix(strval($value)) . "'";
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'number':
		case 'integer':
		case 'int':
			if (is_numeric($value))
			{
				$return_value = "'" . intval($value) . "'"; // Very tricky to go without the quotes, particularly when feeding integers into enum fields
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'double':
		case 'float':
			if (is_numeric($value))
			{
				$return_value = "'" . floatval($value) . "'"; // Play it safe; add quotes around the value anyway.
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'boolean':  //boolean to use this with a bit field
		case 'bool':
		case 'bit':
			if (self::GetBooleanValue($value))
			{
				 $return_value = "'1'";
			}
			else
			{
				 $return_value = "'0'";
			}
			break;

		case 'y-n':  //boolean to use this with a char(1) field
			if (self::GetBooleanValue($value))
			{
				$return_value = "'Y'";
			}
			else
			{
				$return_value = "'N'";
			}
			break;

		case 't-f':  //boolean to use this with a char(1) field
			if (self::GetBooleanValue($value))
			{
				$return_value = "'T'";
			}
			else
			{
				$return_value = "'F'";
			}
			break;

		case 'date':
			if (self::IsDateStr($value))
			{
				$return_value = "'" . date('Y-m-d', strtotime($value)) . "'";
			}
			elseif (is_int($value) && $value > 0)
			{
				$return_value = "'" . date('Y-m-d', $value) . "'";
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'datetime':
			if (self::IsDateStr($value))
			{
				$return_value = "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
			}
			elseif (is_int($value) && $value > 0)
			{
				$return_value = "'" . date('Y-m-d H:i:s', $value) . "'";
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'time':
			if (self::IsDateStr($value))
			{
				$return_value = "'" . date('H:i:s', strtotime($value)) . "'";
			}
			elseif (is_int($value) && $value > 0)
			{
				$return_value = "'" . date('H:i:s', $value) . "'";
			}
			else
			{
				$return_value = 'NULL';
			}
			break;

		case 'null':
			$return_value = 'NULL';
			break;

		default:
			exit('ERROR: Invalid data type specified in SQLValue method');
		}
		return $return_value;
	}

	/**
	 * Returns last measured duration (time between TimerStart and TimerStop)
	 *
	 * @api
	 * @param integer $decimals (Optional) The number of decimal places to show (Default: 4)
	 * @return Float Microseconds elapsed
	 *
	 * @example
	 * $db->TimerStart();
	 * // Do something or run some queries
	 * $db->TimerStop();
	 * echo $db->TimerDuration(2) . " microseconds";
	 */
	public function TimerDuration($decimals = 4)
	{
		return number_format($this->time_diff, $decimals);
	}

	/**
	 * Starts time measurement (in microseconds)
	 *
	 * @api
	 *
	 * @example
	 * $db->TimerStart();
	 * // Do something or run some queries
	 * $db->TimerStop();
	 * echo $db->TimerDuration() . " microseconds";
	 */
	public function TimerStart()
	{
		$parts = explode(' ', microtime());
		$this->time_diff = 0;
		$this->time_start = $parts[1].substr($parts[0],1);
	}

	/**
	 * Stops time measurement (in microseconds)
	 *
	 * @api
	 *
	 * @example
	 * $db->TimerStart();
	 * // Do something or run some queries
	 * $db->TimerStop();
	 * echo $db->TimerDuration() . " microseconds";
	 */
	public function TimerStop()
	{
		$parts  = explode(' ', microtime());
		$time_stop = $parts[1].substr($parts[0],1);
		$this->time_diff  = ($time_stop - $this->time_start);
		$this->time_start = 0;
	}

	/**
	 * Starts a transaction
	 *
	 * @api
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $sql = "INSERT INTO MyTable (Field1, Field2) Values ('abc', 123)";
	 * $db->TransactionBegin();
	 * if ($db->Query($sql))
	 * {
	 *     $db->TransactionEnd();
	 *     echo "Last ID inserted was: " . $db->GetLastInsertID();
	 * }
	 * else
	 * {
	 *     $db->TransactionRollback();
	 *     echo "Query Failed";
	 * }
	 */
	public function TransactionBegin()
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			if (!$this->in_transaction)
			{
				$sql = 'START TRANSACTION';
				$this->last_sql = $sql;
				$this->query_count++;
				if (!mysql_query($sql, $this->mysql_link))
				{
					return $this->SetError('Could not start transaction');
				}
				else
				{
					$this->in_transaction = true;
					return true;
				}
			}
			else
			{
				return $this->SetError('Already in transaction', -1);
			}
		}
	}

	/**
	 * Ends a transaction and commits the queries
	 *
	 * @api
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * $sql = "INSERT INTO MyTable (Field1, Field2) Values ('abc', 123)";
	 * $db->TransactionBegin();
	 * if ($db->Query($sql))
	 * {
	 *     $db->TransactionEnd();
	 *     echo "Last ID inserted was: " . $db->GetLastInsertID();
	 * }
	 * else
	 * {
	 *     $db->TransactionRollback();
	 *     echo "Query Failed";
	 * }
	 */
	public function TransactionEnd()
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			if ($this->in_transaction)
			{
				$sql = 'COMMIT';
				$this->last_sql = $sql;
				$this->query_count++;
				if (!mysql_query($sql, $this->mysql_link))
				{
					// $this->TransactionRollback();
					return $this->SetError();
				}
				else
				{
					$this->in_transaction = false;
					return true;
				}
			}
			else
			{
				return $this->SetError('Not in a transaction', -1);
			}
		}
	}

	/**
	 * Rolls the transaction back
	 *
	 * @api
	 * @return boolean Returns TRUE on success or FALSE on failure
	 *
	 * @example
	 * $sql = "INSERT INTO MyTable (Field1, Field2) Values ('abc', 123)";
	 * $db->TransactionBegin();
	 * if ($db->Query($sql))
	 * {
	 *     $db->TransactionEnd();
	 *     echo "Last ID inserted was: " . $db->GetLastInsertID();
	 * }
	 * else
	 * {
	 *     $db->TransactionRollback();
	 *     echo "Query Failed";
	 * }
	 */
	public function TransactionRollback()
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$sql = 'ROLLBACK';
			$this->last_sql = $sql;
			$this->query_count++;
			if (!mysql_query($sql, $this->mysql_link))
			{
				return $this->SetError('Could not rollback transaction', -1);
			}
			else
			{
				$this->in_transaction = false;
				return true;
			}
		}
	}

	/**
	 * Truncates a table removing all data
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function TruncateTable($tableName)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$sql = 'TRUNCATE TABLE ' . self::SQLFixName($tableName);
			return !!$this->Query($sql);
		}
	}

	/**
	 * Update selected rows
	 *
	 * Updates rows in a table based on a WHERE filter
	 * (can be just one or many rows based on the filter).
	 *
	 * @api
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, etc.)
	 * @param array|string $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, etc.). If not specified
	 *                           then all values in the table are updated.
 	 *                           <br/>
	 *                           This parameter may alternatively be a string, in
	 *                           which case it is used verbatim for the WHERE
	 *                           clause of the query. This is useful when
	 *                           advanced queries are constructed.
	 * @return boolean Returns TRUE on success or FALSE on error
	 *
	 * @example
	 * // Create an array that holds the update information
	 * // $arrayVariable["column name"] = formatted SQL value
	 * $update["Name"] = MySQL::SQLValue("Bob");
	 * $update["Age"]  = MySQL::SQLValue(25, MySQL::SQLVALUE_NUMBER);
	 *
	 * // Execute the update where the ID is 1
	 * if (!$db->UpdateRow("test", $values, array("id" => 1))) $db->Kill();
	 */
	public function UpdateRow($tableName, $valuesArray, $whereArray = null)
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$sql = $this->BuildSQLUpdate($tableName, $valuesArray, $whereArray);
			if (!is_string($sql)) return false;
			// Execute the UPDATE
			return !!$this->Query($sql);
		}
	}

	/**
	 * Return a few database statistics in an array.
	 *
	 * @api
	 * @return array Returns an array of statistics values on success or FALSE on error.
	 */
	public function GetStatistics()
	{
		$this->ResetError();
		if (!$this->IsConnected())
		{
			return $this->SetError('No connection', -1);
		}
		else
		{
			$result = mysql_stat($this->mysql_link);
			if (empty($result))
			{
				$this->SetError('Failed to obtain database statistics', -1); // do NOT return to caller yet!
				return array('Query Count' => $this->query_count);
			}

			$tot_count = preg_match_all('/([a-z ]+):\s*([0-9.]+)/i', $result, $matches);

			$info = array('Query Count' => $this->query_count);
			for ($i = 0; $i < $tot_count; $i++)
			{
				$info[$matches[1][$i]] = $matches[2][$i];
			}
			return $info;
		}
	}
}
