<?php

class Model_Auth implements Zend_Auth_Adapter_Interface {

	public $email;
	public $password;

	/**
	 * Sets username and password for authentication
	 *
	 * @return void
	 */
	public function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Performs an authentication attempt
	 *
	 * @throws Zend_Auth_Adapter_Exception If authentication cannot
	 *                                     be performed
	 * @return Zend_Auth_Result
	 */
	public function authenticate() {
		$config = Zend_Registry::get('config');
		if ($this->username === $config->auth->username && $this->password === $config->auth->password) {
			return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $this->username);
		} else {
			return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID, null);
		}
		return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_UNCATEGORIZED, null);
	}

}
