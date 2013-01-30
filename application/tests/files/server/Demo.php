<?php

/**
 * Responsible for instance management on the demo type server
 */
class Model_Server_Demo extends Model_Server_Abstract {

	/**
	 * Lists all applications for that recipe on a demo directadmin server.
	 * @param Model_Recipe $recipe
	 * @return array
	 */
	public function getApplications($recipe = null) {
		// Get different applications from demo directadmin environment for deployment to prod or dev.
		// Not needed now.
		return array();
	}

	/**
	 * Returns an associative array containing the data required to fill the dynamic form section of target form
	 * @return array Associative array with input fields as key and full names as value
	 */
	public function getAdditionalFields() {
		$fields['directAdminUser'] = array(
				'label' => 'Direct Admin Username',
				'type' => 'text',
				'placeholder' => 'Direct Admin Username'
				);
		$fields['directAdminPassword'] = array(
			   'label' => 'Direct Admin Password',
				'type' => 'password',
				'placeholder' => 'Direct Admin Password'
				);
		$fields['databaseName'] = array(
			   'label' => 'Database name/user',
				'type' => 'text',
				'placeholder' => 'Database name/user'
				);
		$fields['databasePassword'] = array(
			   'label' => 'Database Password',
				'type' => 'password',
				'placeholder' => 'Database Password'
				);
		return $fields;
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
		if (!isset($options['directAdminUser']) || $options['directAdminUser'] === '') {
			$errors['directAdminUser'] = 'The directadmin username field is required.';
		}
		
		if (!isset($options['directAdminPassword']) || $options['directAdminPassword'] === '') {
			$errors['directAdminPassword'] = 'The directadmin password field is required.';
		}
		
		if (!isset($options['databaseName']) || $options['databaseName'] === '') {
			$errors['databaseName'] = 'The database username field is required.';
		}
		
		if (!isset($options['databasePassword']) || $options['databasePassword'] === '') {
			$errors['databasePassword'] = 'The database password field is required.';
		}
		
		if (!$errors) {
			$sock = new Directadmin_HttpSocket();
			$sock->connect($this->host, 2222);
		
			//Login for test DA instance this script should execute on.
			$sock->set_login($options['directAdminUser'], $options['directAdminPassword']);

			$sock->query('/CMD_API_SHOW_DOMAINS', array());
			
			$result = $sock->fetch_parsed_body();
			
			if (!isset($result['list'])) {
				$errors['directAdminPassword'] = 'The directadmin login details are incorrect.';
			}
		}
		if (!$errors) {
			$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);
			if (!in_array($url, $result['list'])) {
				$errors['url'] = 'This desired url is not attached to this supplied directadmin username.'.
					'The available options are: '.implode(',',$result['list']).'!';
			}
			
			
		}
		
		if ($errors) {
			return $errors;
		} else {
			return null;
		}
	}
	
	/**
	 * Creates an instance of an application and prepares it with settings of a production server
	 * @param Model_Application $application The application to add
	 * @param string $url The url to which the site will be deployed.
	 * @return Model_Instance The resulting instance with preset properties
	 * @throws Exception Throws an error when the url does not have the correct format
	 */
	public function addApplication(Model_Application $application, $url, $options=array()) {
		$url = preg_replace('`^(.+?)://(www\.|)`i', '', $url);

		$instance = R::dispense('instance');
		$instance->application = $application;
		$instance->server = $this->unbox();
		
		$instance = $instance->box();
		
		//remoteUser=${van DirectAdmin user veld}
		$instance->user = $options['directAdminUser'];
		
		//remoteGroup=${van DirectAdmin user veld}
		$instance->group = $options['directAdminUser'];
		
		//remotePassword=${van DirectAdmin password veld}
		$instance->password = $options['directAdminPassword'];
		
		//Directadmin login...
		$instance->cpu = $options['directAdminUser'];
		$instance->cpp = $options['directAdminPassword'];
		
		//remoteUrl=${van Desired Url veld}
		$instance->url = $url;
		
		//remoteProject=${van DirectAdmin user veld}?
		$instance->project = $options['directAdminUser'];
		
		//remoteBuildsdir=/home/${van DirectAdmin user veld}/_public_html/
		$instance->buildsdir = '/home/'.$options['directAdminUser'].'/domains/'.$url.'/_public_html';
		
		//remoteWebroot=/home/${van DirectAdmin user veld}/public_html/
		$instance->webroot = '/home/'.$options['directAdminUser'].'/domains/'.$url.'/public_html';
		
		//remoteDatabaseName=${van DirectAdmin user veld}_www
		if (strpos($options['databaseName'], $options['directAdminUser'].'_') === 0) {
			$instance->databaseName = $options['databaseName'];
			$instance->databaseUser = $options['databaseName'];
			$instance->databaseNameShort = substr($options['databaseName'],  strlen($options['directAdminUser'].'_'));
		} else {
			$instance->databaseName = $options['directAdminUser'].'_'.$options['databaseName'];
			$instance->databaseUser = $options['directAdminUser'].'_'.$options['databaseName'];
			$instance->databaseNameShort = $options['databaseName'];
		}
		
		$instance->databasePassword = $options['databasePassword'];

		return $instance;
	}

}
