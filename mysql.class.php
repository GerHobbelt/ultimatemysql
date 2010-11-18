<?php

/* make sure no-one can run anything here if they didn't arrive through 'proper channels' */
if(!defined("COMPACTCMS_CODE")) { die('Illegal entry point!'); } /*MARKER*/

/**
 * Copyright (C) 2008 - 2010 by Xander Groesbeek (CompactCMS.nl)
 * 
 * Last changed: $LastChangedDate$
 * @author $Author$
 * @version $Revision$
 * @package CompactCMS.nl
 * @license GNU General Public License v3
 * 
 * This external class is part of CompactCMS.
 * 
 * Copyright (C) 2009 for UMWC Jeff L. Williams
 * Project: http://www.phpclasses.org/ultimatemysql
 * 
 * Ultimate MySQL Wrapper Class (UMWC) is free software; you can 
 * redistribute it and/or modify it under the terms of the GNU General
 * Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 * 
 * UMWC is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with CompactCMS. If not, see <http://www.gnu.org/licenses/>.
 * 
 * > Contact me for any inquiries.
 * > E: Xander@CompactCMS.nl
 * > W: http://community.CompactCMS.nl/forum
**/

class MySQL
{
	// SET THESE VALUES TO MATCH YOUR DATA CONNECTION
	private $db_host    = "localhost";  // server name
	private $db_user    = "";           // user name
	private $db_pass    = "";           // password
	private $db_dbname  = "";           // database name
	private $db_charset = "utf8";       // optional character set (i.e. utf8)
	private $db_pcon    = false;        // use persistent connection?

	// constants for SQLValue function
	const SQLVALUE_BIT      = "bit";
	const SQLVALUE_BOOLEAN  = "boolean";
	const SQLVALUE_DATE     = "date";
	const SQLVALUE_DATETIME = "datetime";
	const SQLVALUE_NUMBER   = "number";
	const SQLVALUE_ENUMERATE = "enum";
	const SQLVALUE_T_F      = "t-f";
	const SQLVALUE_TEXT     = "text";
	const SQLVALUE_TIME     = "time";
	const SQLVALUE_Y_N      = "y-n";

	// class-internal variables - do not change
	private $active_row     = -1;       // current row
	private $error_desc     = "";       // last mysql error string
	private $error_number   = 0;        // last mysql error number
	private $in_transaction = false;    // used for transactions
	private $last_insert_id;            // last id of record inserted
	private $last_result;               // last mysql query result
	private $last_sql       = "";       // last mysql query
	private $mysql_link     = 0;        // mysql link resource
	private $time_diff      = 0;        // holds the difference in time
	private $time_start     = 0;        // start time for the timer
	private $query_count    = 0;        // tracks the number of queries executed through this instance

	/**
	 * Determines if an error throws an exception
	 *
	 * @var boolean Set to true to throw error exceptions
	 */
	public $ThrowExceptions = false;

	/**
	 * Constructor: Opens the connection to the database
	 *
	 * @param boolean $connect (Optional) Auto-connect when object is created
	 * @param string $database (Optional) Database name
	 * @param string $server   (Optional) Host address
	 * @param string $username (Optional) User name
	 * @param string $password (Optional) Password
	 * @param string $charset  (Optional) Character set
	 */
	public function __construct($connect = true, $database = null, $server = null,
								$username = null, $password = null, $charset = null) 
	{
		if ($database !== null) $this->db_dbname  = $database;
		if ($server   !== null) $this->db_host    = $server;
		if ($username !== null) $this->db_user    = $username;
		if ($password !== null) $this->db_pass    = $password;
		if ($charset  !== null) $this->db_charset = $charset;

		if (strlen($this->db_host) > 0 &&
			strlen($this->db_user) > 0) 
		{
			if ($connect) $this->Open();
		}
	}

	/**
	 * Destructor: Closes the connection to the database
	 *
	 */
	public function __destruct() 
	{
		$this->Close();
	}

	/**
	 * Automatically does an INSERT or UPDATE depending if an existing record
	 * exists in a table
	 *
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, ect)
	 * @param array $whereArray An associative array containing the column
	 *                           names as keys and values as data. The values
	 *                           must be SQL ready (i.e. quotes around strings,
	 *                           formatted dates, ect).
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
			return $this->UpdateRows($tableName, $valuesArray, $whereArray);
		} 
		else 
		{
			return $this->InsertRow($tableName, $valuesArray);
		}
	}

	/**
	 * Returns true if the internal pointer is at the beginning of the records
	 *
	 * @return boolean TRUE if at the first row or FALSE if not
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
			return $this->SetError("No connection", -1);
		}
	}

	/**
	 * Builds a comma delimited list of columns for use with SQL
	 *
	 * @param array $valuesArray An array containing the column names.
	 * @param boolean $addQuotes (Optional) TRUE to add quotes
	 * @param boolean $showAlias (Optional) TRUE to show column alias
	 * @param boolean $withSortMarker (Optional) TRUE when the field list is meant 
	 *                  for an ORDER BY clause; fields may be prefixed by a 
	 *                  plus(+) or minus(-) to indicate sort order. 
	 *                  Default is ASCending for each field.
	 * @return string Returns the SQL column list on success or NULL on failure
	 */
	private function BuildSQLColumns($columns, $addQuotes = true, $showAlias = true, $withSortMarker = false) 
	{
		switch (gettype($columns)) 
		{
		case "array":
			$sql = "";
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
					$sql .= ", ";
				}
				if ($addQuotes) 
				{
					$sql .= "`" . self::SQLFix($value) . "`";
				} 
				else 
				{
					$sql .= $value;
				}
				if ($showAlias && is_string($key) && (!empty($key))) 
				{
					$sql .= ' AS "' . self::SQLFix($key) . '"';
				}
				else if ($withSortMarker)
				{
					$sql .= $asc;
				}
			}
			return $sql;

		case "string":
			if ($addQuotes) 
			{
				return "`" . self::SQLFix($columns) . "`";
			} 
			else 
			{
				return $columns;
			}

		default:
			return false;
		}
	}

	/**
	 * Builds a SQL DELETE statement
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, ect). If not specified
	 *                           then all values in the table are deleted.
	 * @return string Returns the SQL DELETE statement
	 */
	public function BuildSQLDelete($tableName, $whereArray = null) 
	{
		$sql = "DELETE FROM `" . self::SQLFix($tableName) . "`";
		if (! is_null($whereArray)) 
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= $wh;
		}
		return $sql;
	}

	/**
	 * Builds a SQL INSERT statement
	 *
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, ect)
	 * @return string Returns a SQL INSERT statement
	 */
	public function BuildSQLInsert($tableName, $valuesArray) 
	{
		$columns = $this->BuildSQLColumns(array_keys($valuesArray), true, false);
		$values  = $this->BuildSQLColumns($valuesArray, false, false);
		if (empty($columns) || empty($values)) return false;
		$sql = "INSERT INTO `" . self::SQLFix($tableName) .
			   "` (" . $columns . ") VALUES (" . $values . ")";
		return $sql;
	}

	/**
	 * Builds a simple SQL SELECT statement
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @return string Returns a SQL SELECT statement
	 */
	public function BuildSQLSelect($tableName, $whereArray = null, $columns = null,
										  $sortColumns = null, $limit = null) 
	{
		if (!is_null($columns)) 
		{
			$sql = $this->BuildSQLColumns($columns, false, true);
			if (!is_string($sql)) return false;
			$sql = trim($sql);
		} 
		if (empty($sql))
		{
			$sql = "*";
		}
		$sql = "SELECT " . $sql . " FROM `" . self::SQLFix($tableName) . "`";
		if (!is_null($whereArray)) 
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= ' ' . $wh;
		}
		if (!is_null($sortColumns)) 
		{
			$ordstr = $this->BuildSQLColumns($sortColumns, false, false, true);
			if (!is_string($ordstr)) return false;
			$ordstr = trim($ordstr);
			if (!empty($ordstr))
			{
				$sql .= " ORDER BY " . $ordstr;
			}
		}
		if (!is_null($limit)) 
		{
			if (1 == preg_match('/[^0-9 ,]/', $limit))
				return $this->SetError('ERROR: Invalid LIMIT clause specified in BuildSQLSelect method.', -1);
			$sql .= " LIMIT " . $limit;
		}
		return $sql;
	}

	/**
	 * Builds a SQL UPDATE statement
	 *
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, ect)
	 * @param array $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, ect). If not specified
	 *                           then all values in the table are updated.
	 * @return string Returns a SQL UPDATE statement
	 */
	public function BuildSQLUpdate($tableName, $valuesArray, $whereArray = null) 
	{
		if (!is_array($valuesArray))
			return $this->SetError("ERROR: Invalid valuesArray type specified in BuildSQLUpdate method", -1);
		if (count($valuesArray) <= 0)
			return $this->SetError("ERROR: Invalid/Empty valuesArray array specified in BuildSQLUpdate method", -1);

		$sql = "";
		foreach ($valuesArray as $key => $value) 
		{
			if (empty($value) && !is_integer($value))
				return $this->SetError("ERROR: Invalid value specified in BuildSQLUpdate method", -1);
			if (empty($key))
				return $this->SetError("ERROR: Invalid key specified in BuildSQLUpdate method", -1);

			if (strlen($sql) != 0) 
				$sql .= ", ";
			$sql .= "`" . $key . "` = " . $value;
		}
		$sql = "UPDATE `" . self::SQLFix($tableName) . "` SET " . $sql;

		if (!is_null($whereArray)) 
		{
			$wh = $this->BuildSQLWhereClause($whereArray);
			if (!is_string($wh)) return false;
			$sql .= ' ' . $wh;
		}
		return $sql;
	}

	/**
	 * [STATIC] Construct a value string suitable for incorporation anywhere
	 * in a SQL query. This methos invokes self::SQLValue() under the hood.
	 *
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
	 * If a key is specified, the key is used at the field name and the value
	 * as a comparison. If a key is not used, the value is used as the clause.
	 *
	 * @param array $whereArray An associative array containing the column
	 *                           names as keys and values as data. The values
	 *                           must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, ect)
	 * @return string Returns a string containing the SQL WHERE clause
	 */
	public function BuildSQLWhereClause($whereArray) 
	{
		switch (gettype($whereArray)) 
		{
		case "array":
			$where = "";
			foreach ($whereArray as $key => $value)
			{
				if (strlen($where) == 0)
				{
					$where = "WHERE ";
				} 
				else 
				{
					$where .= " AND ";
				} 

				if (is_string($key) && empty($key))
					return $this->SetError("ERROR: Invalid key specified in BuildSQLWhereClause method", -1);
				if (empty($value) && !is_integer($value))
					return $this->SetError("ERROR: Invalid value specified in BuildSQLWhereClause method for key '" . $key . "'", -1);

				if (is_string($key))
				{
					$where .= "`" . $key . "` = " . $value;
				} 
				else 
				{
					$where .= $value;
				}
			}
			return $where;

		case "string":
			return $whereArray;

		default:
			return $this->SetError("ERROR: Invalid key specified in BuildSQLWhereClause method", -1);
		}
	}

	/**
	 * Close current MySQL connection
	 *
	 * @return object Returns TRUE on success or FALSE on error
	 */
	public function Close() 
	{
		$this->ResetError();
		$this->active_row = -1;
		$success = $this->Release();
		if ($success) 
		{
			$success = @mysql_close($this->mysql_link);
			if (! $success) 
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
	 * Deletes rows in a table based on a WHERE filter
	 * (can be just one or many rows based on the filter)
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect). If not specified
	 *                          then all values in the table are deleted.
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function DeleteRows($tableName, $whereArray = null) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
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
	 * @return boolean TRUE if at the last row or FALSE if not
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
			return $this->SetError("No connection", -1);
		}
	}

	/**
	 * Returns the last MySQL error as text
	 *
	 * @return string Error text from last known error
	 */
	public function Error() 
	{
		$error = $this->error_desc;
		if (empty($error)) 
		{
			if ($this->error_number <> 0) 
			{
				$error = "Unknown Error (#" . $this->error_number . ")";
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
				$error .= " (#" . $this->error_number . ")";
			}
		}
		return $error;
	}

	/**
	 * Returns the last MySQL error as a number
	 *
	 * @return integer Error number from last known error
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
	 * [STATIC] Converts any value of any datatype into boolean (true or false)
	 *
	 * @param mixed $value Value to analyze for TRUE or FALSE
	 * @return boolean Returns TRUE or FALSE
	 */
	static public function GetBooleanValue($value) 
	{
		if (gettype($value) == "boolean") 
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

			if ($cleaned == "ON") 
			{
				return true;
			} 
			elseif ($cleaned == "SELECTED" || $cleaned == "CHECKED") 
			{
				return true;
			} 
			elseif ($cleaned == "YES" || $cleaned == "Y") 
			{
				return true;
			} 
			elseif ($cleaned == "TRUE" || $cleaned == "T") 
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
	 * Returns the comments for fields in a table into an
	 * array or NULL if the table has not got any fields
	 *
	 * @param string $table Table name
	 * @return array An array that contains the column comments
	 */
	public function GetColumnComments($table) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		$this->query_count++;
		$records = mysql_query("SHOW FULL COLUMNS FROM " . $table, $this->mysql_link);
		if (! $records) 
		{
			return $this->SetError();
		} 
		else 
		{
			// Get the column names
			$columnNames = $this->GetColumnNames($table);
			if ($this->ErrorNumber()) 
			{
				return false;
			} 
			else 
			{
				$index = 0;
				$columns = array();
				// Fetchs the array to be returned (column 8 is field comment):
				while ($array_data = mysql_fetch_array($records, MYSQL_NUM)) 
				{
					//$columns[$index] = $array_data[8];
					$columns[$columnNames[$index++]] = $array_data[8];
				}
				return $columns;
			}
		}
	}

	/**
	 * This function returns the number of columns or returns FALSE on error
	 *
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      column count is returned from the last query
	 * @return integer The total count of columns
	 */
	public function GetColumnCount($table = "") 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		if (empty($table)) 
		{
			$result = mysql_num_fields($this->last_result);
			if (! $result) return $this->SetError();
		} 
		else 
		{
			$this->query_count++;
			$records = mysql_query("SELECT * FROM " . $table . " LIMIT 1", $this->mysql_link);
			if (! $records) 
			{
				return $this->SetError();
			} 
			else 
			{
				$result = mysql_num_fields($records);
				if (! $result) return $this->SetError();
				$success = @mysql_free_result($records);
				if (! $success) 
				{
					return $this->SetError();
				}
			}
		}
		return $result;
	}

	/**
	 * This function returns the data type for a specified column. If
	 * the column does not exists or no records exist, it returns FALSE
	 *
	 * @param string $column Column name or number (first column is 0)
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used
	 * @return string MySQL data (field) type
	 */
	public function GetColumnDataType($column, $table = "") 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
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
			$this->query_count++;
			$result = mysql_query("SELECT " . $column . " FROM " . $table . " LIMIT 1", $this->mysql_link);
			if (mysql_num_fields($result) > 0) 
			{
				return mysql_field_type($result, 0);
			} 
			else 
			{
				return $this->SetError("The specified column or table does not exist, or no data was returned", -1);
			}
		}
	}

	/**
	 * This function returns the position of a column
	 *
	 * @param string $column Column name
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return integer Column ID
	 */
	public function GetColumnID($column, $table = "") 
	{
		$this->ResetError();
		$columnNames = $this->GetColumnNames($table);
		if (! $columnNames) 
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
				return $this->SetError("Column name not found", -1);
			}
		}
	}

	/**
	 * This function returns the field length or returns FALSE on error
	 *
	 * @param string $column Column name
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return integer Field length
	 */
	public function GetColumnLength($column, $table = null) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
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
			if (! $columnID) 
			{
				return false;
			} 
			else 
			{
				$result = mysql_field_len($this->last_result, $columnID);
				if (! $result) 
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
			$this->query_count++;
			$records = mysql_query("SELECT " . $column . " FROM " . $table . " LIMIT 1", $this->mysql_link);
			if (! $records) 
			{
				return $this->SetError();
			}
			$result = mysql_field_len($records, 0);
			if (! $result) 
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
	 * This function returns the name for a specified column number. If
	 * the index does not exists or no records exist, it returns FALSE
	 *
	 * @param string $columnID Column position (0 is the first column)
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used.
	 * @return string Field Name
	 */
	public function GetColumnName($columnID, $table = null) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		if (empty($table)) 
		{
			if ($this->RowCount() > 0) 
			{
				$result = mysql_field_name($this->last_result, $columnID);
				if (! $result) return $this->SetError();
			} 
			else 
			{
				return false;
			}
		} 
		else 
		{
			$this->query_count++;
			$records = mysql_query("SELECT * FROM " . $table . " LIMIT 1", $this->mysql_link);
			if (! $records) 
			{
				return $this->SetError();
			} 
			else 
			{
				if (mysql_num_fields($records) > 0) 
				{
					$result = mysql_field_name($records, $columnID);
					if (! $result) return $this->SetError();
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
	 * Returns the field names in a table or query in an array
	 *
	 * @param string $table (Optional) If a table name is not specified, the
	 *                      last returned records are used
	 * @return array An array that contains the column names
	 */
	public function GetColumnNames($table = null) 
	{
		$this->ResetError();
		$columns = array();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		if (empty($table)) 
		{
			$columnCount = mysql_num_fields($this->last_result);
			if (! $columnCount) 
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
			$this->query_count++;
			$result = mysql_query("SHOW COLUMNS FROM " . $table, $this->mysql_link);
			if (! $result) 
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
	 * This function returns the last query as an HTML table
	 *
	 * @param boolean $showCount (Optional) TRUE if you want to show the row count,
	 *                           FALSE if you do not want to show the count
	 * @param string $styleTable (Optional) table tag attributes, e.g. styling
	 * @param string $styleHeader (Optional) header row tag attributes
	 * @param string $styleData (Optional) cell tag attributes
	 * @return string HTML containing a table with all records listed
	 */
	public function GetHTML($showCount = true, $styleTable = null, $styleHeader = null, $styleData = null) 
	{
		if ($styleTable === null) 
		{
			$tb = 'style="border-collapse:collapse;empty-cells:show" cellpadding="2" cellspacing="2"';
		} 
		else 
		{
			$tb = $styleTable;
		}
		if ($styleHeader === null) 
		{
			$th = 'style="border-width:1px;border-style:solid;background-color:navy;color:white"';
		} 
		else 
		{
			$th = $styleHeader;
		}
		if ($styleData === null) 
		{
			$td = 'style="border-width:1px;border-style:solid"';
		} 
		else 
		{
			$td = $styleData;
		}

		if ($this->last_result) 
		{
			if ($this->RowCount() > 0) 
			{
				$html = "";
				if ($showCount) $html = "Record Count: " . $this->RowCount() . "<br />\n";
				$html .= "<table $tb>\n";
				$this->MoveFirst();
				$header = false;
				while ($member = mysql_fetch_object($this->last_result)) 
				{
					if (!$header) 
					{
						$html .= "\t<tr>\n";
						foreach ($member as $key => $value) 
						{
							$html .= "\t\t<th $th><strong>" . htmlspecialchars($key) . "</strong></th>\n";
						}
						$html .= "\t</tr>\n";
						$header = true;
					}
					$html .= "\t<tr>\n";
					foreach ($member as $key => $value) 
					{
						$html .= "\t\t<td $td>" . htmlspecialchars($value) . "</td>\n";
					}
					$html .= "\t</tr>\n";
				}
				$this->MoveFirst();
				$html .= "</table>";
			} 
			else 
			{
				$html = "No records were returned.";
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
	 * Returns the last query as a JSON document
	 *
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
					$json .= json_encode($member) . ",";
				}
				$json .= ']';
				$json = str_replace("},]", "}]", $json);
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
	 * Returns the last autonumber ID field from a previous INSERT query
	 *
	 * @return  integer ID number from previous INSERT query
	 */
	public function GetLastInsertID() 
	{
		return $this->last_insert_id;
	}

	/**
	 * Returns the last SQL statement executed
	 *
	 * @return string Current SQL query string
	 */
	public function GetLastSQL() 
	{
		return $this->last_sql;
	}

	/**
	 * This function returns table names from the database
	 * into an array. If the database does not contains
	 * any tables, the returned value is FALSE
	 *
	 * @return array An array that contains the table names
	 */
	public function GetTables() 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		// Query to get the tables in the current database:
		$this->query_count++;
		$records = mysql_query("SHOW TABLES", $this->mysql_link);
		if (! $records) 
		{
			return $this->SetError();
		} 
		else 
		{
			$tables = array();
			while ($array_data = mysql_fetch_array($records, MYSQL_NUM)) 
			{
				$tables[] = $array_data[0];
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
	 * Returns the last query as an XML Document
	 *
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
			$root->setAttribute('error', "");

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

					// $fieldvalue = iconv("ISO-8859-1", "UTF-8", $fieldvalue);
					$fieldvalue = htmlspecialchars($fieldvalue);
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
				$root->setAttribute('error', "No query has been executed.");
			}
		}

		// Show the XML document
		return $doc->saveXML();
	}

	/**
	 * Determines if a query contains any rows
	 *
	 * @param string $sql [Optional] If specified, the query is first executed
	 *                    Otherwise, the last query is used for comparison
	 * @return boolean TRUE if records exist, FALSE if not or query error
	 */
	public function HasRecords($sql = null) 
	{
		if (!empty($sql)) 
		{
			if (! $this->Query($sql)) return false;
		}
		return ($this->RowCount() > 0);
	}

	/**
	 * Inserts a row into a table in the connected database
	 *
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, ect)
	 * @return integer Returns last insert ID on success or FALSE on failure
	 */
	public function InsertRow($tableName, $valuesArray) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			// Execute the query
			$sql = self::BuildSQLInsert($tableName, $valuesArray);
			if (!is_string($sql)) return false;
			if (! $this->Query($sql)) 
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
	 * @return boolean TRUE idf connectect or FALSE if not connected
	 */
	public function IsConnected() 
	{
		return (gettype($this->mysql_link) == "resource");
	}

	/**
	 * [STATIC] Determines if a value of any data type is a date PHP can convert
	 *
	 * @param string $value
	 * @return boolean Returns TRUE if value is date or FALSE if not date
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
	 */
	public function Kill($message = "", $prepend_message = true) 
	{
		exit($this->MyDyingMessage($message, $prepend_message));
	}

	/**
	 * Return the error message ready for throwing back out to the client side while dying, a.k.a. Kill() without the death nor the echo'ing.
	 */
	public function MyDyingMessage($message = "", $prepend_message = true) 
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
		if ($cfg['IN_DEVELOPMENT_ENVIRONMENT']) $message .= "<h1>Offending SQL query</h1><p>" . htmlspecialchars($this->last_sql) . "</p><h2>Error Message</h2><p> ";
		return $message . $this->Error();
	}

	/**
	 * Seeks to the beginning of the records
	 *
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function MoveFirst() 
	{
		$this->ResetError();
		if (! $this->Seek(0)) 
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
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function MoveLast() 
	{
		$this->ResetError();
		$this->active_row = $this->RowCount() - 1;
		if (! $this->ErrorNumber()) 
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
	 * @param string $database (Optional) Database name
	 * @param string $server   (Optional) Host address
	 * @param string $username (Optional) User name
	 * @param string $password (Optional) Password
	 * @param string $charset  (Optional) Character set
	 * @param boolean $pcon    (Optional) Persistant connection
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function Open($database = null, $server = null, $username = null,
						 $password = null, $charset = null, $pcon = false) 
	{
		$this->ResetError();

		// Use defaults?
		if ($database !== null) $this->db_dbname  = $database;
		if ($server   !== null) $this->db_host    = $server;
		if ($username !== null) $this->db_user    = $username;
		if ($password !== null) $this->db_pass    = $password;
		if ($charset  !== null) $this->db_charset = $charset;
		if (is_bool($pcon))     $this->db_pcon    = $pcon;

		$this->active_row = -1;

		// Open persistent or normal connection
		if ($pcon) 
		{
			$this->mysql_link = @mysql_pconnect(
				$this->db_host, $this->db_user, $this->db_pass);
		} 
		else 
		{
			$this->mysql_link = @mysql_connect (
				$this->db_host, $this->db_user, $this->db_pass);
		}
		// Connect to mysql server failed?
		if (! $this->IsConnected()) 
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
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP 'mysql result' resource object containing the records
	 *                on SELECT, SHOW, DESCRIBE or EXPLAIN queries and returns;
	 *                TRUE or FALSE for all others i.e. UPDATE, DELETE, DROP
	 *                AND FALSE on all errors (setting the local Error message)
	 */
	public function Query($sql) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		$this->last_sql = $sql;
		$this->query_count++;
		$this->last_result = @mysql_query($sql, $this->mysql_link);
		if(! $this->last_result) 
		{
			$this->active_row = -1;
			return $this->SetError();
		} 
		else 
		{
			if (preg_match('/\binsert\b/i', $sql)) 
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
			else if(preg_match('/\bselect\b/i', $sql)) 
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
	 * Returns a multidimensional array of rows from a table based on a WHERE filter
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array A multi-dimensional array containing all the data
	 *               returned from the query or FALSE on all errors
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
	 * Executes the given SQL query and returns only one (the first) row
	 *
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP resource object containing the first row or
	 *                FALSE if no row is returned from the query
	 */
	public function QuerySingleRow($sql) 
	{
		if (! $this->Query($sql)) 
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
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @return object PHP resource object containing the first row or
	 *                FALSE if no row is returned from the query
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
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array An array containing the first row or FALSE if no row
	 *               is returned from the query
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
	 * Returns a single value from from the first row SELECTed from a table based on a 
	 * WHERE filter.
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @return mixed The value returned or FALSE if no value
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
	 * @param string $sql The query string should not end with a semicolon
	 * @return object PHP 'mysql result' resource object containing the records
	 *                on SELECT, SHOW, DESCRIBE or EXPLAIN queries and returns
	 *                TRUE or FALSE for all others i.e. UPDATE, DELETE, DROP
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
	 * @return object PHP 'mysql result' resource object containing the records
	 *                for the last query executed
	 */
	public function Records() 
	{
		return $this->last_result;
	}

	/**
	 * Returns all records from last query and returns contents as array
	 * or FALSE on error
	 *
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return Records in array form or FALSE on error. May return an 
	 *         EMPTY array when no records are available.
	 */
	public function RecordsArray($resultType = MYSQL_ASSOC) 
	{
		$this->ResetError();
		if ($this->last_result) 
		{
			if (! mysql_data_seek($this->last_result, 0)) 
			{
				return $this->SetError();
			} 
			else 
			{
				$members = array();
				//while($member = mysql_fetch_object($this->last_result)){
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
			return $this->SetError("No query results exist", -1);
		}
	}

	/**
	 * Frees memory used by the query results and returns the function result.
	 *
	 * @warning It is an (non-fatal) error to Release() a query result 
	 *          more than once.
	 *
	 * @param resource $result (Optional) the result originally returned 
	 *                 by any previous SQL query.
	 * @return boolean Returns TRUE on success or FALSE on failure
	 */
	public function Release($result = null) 
	{
		$this->ResetError();
		if (!is_resource($result))
		{
			$result = $this->last_result;
		}
		if (! $this->last_result) 
		{
			$success = true;
		} 
		else 
		{
			$success = @mysql_free_result($this->last_result);
			if (! $success) $this->SetError();
		}
		return $success;
	}

	/**
	 * Clears the internal variables from any error information
	 *
	 */
	private function ResetError() 
	{
		$this->error_desc = '';
		$this->error_number = 0;
	}
	
	/**
	 * Reads the current row and returns contents as a
	 * PHP object or returns false on error
	 *
	 * @param integer $optional_row_number (Optional) Use to specify a row
	 * @return object PHP object or FALSE on error
	 */
	public function Row($optional_row_number = null) 
	{
		$this->ResetError();
		if (! $this->last_result) 
		{
			return $this->SetError("No query results exist", -1);
		} 
		elseif ($optional_row_number === null) 
		{
			if (($this->active_row) > $this->RowCount()) 
			{
				return $this->SetError("Cannot read past the end of the records", -1);
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
				return $this->SetError("Row number is greater than the total number of rows", -1);
			} 
			else 
			{
				$this->active_row = $optional_row_number;
				$this->Seek($optional_row_number);
			}
		}
		$row = mysql_fetch_object($this->last_result);
		if (! $row) 
		{
			return $this->SetError();
		} 
		else 
		{
			return $row;
		}
	}

	/**
	 * Reads the current row and returns contents as an
	 * array or returns false on error
	 *
	 * @param integer $optional_row_number (Optional) Use to specify a row
	 * @param integer $resultType (Optional) The type of array
	 *                Values can be: MYSQL_ASSOC, MYSQL_NUM, MYSQL_BOTH
	 * @return array Array that corresponds to fetched row or FALSE if no rows
	 */
	public function RowArray($optional_row_number = null, $resultType = MYSQL_ASSOC) 
	{
		$this->ResetError();
		if (! $this->last_result) 
		{
			return $this->SetError("No query results exist", -1);
		} 
		elseif ($optional_row_number === null) 
		{
			if (($this->active_row) > $this->RowCount()) 
			{
				return $this->SetError("Cannot read past the end of the records", -1);
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
				return $this->SetError("Row number is greater than the total number of rows", -1);
			} 
			else 
			{
				$this->active_row = $optional_row_number;
				$this->Seek($optional_row_number);
			}
		}
		$row = mysql_fetch_array($this->last_result, $resultType);
		if (! $row) 
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
	 * @return integer Row count or FALSE on error
	 */
	public function RowCount() 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		elseif (! $this->last_result) 
		{
			return $this->SetError("No query results exist", -1);
		} 
		else 
		{
			$result = @mysql_num_rows($this->last_result);
			if (! $result) 
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
	 * @param integer $row_number Row number
	 * @return object Fetched row as PHP object on success or FALSE on error
	 */
	public function Seek($row_number) 
	{
		$this->ResetError();
		$row_count = $this->RowCount();
		if (! $row_count) 
		{
			return false;
		} 
		elseif ($row_number >= $row_count) 
		{
			return $this->SetError("Seek parameter is greater than the total number of rows", -1);
		} 
		else 
		{
			$this->active_row = $row_number;
			$result = mysql_data_seek($this->last_result, $row_number);
			if (! $result) 
			{
				return $this->SetError();
			} 
			else 
			{
				$record = mysql_fetch_row($this->last_result);
				if (! $record) 
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
	 * @return integer Current row number
	 */
	public function SeekPosition() 
	{
		return $this->active_row;
	}

	/**
	 * Selects a different database and character set
	 *
	 * @param string $database Database name
	 * @param string $charset (Optional) Character set (i.e. utf8)
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function SelectDatabase($database, $charset = "") 
	{
		if (! $charset) $charset = $this->db_charset;
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		}
		if (! mysql_select_db($database, $this->mysql_link)) 
		{
			return $this->SetError();
		} 
		else 
		{
			if (strlen($charset) > 0) 
			{
				if (! mysql_query("SET CHARACTER SET '{$charset}'", $this->mysql_link)) 
				{
					return $this->SetError();
				}
			}
		}
		return true;
	}

	/**
	 * Gets rows in a table based on a WHERE filter
	 *
	 * @param string $tableName The name of the table
	 * @param array $whereArray (Optional) An associative array containing the
	 *                          column names as keys and values as data. The
	 *                          values must be SQL ready (i.e. quotes around
	 *                          strings, formatted dates, ect)
	 * @param array/string $columns (Optional) The column or list of columns to select
	 * @param array/string $sortColumns (Optional) Column or list of columns to sort by
	 * @param boolean $sortAscending (Optional) TRUE for ascending; FALSE for descending
	 *                               This only works if $sortColumns are specified
	 * @param integer/string $limit (Optional) The limit of rows to return
	 * @return boolean Returns records on success or FALSE on error
	 */
	public function SelectRows($tableName, $whereArray = null, $columns = null,
							   $sortColumns = null, $limit = null) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			$sql = $this->BuildSQLSelect($tableName, $whereArray,
					$columns, $sortColumns, $limit);
			if (!is_string($sql)) return false;
			// Execute the UPDATE
			if (! $this->Query($sql)) 
			{
				return false;
			}
			return $this->last_result;
		}
	}

	/**
	 * Retrieves all rows in a specified table
	 *
	 * @param string $tableName The name of the table
	 * @return boolean Returns records on success or FALSE on error
	 */
	public function SelectTable($tableName) 
	{
		return $this->SelectRows($tableName);
	}

	/**
	 * Sets the local variables with the first error information
	 *
	 * @param string $errorMessage The error description
	 * @param integer $errorNumber The error number
	 */
	private function SetError($errorMessage = "", $errorNumber = 0) 
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
			throw new Exception($this->error_desc);
		}
		return false; // always return 'false' which is used as an error marker throughout.
	}

	/**
	 * [STATIC] Converts a boolean into a formatted TRUE or FALSE value of choice
	 *
	 * @param mixed $value value to analyze for TRUE or FALSE
	 * @param mixed $trueValue value to use if TRUE
	 * @param mixed $falseValue value to use if FALSE
	 * @param string $datatype Use SQLVALUE constants or the strings:
	 *                          string, text, varchar, char, boolean, bool,
	 *                          Y-N, T-F, bit, date, datetime, time, integer,
	 *                          int, number, double, float
	 * @return string SQL formatted value of the specified data type on success or FALSE on error
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
	 * [STATIC] Returns string suitable for SQL
	 *
	 * @param string $value
	 * @return string SQL formatted value
	 */
	static public function SQLFix($value) 
	{
		return @mysql_real_escape_string($value);
	}

	/**
	 * [STATIC] Returns MySQL string as normal string
	 *
	 * @param string $value
	 * @return string
	 *
	 * @warning Do NOT use on columns returned by a database query: such data has already
	 *          been adequately processed by MySQL itself.
	 *          The only probable place where the SQLUnfix() method MAY be useful is when
	 *          DIRECTLY accessing strings produced by the SQLValue() method.
	 */
	static public function SQLUnfix($value)
	{
		return @stripslashes($value);
	}

	/**
	 * [STATIC] Formats any value into a string suitable for SQL statements
	 * (NOTE: Also supports data types returned from the gettype function)
	 *
	 * @param mixed $value Any value of any type to be formatted to SQL
	 * @param string $datatype Use SQLVALUE constants or the strings:
	 *                          string, text, varchar, char, boolean, bool,
	 *                          Y-N, T-F, bit, date, datetime, time, integer,
	 *                          int, number, double, float
	 * @return string
	 */
	static public function SQLValue($value, $datatype = self::SQLVALUE_TEXT) 
	{
		$return_value = "";

		switch (strtolower(trim($datatype))) 
		{
		case "text":
		case "string":
		case "varchar":
		case "char":
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
					$return_value = "NULL";
				}
			} 
			else 
			{
				if (get_magic_quotes_gpc()) 
				{
					$strvalue = stripslashes($strvalue);
				}
				$return_value = "'" . self::SQLFix($strvalue) . "'";
			}
			break;
				
		case "enum":
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
				$return_value = "NULL";
			}
			break;
				
		case "number":
		case "integer":
		case "int":
			if (is_numeric($value)) 
			{
				$return_value = intval($value);
			} 
			else 
			{
				$return_value = "NULL";
			}
			break;
				
		case "double":
		case "float":
			if (is_numeric($value)) 
			{
				$return_value = "'" . floatval($value) . "'"; // Play it safe; add quotes around the value anyway.
			} 
			else 
			{
				$return_value = "NULL";
			}
			break;
				
		case "boolean":  //boolean to use this with a bit field
		case "bool":
		case "bit":
			if (self::GetBooleanValue($value)) 
			{
			   $return_value = "'1'";
			} 
			else 
			{
			   $return_value = "'0'";
			}
			break;
				
		case "y-n":  //boolean to use this with a char(1) field
			if (self::GetBooleanValue($value)) 
			{
				$return_value = "'Y'";
			} 
			else 
			{
				$return_value = "'N'";
			}
			break;
				
		case "t-f":  //boolean to use this with a char(1) field
			if (self::GetBooleanValue($value)) 
			{
				$return_value = "'T'";
			} 
			else 
			{
				$return_value = "'F'";
			}
			break;
				
		case "date":
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
				$return_value = "NULL";
			}
			break;
				
		case "datetime":
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
				$return_value = "NULL";
			}
			break;
				
		case "time":
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
				$return_value = "NULL";
			}
			break;
				
		case "null":
			$return_value = "NULL";
			break;
				
		default:
			exit("ERROR: Invalid data type specified in SQLValue method");
		}
		return $return_value;
	}

	/**
	 * Returns last measured duration (time between TimerStart and TimerStop)
	 *
	 * @param integer $decimals (Optional) The number of decimal places to show
	 * @return Float Microseconds elapsed
	 */
	public function TimerDuration($decimals = 4) 
	{
		return number_format($this->time_diff, $decimals);
	}

	/**
	 * Starts time measurement (in microseconds)
	 */
	public function TimerStart() 
	{
		$parts = explode(" ", microtime());
		$this->time_diff = 0;
		$this->time_start = $parts[1].substr($parts[0],1);
	}

	/**
	 * Stops time measurement (in microseconds)
	 */
	public function TimerStop() 
	{
		$parts  = explode(" ", microtime());
		$time_stop = $parts[1].substr($parts[0],1);
		$this->time_diff  = ($time_stop - $this->time_start);
		$this->time_start = 0;
	}

	/**
	 * Starts a transaction
	 *
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function TransactionBegin() 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			if (! $this->in_transaction) 
			{
				if (! mysql_query("START TRANSACTION", $this->mysql_link)) 
				{
					return $this->SetError();
				} 
				else 
				{
					$this->in_transaction = true;
					return true;
				}
			} 
			else 
			{
				return $this->SetError("Already in transaction", -1);
			}
		}
	}

	/**
	 * Ends a transaction and commits the queries
	 *
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function TransactionEnd() 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			if ($this->in_transaction) 
			{
				if (! mysql_query("COMMIT", $this->mysql_link)) 
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
				return $this->SetError("Not in a transaction", -1);
			}
		}
	}

	/**
	 * Rolls the transaction back
	 *
	 * @return boolean Returns TRUE on success or FALSE on failure
	 */
	public function TransactionRollback() 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			if(! mysql_query("ROLLBACK", $this->mysql_link)) 
			{
				return $this->SetError("Could not rollback transaction", -1);
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
	 * @param string $tableName The name of the table
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function TruncateTable($tableName) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			$sql = "TRUNCATE TABLE `" . self::SQLFix($tableName) . "`";
			return !!$this->Query($sql);
		}
	}

	/**
	 * Updates rows in a table based on a WHERE filter
	 * (can be just one or many rows based on the filter)
	 *
	 * @param string $tableName The name of the table
	 * @param array $valuesArray An associative array containing the column
	 *                            names as keys and values as data. The values
	 *                            must be SQL ready (i.e. quotes around
	 *                            strings, formatted dates, ect)
	 * @param array $whereArray (Optional) An associative array containing the
	 *                           column names as keys and values as data. The
	 *                           values must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, ect). If not specified
	 *                           then all values in the table are updated.
	 * @return boolean Returns TRUE on success or FALSE on error
	 */
	public function UpdateRows($tableName, $valuesArray, $whereArray = null) 
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
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
	 * @return array Returns an array of statistics values on success or FALSE on error.
	 */
	public function GetStatistics()
	{
		$this->ResetError();
		if (! $this->IsConnected()) 
		{
			return $this->SetError("No connection", -1);
		} 
		else 
		{
			$result = mysql_stat($this->mysql_link);
			if (empty($result))
			{
				$this->SetError("Failed to obtain database statistics", -1); // do NOT return to caller yet!
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
?>