<?php

class deploymentController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
		
	}

	public function deploymentAction() {
		$d = new Zend_Session_Namespace('d');
		// Check if the session has what is needed to be here
		if (isset($d->model)) {
			// Get debug mode and pass that to javascript from the view.
			$recipe = $d->model->getRecipe();
			$this->view->debugMode = $recipe->getDebugMode();
		//No session, return to index.
		} else {
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}
		
		// If the previous step is not completed, return to the previous step.
		if ($d->model->unbox()->type === null) {
			$this->_helper->Redirector->gotoSimple('detection', 'index');
		}
		
		// Handle the restart request from the debug button
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('restart') === 'Restart') {
			// Reset the 'running' Process. This is usually finished when the debug button is used.
			$process = $d->model->getRunningProcess();
			
			// Remove the input and output from and to the proces.
			$process->ownStdin = array();
			$process->ownStdout = array();
			
			// Set status to be the next to be executed files
			$process->status = 'Queued';
			
			// Save
			R::store($process);
			
			// Start and continue showing the normal waiting page!
			$d->model->initiateDeployment();
		}

		// To prevent 5 time the same view, render the index/waiting.phtml view.
		$this->_helper->viewRenderer('waiting');
		
		// Sent all the text to it.
		$this->view->title = 'Deploying';
		$this->view->text = 'Please wait...';
		$this->view->initialProgressText = 'Starting deployment process';
		$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('deployment', 'index', 'default');
		$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'deployment', 'default');
		$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'deployment', 'default');
		$this->view->defaultProcessText = 'Deploying...';
		
		// To prevent 5 times the same view, render the index/waiting.phtml view.
		$this->_helper->viewRenderer->renderBySpec('waiting', array('module' => 'default', 'controller' => 'index'));
	}

	public function completeAction() {
		// Check if the session has what is needed to be here
		$d = new Zend_Session_Namespace('d');
		if (!isset($d->model)) {
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		// Handle the finish button, unset session and return to index
		if ($this->getRequest()->isPost()) {
			Zend_Session::namespaceUnset('d');
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		// Gather logs/output of all processes and check if every process succeeded.
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

		// Pass the target url to the view.
		$this->view->url = 'http://' . $d->model->box()->remoteUrl;
		
		// Favorites link
		$this->view->remoteUrl = $d->model->box()->remoteUrl;
		$this->view->source_id = $d->model->box()->source->id;
		$this->view->target_id = $d->model->box()->target->id;
	}

}