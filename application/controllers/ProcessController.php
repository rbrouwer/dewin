<?php

class ProcessController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function cliAction() {
		if (PHP_SAPI === 'cli') {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);
			$processId = $this->getRequest()->getParam(0);
			if ($processId) {
				$process = R::load('process', $processId);
			}
			if ($processId && $process->id !== 0) {
				$process->run();
			}
		} else {
			throw new Zend_Controller_Action_Exception('This page does not exist', 404);
		}
	}

	public function indexAction() {
		//Ajax requests are for sending and receiving to the process.
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			//(Part #1) Sent input into the processManager
			if ($this->getRequest()->isPost()) {
				//If it didn't succeed, it failed.
				$resultArray = array('receive' => false);

				//Read stdin and procesId
				$stdin = $this->getRequest()->getParam('stdin');
				$processId = $this->getRequest()->getParam('processId');
				if ($processId !== null) {
					$process = R::load('process', $processId);
				}
				if ($stdin !== null && $processId !== null && $process->id !== 0) {
					$process->addStdin($stdin);
					$resultArray = array('receive' => true);
				}
			}

			//(Part #2) Request looking for stdouts!
			if (!$this->getRequest()->isPost() || ($this->getRequest()->isPost() && $stdin === null)) {
				$processId = $this->getRequest()->getParam('processId');
				if ($processId !== null) {
					$process = R::load('process', $processId);
				}
				if ($processId !== null && $process->id !== 0) {
					$minId = $this->getRequest()->getParam('minId');
					$resultArray = $process->getStdoutArray($minId);
					$resultArray['sent'] = true;
				} else {
					$resultArray = array('sent' => false);
				}
			}
			print Zend_Json::encode($resultArray);
			
		// Non-ajax requests are handled here...
		} else {
			$this->_helper->layout->setLayout('layout-minimal');
			$processId = $this->getRequest()->getParam('processId');
			if ($processId !== null) {
				$process = R::load('process', $processId);
			}
			if ($processId !== null && $process->id !== 0) {
				$this->view->processId = $process->id;
				$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('index', 'process', 'default');
			} else {
				$this->_helper->redirector('index', 'index');
			}
		}
	}

}

