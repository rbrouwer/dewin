<?php

class Model_Deployment extends RedBean_SimpleModel {

	/**
	 * The model of the recipe selected for this deployment
	 * @var Model_Recipe
	 */
	private $recipe;

	/**
	 * The array which contains all temporary deployment properties.
	 * @var array
	 */
	private $tempProperties;

	/**
	 * The array which contains all deployment properties.
	 * @var array 
	 */
	private $properties;

	/**
	 * Is the settings array populated.
	 * @var boolean 
	 */
	private $loadedProperties;

	/**
	 * Sets the deployment timestamp(can't do that later, because rollback sets it itself)
	 */
	public function dispense() {
		$bean = $this->unbox();
		$time = time();
		$bean->created = R::isoDateTime($time);
		$this->loadedProperties = false;
		$this->properties = array();
		$this->properties['deploymentDate'] = date('YmdHis', $time);
	}

	/**
	 * Updates the child properties with the changes made to the deployment
	 */
	public function update() {
		// When loaded, fully sync the property array.
		if ($this->loadedSettings !== false) {
			$properties = array();
			$ownProperties = $this->bean->ownProperty;
			foreach ($this->properties as $k => $v) {
				$found = false;
				foreach ($ownProperties as $property) {
					if ($property->name === $k) {
						$property->value = $v;
						$found = true;
						$properties[] = $property;
					}
				}
				if (!$found) {
					$property = R::dispense('property');
					$property->name = $k;
					$property->value = $v;
					$properties[] = $property;
				}
			}
			$this->bean->ownProperty = $properties;
			// When loaded, only add and modify properties, but do not delete any
		} elseif (!empty($this->properties)) {
			$ownProperties = $this->bean->ownProperty;
			foreach ($this->properties as $k => $v) {
				$found = false;
				foreach ($ownProperties as $property) {
					if ($property->name === $k) {
						$property->value = $v;
						$found = true;
						$this->bean->ownProperty[$property->id] = $property;
					}
				}
				if (!$found) {
					$property = R::dispense('property');
					$property->name = $k;
					$property->value = $v;
					$this->bean->ownProperty[] = $property;
				}
			}
		}
	}

	/**
	 * Preloads the deployment properties and saves them in an array
	 */
	public function open() {
		$this->properties = array();
		$this->loadedProperties = false;
	}

	private function loadProperties() {
		$ownProperties = $this->bean->ownProperty;
		foreach ($ownProperties as $property) {
			$this->properties[$property->name] = $property->value;
		}
		$this->loadedProperties = true;
	}

	/**
	 * Returns the model of the recipe selected for this deployment
	 * @return Model_Recipe
	 */
	public function getRecipe() {
		if ($this->recipe === null) {
			$bean = $this->unbox();
			$path = $bean->recipe;
			if (is_file($path)) {
				$this->recipe = new Model_Recipe(basename($path), dirname($path) . '/');
			} else {
				$path = basename($path);
				$config = Zend_Registry::get('config');
				$bsDir = $config->directories->buildscript;
				if (is_file($bsDir . $path)) {
					$this->recipe = new Model_Recipe(basename($path), dirname($path) . '/');
				}
			}
		}
		return $this->recipe;
	}

	/**
	 * Sets the model of the recipe for this deployment
	 * @param Model_Recipe $recipe
	 */
	public function setRecipe(Model_Recipe $recipe) {
		$this->recipe = $recipe;
		$bean = $this->unbox();
		$bean->recipe = $recipe->getPath();
		$bean->version = $recipe->getVersion();
	}

	/**
	 * Adds a process to this deployment which triggers the selected target.
	 * @param string $target
	 * @throws Exception
	 */
	public function queueTarget($target) {
		if ($this->recipe === null) {
			$this->getRecipe();
		}
		if ($this->recipe !== null) {
			if ($this->recipe->hasTarget($target)) {
				$process = R::dispense('process');
				$process->status = 'Queued';
				$process->target = $target;
				$process->command = realpath(APPLICATION_PATH.'/../tools/phing/bin/phing');
				$debugMode = $this->getRecipe()->getDebugMode();

				if ($debugMode <= 0) {
					$process->command .= ' -f "' . $this->getRecipe()->getPath() . '" ' . $target;
				} elseif ($debugMode == 1) {
					$process->command .= ' -verbose -f "' . $this->getRecipe()->getPath() . '" ' . $target;
				} else {
					$process->command .= ' -verbose -debug -f "' . $this->getRecipe()->getPath() . '" ' . $target;
				}
				$process->percent = 0;
				$bean = $this->unbox();
				$bean->ownProcess[] = $process;
			} else {
				throw new Exception('Target does not exist in recipe.');
			}
		} else {
			throw new Exception('Recipe of this deployment has not been set.');
		}
	}

	/**
	 * Returns the model of the processes of this deployment
	 * @return Model_process|null
	 */
	public function getProcesses() {
		$bean = $this->unbox();
		return $bean->with(' ORDER BY `id` ')->ownProcess;
	}

	/**
	 * Returns the model of the process of this deployment
	 * @return Model_process|null
	 */
	public function getNextProcess() {
		$bean = $this->unbox();
		$processes = $bean->with(' AND `status` = "Queued" ORDER BY `id` ASC LIMIT 1 ')->ownProcess;
		return end($processes);
	}

	/**
	 * Returns the model of the process of this deployment
	 * @return Model_process|null
	 */
	public function getRunningProcess() {
		$bean = $this->unbox();
		$processes = $bean->with(' AND `status` != "Queued" ORDER BY `id` DESC LIMIT 1 ')->ownProcess;
		return end($processes);
	}

	/**
	 * Implements set() function which splits up the variable to their own namespace 
	 * @param string $property
	 * @return boolean
	 */
	public function __set($property, $value) {
		if (strpos($property, 'source') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				$this->bean->source = $value;
			} else {
				$sourceInstance = $this->getSourceInstance();
				if ($sourceInstance !== null) {
					$sourceInstance->$property = $value;
					$this->bean->setMeta('tainted', true);
				}
			}
		} elseif (strpos($property, 'remote') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				$this->bean->target = $value;
			} else {
				$targetInstance = $this->getTargetInstance();
				if ($targetInstance !== null) {
					$targetInstance->$property = $value;
					$this->bean->setMeta('tainted', true);
				}
			}
			//Temporary properties are kept in the object, and not written to the database.
		} elseif (strpos($property, 'temp') === 0) {
			$property = lcfirst(substr($property, 4));
			$this->tempProperties[$property] = $value;
		} elseif (isset($this->bean->$property)) {
			$this->bean->$property = $value;
		} elseif (!isset($this->properties[$property]) || $this->properties[$property] !== $value) {
			$this->properties[$property] = $value;
			$this->bean->setMeta('tainted', true);
		}
	}

	/**
	 * Implements isset() function for use as an array.
	 * @param string $property
	 * @return boolean
	 */
	public function __isset($property) {
		$bean = $this->unbox();
		if (strpos($property, 'source') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				return isset($this->bean->source);
			} else {
				$sourceInstance = $this->getSourceInstance();
				$sourceServer = $this->getSourceServer();
				if ($sourceInstance !== null && $sourceServer !== null) {
					return (isset($sourceInstance->$property) || isset($sourceServer->$property));
				} elseif ($sourceInstance !== null) {
					return (isset($sourceInstance->$property));
				} else {
					return false;
				}
			}
		} elseif (strpos($property, 'remote') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				return isset($this->bean->target);
			} else {
				$targetInstance = $this->getTargetInstance();
				$targetServer = $this->getTargetServer();
				if ($targetInstance !== null && $targetServer !== null) {
					return (isset($targetInstance->$property) || isset($targetServer->$property));
				} elseif ($targetInstance !== null) {
					return (isset($targetInstance->$property));
				} else {
					return false;
				}
			}
			//Temporary properties are kept in the object, and not written to the database.
		} elseif (strpos($property, 'temp') === 0) {
			$property = lcfirst(substr($property, 4));
			return isset($this->tempProperties[$property]);
		} elseif (isset($this->bean->$property)) {
			return true;
		} else {
			if ($this->loadedProperties === false) {
				$this->loadProperties();
			}
			return isset($this->properties[$property]);
		}
	}

	/**
	 * Unsets a property. This method will load the property first using
	 * __get.
	 *
	 * @param  string $property property
	 *
	 * @return void
	 */
	public function __unset($property) {
		if (strpos($property, 'source') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				unset($this->bean->source);
			} else {
				unset($this->source->$property);
				$this->bean->setMeta('tainted', true);
			}
		} elseif (strpos($property, 'remote') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				unset($this->bean->target);
			} else {
				unset($this->remote->$property);
				$this->bean->setMeta('tainted', true);
			}
			//Temporary properties are kept in the object, and not written to the database.
		} elseif (strpos($property, 'temp') === 0) {
			$property = lcfirst(substr($property, 4));
			unset($this->tempProperties[$property]);
		} else {
			if (isset($this->properties[$property])) {
				if ($this->loadedProperties === false) {
					$this->loadProperties();
				}
				unset($this->properties[$property]);
				$this->bean->setMeta('tainted', true);
			}
			if (isset($this->bean->$property)) {
				unset($this->bean->$property);
			}
		}
	}

	/**
	 * Magic Getter. Gets the value for a specific property of this deployment.
	 * return NULL instead.
	 * @param string $property
	 * @return mixed|null
	 */
	public function __get($property) {
		if (strpos($property, 'source') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				return $this->getSourceInstance();
			} else {
				$sourceInstance = $this->getSourceInstance();
				$sourceServer = $this->getSourceServer();
				if ($sourceInstance !== null && isset($sourceInstance->$property)) {
					return $sourceInstance->$property;
				} elseif ($sourceServer !== null && isset($sourceServer->$property)) {
					return $sourceServer->$property;
				} else {
					return null;
				}
			}
		} elseif (strpos($property, 'remote') === 0) {
			$property = lcfirst(substr($property, 6));
			if ($property === '') {
				return $this->getTargetInstance();
			} else {
				$targetInstance = $this->getTargetInstance();
				$targetServer = $this->getTargetServer();
				if ($targetInstance !== null && isset($targetInstance->$property)) {
					return $targetInstance->$property;
				} elseif ($targetServer !== null && isset($targetServer->$property)) {
					return $targetServer->$property;
				} else {
					return null;
				}
			}
			//Temporary properties are kept in the object, and not written to the database.
		} elseif (strpos($property, 'temp') === 0) {
			$property = lcfirst(substr($property, 4));
			if (isset($this->tempProperties[$property])) {
				return $this->tempProperties[$property];
			}
		} elseif (isset($this->bean->$property)) {
			return $this->bean->$property;
		} else{
			if ($this->loadedProperties === false) {
				$this->loadProperties();
			}
			if (isset($this->properties[$property])) {
				return $this->properties[$property];
			} else {
				return null;
			}
		}
	}

	/**
	 * Gets the source instance of this deployment
	 * The instance be saved in the db, or just added without saving.
	 * @return RedBean_SimpleModel|null The Source Instance
	 */
	public function getSourceInstance() {
		$instance = $this->bean->fetchAs('instance')->source;
		if ($instance instanceof RedBean_OODBBean && ($instance->id !== 0 || $instance->getMeta('tainted') || !$instance->isEmpty())) {
			return $instance->box();
		} elseif ($instance instanceof RedBean_SimpleModel && ($instance->unbox()->id !== 0 || $instance->unbox()->getMeta('tainted') || !$instance->unbox()->isEmpty())) {
			return $instance;
		}
		return null;
	}

	/**
	 * Gets the target instance of this deployment
	 * The instance be saved in the db, or just added without saving.
	 * @return RedBean_SimpleModel|null The Source Instance
	 */
	public function getTargetInstance() {
		$instance = $this->bean->fetchAs('instance')->target;
		if ($instance instanceof RedBean_OODBBean && ($instance->id !== 0 || $instance->getMeta('tainted') || !$instance->isEmpty())) {
			return $instance->box();
		} elseif ($instance instanceof RedBean_SimpleModel && ($instance->unbox()->id !== 0 || $instance->unbox()->getMeta('tainted') || !$instance->unbox()->isEmpty())) {
			return $instance;
		}
		return null;
	}

	/**
	 * Gets the source server of this deployment
	 * @return RedBean_SimpleModel|null The Source Server
	 */
	public function getSourceServer() {
		$instance = $this->getSourceInstance();
		if ($instance !== null) {
			$server = $instance->unbox()->server;
			if ($server !== null) {
				return $server->box();
			}
		}
		return null;
	}

	/**
	 * Gets the source server of this deployment
	 * @return RedBean_SimpleModel|null The Target Server
	 */
	public function getTargetServer() {
		$instance = $this->getTargetInstance();
		if ($instance !== null) {
			$server = $instance->unbox()->server;
			if ($server !== null) {
				return $server->box();
			}
		}
		return null;
	}

	/**
	 * Returns the application of this deployment
	 * Assumed is that the actual application is always the source application.
	 * @return Model_Application|null
	 */
	public function getApplication() {
		$instance = $this->getSourceInstance();
		if ($instance !== null) {
			$application = $instance->unbox()->application;
			if ($application !== null) {
				return $application;
			}
		}
		return null;
	}

	/**
	 * Adds $prefix before every key of the array
	 * @param array $props
	 * @param string $prefix
	 * @return array
	 */
	private function addPrefixProperties($props, $prefix) {
		$conProps = array();
		foreach ($props as $k => $v) {
			$conProps[$prefix . ucfirst($k)] = $v;
		}
		return $conProps;
	}

	/**
	 * Returns all properties of thi deployment(both from the source and target)
	 * @return array
	 */
	public function getAllProperties() {
		$bean = $this->unbox();

		$properties = $this->properties;

		$sourceInstance = $this->getSourceInstance();
		if ($sourceInstance !== null) {
			$sourceServer = $this->getSourceServer();
			if ($sourceServer !== null) {
				$properties = array_merge($properties, $this->addPrefixProperties($sourceServer->box()->getProperties(), 'source'));
			}
			$properties = array_merge($properties, $this->addPrefixProperties($sourceInstance->box()->getProperties(), 'source'));
		}

		$targetInstance = $this->getTargetInstance();
		if ($targetInstance !== null) {
			$targetServer = $this->getTargetServer();
			if ($targetServer !== null) {
				$properties = array_merge($properties, $this->addPrefixProperties($targetServer->box()->getProperties(), 'remote'));
			}
			$properties = array_merge($properties, $this->addPrefixProperties($targetInstance->box()->getProperties(), 'remote'));
		}

		// Finally some special 
		$properties['deploymentId'] = $bean->id;
		$config = Zend_Registry::get('config');
		$properties['buildscriptDir'] = $config->directories->buildscript;
		return $properties;
	}

	/**
	 * Creates a property file for phing... Might be possible for other applications to read it as well
	 * @param string $path Location where the location should be located
	 * @throws Exception Throw an exception when the file in not writeable
	 */
	public function writePropertiesFile($path) {
		//Gather all properties
		$properties = $this->getAllProperties();

		$propertiesString = '';
		foreach ($properties as $propertyName => $propertyValue) {
			$propertiesString .= str_replace('_', '.', $propertyName) . "=" . $propertyValue . PHP_EOL;
		}

		if (!file_put_contents($path, $propertiesString)) {
			throw new Exception('Failed writing to ' . $path);
		}
	}

	/**
	 * Read a property file and adds the properties to this deployment
	 * @param type $path
	 * @param type $overwrite
	 * @throws Exception Throw an exception when the file cannot be read.
	 */
	public function loadPropertiesFile($path, $overwrite = true) {
		if ($path === null && !file_exists($path)) {
			throw new Exception('Cannot load a file that does not exist or is not set...');
		}

		if (($lines = @file($path)) === false) {
			throw new Exception('Unable to parse contents of ' . $path);
		}

		foreach ($lines as $line) {
			// strip comments and leading/trailing spaces
			$line = trim(preg_replace("/\s+[;#]\s.+$/", "", $line));

			if (empty($line) || $line[0] == ';' || $line[0] == '#') {
				continue;
			}

			$pos = strpos($line, '=');
			$property = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));

			if ($overwrite || !isset($this->$property)) {
				$this->$property = $value;
			}
		}
	}

	/**
	 * Adds processes and changes some settings based on the deployment type
	 */
	public function prepareDeployment() {
		$bean = $this->unbox();
		if ($bean->id === 0) {
			R::store($this);
		}
		$config = Zend_Registry::get('config');
		$workdir = $config->directories->deployment;
		$oldumask = umask(0); 
		if (!is_dir($workdir)) {
			mkdir($workdir, 0777, true);
		}
		$this->deploymentDir = $workdir . $this->id;
		if ($bean->type === 'rollback') {
			$this->queueTarget('RollbackPilot');
			$this->queueTarget('Rollback');

			$bean->rollback = false;
		} elseif ($bean->type === 'upgrade') {
			$this->queueTarget('UpgradePilot');
			$this->queueTarget('Upgrade');

			$bean->rollback = true;
		} else {
			$this->queueTarget('Deploy');

			$bean->rollback = true;
		}
		
		if (!is_dir($workdir . $bean->id)) {
			mkdir($workdir . $bean->id, 0777, true);
		}
		umask($oldumask);
		$this->writePropertiesFile($workdir . $bean->id . '/build.properties');
		R::store($this);
	}

	/**
	 * Starts the process of either a pilot or deployment
	 */
	public function initiateDeployment() {
		$bean = $this->unbox();
		
		$config = Zend_Registry::get('config');
		$workdir = $config->directories->deployment;
		
		$oldumask = umask(0);
		if (!is_dir($workdir)) {
			mkdir($workdir, 0777, true);
		}
		if (!is_dir($workdir . $bean->id)) {
			mkdir($workdir . $bean->id, 0777, true);
		}
		umask($oldumask);
		
		$this->writePropertiesFile($workdir . $bean->id . '/build.properties');
		$process = $this->getNextProcess();
		$process->status = 'Not started';
		R::store($process);
		$process->startProcessWrapper();
	}

	/**
	 * Returns 0 or 1: 0 cannot rollback, 1 can rollback
	 * @return int
	 */
	public function canRollback() {
		return $this->bean->rollback;
	}

	/**
	 * Returns a description of the deployment based on sourceurl, targeturl and type
	 * @return string
	 */
	public function getDescription() {
		$bean = $this->unbox();
		$description = '';
		if ($bean->type === 'rollback') {
			$description .= 'Rollback of ' . $this->remoteUrl;
		} elseif ($bean->type === 'upgrade') {
			$description .= 'Upgrade of ' . $this->remoteUrl . ' with upgrades from ' . $this->sourceUrl;
		} else {
			$description .= 'Deploment of ' . $this->sourceUrl . ' to ' . $this->remoteUrl;
		}
		return $description;
	}

	public function getWarnings() {
		$targetServer = $this->getTargetServer();
		$appId = $this->getTargetInstance()->unbox()->application->id;
		if (($appId === null || $appId === 0) && $targetServer->type === 'production') {
			return array('NON-OTAP deployment; This application is not yet deployed to a demo server!');
		}
		if ($targetServer->type === 'production') {
			$warnings = array();
			$instances = R::find('instance', ' application_id = ? ', array($appId));

			$fileHash = null;
			$dbHashes = null;
			$demoFileHashes = array();
			$demoDbHashes = array();

			R::preload($instances, array('server'));
			foreach ($instances as $instance) {
				if ($instance->id === $this->getSourceInstance()->unbox()->id) {
					$fileHash = $instance->filehash;
					$dbHashes = $instance->dbhash;
					continue;
				}
				if ($instance->server->type === 'demo') {
					$demoFileHashes[] = $instance->filehash;
					$demoDbHashes[] = $instance->dbhash;
				}
			}

			if (!$demoFileHashes || !$demoDbHashes) {
				return array('NON-OTAP deployment; This application is not yet deployed to a demo server!');
			}

			if ($fileHash === null) {
				$warnings[] = 'Still running checks for OTAP validation. Files may have been changes since deployment to demo server.';
			} elseif ($fileHash === '') {
				$warnings[] = 'Failed to generate a file hash. Files may have been changes since deployment to demo server.';
			} elseif (!in_array($fileHash, $demoFileHashes)) {
				$warnings[] = 'NON-OTAP deployment; Files has been modified since deployment to demo server!';
			}

			if ($dbHashes === null) {
				$warnings[] = 'Still running checks for OTAP validation. The database scheme may have been changes since deployment to demo server.';
			} elseif ($dbHashes === '') {
				$warnings[] = 'Failed to generate a database hash. The database scheme may have been changes since deployment to demo server.';
			} elseif (!in_array($dbHashes, $demoDbHashes)) {
				$warnings[] = 'NON-OTAP deployment; Files has been modified since deployment to demo server!';
			}

			if ($warnings) {
				return $warnings;
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

}