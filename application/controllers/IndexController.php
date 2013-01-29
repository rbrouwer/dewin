<?php

class IndexController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function indexAction() {
		$d = new Zend_Session_Namespace('d');
		if ($this->getRequest()->isPost()) {
			$recipe = Model_Recipe::getRecipe($this->getRequest()->getParam('recipe'));

			if ($recipe !== null) {
				if (!isset($d->model) || $recipe->getPath() !== $d->model->recipe) {
					$d->unsetAll();
					$d->model = R::dispense('deployment');
					$d->model->unbox()->success = false;
				}

				$d->model->setRecipe($recipe);
				$this->_helper->Redirector->gotoSimple('recipe');
			} else {
				$this->view->errors = array();
			}
		}

		if (isset($d->model) && $d->model->recipe !== null) {
			$this->view->recipePath = $d->model->recipe;
		}

		$this->view->recipes = Model_Recipe::getRecipes();
	}

	public function recipeAction() {
		$d = new Zend_Session_Namespace('d');

		if (!isset($d->model) && $d->model->getRecipe() === null) {
			$this->_helper->Redirector->gotoSimple('index');
		}

		$this->view->recipe = $d->model->getRecipe();
	}

	public function applicationAction() {
		$d = new Zend_Session_Namespace('d');
		if (!isset($d->model) && $d->model->getRecipe() === null) {
			$this->_helper->Redirector->gotoSimple('index');
		} else {
			$recipe = $d->model->getRecipe();
		}
		$server = R::load('server', 1);

		if ($this->getRequest()->isPost()) {
			$applicationPath = $this->getRequest()->getParam('application');
			//Find or create the application...
			$app = R::findOne('application', 'path = ?', array($applicationPath));
			if ($app === null) {
				$app = R::dispense('application');
				$app->path = $applicationPath;
				$instance = null;
			} else {
				$instance = R::findOne('instance', ' server_id = ? AND application_id = ?', array($server->id, $app->id));
			}

			//This application is appearently not part of the dev server... Add it straight away.
			if ($instance === null) {
				$url = preg_replace('`^/var/www/(.+?)/(.+?)$`i', '$2.$1', $applicationPath);
				$instance = $server->box()->addApplication($app->box(), $url);
			} else {
				$hashing = false;
				if (!isset($d->model->tempHashFilePid) || (isset($d->model->tempHashFilePid) && $d->model->source->unbox()->id !== $instance->unbox()->id)) {
					$hashFilePid = trim(shell_exec('nohup php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' hash files ' . $instance->id . ' 2> /dev/null > /dev/null & echo $!'));
					$d->model->box()->tempHashFilePid = $hashFilePid;
					$instance->filehash = null;
					$hashing = true;
				}
				if (!isset($d->model->tempHashDbPid) || (isset($d->model->tempHashDbPid) && $d->model->source->unbox()->id !== $instance->unbox()->id)) {
					$hashDbPid = trim(shell_exec('nohup php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' hash db ' . $instance->id . ' 2> /dev/null > /dev/null & echo $!'));
					$d->model->box()->tempHashDbPid = $hashDbPid;
					$instance->dbhash = null;
					$hashing = true;
				}
				if ($hashing) {
					R::store($instance);
				}
			}
			
			$d->model->source = $instance->unbox();
			$this->_helper->Redirector->gotoSimple('target');
		}

		if (isset($d->model->source->application)) {
			$this->view->selectedPath = $d->model->source->application->path;
		} else {
			$this->view->selectedPath = null;
		}

		// Need to box to by-pass the method_exists check which does not like __call
		$this->view->applications = $server->box()->getApplications($recipe);
	}

	public function targetAction() {
		$d = new Zend_Session_Namespace('d');
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);
		}
		// Check if the are some basic values
		if ((!isset($d->model) || $d->model->getRecipe() === null) && !$this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->Redirector->gotoSimple('index');
		}

		// Disable lay-out and view rendering when this request is an ajax request.
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);
		} else {
			// Check for settings that should be set in the applicationAction
			if (!isset($d->model->box()->sourceProject) && !isset($d->model->box()->sourceWebroot)) {
				$this->_helper->Redirector->gotoSimple('application');
			}
		}

		// Handle Ajax request for this page.
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			if ($this->getRequest()->isPost()) {
				$errors = array();
				$serverId = $this->getRequest()->getParam('serverId');
				$valid = false;

				if ($serverId !== null) {
					$server = R::load('server', $serverId);
					if ($server->id !== 0) {
						$fields = $server->box()->getAdditionalFields();
						$options = array();
						foreach ($fields as $name => $field) {
							$options[$name] = $this->getRequest()->getParam($name);
						}
						$errors = $server->box()->validateOptions($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
						if ($errors === null) {
							$instance = $server->box()->addApplication($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
							if ($instance !== null && $instance->id !== 0) {
								$valid = $instance->validateDb();
							} elseif ($instance !== null) {
								$valid = true;
							}
						}
					}
				}
				if ($serverId === null || $server->id === 0) {
					$errors['serverId'] = 'You should select a target server!';
					$fields = null;
					$options = null;
				}

				print Zend_Json::encode(array('valid' => $valid, 'fields' => $fields, 'values' => $options, 'errors' => $errors));
			} else {
				if (isset($d->model->box()->tempTargetFields)) {
					$fields = $d->model->box()->tempTargetFields;
				} else {
					$fields = array();
				}
				if (isset($d->model->box()->tempTargetOptions)){
					$options = $d->model->box()->tempTargetOptions;
				} else {
					$options = array();
				}
				print Zend_Json::encode(array('fields' => $fields, 'values' => $options));
			}
			// Handle normal requests here.
		} else {
			if ($this->getRequest()->isPost()) {
				$this->view->errors = array();
				$serverId = $this->getRequest()->getParam('serverId');

				if ($serverId !== null) {
					$server = R::load('server', $serverId);
					if ($server->id !== 0) {
						$fields = $server->box()->getAdditionalFields();
						$options = array();
						foreach ($fields as $name => $field) {
							$options[$name] = $this->getRequest()->getParam($name);
						}
						$this->view->errors = $server->box()->validateOptions($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
						if ($this->view->errors === null) {
							$instances = R::find('instance', ' server_id = ? AND application_id = ?', array($server->id, $d->model->getApplication()->id));
							
							$instance = null;
							foreach ($instances as $inst) {
								if ($inst->box()->url == preg_replace('`^(.+?)://(www\.|)`i', '', $this->getRequest()->getParam('url'))) {
									$instance = $inst->box();
									break;
								}
							}
							if ($instance === null) {
								$instance = $server->box()->addApplication($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
							}
						}
					} else {
						$this->view->errors['serverId'] = 'Target has not been selected!';
					}
				} else {
					$this->view->errors['serverId'] = 'Target has not been selected!';
				}

				if (!$this->view->errors) {
					$d->model->target = $instance->unbox();
					$d->model->box()->tempTargetFields = $fields;
					$d->model->box()->tempTargetOptions = $options;
					$this->_helper->Redirector->gotoSimple('detection');
				}
			}

			if (isset($d->model->box()->getTargetServer()->id)) {
				$this->view->server_id = $d->model->box()->getTargetServer()->id;
			} else {
				$this->view->server_id = null;
			}

			if (isset($d->model->box()->remoteUrl)) {
				$this->view->url = $d->model->box()->remoteUrl;
			} else {
				$this->view->url = null;
			}

			$this->view->servers = R::find('server', ' id != 1');
			$this->view->source_project = $d->model->box()->sourceProject;
			$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('target', 'index', 'default');
		}
	}

	public function detectionAction() {
		$d = new Zend_Session_Namespace('d');
		if (isset($d->model)) {
			$recipe = $d->model->getRecipe();
		} else {
			$this->_helper->Redirector->gotoSimple('index');
		}

		if (!isset($d->model->target)) {
			$this->_helper->Redirector->gotoSimple('target');
		}
		
		if (isset($d->model->box()->remotePassword)) {
			$password = $d->model->box()->remotePassword;
		} else {
			$password = null;
		}
		
		if ($recipe->validateFolder(new Model_Filesystem_Sftp($d->model->box()->remoteHost, $d->model->box()->remoteUser, $d->model->box()->remoteWebroot, $password))) {
			$d->model->unbox()->type = 'upgrade';
			$this->view->upgrade = true;
		} else {
			$d->model->unbox()->type = 'deploy';
			$this->view->upgrade = false;
		}
		
		$this->view->warnings = $d->model->box()->getWarnings();

		if ($this->getRequest()->isPost()) {
			$d->model->prepareDeployment();
			$d->model->initiateDeployment();
			if ($d->model->unbox()->type === 'upgrade') {
				$this->_helper->Redirector->gotoSimple('pilot', 'upgrade');
			} else {
				$this->_helper->Redirector->gotoSimple('deployment', 'deployment');
			}
		}

		$this->view->url = 'http://' . $d->model->box()->remoteUrl;
	}
	
	public function deploymentAction() {
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		
		$d = new Zend_Session_Namespace('d');
		if ($this->getRequest()->isXmlHttpRequest()) {
			if (!isset($d->model) && $d->model->getProcess() === null) {
				throw new Exception('Cannot monitor a process that does not exist!');
			}
			$process = $d->model->getRunningProcess();
			if ($process !== false) {
				$minId = $this->getRequest()->getParam('minId');
				$resultArray = $process->getStdoutArray($minId);
			} else {
				$resultArray = array();
			}
			if (empty($process->msg)) {
				$resultArray['status'] = '';
			} else {
				$resultArray['status'] = $this->view->escape($process->msg);
			}
			if ($process !== false && $process->percent !== null) {
				$resultArray['percent'] = $this->view->escape($process->percent);
			}

			if ($process !== false && $process->status === 'Finished') {
				if ($process->exitcode != 0) {
					$resultArray['exit'] = 'Fail';
				} else {
					$resultArray['exit'] = 'Success';
				}
			}
			print Zend_Json::encode($resultArray);
		}
	}
}