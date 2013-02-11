<?php

/**
 * Responsible for instance management on the dev type server
 */
class Model_Server_Dev extends Model_Server_Abstract {

	/**
	 * Lists all applications for that recipe on a dev server.
	 * @param Model_Recipe $recipe
	 * @return type
	 */
	public function getInstances($recipe = null) {
		$config = Zend_Registry::get('config');
		$appDir = $config->servertypes->dev->applications;

		//Preload instances, applications and their properties.
		$instances = $this->unbox()->ownInstance;
		R::preload($instances, array('application', 'setting'));

		$paths = array();

		foreach ($instances as $id => $instance) {
			$paths[$id] = $instance->box()->webroot;
		}

		$appsFolders = scandir($appDir);
		$apps = array();

		foreach ($appsFolders as $app) {
			$path = $appDir . $app;

			if (substr($app, 0, 1) === '.' || !is_dir($path)) {
				continue;
			}
			if (!$recipe instanceof Model_Recipe || $recipe->validateFolder(new Model_Filesystem_Local($path))) {
				// Look for an instance with the same path. I believe those will be the same!
				if (($id = array_search($path, $paths))) {
					array_push($apps, $instances[$id]);
				} else {
					$application = R::dispense('application');
					$application->path = $path;

					$instance = R::dispense('instance');
					$instance->box()->identifier = 'app_' . $app;
					$instance->box()->webroot = $path;
					$instance->server = $this->server;
					$instance->application = $application;
					array_push($apps, $instance);
				}
			}
		}

		return $apps;
	}

	/**
	 * Get an instance using an indentifier. This allows instances which are not saved 
	 * in the database to be selected in the application screen.
	 * @param String $uniqueIdentifier The unique identifier
	 * @param Model_Recipe $recipe The recipe which checks the application before returning it.
	 * @return Model_Instances The Model of the Instance.
	 */
	public function getInstance($uniqueIdentifier, $recipe = null) {
		$appDir = preg_replace('`^app_`i', '', $uniqueIdentifier);

		// Create the app
		$app = R::dispense('application');
		$app->path = '/var/www/';

		// Create the instances
		$url = $appDir . '.dev';
		$instance = $this->addApplication($app->box(), $url);

		//Add identifier for when we return to that form!
		$instance->box()->identifier = $uniqueIdentifier;
		return $instance;
	}

	/**
	 * Generates a name for an instance. This could be saved in the instance, 
	 * but that would mean manually naming all currently existing instances.
	 * @param Model_Instance $instance The instance to generate a name for.
	 * @return String The name
	 */
	public function getInstanceName(Model_Instance $instance) {
		$path = $instance->webroot;
		$path = explode('/', $path);

		$project = array_pop($path);
		$dev = array_pop($path);

		return $dev . ' - ' . $project;
	}

	/**
	 * Lists all applications for that recipe on a dev server.
	 * @param Model_Recipe $recipe
	 * @return type
	 */
	public function getApplications($recipe = null) {
		$config = Zend_Registry::get('config');
		$appDir = $config->servertypes->dev->applications;
		$appsFolders = scandir($appDir);
		$apps = array();
		foreach ($appsFolders as $app) {
			$path = $appDir . $app;

			if (substr($app, 0, 1) === '.' || !is_dir($path)) {
				continue;
			}
			if (!$recipe instanceof Model_Recipe || $recipe->validateFolder(new Model_Filesystem_Local($path))) {
				$application = R::dispense('application');
				$application->path = $path;
				array_push($apps, $application);
			}
		}

		return array($this->name => $apps);
	}

	/**
	 * Checks the input from the target screen.
	 * @param Model_Application $application
	 * @param string $url The desired URL
	 * @param string $options 
	 * @return array|null Array with error or null when the parameters are valid.
	 */
	public function validateOptions(Model_Application $application, $url, $options) {
		$errors = array();
		$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);
		if (!preg_match('/^(.+?)\.dev$/i', $url)) {
			$errors['url'] = 'This desired url must be in the following format: http://[project name].dev!';
		}

		if ($errors) {
			return $errors;
		} else {
			return null;
		}
	}

	/**
	 * Extracts the project name from the url
	 * @param string $url The url to extract the name from
	 * @return string The project name
	 */
	private function getNameFromUrl($url) {
		$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);
		if (preg_match('/^(.+?)\.dev$/i', $url, $matches)) {
			return implode('_', array_reverse(explode('.', $matches['0'])));
		} else {
			return '';
		}
	}

	/**
	 * Extracts the webroot directory from the url
	 * @param string $url The url to extract the name from
	 * @return string The webroot directory
	 */
	private function getDirFromUrl($url) {
		$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);
		if (preg_match('/^((.*)\.|)(.+?)\.dev$/i', $url, $matches)) {
			return $matches['3'];
		} else {
			return '';
		}
	}

	/**
	 * Creates an instance of an application and prepares it with settings of a demo server
	 * @param Model_Application $application The application to add.
	 * @param string $url The url to which the site will be deployed.
	 * @param array $options An array with additional options.
	 * @return Model_Instance The resulting instance with preset properties.
	 */
	public function addApplication(Model_Application $application, $url, $options = array()) {
		$config = Zend_Registry::get('config');
		$appDir = $config->servertypes->dev->applications;

		$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);

		$instance = R::dispense('instance');
		$instance->application = $application;
		$instance->server = $this->unbox();

		$instance = $instance->box();
		$instance->url = $url;
		$project = $this->getNameFromUrl($url);
		$instance->user = 'www-data';
		$instance->group = 'www-data';
		$instance->project = $project;
		if ($project === '') {
			$instance->buildsdir = '';
			$instance->webroot = $appDir;
			$instance->databaseName = 'website';
		} else {
			$instance->buildsdir = '';
			$instance->webroot = $appDir . $this->getDirFromUrl($url);
			$instance->databaseName = $project;
		}

		$instance->databaseUser = 'root';
		$instance->databasePassword = 'root';

		return $instance;
	}

}
