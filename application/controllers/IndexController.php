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
		if (!isset($d->model) && $d->model->getRecipe() === null && !$this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->Redirector->gotoSimple('index');
		} else {
			$recipe = $d->model->getRecipe();
		}

		if ($this->getRequest()->isPost()) {
			$serverId = $this->getRequest()->getParam('serverId');
			if ($serverId !== null) {
				$server = R::load('server', $serverId);
			}

			if ($serverId !== null && $server->id != 0) {
				$instanceId = $this->getRequest()->getParam('instance');
				// Number should be IDs
				if (preg_match('/^(\d+)$/', $instanceId)) {
					$instance = R::load('instance', $instanceId);
					if ($instance->id == 0) {
						$instance = $server->box()->getInstance($instanceId, $recipe);
					} elseif($server->type === 'dev') {
						// Issue rehash, when appropiate
						if ($d->model->source === null || $d->model->source->unbox()->id !== $instance->unbox()->id) {
							// Hash is broken!
							//$d->model->tempHashFilePid = $instance->startFileHash();
							//$d->model->tempHashDbPid = $instance->startDbHash();
						}
					}
				} else {
					$instance = $server->box()->getInstance($instanceId, $recipe);
				}
				if ($instance !== null) {
					$d->model->source = $instance->unbox();
					$this->_helper->Redirector->gotoSimple('target');
				}
			}
		}

		$servers = R::find('server');

		// Read the selected and populate applications menu
		$server = $d->model->getSourceServer();
		$this->view->server = $server;
		if ($server !== null) {
			$this->view->instances = $server->box()->getInstances($recipe);
		} else {
			$this->view->instances = null;
		}

		$instance = $d->model->getSourceInstance();
		if ($instance !== null) {
			$this->view->instance = $instance;
		} else {
			$this->view->instance = null;
		}

		// Need to box to by-pass the method_exists check which does not like __call
		$this->view->servers = $servers;
		$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('instances', 'index', 'default');
	}

	public function instancesAction() {
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			$d = new Zend_Session_Namespace('d');
			if (isset($d->model)) {
				$recipe = $d->model->getRecipe();
			} else {
				$recipe = null;
			}

			$output = array();

			if ($this->getRequest()->isPost()) {
				$serverId = $this->getRequest()->getParam('serverId');
				$filterNew = $this->getRequest()->getParam('filterNew', false);
				$filterSource = $this->getRequest()->getParam('filterSource', false);
				if ($serverId !== null) {
					$server = R::load('server', $serverId);
				}

				if ($serverId !== null && $server->id != 0) {
					$instances = $server->box()->getInstances($recipe);
					if ($filterSource && isset($d->model)) {
						$source = $d->model->getSourceInstance();
						if ($source !== null) {
							$source = $source->id;
						}
					} else {
						$source = null;
					}
					// json doesn't like beans, so transform those. In the transformation, remove new instancess. 
					foreach ($instances as $label => $serverInstance) {
						if (is_array($serverInstance)) {
							$newServerInstance = array();
							foreach ($serverInstance as $instance) {
								if (($instance->id != 0 || !$filterNew) && ($instance->id !== $source || !$filterSource)) {
									$id = ($instance->id != 0) ? $instance->id : $instance->box()->identifier;
									$newServerInstance[$id] = $instance->getName();
								}
							}

							if (!empty($newServerInstance)) {
								$output[$label] = $newServerInstance;
							}
						} else {
							if (($serverInstance->id != 0 || $filterNew) && ($serverInstance->id !== $source || !$filterSource)) {
								$id = ($serverInstance->id != 0) ? $serverInstance->id : $serverInstance->box()->identifier;
								$output[$id] = $serverInstance->getName();
							}
						}
					}
				}
			}

			print Zend_Json::encode($output);
		} else {
			throw new Zend_Controller_Action_Exception('This page does not exist', 404);
		}
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
				$instance = null;

				if ($serverId !== null) {
					$server = R::load('server', $serverId);
					if ($server->id !== 0) {
						$options = array();
						
						$fields = $server->box()->getAdditionalFields();
						
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
						
						$instances = R::find('instance', ' server_id = ? AND application_id = ?', array($server->id, $d->model->getApplication()->id));

						R::preload($instances, array('setting'));
						foreach ($instances as $inst) {
							if ($inst->box()->url == preg_replace('`^(.+?)://(www\.|)`i', '', $this->getRequest()->getParam('url'))) {
								$options['instance'] = $inst->id;
								break;
							}
						}
						
						if (isset($options['instance'])) {
							$source = $d->model->getSourceInstance();
							if ($source !== null) {
								$source = $source->id;
							}
							if ($options['instance'] == $source) {
								$errors['url'] = 'Source and target should not be the same!';
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
				if (isset($d->model->box()->tempTargetOptions)) {
					$options = $d->model->box()->tempTargetOptions;
				} else {
					$options = array();
				}
				$instance = $d->model->getTargetInstance();
				if ($instance !== null && $instance->id != 0) {
					$options['instance'] = $instance->id;
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
						$instance = $this->getRequest()->getParam('instance');
						if ($instance === null || !preg_match('/^(\d+)$/', $instance)) {
							$fields = $server->box()->getAdditionalFields();
							$options = array();
							foreach ($fields as $name => $field) {
								$options[$name] = $this->getRequest()->getParam($name);
							}
							$this->view->errors = $server->box()->validateOptions($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
							if ($this->view->errors === null) {
								// This search is redundant!
								$instances = R::find('instance', ' server_id = ? AND application_id = ?', array($server->id, $d->model->getApplication()->id));

								$instance = null;
								R::preload($instances, array('setting'));
								foreach ($instances as $inst) {
									if ($inst->box()->url == preg_replace('`^(.+?)://(www\.|)`i', '', $this->getRequest()->getParam('url'))) {
										$instance = $inst->box();
										break;
									}
								}
								
								if ($instance !== null) {
									$source = $d->model->getSourceInstance();
									if ($source !== null) {
										$source = $source->id;
									}
									if ($instance->id == $source) {
										$errors['url'] = 'Source and target should not be the same!';
									}
								}
								
								if ($instance === null) {
									$instance = $server->box()->addApplication($d->model->getApplication()->box(), $this->getRequest()->getParam('url'), $options);
								}
							}
						} else {
							$instance = R::load('instance', $instance);
							if ($instance->id == 0) {
								$this->view->errors['instance'] = 'This instance does not exist!';
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

			$this->view->servers = R::find('server');
			$this->view->source_project = $d->model->box()->sourceProject;
			$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('target', 'index', 'default');
			$this->view->callbackUrlInstance = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('instances', 'index', 'default');
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

		if ($recipe->validateFolder($d->model->getTargetInstance()->getFilesystem())) {
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
	
	/**
	 * Bookmark action
	 * Prepares a deployment based on a simple link for the really frequent updates!
	 */
	public function bookmarkAction() {
		$source = $this->getRequest()->getParam('source');
		$target = $this->getRequest()->getParam('target');
		$errors = true;
		// Are set and not the same... Do not want ppl trying to deploy from and to the same place
		if ($source !== null && $target !== null && $source !== $target) {
			$source = R::load('instance', $source);
			$target = R::load('instance', $target);
			if ($source->id != 0 && $target->id != 0) {
				$recipe = $target->getDeploymentRecipe();
				if ($recipe === null) {
					$recipe = $source->getDeploymentRecipe();
				}
				if ($recipe !== null) {
					$deployment = R::dispense('deployment');
					$deployment->box()->setRecipe(new Model_Recipe(null, $recipe));
					$deployment->box()->source = $source;
					$deployment->box()->remote = $target;
					$d = new Zend_Session_Namespace('d');
					if ($d->model->id == 0 || $this->getRequest()->isPost()) {
						$d->model = $deployment;
						$this->_helper->Redirector->gotoSimple('detection');
					} else {
						$errors = false;
					}
				}
			}
		}
		
		if ($errors === true) {
			$this->_helper->Redirector->gotoSimple('index');
		}
		
		$this->view->source = $source->id;
		$this->view->target = $target->id;
	}
}