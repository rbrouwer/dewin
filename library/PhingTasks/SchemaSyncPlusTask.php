<?php

require_once 'phing/Task.php';

class SchemaSyncPlusTask extends Task {

	protected $outputDirectory;
	protected $logDirectory;
	protected $tag;
	protected $host;
	protected $referenceHost;
	protected $port;
	protected $referencePort;
	protected $user;
	protected $referenceUser;
	protected $password;
	protected $referencePassword;
	protected $database;
	protected $referenceDatabase;
	protected $syncAutoIncrement = false;
	protected $syncComments = false;
	protected $patchScriptPathProperty;
	protected $rollbackScriptPathProperty;
	protected $dbInSyncProperty;

	/**
	 * Set the directory where SchemaSync will output its patch and rollback SQL file.
	 * @param string $outputDirectory
	 */
	public function setOutputDirectory($outputDirectory) {
		$this->outputDirectory = $outputDirectory;
	}

	/**
	 * Set the directory where SchemaSync will output its log file.
	 * @param string $logDirectory
	 */
	public function setLogDirectory($logDirectory) {
		$this->logDirectory = $logDirectory;
	}

	/**
	 * Tag the migration scripts as <database>_<tag>.
	 * Valid characters include [A-Za-z0-9-_]
	 * @param string $tag
	 */
	public function setTag($tag) {
		$this->tag = $tag;
	}

	/**
	 * Set the host of the target server
	 * @param string $host
	 */
	public function setHost($host) {
		$this->host = $host;
	}

	/**
	 * Set the host of the reference server
	 * @param string $referenceHost
	 */
	public function setReferenceHost($referenceHost) {
		$this->referenceHost = $referenceHost;
	}

	/**
	 * Set the port of the targetreference server.
	 * @param int $port
	 */
	public function setPort($port) {
		$this->port = $port;
	}

	/**
	 * Set the port of the reference server.
	 * @param int $referencePort
	 */
	public function setReferencePort($referencePort) {
		$this->referencePort = $referencePort;
	}

	/**
	 * Set the username of the target server
	 * @param string $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Set the username of the reference server
	 * @param string $referenceUser
	 */
	public function setReferenceUser($referenceUser) {
		$this->referenceUser = $referenceUser;
	}

	/**
	 * Set the password of the target server
	 * @param string $password
	 */
	public function setPassword($password) {
		$this->password = $password;
	}

	/**
	 * Set the password of the reference server
	 * @param string $referencePassword
	 */
	public function setReferencePassword($referencePassword) {
		$this->referencePassword = $referencePassword;
	}

	/**
	 * Set the database of the target server
	 * @param string $database
	 */
	public function setDatabase($database) {
		$this->database = $database;
	}

	/**
	 * Set the database of the reference server
	 * @param string $referenceDatabase
	 */
	public function setReferenceDatabase($referenceDatabase) {
		$this->referenceDatabase = $referenceDatabase;
	}

	/**
	 * sync the AUTO_INCREMENT value for each table
	 * @param boolean $syncAutoIncrement
	 */
	public function setSyncAutoIncrement($syncAutoIncrement) {
		$this->syncAutoIncrement = (boolean) $syncAutoIncrement;
	}

	/**
	 * sync the COMMENT field for all tables AND columns
	 * @param boolean $syncComments
	 */
	public function setSyncComments($syncComments) {
		$this->syncComments = (boolean) $syncComments;
	}

	/**
	 * The path of the patch script is written to this property.
	 * @param string $patchScriptPathProperty
	 */
	public function setPatchScriptPathProperty($patchScriptPathProperty) {
		$this->patchScriptPathProperty = $patchScriptPathProperty;
	}

	/**
	 * The path of the rollback script is written to this property.
	 * @param string $rollbackScriptPathProperty
	 */
	public function setRollbackScriptPathProperty($rollbackScriptPathProperty) {
		$this->rollbackScriptPathProperty = $rollbackScriptPathProperty;
	}

	/**
	 * True is written to this property, if the databases are different.
	 * @param string $dbInSyncProperty
	 */
	public function setDbInSyncProperty($dbInSyncProperty) {
		$this->dbInSyncProperty = $dbInSyncProperty;
	}

		
	/**
	 * Ensures that correct parameters were passed in.
	 *
	 * @return void
	 * @throws BuildException
	 */
	protected function checkParams() {
		if (null === $this->host || null === $this->referenceHost) {
			throw new BuildException('Please provide 2 hosts for database acccess!');
		}

		if (null === $this->user) {
			throw new BuildException('Please provide a username to access the database!');
		}

		if (null === $this->referenceUser) {
			throw new BuildException('Please provide a username to access the reference database!');
		}

		if (null === $this->user) {
			throw new BuildException('Please provide a database name to access the database!');
		}

		if (null === $this->referenceUser) {
			throw new BuildException('Please provide a database name to access the reference database!');
		}

		if (null === $this->outputDirectory) {
			throw new BuildException('Please provide an output directory!');
		}

		if (!is_dir($this->outputDirectory)) {
			throw new BuildException('The output directory does not exist!');
		}

		if (null !== $this->logDirectory && !is_dir($this->logDirectory)) {
			throw new BuildException('The log directory does not exist!');
		}

		if (!preg_match('/^[A-Za-z0-9-_]*$/', $this->tag)) {
			throw new BuildException('The tag contains invalid characters!');
		}
	}

	/**
	 * Creates the url schema sync expects.
	 * @param string $user The username to be used to access the database.
	 * @param string $password The password to be used to access the database.
	 * @param string $host The host of the database server.
	 * @param string $port The port of the database server.
	 * @param string $database The database to compare.
	 * @return string The url which schemasync expect.
	 */
	protected function createUrl($user, $password, $host, $port, $database) {
		$url = 'mysql://' . $user;
		$url .= ($password !== null) ? ':' . $password : '';
		$url .= '@' . $host;
		$url .= ($port !== null) ? ':' . $port : '';
		$url .= '/' . $database;

		return $url;
	}

	/**
	 * Connects to a mysql database using the details provided.
	 * @param string $user The username to be used to access the database.
	 * @param string $password The password to be used to access the database.
	 * @param string $host The host of the database server.
	 * @param string $port The port of the database server.
	 * @param string $database The database to compare.
	 * @return \PDO
	 * @throws BuildException
	 */
	protected function connect($user, $password, $host, $port, $database) {
		if (null !== $port) {
			$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database;
		} else {
			$dsn = 'mysql:host=' . $host . ';dbname=' . $database;
		}
		try {
			$db = new PDO($dsn, $user, $password);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			throw new BuildException('PDO Connection failed: ' . $e->getMessage());
		}
		return $db;
	}

	/**
	 * Get the views of a database.
	 * @param \PDO $db The PDO instance to be used.
	 * @param string $database The database name to return the views for.
	 * @return array An array with the table names of the views.
	 * @throws BuildException
	 */
	protected function getViews($db, $database) {
		$views = array();
		try {

			$sql = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = :database';

			$stmt = $db->prepare($sql);

			$stmt->bindParam(':database', $database, PDO::PARAM_STR);
			$stmt->execute();

			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$views[] = $row['TABLE_NAME'];
			}
		} catch (PDOException $e) {
			throw new BuildException('PDO Query failed: ' . $e->getMessage());
		}

		return $views;
	}

	/**
	 * Gets the SQL statements which are used to create these views
	 * @param \PDO $db The PDO instance to be used.
	 * @param array $views An array with table names of views.
	 * @return array An associative array with the table names of views as key. The value is another associative array containing TABLE_NAME and CREATE_VIEW.
	 * @throws BuildException
	 */
	protected function getCreateView($db, $views) {
		$results = array();
		try {
			foreach ($views as $viewName) {
				//Cannot bind view name... binding it escapes it, which makes mysql angry.
				$sql = 'SHOW CREATE VIEW ' . $viewName;

				$stmt = $db->prepare($sql);

				$stmt->execute();

				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$result = array();
					$result['TABLE_NAME'] = $viewName;
					$result['CREATE_VIEW'] = $row['Create View'];
					$results[$viewName] = $result;
				}
			}
		} catch (PDOException $e) {
			throw new BuildException('PDO Query failed: ' . $e->getMessage());
		}
		return $results;
	}

	/**
	 * Reads the query used to create the view and returns it is pieces.
	 * @param array $views The associative array as created by the getCreateView function.
	 * @return array An associative array with the table names of views as key. The value is another associative array containing ALGORITHM, CHECK_OPTION and VIEW_DEFINITION.
	 */
	protected function processDefinition($views) {
		foreach ($views as $viewName => $view) {
			if (preg_match('/^CREATE (ALGORITHM=(UNDEFINED|MERGE|TEMPTABLE) | )(DEFINER=(`(.*?)`@`(.*?)`|CURRENT_USER) | )(SQL SECURITY (DEFINER|INVOKER) | )VIEW `(.*?)` AS (.*?)( WITH (CASCADED |LOCAL |)CHECK OPTION|)$/', $view['CREATE_VIEW'], $matches)) {
				if ($matches['1'] !== ' ') {
					$views[$viewName]['ALGORITHM'] = $matches['2'];
				}
				if ($matches['11']) {
					$views[$viewName]['CHECK_OPTION'] = $matches['12'];
				}
				$views[$viewName]['VIEW_DEFINITION'] = $matches['10'];
			}
		}
		return $views;
	}

	/**
	 * Compares two arrays of views and creates the statements needed to change target into reference.
	 * @param array $referenceViews The associative array as created by the processDefinition function.
	 * @param array $targetViews The associative array as created by the processDefinition function.
	 * @return array An array containing SQL statements.
	 */
	protected function compareViews($referenceViews, $targetViews) {
		$changes = array();
		
		// Find missing views
		$missingViews = array_diff(array_keys($referenceViews), array_keys($targetViews));
		foreach ($missingViews as $missingView) {
			// Create Create statements for the missing views.
			$change = $this->createViewStatement($referenceViews[$missingView], 'CREATE');
			if ($change) {
				$changes[] = $change;
			}
			unset($referenceViews[$missingView]);
		}

		// Find unexpected views
		$unexpectedViews = array_diff(array_keys($targetViews), array_keys($referenceViews));
		foreach ($unexpectedViews as $unexpectedView) {
			// Create drop statements for the unexpected views.
			$change = $this->dropView($targetViews[$unexpectedView]);
			if ($change) {
				$changes[] = $change;
			}
			unset($targetViews[$unexpectedView]);
		}

		// The remaining views exists on both sides.
		foreach ($referenceViews as $view => $referenceView) {
			// Technically I could merge the next if statements into 1 and optimise that...
			// But this is far easier to read.
			// View is changed if view definition are different.
			if ($referenceView['VIEW_DEFINITION'] !== $targetViews[$view]['VIEW_DEFINITION']) {
				$change = $this->createViewStatement($referenceViews[$view], 'ALTER');
				if ($change) {
					$changes[] = $change;
				}
				continue;
			}
			
			// If algorithms are set on just 1 side, the views are different.
			if (isset($referenceView['ALGORITHM']) xor isset($targetViews[$view]['ALGORITHM'])) {
				$change = $this->createViewStatement($referenceViews[$view], 'ALTER');
				if ($change) {
					$changes[] = $change;
				}
				continue;
			// If algorithms are set on both and are different, the views are different.
			} elseif (isset($referenceView['ALGORITHM']) && isset($targetViews[$view]['ALGORITHM']) && $referenceView['ALGORITHM'] !== $targetViews[$view]['ALGORITHM']) {
				$change = $this->createViewStatement($referenceViews[$view], 'ALTER');
				if ($change) {
					$changes[] = $change;
				}
				continue;
			}
			
			// If check options are set on just 1 side, the views are different.
			if (isset($referenceView['CHECK_OPTION']) xor isset($targetViews[$view]['CHECK_OPTION'])) {
				$change = $this->createViewStatement($referenceViews[$view], 'ALTER');
				if ($change) {
					$changes[] = $change;
				}
				continue;
			// If check options are set on both and are different, the views are different.
			} elseif (isset($referenceView['CHECK_OPTION']) && isset($targetViews[$view]['CHECK_OPTION']) && $referenceView['CHECK_OPTION'] !== $targetViews[$view]['CHECK_OPTION']) {
				$change = $this->createViewStatement($referenceViews[$view], 'ALTER');
				if ($change) {
					$changes[] = $change;
				}
				continue;
			}
		}
		
		return $changes;
	}

	/**
	 * Creates the SQL statement to patch to or create the supplied view. 
	 * @param array $view An item of the associative array as created by the processDefinition function.
	 * @param string $operation Accepts ALTER or CREATE or CREATE OR REPLACE.
	 * @return string A SQL statement.
	 */
	protected function createViewStatement($view, $operation) {
		$statement = $operation . ' ';
		if (isset($view['ALGORITHM'])) {
			$statement .= 'ALGORITHM = ' . $view['ALGORITHM'] . ' ';
		}
		$statement .= 'VIEW ' . $view['TABLE_NAME'] . ' AS ' . $view['VIEW_DEFINITION'];
		if (isset($view['CHECK_OPTION'])) {
			$statement .= ' WITH ' . $view['CHECK_OPTION'] . 'CHECK OPTION';
		}
		$statement .= ';';
		return $statement;
	}

	/**
	 * Creates a drop view SQL statement for the supplied view
	 * @param array $view An item of the associative array as created by the processDefinition function.
	 * @return string A SQL statement.
	 */
	protected function dropView($view) {
		$change = 'DROP VIEW IF EXISTS ' . $view['TABLE_NAME'].';';
		return $change;
	}
	
	/**
	 * 
	 * @param string $file The path to the file to append to. If it doesn't exist a new file will be created in the output directory
	 * @param string $database The database to use in the filename, incase it needs to be created.
	 * @param boolean $tag The tag to use in the filename, incase it needs to be created.
	 * @param string $type The type to use in the filename, incase it needs to be created. Accepts patch or rollback
	 * @param type $sqlArray Array with SQL statements to append.
	 * @return string The path to the file.
	 */
	protected function writeToFile($file, $database, $tag, $type, $sqlArray) {
		if ($file === null || !is_file($file)) {
			$file = $this->outputDirectory;
			$file .= (substr($this->outputDirectory, -1) === '/') ? '' : '/';
			$file .= $database;
			if ($tag !== null) {
				$file .= '_'.$tag;
			}
			$file .= '.'.date('Ymd').'.';
			$file .= $type.'.sql';
		}
		$handle = fopen($file, 'a');
		fwrite($handle, "\n".'-- Schema Sync PLUS'."\n".implode("\n", $sqlArray));
		fclose($handle);
		return $file;
	}

	/**
	 * Starts the Schema Sync Plus task.
	 * @return type
	 * @throws BuildException
	 */
	public function main() {
		$this->checkParams();
		$command = 'schemasync ';

		if ($this->syncAutoIncrement) {
			$command .= '-a ';
		}

		if ($this->syncComments) {
			$command .= '-c ';
		}

		if ($this->tag !== null) {
			$command .= '--tag=' . escapeshellarg($this->tag) . ' ';
		}

		$command .= '--output-directory=' . escapeshellarg($this->outputDirectory) . ' ';

		if ($this->logDirectory !== null) {
			$command .= '--log-directory=' . escapeshellarg($this->logDirectory) . ' ';
		}
		$command .= $this->createUrl($this->referenceUser, $this->referencePassword, $this->referenceHost, $this->referencePort, $this->referenceDatabase) . ' ';

		$command .= $this->createUrl($this->user, $this->password, $this->host, $this->port, $this->database);

		$this->log($command);
		$output = shell_exec($command.' 2>&1');
		if ($output) {
			$dbInSync = false;
			$patchScript = null
;			$revertScript = null;
			if(preg_match('` mysql://(.*?)/(.*?) and mysql://(.*?)/(.*?) were in sync.`', $output)) {
				$dbInSync = true;
			} elseif(preg_match('/Patch Script: (.*)\nRevert Script: (.*)/', $output, $matches)) {
				$patchScript = $matches['1'];
				$revertScript = $matches['2'];
			} else {
				throw new BuildException('SchemaSync gave the follow output which is likely an error: ' . $output);
			}
		}
		
		// Connect for view comparison.
		$ref = $this->connect($this->referenceUser, $this->referencePassword, $this->referenceHost, $this->referencePort, $this->referenceDatabase);
		$target = $this->connect($this->user, $this->password, $this->host, $this->port, $this->database);

		// Get all views from that database.
		$refViews = $this->getViews($ref, $this->referenceDatabase);
		// Get SHOW CREATE VIEW.
		$refViews = $this->getCreateView($ref, $refViews);
		$refViews = $this->processDefinition($refViews);
		
		// Same for target server...
		$targetViews = $this->getViews($target, $this->database);
		$targetViews = $this->getCreateView($target, $targetViews);
		$targetViews = $this->processDefinition($targetViews);

		// Do comparison.
		$changes = $this->compareViews($refViews, $targetViews);
		// If comparing 1 way, makes the changes. The reverse compare will be the rollback.
		$rollback = $this->compareViews($targetViews, $refViews);
		
		if ($changes && $rollback) {
			$dbInSync = false;
			//Append changes to file.
			$patchScript = $this->writeToFile($patchScript, $this->database, $this->tag, 'patch', $changes);
			$revertScript = $this->writeToFile($revertScript, $this->database, $this->tag, 'rollback', $rollback);
		}
		
		
		// Set final properties.
		if ($this->patchScriptPathProperty) {
			$this->project->setProperty($this->patchScriptPathProperty, $patchScript);
		}
		
		if ($this->rollbackScriptPathProperty) {
			$this->project->setProperty($this->rollbackScriptPathProperty, $revertScript);
		}
		
		if ($this->dbInSyncProperty) {
			if ($dbInSync) {
				$this->project->setProperty($this->dbInSyncProperty, 'true');
			} else {
				$this->project->setProperty($this->dbInSyncProperty, 'false');
			}
		}
		
		return;
	}

}