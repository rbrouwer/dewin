<?php

class InstanceController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function indexAction() {
		$instances = R::find('instance');
		foreach ($instances as $id => $instance) {
			if (!$instance->isDeployed()) {
				unset($instances[$id]);
			}
		}
		R::preload($instances, array('property'));
		R::preload($instances, array('application'));
		$this->view->instances = $instances;
	}

	public function addAction() {
		// Disable lay-out and view rendering when this request is an ajax request
		// & Handle Ajax request for this page.
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);


			if ($this->getRequest()->isPost()) {
				$errors = array();
				$fields = null;
				$options = null;
				$valid = false;

				$recipe = Model_Recipe::getRecipe($this->getRequest()->getParam('recipe'));

				if ($recipe === null) {
					$errors['recipe'] = 'You should select a recipe!';
				}

				if (!$errors) {
					$applicationPath = $this->getRequest()->getParam('application');
					$application = R::load('application', $applicationPath);
					if ($application->id === 0) {
						$application = R::dispense('application');
						$application->path = $applicationPath;
					}

					if (!$recipe->validateFolder(new Model_Filesystem_Local($application->path))) {
						$errors['application'] = 'This application is invalid for the selected recipe!';
					}
				}

				$serverId = $this->getRequest()->getParam('serverId');

				if (!$errors && $serverId !== null) {
					$server = R::load('server', $serverId);
					if ($server->id !== 0) {
						$fields = $server->box()->getAdditionalFields();
						$options = array();
						foreach ($fields as $name => $field) {
							$options[$name] = $this->getRequest()->getParam($name);
						}
						$errors = $server->box()->validateOptions($application->box(), $this->getRequest()->getParam('url'), $options);
						if ($errors === null) {
							$instance = $server->box()->addApplication($application->box(), $this->getRequest()->getParam('url'), $options);
							if ($server->id !== 1 && !$instance->validateDb()) {
								if (!$instance->validateDb()) {
									$errors['databasePassword'] = 'The database details are incorrect.';
									$valid = false;
								} else {
									$valid = true;
								}
							} elseif ($instance !== null) {
								$valid = true;
							}
						}
					} else {
						$errors['serverId'] = 'You should select a server!';
					}
				} elseif ($serverId === null) {
					$errors['serverId'] = 'You should select a server!';
				}

				print Zend_Json::encode(array('valid' => $valid, 'fields' => $fields, 'values' => $options, 'errors' => $errors));
			} else {
				$recipe = Model_Recipe::getRecipe($this->getRequest()->getParam('recipe'));
				$servers = R::find('server', 1);
				$applications = $servers['1']->box()->getApplications($recipe);
				print Zend_Json::encode(array('apps' => $applications));
			}
			// Handle normal requests here.
		} else {
			if ($this->getRequest()->isPost()) {
				$errors = array();

				$recipe = Model_Recipe::getRecipe($this->getRequest()->getParam('recipe'));

				if ($recipe === null) {
					$errors['recipe'] = 'You should select a recipe!';
				}

				if (!$errors) {
					$applicationPath = $this->getRequest()->getParam('application');
					$application = R::load('application', $applicationPath);
					if ($application->id === 0) {
						$application = R::dispense('application');
						$application->path = $applicationPath;
					}

					if (!$recipe->validateFolder(new Model_Filesystem_Local($application->path))) {
						$errors['application'] = 'This application is invalid for the selected recipe!';
					}
				}

				$serverId = $this->getRequest()->getParam('serverId');

				if (!$errors && $serverId !== null) {
					$server = R::load('server', $serverId);
					if ($server->id !== 0) {
						$fields = $server->box()->getAdditionalFields();
						$options = array();
						foreach ($fields as $name => $field) {
							$options[$name] = $this->getRequest()->getParam($name);
						}
						$errors = $server->box()->validateOptions($application->box(), $this->getRequest()->getParam('url'), $options);
						if ($errors === null) {
							$instance = $server->box()->addApplication($application->box(), $this->getRequest()->getParam('url'), $options);
							if ($server->id !== 1 && !$instance->validateDb()) {
								$errors['databasePassword'] = 'The database details are incorrect.';
							}
						}
					} else {
						$errors['serverId'] = 'You should select a server!';
					}
				} elseif ($serverId === null) {
					$errors['serverId'] = 'You should select a server!';
				}
				if ($errors) {
					$this->view->errors = $errors;
				} else {
					R::store($application);
					R::store($instance);
					$deployment = R::dispense('deployment');
					$deployment->setRecipe($recipe);
					$deployment->target = $instance;
					$deployment->type = 'deploy';
					$deployment->success = true;
					$deployment->false = false;
					R::store($deployment);
					
					$this->_helper->Redirector->gotoSimple('index');
				}
			}

			$servers = R::find('server');
			$this->view->recipes = Model_Recipe::getRecipes();
			$this->view->servers = $servers;
			$this->view->applications = $servers['1']->box()->getApplications();
			$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('add', 'instance', 'default');
		}
	}

}