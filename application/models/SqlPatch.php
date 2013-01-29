<?php

/**
 * Does not support postgres.... Sorry!
 */
class Model_SqlPatch {

	/**
	 * Path to the sql file.
	 * @var string 
	 */
	private $sqlPath;

	/**
	 * Should a new file be created if the patch file does not exist.
	 * @var boolean 
	 */
	private $create;

	/**
	 * The delimiter for SQL statements.
	 * @var string
	 */
	private $delimiter;

	/**
	 * Bits of started SQL statement from previous lines.
	 * @var string
	 */
	private $sqlBacklog;

	/**
	 * The file handle.
	 * @var resource
	 */
	private $handle;

	/**
	 * Is counting completed.
	 * @var boolean
	 */
	private $counted;

	/**
	 * Ammount of SQL statements
	 * @var int
	 */
	private $counter;

	/**
	 * Pointer location (in SQL statements)
	 * @var int
	 */
	private $posSql;

	/**
	 * Removed 
	 * @var string
	 */
	private $thrash;

	/**
	 * 
	 * @param type $sqlPath
	 * @param type $createIfNonexistant
	 */
	public function __construct($sqlPath, $createIfNonexistant = true, $delimiter = ';') {
		// Set options
		$this->sqlPath = $sqlPath;
		$this->create = $createIfNonexistant;
		$this->delimiter = $delimiter;

		// Init counters
		$this->counted = false;
		$this->counter = 0;
		$this->posSql = 0;

		// Open the file.
		$this->handle = $this->openSqlPatchFile($this->sqlPath);
	}

	/**
	 * Returns the amount of SQL statements currently known.
	 * @return int
	 */
	public function getCounter() {
		return $this->counter;
	}

	/**
	 * Returns the current position of the filepointer in SQL statements.
	 * @return int
	 */
	public function getPosSql() {
		return $this->posSql;
	}

	/**
	 * Return all comments between the last returned statement and the statement before that.
	 * @return string
	 */
	public function getThrash() {
		return $this->thrash;
	}

	/**
	 * Returns the next query in this SQL file
	 * @return \Model_SqlPatchStatement|null Null if there is no next query otherwise a SqlPatchStatement
	 */
	public function nextQuery() {
		//Init vars and counters
		$sql = "";
		$this->thrash = '';
		$hasQuery = false;
		if (!$this->counted) {
			$this->counter++;
		}
		$this->posSql++;

		//Read line by line.
		while (($line = $this->readLine()) !== null) {
			//Skip empty lines or comments.
			if (($line != $this->delimiter) && preg_match('/^[ \t]*((--|\/\/|#).*|)$/', $line)) {
				$this->thrash .= $line;
				continue;
			}

			//Skip remarks
			if (strlen($line) > 4
							&& strtoupper(substr($line, 0, 4)) == "REM ") {
				$this->thrash .= $line;
				continue;
			}

			// MySQL supports defining new delimiters
			if (preg_match('/DELIMITER [\'"]?([^\'" $]+)[\'"]?/i', $line, $matches)) {
				$this->delimiter = $matches[1];
				continue;
			}

			if ($this->sqlBacklog !== "") {
				$sql = $this->sqlBacklog;
				$this->sqlBacklog = "";
			}

			$sql .= $line;

			// SQL defines "--" as a comment to EOL
			// and in Oracle it may contain a hint
			// so we cannot just remove it, instead we must end it
			if (strpos($line, "--") !== false) {
				$sql .= "\n";
			}

			//Split the line into parts that are not enclosed.
			$reg = "#((?:\"(?:\\\\.|[^\"])*\"?)+|'(?:\\\\.|[^'])*'?|" . preg_quote($this->delimiter) . ")#";
			$sqlParts = preg_split($reg, $sql, 0, PREG_SPLIT_DELIM_CAPTURE);
			$this->sqlBacklog = "";
			foreach ($sqlParts as $sqlPart) {
				// we always want to append a part. Do not want to lose a part of a query!
				$this->sqlBacklog .= $sqlPart;

				// We found a single (not enclosed by ' or ") delimiter, so we can use all stuff before the delim as the actual query
				if ($sqlPart === $this->delimiter) {
					$sql = $this->sqlBacklog;
					$this->sqlBacklog = "";
					$hasQuery = true;
				}
			}

			if ($hasQuery) {
				return new Model_SqlPatchStatement($sql);
			}
		}

		// Catch any statements not followed by ;
		if ($sql !== "") {
			return new Model_SqlPatchStatement($sql . ';');
		}

		//Compensate counters if none was found.
		if (!$this->counted) {
			$this->counter--;
		}
		$this->posSql--;
		$this->counted = true;
		return null;
	}

	/**
	 * Open or creates the SQL file.
	 * @param string $sqlPath Path to the file to be opened.
	 * @param boolean $readOnly Attempt to create a new file if the path does not exist.
	 * @return resource The file handle.
	 * @throws Exception
	 */
	private function openSqlPatchFile($sqlPath, $readOnly = true) {
		// Set mode to open the file.
		if ($readOnly) {
			$mode = 'r';
		} else {
			$mode = 'r+';
		}
		if (file_exists($sqlPath)) {
			if (is_readable($sqlPath)) {
				$handle = @fopen($sqlPath, $mode);
				if ($handle) {
					return $handle;
				} else {
					throw new Exception('SQL Patch file(' . $sqlPath . ') could not be opened.');
				}
			}
		} elseif ($this->create === true) {
			file_put_contents($sqlPath, '');
			$handle = @fopen($sqlPath, $mode);
			if ($handle) {
				return $handle;
			} else {
				throw new Exception('SQL Patch file(' . $sqlPath . ') could not be opened.');
			}
		} else {
			throw new Exception('SQL Patch file(' . $sqlPath . ') does not exist.');
		}
	}

	/**
	 * Reads one line from the file.
	 * @return string
	 * @throws Exception
	 */
	private function readLine() {
		if (($buffer = fgets($this->handle)) === false) {
			if (!feof($this->handle)) {
				throw new Exception('Failed to read a line from SQL Patch File');
			}
		} else {
			return $buffer;
		}
	}

	/**
	 * Gets the 'id'th statement from the file.
	 * @param int $id The id of the wanted statement
	 * @param boolean $restorePointerPos should the function return the filepoint to the original position.
	 * @return \Model_SqlPatchStatement|null Null if the query do not exist otherwise a SqlPatchStatement
	 */
	private function getSqlStatement($id, $restorePointerPos = true) {
		// Save current state
		if ($restorePointerPos) {
			$pos = ftell($this->handle);
			$posSQL = $this->posSql;
			$backlog = $this->sqlBacklog;
		}

		// Is the filepoint already past the wanted statement.
		if ($id < $this->posSql) {
			// If it is, reset position.
			$this->resetPosition();
		} else {
			// Lower id to compensate for already read statements.
			$id -= $this->posSql;
		}

		// Read id - 1 querys.
		for ($i = 1; $i < $id; $i++) {
			$this->nextQuery();
		}
		// Read the wanted statement
		$sql = $this->nextQuery();

		// Restore the state
		if ($restorePointerPos) {
			fseek($this->handle, $pos);
			$this->posSql = $posSQL;
			$this->sqlBacklog = $backlog;
		}

		//Return it
		return $sql;
	}

	/**
	 * Puts the filepointer to the start of the file and resets all attached variables.
	 */
	public function resetPosition() {
		fseek($this->handle, 0);
		$this->posSql = 0;
		$this->sqlBacklog = '';
		if (!$this->counted) {
			$this->counter = 0;
		}
	}

	/**
	 * Replaced the new SQL file with the only the statements in the sqlIds array.
	 * @param Array $sqlIds Array with SQL IDs. First SQL ID will be the first in the file.
	 * @param string $appendSql Any SQL that should be appended to the file.
	 * @throws Exception
	 */
	public function buildSqlPatchFile($sqlIds, $appendSql = null) {
		// Start at the start
		$this->resetPosition();

		// Get another file handle... Can't read and write
		$handle = $this->openSqlPatchFile($this->sqlPath . '.new', false);

		// Loop through the array
		foreach ($sqlIds as $sqlId) {
			// Get the needed SQL statement. Restore pointer if already past the statement. 
			$sql = $this->getSqlStatement($sqlId, ($sqlId < $this->posSql));
			// Write it, if there is a query
			if ($sql !== null) {
				fwrite($handle, $this->getThrash() . $sql->getSql());
			}
		}

		// Append SQL.
		if ($appendSql) {
			fwrite($handle, "\n" . $appendSql);
		}

		// Close handler
		fclose($handle);

		// Replace the SQL Patch file
		if (!rename($this->sqlPath . '.new', $this->sqlPath)) {
			unlink($this->sqlPath . '.new');
			throw new Exception('Cannot overwrite SQL Patch file.');
		}

		// Open the newly replaced file
		$this->handle = $this->openSqlPatchFile($this->sqlPath);
		// Reset position
		$this->counted = false;
		$this->resetPosition();
	}
	
	/**
	 * Replaced the new SQL file with the only the statements in the sqlIds array.
	 * @param Array $sqlIds Array with SQL IDs. First SQL ID will be the first in the file.
	 * @param string $appendSql Any SQL that should be appended to the file.
	 * @throws Exception
	 */
	public function buildAddMissingSqlPatchFile() {
		// Start at the start
		$this->resetPosition();

		// Get another file handle... Can't read and write
		$handle = $this->openSqlPatchFile($this->sqlPath . '.new', false);

		while (($stmt = $this->nextQuery())) {
			if ($stmt !== null && $stmt->IsCreateStatement()) {
				fwrite($handle, $this->getThrash() . $stmt->getSql());
			}
		}

		// Close handler
		fclose($handle);

		// Replace the SQL Patch file
		if (!rename($this->sqlPath . '.new', $this->sqlPath)) {
			unlink($this->sqlPath . '.new');
			throw new Exception('Cannot overwrite SQL Patch file.');
		}

		// Open the newly replaced file
		$this->handle = $this->openSqlPatchFile($this->sqlPath);
		// Reset position
		$this->counted = false;
		$this->resetPosition();
	}
	
	public function removePatch() {
		fclose($this->handle);
		unlink($this->sqlPath);
	}
	public function movePatch($path) {
		fclose($this->handle);
		if (!rename($this->sqlPath, $path)) {
			throw new Exception('Cannot move SQL Patch file.');
		} else {
			$this->sqlPath = $path;
			// Open the newly replaced file
			$this->handle = $this->openSqlPatchFile($this->sqlPath);
			// Reset position
			$this->resetPosition();
		}
	}

}
