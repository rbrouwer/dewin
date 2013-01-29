<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {

	public function run() {
		//Load config
		if (is_file(APPLICATION_PATH . '/configs/config.xml')) {
			$config = new Zend_Config_Xml(APPLICATION_PATH . '/configs/config.xml', APPLICATION_ENV, true);
			Zend_Registry::set('config', $config);
		}
		
		if (is_file( APPLICATION_PATH . '/../library/RedBean/rb.php')) {
			$loader = Zend_Loader_Autoloader::getInstance()->registerNamespace('RedBean_');
			require_once( APPLICATION_PATH . '/../library/RedBean/rb.php'); //or rb.php
			if ($config->database->adapter === 'mysql') {
				R::setup('mysql:host='.$config->database->params->host.';dbname='.$config->database->params->dbname, 
						  $config->database->params->username, $config->database->params->password);
			} elseif ($config->database->adapter === 'pgsql') {
				R::setup('pgsql:host='.$config->database->params->host.';dbname='.$config->database->params->dbname, 
						  $config->database->params->username, $config->database->params->password);
			} elseif ($config->database->adapter === 'sqlite') {
				R::setup('sqlite:'.$config->database->params->file, 
						  $config->database->params->username, $config->database->params->password);
			} elseif ($config->database->adapter === 'cubrid') {
				R::setup('mysql:host='.$config->database->params->host.';port='.$config->database->params->port.';dbname='.$config->database->params->dbname, 
						  $config->database->params->username, $config->database->params->password);
			} else {
				throw new Zend_Application_Exception('Unknown database adapter in config.');
			}
			Zend_Registry::set("tools", R::$toolbox);
			Zend_Registry::set("db", R::$adapter);
			Zend_Registry::set("redbean", R::$redbean);
		}
		
		parent::run();
	}

	protected function _initResourceLoader() {
		$resourceLoader = new Zend_Loader_Autoloader_Resource(array(
						'namespace' => '',
						'basePath' => APPLICATION_PATH,
				  ));
      $resourceLoader->addResourceTypes(array(
            'model'   => array(
                'namespace' => 'Model',
                'path'      => 'models',
            ),
				'test' => array(
                'namespace' => 'Test',
                'path'      => 'tests',
            ),
            'viewhelper' => array(
                'namespace' => 'View_Helper',
                'path'      => 'views/helpers',
            )
        ));

		return $resourceLoader;
	}

	protected function _initRouter() {
		if (PHP_SAPI == 'cli') {
			//Change the router to allow for a more command line friendly version.
			$this->bootstrap('FrontController');
			$front = $this->getResource('FrontController');
			$front->setParam('disableOutputBuffering', true);
			$front->setRequest(new Zend_Controller_Request_Simple());
			$front->setRouter(new App_Router());
		}
	}

	protected function _initError() {
		if (PHP_SAPI == 'cli') {
			//Change the error action for a more command line friendly version.
			$front = $this->getResource('FrontController');
			$errorHandler = new Zend_Controller_Plugin_ErrorHandler();
			$errorHandler->setErrorHandlerModule('default')
					  ->setErrorHandlerController('error')
					  ->setErrorHandlerAction('cli');
			$front->registerPlugin($errorHandler);
		}
	}

}

