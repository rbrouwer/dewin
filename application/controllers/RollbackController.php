<?php

class RollbackController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function indexAction() {
		$d = new Zend_Session_Namespace('d');
		if ($this->getRequest()->isPost()) {
			$d->unsetAll();
			$deploymentRollback = R::load('deployment', $this->getRequest()->getParam('deployment'));
			if ($deploymentRollback->id != 0) {
				$d->prevModel = $deploymentRollback;
				$d->model = R::dispense('deployment');
				$d->model->target = $deploymentRollback->fetchAs('instance')->target;
				$d->model->setRecipe($deploymentRollback->getRecipe());
				
				//Deployment Copy-Properties method?
				foreach ($deploymentRollback->ownProperty as $property) {
					if ($property->name !== 'deploymentDate' && $property->name !== 'deploymentDir') {
						$name = $property->name;
						$d->model->$name = $property->value;
					}
				}
				$d->model->box()->deploymentDateSource = $deploymentRollback->box()->deploymentDate;
				$previousDeployment = R::findOne('deployment', ' success = ? AND target_id = ? AND id < ? ORDER BY `id` DESC LIMIT 1', array(1, $deploymentRollback->target_id, $deploymentRollback->id));
				if ($previousDeployment === null) {
					$d->model->box()->deploymentDate = '00000000000000';
				} else {
					foreach ($previousDeployment->ownProperty as $property) {
						if ($property->name === 'deploymentDate') {
							$d->model->box()->deploymentDate = $property->value;
						}
					}
				}
				$d->model->type = 'rollback';
				$d->model->deployment = $deploymentRollback;
				$d->model->prepareDeployment();
				$d->model->initiateDeployment();
				$this->_helper->Redirector->gotoSimple('pilot');
			}
		}
		$this->view->deployments = R::find('deployment', ' success = ?', array(1));
	}
	
	public function pilotAction() {
		$d = new Zend_Session_Namespace('d');
		
		if (isset($d->model)) {
			$recipe = $d->model->getRecipe();
			$this->view->debugMode = $recipe->getDebugMode();
		} else {
			$this->_helper->Redirector->gotoSimple('index');
		}

		if ($d->model->unbox()->type === null) {
			$this->_helper->Redirector->gotoSimple('index');
		}
		
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('restart') === 'Restart') {
			$process = $d->model->getRunningProcess();
			$process->ownStdin = array();
			$process->ownStdout = array();
			$process->status = 'Queued';
			R::store($process);
			$d->model->initiateDeployment();
		}

		$this->view->title = 'Rollback Pilot';
		$this->view->text = 'Please wait...';
		$this->view->initialProgressText = 'Detecting Database Changes';
		$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('deployment', 'index', 'default');
		$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'rollback', 'default');
		$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('rollback', 'rollback', 'default');
		$this->view->defaultProcessText = 'Detecting Database Changes...';
		$this->_helper->viewRenderer->renderBySpec('waiting', array('module' =>
'default', 'controller' => 'index'));
	}

	public function rollbackAction() {
		$d = new Zend_Session_Namespace('d');
		$dbStrategies = array('0' => 'Do not touch database', '1' => 'Restore database');
		if ($this->getRequest()->isPost()) {
			$errors = array();
			$dbStrategy = $this->getRequest()->getParam('dbStrategy');
			if (in_array($dbStrategy, array_keys($dbStrategies))) {
				if ($dbStrategy == 1) {
					@rename($d->model->box()->deploymentDir . '/sql/sql.full.sql', $d->model->box()->deploymentDir . '/sql/sql.sql');
					@unlink($d->model->box()->deploymentDir . '/sql/sql.patch.sql');
				} else {
					@unlink($d->model->box()->deploymentDir . '/sql/sql.full.sql');
					@unlink($d->model->box()->deploymentDir . '/sql/sql.patch.sql');
				}
			} else {
				$errors['dbStrategy'] = 'You must select a database upgrade strategy';
			}

			if ($errors) {
				$this->view->errors = $errors;
			} else {
				$d->model->initiateDeployment();
				$this->_helper->Redirector->gotoSimple('deployment');
			}
		}
		$this->view->deployment = $d->prevModel;
		$this->view->dbChanged = file_exists($d->model->box()->deploymentDir . '/sql/sql.patch.sql');
		$this->view->dbStrategies = $dbStrategies;
	}

	public function deploymentAction() {
		$d = new Zend_Session_Namespace('d');
		$this->_helper->viewRenderer('waiting');
		if (isset($d->model)) {
			$recipe = $d->model->getRecipe();
			$this->view->debugMode = $recipe->getDebugMode();
		} else {
			$this->_helper->Redirector->gotoSimple('index');
		}

		if ($d->model->unbox()->type === null) {
			$this->_helper->Redirector->gotoSimple('index');
		}
		
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('restart') === 'Restart') {
			$process = $d->model->getRunningProcess();
			$process->ownStdin = array();
			$process->ownStdout = array();
			$process->status = 'Queued';
			R::store($process);
			$d->model->initiateDeployment();
		}

		$this->view->title = 'Deploying';
		$this->view->text = 'Please wait...';
		$this->view->initialProgressText = 'Starting deployment process';
		$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('deployment', 'index', 'default');
		$this->view->successUrl = $this->view->failUrl= $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('deployment', 'rollback', 'complete');
		$this->view->defaultProcessText = 'Deploying...';
	}

	public function completeAction() {
		$d = new Zend_Session_Namespace('d');
		if (!isset($d->model)) {
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		if ($this->getRequest()->isPost()) {
			Zend_Session::namespaceUnset('d');
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		$processes = $d->model->getProcesses();
		$stdouts = array();
		$this->view->success = true;
		if ($processes) {
			R::preload($processes, array('stdout'));
			foreach ($processes as $process) {
				$procStdouts = $process->ownStdout;
				foreach ($procStdouts as $stdout) {
					$stdouts[] = $stdout;
				}
				if ($process->exitcode != 0) {
					$this->view->success = false;
				}
			}
		}

		if ($stdouts) {
			$this->view->stdouts = $stdouts;
		} else {
			$this->view->stdouts = null;
		}

		$this->view->url = 'http://' . $d->model->box()->remoteUrl;
	}

}