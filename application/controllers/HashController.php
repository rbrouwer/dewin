<?php

class HashController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function filesAction() {
		error_reporting(E_ALL);
		ini_set('display_errors', '1');
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$instanceId = $this->getRequest()->getParam(0);
		if ($instanceId) {
			$instance = R::load('instance', $instanceId);
		}
		if ($instanceId && $instance->id !== 0) {
			$server = $instance->server;
			if ($server->access === 'local') {
				$filesystem = new Model_Filesystem_Local($instance->box()->webroot);
			} elseif ($server->access === 'remote') {

				if (isset($instance->box()->host)) {
					$host = $instance->box()->host;
				} elseif (isset($server->host)) {
					$host = $server->host;
				} else {
					$host = null;
				}

				if (isset($instance->box()->user)) {
					$user = $instance->box()->user;
				} elseif (isset($server->cpu) && !empty($server->cpu)) {
					$user = $server->cpu;
				} else {
					$user = null;
				}

				if (isset($instance->box()->password)) {
					$password = $instance->box()->password;
				} else {
					$password = null;
				}
				$filesystem = new Model_Filesystem_Sftp($host, $user, $instance->box()->webroot, $password);
			}

			$hash = $filesystem->getHash('');
			$instance = R::load('instance', $instance->id);
			$instance->filehash = $hash;
			R::store($instance);
		}
	}

	/**
	 * Validates if the database is accessible using the settings of the server and the instance
	 * @return boolean True when the database is accessible.
	 */
	public function dbAction() {
		error_reporting(E_ALL);
		ini_set('display_errors', '1');
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$instanceId = $this->getRequest()->getParam(0);
		if ($instanceId) {
			$instance = R::load('instance', $instanceId);
		}
		if ($instanceId && $instance->id !== 0) {
			$server = $instance->server;
			if (isset($instance->box()->host)) {
				$host = $instance->box()->host;
			} elseif (isset($server->host)) {
				$host = $server->host;
			} else {
				$host = null;
			}

			if (isset($instance->box()->databaseName)) {
				$user = $instance->box()->databaseName;
			} else {
				$user = null;
			}

			if (isset($instance->box()->databasePassword)) {
				$password = $instance->box()->databasePassword;
			} else {
				$password = null;
			}

			$dsn = 'mysql:host=' . $host . ';dbname=' . $user;
			try {
				$db = new PDO($dsn, $user, $password);
				$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$dbhash = shell_exec('mysqldump -h ' . $host . ' -u' . $user . ' -p' . $password . ' --database ' . $user . ' -d --compact | sha1sum | awk \'{ print $1 }\'');
			} catch (PDOException $e) {
				$dbhash = '';
			}
			$instance = R::load('instance', $instance->id);
			$instance->dbhash = $dbhash;
			R::store($instance);
		}
	}

}

