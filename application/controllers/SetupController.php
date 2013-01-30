<?php

class SetupController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function indexAction() {
		if ($this->getRequest()->isPost()) {
			$errors = array();
			if (is_writable(realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'library'))) {
				//$errors['general'][] = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'library/').' is not writable';
			}

			if (is_writable(realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tools'))) {
				//$errors['general'][] = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tools/').' is not writable';
			}

			if (is_writable(realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config'))) {
				$errors['general'][] = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config/') . ' is not writable';
			}

			$databaseHost = $this->getRequest()->getParam('databaseHost', 'localhost');
			if (empty($databaseHost)) {
				$databaseHost = 'localhost';
			}
			$databaseName = $this->getRequest()->getParam('databaseName', 'dewin');
			if (empty($databaseName)) {
				$databaseName = 'dewin';
			}
			$databaseUser = $this->getRequest()->getParam('databaseUser', 'dewin');
			if (empty($databaseUser)) {
				$databaseUser = 'dewin';
			}
			$databasePassword = $this->getRequest()->getParam('databasePassword');
			if (empty($databasePassword)) {
				$errors['databasePassword'] = 'Although technically database users are not required to have a password, this setup requires that anyway!';
			}

			$username = $this->getRequest()->getParam('username', 'admin');
			if (empty($username)) {
				$username = 'admin';
			}
			$password = $this->getRequest()->getParam('password');
			if (empty($password)) {
				$errors['password'] = 'Not setting a password is very discouraged!';
			}
			$password2 = $this->getRequest()->getParam('password2');
			if ($password !== $password2) {
				$errors['password2'] = 'The passwords do not match!';
			}
			$buildscriptPath = $this->getRequest()->getParam('buildscriptPath', realpath(APPLICATION_PATH . '/../buildscripts') . '/');
			if (empty($buildscriptPath)) {
				$buildscriptPath = realpath(APPLICATION_PATH . '/../buildscripts') . '/';
			}
			if (!is_readable($buildscriptPath) && is_dir($buildscriptPath)) {
				$errors['buildscriptPath'] = 'This directory should be readable.';
			}

			$deploymentPath = $this->getRequest()->getParam('deploymentPath', '/tmp/dewin/');
			if (empty($deploymentPath)) {
				$deploymentPath = '/tmp/dewin/';
			}
			if (!is_dir($deploymentPath)) {
				mkdir($deploymentPath, 0755, true);
			}
			if (!is_dir($deploymentPath) && is_writable($deploymentPath)) {
				$errors['deploymentPath'] = 'This directory should exist and be writable.';
			}

			//Attempt to connect
			$dsn = 'mysql:host=' . $databaseHost . ';dbname=' . $databaseName;
			try {
				$db = new PDO($dsn, $databaseUser, $databasePassword);
				$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				$errors['database'] = 'The database details are incorrect.';
			}

			if ($errors) {
				$this->view->errors = $errors;
			} else {
				// Write config.
				$config = new Zend_Config(array(), true);
				$config->setExtend('production');
				$config->production = array();
				$config->staging = array();
				$config->testing = array();
				$config->development = array();
				$config->production->auth = array();
				$config->production->auth->username = $username;
				$config->production->auth->password = $password;
				$config->production->database = array();
				$config->production->database->adapter = 'mysql';
				$config->production->database->params = array();
				$config->production->database->params->host = $databaseHost;
				$config->production->database->params->dbname = $databaseName;
				$config->production->database->params->username = $databaseUser;
				$config->production->database->params->password = $databasePassword;
				$config->production->directories = array();
				$config->production->directories->buildscript = $buildscriptPath;
				$config->production->directories->deployment = $deploymentPath;

				// Temporary
				$config->production->servertypes = array();
				$config->production->servertypes->dev = array();
				$config->production->servertypes->dev->applications = '/var/www/';
				$config->setExtend('staging', 'production');
				$config->setExtend('testing', 'staging');
				$config->setExtend('development', 'testing');
				Zend_Registry::set('config', $config);

				$writer = new Zend_Config_Writer_Xml();
				$writer->write(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . 'config.xml', $config);

				// Init installer.
				$pid = trim(shell_exec('nohup php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' setup rbspiifs 2> /dev/null > /dev/null & echo $!'));

				$this->_helper->Redirector->gotoSimple('install');
			}
		}
	}

	//RedBean Setup, Phing Install and Init Further Setup.
	public function rbspiifsAction() {
		if (PHP_SAPI === 'cli') {
			//Not a page, do not render stuff
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			$client = $this->getRequest()->getParam(0);

			//Bypass error handling
			error_reporting(E_ALL);
			ini_set('display_errors', '1');

			// Travis needs a config as well, not that it will be used.
			if ($client === 'travis-ci') {
				$config = new Zend_Config(array(), true);
				$config->setExtend('production');
				$config->production = array();
				$config->staging = array();
				$config->testing = array();
				$config->development = array();
				$config->production->database = array();
				$config->production->database->adapter = 'sqlite';
				$config->production->database->params = array();
				$config->production->database->params->file = 'unit.sql';
				$config->production->database->params->username = null;
				$config->production->database->params->password = null;
				$config->setExtend('staging', 'production');
				$config->setExtend('testing', 'staging');
				$config->setExtend('development', 'testing');
				Zend_Registry::set('config', $config);

				$writer = new Zend_Config_Writer_Xml();
				$writer->write(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . 'config.xml', $config);
			}
			
			// No need to install this for travis-ci.
			if ($client !== 'travis-ci') {
				$config = Zend_Registry::get('config');


				//Attempt to connect
				$dsn = 'mysql:host=' . $config->database->params->host . ';dbname=' . $config->database->params->dbname;
				try {
					$db = new PDO($dsn, $config->database->params->username, $config->database->params->password);
					$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				} catch (PDOException $e) {
					throw new InvalidArgumentException("Failed to connect to DB.");
				}


				// Write database.
				$sqlFile = realpath(APPLICATION_PATH . '/../install.sql');

				// Really useful that tools to split sql file are embedded.
				$sqlPatch = new Model_SqlPatch($sqlFile);
				while (($stmt = $sqlPatch->nextQuery())) {
					if ($stmt !== null) {
						$db->exec($stmt->getSql());
					}
				}
			}
			// Install redbean
			$this->installLib('RedBean', 'http://www.redbeanphp.com/downloads/RedBeanPHP3_3_7.tar.gz', array('rb.php'));
			require_once( APPLICATION_PATH . '/../library/RedBean/rb.php');

			// Unit test do not require an initialized redbean(for fancy progress bar!) and phing at this point in time.
			if ($client !== 'travis-ci') {
				// Init redbean.
				R::setup('mysql:host=' . $config->database->params->host . ';dbname=' . $config->database->params->dbname, $config->database->params->username, $config->database->params->password);
				Zend_Registry::set("tools", R::$toolbox);
				Zend_Registry::set("db", R::$adapter);
				Zend_Registry::set("redbean", R::$redbean);

				// Temporary
				$server = R::dispense('server');
				$server->type = 'dev';
				$server->user = 'www-data';
				$server->group = 'www-data';
				$server->baseurl = 'dev';
				$server->host = 'localhost';
				$server->name = 'Development server';
				$server->access = 'access';
				R::store($server);

				// Make proces #1, so i can control progress-bar etc etc.
				$process = R::dispense('process');
				$process->target = 'Installing';
				$process->status = 'Running';
				$process->percent = 20;
				$process->msg = 'Installed RB, done initial fill of the database';
				R::store($process);

				$this->installLib('phing', 'http://www.phing.info/get/phing-2.4.13.tgz', null, true);
				//$this->copyDir(APPLICATION_PATH . '/../library/PhingTasks', APPLICATION_PATH . '/../tools/phing/classes/phing/tasks/dewin');
				symlink(APPLICATION_PATH . '/../library/PhingTasks', APPLICATION_PATH . '/../tools/phing/classes/phing/tasks/dewin');
				file_put_contents(APPLICATION_PATH . '/../tools/phing/classes/phing/tasks/defaults.properties', preg_replace('/phing=phing\.tasks\.system.PhingTask/', 'phing=phing.tasks.dewin.BetterPhingTask', file_get_contents(APPLICATION_PATH . '/../tools/phing/classes/phing/tasks/defaults.properties')));

				$process->percent = 40;
				$process->msg = 'Installed Phing';
				R::store($process);
			}

			$this->installLib('SpikePHPCoverage-unpack', 'http://freefr.dl.sourceforge.net/project/phpcoverage/spikephpcoverage/0.8.2/phpcoverage-0.8.2.tar.gz', null);
			// Do some furth unpacking
			rename(APPLICATION_PATH . '/../library/SpikePHPCoverage-unpack/spikephpcoverage-0.8.2/src', APPLICATION_PATH . '/../library/SpikePHPCoverage');
			$this->deleteDir(APPLICATION_PATH . '/../library/SpikePHPCoverage-unpack/');
			
			// Travis-ci doesn't look at the progress bar. No need to make it
			if ($client !== 'travis-ci') {
				$process->percent = 60;
				$process->msg = 'Installed SpikePHPCoverage';
				R::store($process);
			}
			// convert class
			$this->installLib('Directadmin', 'http://files.directadmin.com/services/all/httpsocket/httpsocket.tar.gz', array('httpsocket.php'));

			rename(APPLICATION_PATH . '/../library/Directadmin/httpsocket.php', APPLICATION_PATH . '/../library/Directadmin/httpsocket.php-tmp');
			file_put_contents(APPLICATION_PATH . '/../library/Directadmin/HttpSocket.php', preg_replace('/if \(\$headers\[\'location\'\]\)/', 'if (isset($headers[\'location\']))', preg_replace('/class HTTPSocket \{/', 'class Directadmin_HttpSocket {', file_get_contents(APPLICATION_PATH . '/../library/Directadmin/httpsocket.php-tmp'))));
			unlink(APPLICATION_PATH . '/../library/Directadmin/httpsocket.php-tmp');
			
			// Travis-ci doesn't look at the progress bar. No need to make it.
			if ($client !== 'travis-ci') {
				$process->percent = 80;
				$process->msg = 'DA-Dewin Link';
				R::store($process);

				$process->percent = 100;
				$process->status = 'Finished';
				$process->msg = 'Dependancies installed';
				R::store($process);
			}
			
			// Travis-ci wants to run unit tests, so remove the setup already so it can do so! And add 2 'dummy' server types.
			if ($client === 'travis-ci') {
				unlink(APPLICATION_PATH . '/controllers/SetupController.php');
				copy(APPLICATION_PATH . '/tests/files/server/Demo.php', APPLICATION_PATH . '/models/Server/Demo.php');
				copy(APPLICATION_PATH . '/tests/files/server/Dev.php', APPLICATION_PATH . '/models/Server/Dev.php');
			}
		} else {
			throw new Zend_Controller_Action_Exception('This page does not exist', 404);
		}
	}

	private function installLib($name, $downloadLink, $fileArray = null, $isTool = false) {
		if ($isTool) {
			$targetDir = APPLICATION_PATH . '/../tools/' . $name;
		} else {
			$targetDir = APPLICATION_PATH . '/../library/' . $name;
		}
		$dh = fopen($downloadLink, 'r');
		mkdir($targetDir, 0777, true);
		$rand = date('YmdHis');
		$sh = fopen($targetDir . '/' . $name . $rand . '.tar.gz', 'w');
		while (!feof($dh)) {
			fwrite($sh, fread($dh, 8192));
		}
		fflush($sh);
		fclose($dh);
		fclose($sh);

		$gz = new PharData($targetDir . '/' . $name . $rand . '.tar.gz');
		$tar = $gz->decompress();
		$tar->extractTo($targetDir . '/', $fileArray, true);
		$gz = $tar = null;
		unlink($targetDir . '/' . $name . $rand . '.tar.gz');
		unlink($targetDir . '/' . $name . $rand . '.tar');
	}

	private function deleteDir($dirPath) {
		if (!is_dir($dirPath)) {
			throw new InvalidArgumentException("$dirPath must be a directory");
		}
		if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
			$dirPath .= '/';
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				$this->deleteDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dirPath);
	}

	private function copyDir($source, $destination) {
		if (!is_dir($source)) {
			throw new InvalidArgumentException("$source must be a directory");
		}
		mkdir($destination);
		$directory = dir($source);
		while (FALSE !== ( $readdirectory = $directory->read() )) {
			if ($readdirectory == '.' || $readdirectory == '..') {
				continue;
			}
			$PathDir = $source . '/' . $readdirectory;
			if (is_dir($PathDir)) {
				$this->copyDir($PathDir, $destination . '/' . $readdirectory);
				continue;
			}
			copy($PathDir, $destination . '/' . $readdirectory);
		}

		$directory->close();
	}

	public function installAction() {
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);
			$resultArray = array();
			if (class_exists('R', false)) {
				$process = R::findOne('process', '`deployment_id` IS NULL AND `target` = "Installing" ORDER BY `id` DESC LIMIT 1');
				if ($process->id !== null) {
					if (empty($process->msg)) {
						$resultArray['status'] = '';
					} else {
						$resultArray['status'] = $this->view->escape($process->msg);
					}

					if ($process->percent !== null) {
						$resultArray['percent'] = $process->percent;
					}

					if ($process !== false && $process->status === 'Finished') {
						$resultArray['exit'] = 'Success';
					}
				}
			}

			print Zend_Json::encode($resultArray);
		} else {
			$this->view->title = 'Deploying self';
			$this->view->text = 'Welcome to Dewin. If this deployment succeeds, you can use Dewin to deploy your webapplications.';
			$this->view->initialProgressText = 'Starting installation process';
			$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('install', 'setup', 'default');
			$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'setup', 'default');
			$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'setup', 'default');
			$this->view->defaultProcessText = 'Inserting database and installing redbean';
			$this->_helper->viewRenderer->renderBySpec('waiting', array('module' => 'default', 'controller' => 'index'));
		}
	}

	public function completeAction() {
		// Handle the finish button, unset session and return to index
		if ($this->getRequest()->isPost()) {
			unlink(APPLICATION_PATH . '/controllers/SetupController.php');
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		// One of the last things the setup does.
		if (class_exists('Directadmin_HttpSocket')) {
			$this->view->success = true;
		} else {
			$this->view->success = false;
		}
	}

}

