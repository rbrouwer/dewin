<?php

class Model_Instance extends RedBean_SimpleModel {

	/**
	 * Contains all values for which no columns exist.
	 * @var array 
	 */
	private $settings;

	/**
	 * Is the settings array populated.
	 * @var boolean 
	 */
	private $loadedSettings;

	/**
	 * Prepares the instance for properties.
	 */
	public function dispense() {
		$this->settings = array();
		$this->loadedSettings = true;
	}

	/**
	 * Updates the child properties with the changes made to the instance
	 */
	public function update() {
		if ($this->loadedSettings !== false) {
			$settings = array();
			$ownSettings = $this->bean->ownSetting;
			foreach ($this->settings as $k => $v) {
				$found = false;
				foreach ($ownSettings as $setting) {
					if ($setting->name === $k) {
						$setting->value = $v;
						$found = true;
						$settings[] = $setting;
					}
				}
				if (!$found) {
					$setting = R::dispense('setting');
					$setting->name = $k;
					$setting->value = $v;
					$settings[] = $setting;
				}
			}
			$this->bean->ownSetting = $settings;
		} else {
			$ownSettings = $this->bean->ownSetting;
			foreach ($this->settings as $k => $v) {
				$found = false;
				foreach ($ownSettings as $setting) {
					if ($setting->name === $k) {
						$setting->value = $v;
						$found = true;
						$this->bean->ownSetting[] = $setting;
					}
				}
				if (!$found) {
					$setting = R::dispense('setting');
					$setting->name = $k;
					$setting->value = $v;
					$this->bean->ownSetting[] = $setting;
				}
			}
		}
	}

	/**
	 * Preloads all instance settings and saves them in an array
	 */
	public function open() {
		$this->settings = array();
		$this->loadedSettings = false;
	}

	private function loadSettings() {
		$ownSettings = $this->bean->ownSetting;
		foreach ($ownSettings as $setting) {
			$this->settings[$setting->name] = $setting->value;
		}
		$this->loadedSettings = true;
	}

	/**
	 * This magic setter will set properties of the bean, if they exists
	 * Otherwise a instance settings will be created to accomendate the setting
	 * @param string $property
	 * @param string $value
	 */
	public function __set($property, $value) {
		if (isset($this->bean->$property)) {
			$this->bean->$property = $value;
		} else {
			if (!isset($this->settings[$property]) || $this->settings[$property] !== $value) {
				$this->settings[$property] = $value;
				$this->bean->setMeta('tainted', true);
			}
		}
	}

	/**
	 * The magic function will return whenever the requested property
	 * exists as either property of the bean or as setting
	 * @param string $property
	 * @return boolean
	 */
	public function __isset($property) {
		if (isset($this->bean->$property)) {
			return true;
		} else {
			// The magic getter, setter and unsetter all use isset,
			// so only here a settings load is required!
			if ($this->loadedSettings === false) {
				$this->loadSettings();
			}
			return isset($this->settings[$property]);
		}
	}

	/**
	 * The magic unsetter will unset the requested property
	 * First the property will be unset incase it exists
	 * otherwise the property of the bean will be unset
	 * @param string $property
	 * @return boolean
	 */
	public function __unset($property) {
		if (isset($this->settings[$property])) {
			unset($this->settings[$property]);
			$this->bean->setMeta('tainted', true);
		} elseif (isset($this->bean->$property)) {
			unset($this->bean->$property);
		}
	}

	/**
	 * Magic Getter. Gets the value for a specific property of this instance from either settings of the bean.
	 * The settings have a higher priority then the properties in the bean.
	 * return NULL instead.
	 * @param string $property
	 * @return mixed|null
	 */
	public function __get($property) {
		if ($this->loadedSettings === false) {
			$this->loadSettings();
		}
		if (isset($this->settings[$property])) {
			return $this->settings[$property];
		} else {
			return $this->bean->$property;
		}
	}

	/**
	 * Gets all properties of this instance
	 * @return array
	 */
	public function getProperties() {
		$properties = array();
		$props = $this->bean->getProperties();
		foreach ($props as $k => $v) {
			if (!is_array($v) && !is_object($v) && !in_array($k, array('id', 'server_id', 'application_id', 'filehash', 'dbhash'))) {
				$properties[$k] = $v;
			}
		}
		if ($this->loadedSettings === false) {
			$this->loadSettings();
		}
		return array_merge($properties, $this->settings);
	}

	/**
	 * Validates if the database is accessible using the settings of the server and the instance
	 * @return boolean True when the database is accessible.
	 */
	public function validateDB() {
		$dsn = 'mysql:host=' . $this->bean->server->host . ';dbname=' . $this->settings['databaseName'];
		$user = $this->settings['databaseUser'];
		$password = $this->settings['databasePassword'];
		try {
			$db = new PDO($dsn, $user, $password);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieve the recipe used for deploying this instance
	 * @return string Used recipe
	 */
	public function getDeploymentRecipe() {
		$deployment = R::findOne('deployment', ' target_id = ? AND success = 1 AND type = ? ORDER BY created DESC ', array($this->id, 'deploy'));
		if ($deployment !== null) {
			return $deployment->recipe;
		} else {
			return null;
		}
	}
	
	public function isDeployed() {
		$deployment = R::findOne('deployment', ' target_id = ? AND success = 1 AND type = ? ', array($this->id, 'deploy'));
		return ($deployment !== null || $this->bean->server_id == '1');
	}

}