<?php

class App_Controller_Plugin_Auth extends Zend_Controller_Plugin_Abstract {

	public function preDispatch(Zend_Controller_Request_Abstract $request) {
					error_reporting(E_ALL);
			ini_set('display_errors', '1');
		//Auth session for a transparant login.
		$preRewriteRequest = clone $request;
		$auth = Zend_Auth::getInstance();
		if (!is_file(APPLICATION_PATH . '/../library/RedBean/rb.php') || !is_file(APPLICATION_PATH . '/configs/config.xml') || is_file(APPLICATION_PATH . '/controllers/SetupController.php')) {
			if (($request->getModuleName() !== 'default' || $request->getControllerName() !== 'setup') &&
					($request->getModuleName() !== null || $request->getControllerName() !== 'setup') && 
					($request->getModuleName() !== 'default' || $request->getControllerName() !== 'error')) {
				$request->setModuleName('default')->setControllerName('setup')->setActionName('index');
			}
		} elseif (!$auth->hasIdentity()) {
			if (($request->getModuleName() !== 'default' || $request->getControllerName() !== 'authentication' || $request->getActionName() !== 'login') && 
					  ($request->getModuleName() !== null || $request->getControllerName() !== 'process' || $request->getActionName() !== 'cli') && 
					  ($request->getModuleName() !== null || $request->getControllerName() !== 'hash') && 
					  ($request->getModuleName() !== null || $request->getControllerName() !== 'test') && 
					  ($request->getModuleName() !== 'default' || $request->getControllerName() !== 'error')) {
				$this->handleNonAuthenticatedRequest($preRewriteRequest);
				$request->setModuleName('default')->setControllerName('authentication')->setActionName('login');
			}
		} else {
			Zend_Session::namespaceUnset('authRequest');
		}
	}

	private function handleNonAuthenticatedRequest(Zend_Controller_Request_Abstract $request) {
		$authSession = new Zend_Session_Namespace('authRequest');
		if (isset($authSession->request)) {
			if ($request->getModuleName() !== 'default' && $request->getControllerName() !== 'authentication' && $request->getActionName() !== 'login' &&
					  $request->getModuleName() !== $authSession->request->getModuleName() &&
					  $request->getControllerName() !== $authSession->request->getControllerName() &&
					  $request->getActionName() !== $authSession->request->getActionName()) {
				$authSession->request = $request;
			}
		} elseif ($request->getModuleName() !== 'default' || $request->getControllerName() !== 'authentication' || $request->getActionName() !== 'login') {
			$authSession->request = $request;
		}
	}

}
