<?php

class Model_SqlPatchStatement {

	/**
	 * The sql statement of a patch file.
	 * @var string
	 */
	private $sql;

	/**
	 * Contains regex to map description to statements
	 * @var array 
	 */
	private $createMap = array(
			'/^\s*ALTER\s+(IGNORE\s+|)TABLE\s+(.*?)\s+ADD(|\s+(.*));\s*$/i' => 'Alter table $2.',
			'/^\s*CREATE\s+(DATABASE|SCHEMA)\s+(IF NOT EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Create database $3.',
			'/^\s*CREATE\s+(|DEFINER\s*=\s*(\w*|CURRENT_USER)\s+)EVENT\s+(IF NOT EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Create event $4.',
			'/^\s*CREATE\s+(|UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)INDEX\s+(.*?)(|\s+(.*));\s*$/i' => 'Create index $2.',
			'/^\s*CREATE\s+(|DEFINER\s*=\s*(\w*|CURRENT_USER)\s+)FUNCTION\s+(.*?)(|\s+(.*));\s*$/i' => 'Create function $3.',
			'/^\s*CREATE\s+(|DEFINER\s*=\s*(\w*|CURRENT_USER)\s+)PROCEDURE\s+(.*?)(|\s+(.*));\s*$/i' => 'Create procedure $3.',
			'/^\s*CREATE\s+(|TEMPORARY\s+)TABLE\s+(IF NOT EXISTS\s+|)(.*?)\s+(.*);\s*$/i' => 'Create table $3.',
			'/^\s*CREATE\s+(|DEFINER\s*=\s*(\w*|CURRENT_USER)\s+)TRIGGER\s+(.*?)(|\s+(.*));\s*$/i' => 'Create trigger $4.',
			'`^\s*CREATE\s+(ALGORITHM\s*=\s*(UNDEFINED|MERGE|TEMPTABLE)\s+|)(\*/\s*\/\*!(\d+?)\s+|)(DEFINER\s*=\s*((.*?)|CURRENT_USER)\s+|)(SQL\s+SECURITY\s+(DEFINER|INVOKER)\s+|)VIEW (.*?) AS (.*);\s*$`i' => 'Create view $10',
			'/^\s*INSERT\s+INTO\s+(.*?)(|\s+(.*));\s*$/i' => 'Insert data into table $1.',
	);

	/**
	 * Contains regex to map description to statements
	 * @var array
	 */
	private $map = array(
			'/^\s*ALTER\s+DATABASE\s*(.*?)\s+(DEFAULT|CHARACTER|COLLATE)\s+(.*)$/i' => 'Alter database $1.',
			'/^\s*ALTER\s+(|DEFINER\s*=\s*(\w*|CURRENT_USER)\s+)EVENT\s+(.*?)(|\s+(.*));\s*$/i' => 'Alter event $3.',
			'/^\s*ALTER\s+FUNCTION\s+(.*?)(|\s+(.*));\s*$/i' => 'Alter function $1.',
			'/^\s*ALTER\s+PROCEDURE\s+(.*?)(|\s+(.*));\s*$/i' => 'Alter procedure $1.',
			'/^\s*ALTER\s+SERVER\s+(.*?)(|\s+(.*));\s*$/i' => 'Alter server $1.',
			'/^\s*ALTER\s+(IGNORE\s+|)TABLE\s+(.*?)(|\s+(.*));\s*$/i' => 'Alter table $2.',
			'/^\s*DROP\s+(DATABASE|SCHEMA)\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop database $3.',
			'/^\s*DROP\s+EVENT\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop event $2.',
			'/^\s*DROP\s+FUNCTION\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop function $2.',
			'/^\s*DROP\s+INDEX\s+(\w*?)\s+ON\s+(.*?)(|\s+(.*));\s*/i' => 'Drop index $1 of table $2.',
			'/^\s*DROP\s+SERVER\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop server $2.',
			'/^\s*DROP\s+(TEMPORARY\s+|)TABLE\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop table $3.',
			'/^\s*DROP\s+TRIGGER\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop trigger $2.',
			'/^\s*DROP\s+VIEW\s+(IF\s+EXISTS\s+|)(.*?)(|\s+(.*));\s*$/i' => 'Drop view $2.',
			'/^\s*RENAME\s+TABLE\s+(\w*?)\s+TO\s+(.*?)(|\s+(.*));\s*$/i' => 'Rename table $1 to $2.',
			'/^\s*LOCK\s+TABLES\s+(.*?)(|\s+(.*));\s*$/i' => 'Lock table $1.',
	);

	/**
	 * Contains regex to map description to statements.
	 * Statements detected with this map will get visible: false
	 * @var array
	 */
	private $noShowMap = array(
			'/^\s*USE\s+(.*?)(|\s+(.*));\s*$/i' => 'Use $1.',
			'/^\s*UNLOCK\s+TABLES(|\s+(.*));\s*$/i' => 'Unlock tables.',
			'/^\s*SET\s+(.*?)\s*=\s*(.*?)(|\s+(.*));\s*$/i' => 'Set $1 to $2.',
			'/^\s*SET\s+(.*?)(|\s+(.*));\s*$/i' => 'Set $1.',
	);

	/**
	 * Should this statement be added to the sql add patch file.
	 * @var boolean
	 */
	private $isCreate;

	/**
	 * Should this statement be shown in the long list of statements.
	 * @var boolean
	 */
	private $visible;

	/**
	 * The description of this SQL statement.
	 * @var boolean
	 */
	private $description;

	/**
	 * @param string $sql 1 sql statement
	 */
	public function __construct($sql) {
		$this->sql = $sql;
		$this->visible = true;
		$this->description = $this->getDescription();
	}

	/**
	 * SQL statement
	 * @return string
	 */
	public function getSql() {
		return $this->sql;
	}

	/**
	 * Whenever this statements should be added to the add missing structure file.
	 * @return boolean
	 */
	public function getVisible() {
		return $this->visible;
	}
	
	/**
	 * Whenever this statements should be visible.
	 * @return boolean
	 */
	public function IsCreateStatement() {
		return $this->isCreate;
	}

	/**
	 * Changes a SQL statement to a description.
	 * @param array $map The map to use.
	 * @param string $subject The sql statement.
	 * @return string The description.
	 */
	private function executeMap($map, $subject) {
		return preg_replace(array_keys($map), array_values($map), $subject);
	}

	public function loadSQL($sql) {
		//Get the description of the statement
		$description = $this->executeMap($this->createMap, $sql);
		//Might not know what is it yet.
		if ($description == $sql) {
			//Get the description of the statement
			$description = $this->executeMap($this->map, $sql);
			//Might not know what is it yet.
			if ($description == $sql) {
				// Look it the statement is not really important...
				$description = $this->executeMap($this->noShowMap, $sql);
				// Hide not important statements
				if ($description != $sql) {
					$this->isCreate = false;
					$this->visible = false;
				} else {
					// Unrecognised statements have this description. 
					$description = 'Other SQL';
					$this->isCreate = false;
					$this->visible = true;
				}
			} else {
				$this->isCreate = false;
				$this->visible = true;
			}
		} else {
			$this->isCreate = true;
			$this->visible = true;
		}
		return $description;
	}

	/**
	 * Returns the description for a statement.
	 * @return string
	 */
	public function getDescription() {
		if ($this->description === null) {
			//Create arrays to hold pre- and surfixes.
			$prefixes = array();
			$surfixes = array();

			//Remove breaklines... Easier to preg match without those.
			$sql = str_replace("\n", '', $this->sql);
			//Detect conditional statements.
			while (preg_match('`(.*?)\/\*\!(\d+)\s+(.*?)\s*\*\/(.*?);\s*`i', $sql, $matches)) {
				//Add a surfix stating the statement is conditional.
				$surfixes[] = '[Conditional MySQL version: ' . $matches['2'] . ']';
				// Keep the actual statement
				$sql = $matches['1'].$matches['3'].$matches['4'] . ';';
			}
			
			$description = $this->loadSQL($sql);
			
			// Add pre- and surfixes to the description.
			if ($prefixes) {
				$description = implode(' ', $prefixes) . ' ' . $description;
			}

			if ($surfixes) {
				$description .= ' ' . implode(' ', $surfixes);
			}

			return $description;
		} else {
			return $this->description;
		}
	}

}