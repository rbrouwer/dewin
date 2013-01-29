<?php

class AuthenticationController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function loginAction() {
		// Check if the visitor has an identity
		$auth = Zend_Auth::getInstance();
		if ($auth->hasIdentity()) {
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}
		
		
		$errors = array();
		// If there as a request saved, the user attemped to visit a page it could have no permissions for.
		$authSession = new Zend_Session_Namespace('authRequest');
		if (isset($authSession->request) && $authSession->request instanceof Zend_Controller_Request_Abstract) {
			$errors[] = 'You must login to view this page.';
		}
		
		// Handle the login request
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('authUsername') !== null
				   && $this->getRequest()->getParam('authPassword') !== null) {
			// Model Auth checks the details
			$authAdapter = new Model_Auth($this->getRequest()->getParam('authUsername'), $this->getRequest()->getParam('authPassword'));
			
			$result = $auth->authenticate($authAdapter);

			// If the details are valid, redirect the user to the index page (and unset the saved request).
			if ($result->isValid()) {
				// Relic: Someone doesn't want to do tricky redirection back to the page the person couldn't visit.
//				if (isset($authSession->request) && $authSession->request instanceof Zend_Controller_Request_Abstract) {
//					$front = Zend_Controller_Front::getInstance();
//					$front->setRequest($authSession->request);
//					$front->dispatch($authSession->request);
//					$this->_helper->layout()->disableLayout();
//					$this->_helper->viewRenderer->setNoRender(true);
//					$this->getResponse()->clearBody();
//				} else {
					$this->_helper->Redirector->gotoSimple('index', 'index');
//				}
				Zend_Session::namespaceUnset('authRequest');
			// if not valid, produce an error.
			} else {
				$errors[] = 'Your username and/or password are incorrect.';
			}
		}

		// If there is no Identity at this point, prepare to make a login page.
		if (!$auth->hasIdentity()) {
			$this->view->errors = $errors;
		}
	}

	
	public function logoutAction() {
		// Clear identity and redirect slowly to the index page, which cannot be visited when not logged in.
		Zend_auth::getInstance()->clearIdentity();
	}

}