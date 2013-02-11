<?php

class UpgradeController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
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
			$this->_helper->Redirector->gotoSimple('detection');
		}
		
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('restart') === 'Restart') {
			$process = $d->model->getRunningProcess();
			$process->ownStdin = array();
			$process->ownStdout = array();
			$process->status = 'Queued';
			R::store($process);
			$d->model->initiateDeployment();
		}
		
		$this->_helper->viewRenderer('waiting');
		$this->view->title = 'Gathering information';
		$this->view->text = 'Please wait...';
		$this->view->initialProgressText = 'Starting process';
		$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('deployment', 'index', 'default');
		$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'upgrade', 'default');
		$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('database', 'upgrade', 'default');
		$this->view->defaultProcessText = 'Gathering information...';
		$this->_helper->viewRenderer->renderBySpec('waiting', array('module' => 'default', 'controller' => 'index'));
	}

	public function databaseAction() {
		$d = new Zend_Session_Namespace('d');
		if (isset($d->model)) {
			$recipe = $d->model->getRecipe();
		} else {
			$this->_helper->Redirector->gotoSimple('index');
		}
		
		$dbStrategies = array('0' => 'Add missing structures', '1' => 'Do nothing', '2' => 'Manual');
		$databases = $d->model->box()->getRecipe()->getDatabases();
		$dbChanged = false;
		foreach($databases as $database) {
			$patch = new Model_SqlPatch($d->model->box()->deploymentDir . DIRECTORY_SEPARATOR . $database->pathPatch);
			$patch->resetPosition();
			if ($patch->nextQuery() !== null) {
				 $dbChanged = true;
				$patch->resetPosition();
			}
			$patches[$database->name] = $patch;
		}
		$this->view->dbChanged = $dbChanged;
		$this->view->sqlPatch = $patches;
		
		if ($this->getRequest()->isPost()) {
			$errors = array();
			if ($dbChanged) {
				$d->sqlstrat = array();
				foreach($patches as $name => $patch) {
					$dbStrategy = $this->getRequest()->getParam('dbStrategy');
					if (in_array($dbStrategy[$name], array_keys($dbStrategies))) {
						$d->sqlstrat[$name] = $dbStrategy[$name];
					} else {
						$errors['dbStrategy'][$name] = 'You must select a database upgrade strategy';
					}
				}

				$d->sql = $this->getRequest()->getParam('sql', array());
			} else {
				foreach($patches as $name => $patch) {
					$d->sqlstrat[$name] = 1;
					$d->sql[$name] = array();
				}
			}
			if ($errors) {
				$this->view->errors = $errors;
			} else {
				$this->_helper->Redirector->gotoSimple('file');
			}
		} elseif (isset($d->sqlstrat) && isset($d->sql)) {
			$this->view->dbStrategy = $d->sqlstrat;
			$this->view->sql = $d->sql;
		} else {
			$sql = array();
			foreach($patches as $name => $patch) {
				$patch->resetPosition();
				
				while (($stmt = $patch->nextQuery())) {
					if ($stmt !== null) {
						$sql[$name][] = $patch->getPosSql();
					}
				}
			}
			$this->view->sql = $sql;
		}
		$this->view->dbStrategies = $dbStrategies;
	}

	public function fileAction() {
		$d = new Zend_Session_Namespace('d');
		if (isset($d->model)) {
			$recipe = $d->model->getRecipe();
		} else {
			$this->_helper->Redirector->gotoSimple('index');
		}
		if (!isset($d->sqlstrat)) {
			$this->_helper->Redirector->gotoSimple('database');
		}

		$this->view->changeSet = new Model_Rsync_ItemizeChangeset($d->model->box()->deploymentDir . DIRECTORY_SEPARATOR.$recipe->getRsyncItemizedOutput());
		if ($this->getRequest()->isPost()) {
			$databases = $recipe->getDatabases();
			foreach($databases as $database) {
				$patch = new Model_SqlPatch($d->model->box()->deploymentDir . DIRECTORY_SEPARATOR . $database->pathPatch);
				$sqlstrat = $d->sqlstrat[$database->name];
				if ($sqlstrat == 0 || $sqlstrat == 2) {
					if ($sqlstrat == 0) {
						$patch->buildAddMissingSqlPatchFile();
					}
					if ($sqlstrat == 2) {
						$patch->buildSqlPatchFile($d->sql[$database->name]);
					}
					$patch->movePatch($d->model->box()->deploymentDir  . DIRECTORY_SEPARATOR . $database->pathDeploy);
				} elseif ($d->sqlstrat == 1) {
					$patch->removePatch();
				}
				@unlink($d->model->box()->deploymentDir  . DIRECTORY_SEPARATOR . $database->pathFull);
			}
			
			$fileIds = $this->getRequest()->getParam('file');
			if ($fileIds !== null) {
				$this->view->changeSet->buildFileList($this->getRequest()->getParam('file', array()), $d->model->box()->deploymentDir . DIRECTORY_SEPARATOR . $recipe->getRsyncFileList());
				$d->model->box()->rsyncFile = $recipe->getRsyncFileList();
			}
			$fileuploadStrategy = $this->getRequest()->getParam('fileStrat');
			if ($fileuploadStrategy === 'nobackup') {
				$d->model->box()->fileuploadStrategy = 'UpgradeQuick';
				$d->model->rollback = 0;
			} else {
				$d->model->box()->fileuploadStrategy = 'Upgrade';
			}

			$d->model->initiateDeployment();
			$this->_helper->Redirector->gotoSimple('deployment');
		}

		$this->view->file = array_keys($this->view->changeSet->getItemizeChangeset());
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
			$this->_helper->Redirector->gotoSimple('detection');
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
		$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'upgrade', 'default');
		$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'upgrade', 'default');
		$this->view->defaultProcessText = 'Deploying...';
		$this->_helper->viewRenderer->renderBySpec('waiting', array('module' => 'default', 'controller' => 'index'));
	}

	public function completeAction() {
		$d = new Zend_Session_Namespace('d');
		if (!isset($d->model)) {
			$this->_helper->Redirector->gotoSimple('index', 'default');
		}

		if ($this->getRequest()->isPost()) {
			$d->unsetAll();
			$this->_helper->Redirector->gotoSimple('index', 'default');
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
		
		// Favorites link
		$this->view->remoteUrl = $d->model->box()->remoteUrl;
		$this->view->source_id = $d->model->box()->source->id;
		$this->view->target_id = $d->model->box()->target->id;
	}

}