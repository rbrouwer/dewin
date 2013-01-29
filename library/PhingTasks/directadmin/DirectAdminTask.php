<?php

require_once 'phing/Task.php';

class DirectAdminTask extends Task {

	/**
	 * Holds the version number
	 * 
	 * @var string
	 */
	protected $version = "1.0";

	/**
	 * Holds DirectAdmin's host
	 *
	 * @var string
	 */
	protected $_host = null;

	/**
	 * Holds DirectAdmin's port
	 *
	 * @var int
	 */
	protected $_port = 2222;

	/**
	 * Whether to enable detailed logging
	 *
	 * @var boolean
	 */
	protected $_verbose = false;

	/**
	 * Whether to follow redirects
	 * 
	 * @var boolean
	 */
	protected $_followRedirects = true;

	/**
	 * Holds name of the api to be called.
	 *
	 * @var string
	 */
	protected $_api = null;

	/**
	 * Holds config data for the call the task should make
	 *
	 * @var array<Parameter>
	 */
	protected $_apiParams = array();

	/**
	 * Holds the authentication user name
	 *
	 * @var string
	 */
	protected $_authUser = null;

	/**
	 * Holds the authentication password
	 *
	 * @var string
	 */
	protected $_authPassword = '';

	/**
	 * Holds the authentication child user name
	 *
	 * @var string
	 */
	protected $_authLoginAsUser = '';

	/**
	 * Holds the authentication scheme
	 *
	 * @var string
	 */
	protected $_authScheme;

	/**
	 * Holds the events that will be logged
	 *
	 * @var array<string>
	 */
	protected $_observerEvents = array(
			'connect',
			'sentHeaders',
			'sentBodyPart',
			'receivedHeaders',
			'receivedBody',
			'disconnect',
	);

	/**
	 * Sets the request host
	 *
	 * @param string $url
	 */
	public function setHost($url) {
		if (($pos = strpos($url, ':')) === false) {
			$this->_host = $url;
		} else {
			$this->_host = substr($url, 0, $pos);
			$this->setPort(substr($url, $pos + 1));
		}
	}

	/**
	 * Sets the request host
	 *
	 * @param int $port
	 */
	public function setPort($port) {
		$this->_port = $port;
	}

	/**
	 * Sets the name of the api call
	 *
	 * @param string $user
	 */
	public function setApi($api) {
		$this->_api = $api;
	}

	/**
	 * Sets the authentication user name
	 *
	 * @param string $user
	 */
	public function setAuthUser($user) {
		$this->_authUser = $user;
	}

	/**
	 * Sets the authentication password
	 *
	 * @param string $password
	 */
	public function setAuthPassword($password) {
		$this->_authPassword = $password;
	}

	/**
	 * Sets the authentication child user name
	 *
	 * @param string $user
	 */
	public function setAuthLoginAs($user) {
		$this->_authLoginAsUser = $user;
	}

	/**
	 * Sets whether to enable detailed logging
	 *
	 * @param boolean $verbose
	 */
	public function setVerbose($verbose) {
		$this->_verbose = StringHelper::booleanValue($verbose);
	}

	/**
	 * Sets whether to follow redirects
	 *
	 * @param boolean $verbose
	 */
	public function setFollowRedirects($followRedirects) {
		$this->_followRedirects = StringHelper::booleanValue($followRedirects);
	}

	/**
	 * Sets a list of observer events that will be logged
	 * if verbose output is enabled.
	 *
	 * @param string $observerEvents List of observer events
	 *
	 * @return void
	 */
	public function setObserverEvents($observerEvents) {
		$this->_observerEvents = array();

		$token = ' ,;';
		$ext = strtok($observerEvents, $token);

		while ($ext !== false) {
			$this->_observerEvents[] = $ext;
			$ext = strtok($token);
		}
	}

	/**
	 * Creates a config parameter for this task
	 *
	 * @return Parameter The created parameter
	 */
	public function createParam() {
		$num = array_push($this->_apiParams, new Parameter());
		return $this->_apiParams[$num - 1];
	}

	/**
	 * Load the necessary environment for running this task.
	 *
	 * @throws BuildException
	 */
	public function init() {
		@include_once 'HTTP/Request2.php';

		if (!class_exists('HTTP_Request2')) {
			$this->_useHTTPRequest2 = false;
		} else {
			$this->_useHTTPRequest2 = true;

			$this->_authScheme = HTTP_Request2::AUTH_BASIC;

			// Other dependencies that should only be loaded
			// when class is actually used
			require_once 'HTTP/Request2/Observer/Log.php';
		}
	}

	/**
	 * The main function of this task called by phing
	 */
	public function main() {
		if (!isset($this->_host)) {
			throw new BuildException("Required attribute 'host' not set");
		}

		if ($this->_useHTTPRequest2) {
			return $this->httpRequestMain();
		} else {
			return $this->fallbackMain();
		}
	}

	/**
	 * Makes a DirectAdmin api call using http_request2 
	 * @return null|array<string> Incase a lists is supplied by the api, this list will be returned. Otherwise null is returned.
	 * @throws BuildException Throws BuildExceptions if no connections could be made or if errors were returned by the api
	 */
	private function httpRequestMain() {
		$request = new HTTP_Request2('http://' . $this->_host . ':' . $this->_port . '/' . $this->_api);

		// Prefer posting to DirectAdmin
		$request->setMethod(HTTP_Request2::METHOD_POST);

		// Set redirects
		$request->follow_redirects = $this->_followRedirects;

		// Set the authentication data
		if (!empty($this->_authUser) && !empty($this->_authPassword)) {
			if (!empty($this->_authLoginAsUser)) {
				$request->setAuth(
								$this->_authUser . '|' . $this->_authLoginAsUser, $this->_authPassword, $this->_authScheme
				);
			} else {
				$request->setAuth(
								$this->_authUser, $this->_authPassword, $this->_authScheme
				);
			}
		} elseif (!empty($this->_sessionId) && !empty($this->_sessionKey)) {
			$request->addCookie('session', $this->_sessionId);
			$request->addCookie('key', $this->_sessionKey);
		}

		// Create post data
		foreach ($this->_apiParams as $param) {
			if ($param->getValue() == 'boolTrue') {
				$request->addPostParameter($param->getName(), 'yes');
			} else {
				$request->addPostParameter($param->getName(), $param->getValue());
			}
		}

		$request->setHeader(array(
				'User-Agent' => "PhingDATask/$this->version",
				'Host' => ( $this->remote_port == 80 ? $this->remote_host : "$this->remote_host:$this->remote_port" ),
				'Accept' => '*/*',
				'Connection' => 'Close'));

		if ($this->_verbose) {
			$observer = new HTTP_Request2_Observer_Log();

			// set the events we want to log
			$observer->events = $this->_observerEvents;

			$request->attach($observer);
		}

		try {
			$response = $request->send();
		} catch (HTTP_Request2_Exception $e) {
			throw new BuildException("Connection failed: " . $e->getMessage());
		}
		//Handle output
		if ($response->getStatus() != 200) {
			throw new BuildException("DirectAdmin returned a wrong status code: " . $response->getStatus() . ' - ' . $response->getReasonPhrase());
		}

		parse_str($response->getBody(), $parsedResponse);
		return $this->processOutput($parsedResponse);
	}

	/**
	 * Makes a DirectAdmin api call using good old sockets 
	 * @return null|array<string> Incase a lists is supplied by the api, this list will be returned. Otherwise null is returned.
	 * @throws BuildException Throws BuildExceptions if no connections could be made or if errors were returned by the api
	 */
	private function fallbackMain() {

		$array_headers = array(
				'User-Agent' => "PhingDATask/$this->version",
				'Host' => ( $this->_host == 80 ? $this->_host : "$this->_host:$this->_port" ),
				'Accept' => '*/*',
				'Connection' => 'Close');

		$pairs = array();
		foreach ($this->_apiParams as $param) {
			if ($param->getValue() == 'boolTrue') {
				$pairs[] = $param->getName() . '=' . urlencode('yes');
			} elseif($param->getValue() == 'boolFalse') {
				$pairs[] = $param->getName() . '=' . urlencode('no');
			}	else {
				$pairs[] = $param->getName() . '=' . urlencode($param->getValue());
			}
		}
		$content = join('&', $pairs);

		$socket = fsockopen($this->_host, $this->_port, $sock_errno, $sock_errstr, 10);

		if (!$socket) {
			throw new BuildException("Connection failed: Can't create socket connection to " . ( $this->_host == 80 ? $this->_host : "$this->_host:$this->_port" ) . "\n" . $sock_errno . ' - ' . $sock_errstr);
		}

		// Set the authentication data
		if (!empty($this->_authUser) && !empty($this->_authPassword)) {
			if (!empty($this->_authLoginAsUser)) {
				$array_headers['Authorization'] = 'Basic ' . base64_encode($this->_authUser . '|' . $this->_authLoginAsUser . ':' . $this->_authPassword);
			} else {
				$array_headers['Authorization'] = 'Basic ' . base64_encode($this->_authUser . ':' . $this->_authPassword);
			}
		} elseif (!empty($this->_sessionId) && !empty($this->_sessionKey)) {
			$array_headers['Cookie'] = 'session=' . $this->_sessionId . '; key=' . $this->_sessionKey;
		}

		if ($this->_method == "POST") {
			$array_headers['Content-type'] = 'application/x-www-form-urlencoded';
			$array_headers['Content-length'] = strlen($content);
			$query = "POST /$this->_api HTTP/1.0\r\n";
		} else {
			$query = "POST /$this->_api?$content HTTP/1.0\r\n";
		}
		
		foreach ($array_headers as $key => $value) {
			$query .= "$key: $value\r\n";
		}
		
		if ($this->_method == "POST") {
			$query .= "\r\n" . $content . "\r\n\r\n";
		} else {
			$query .= "\r\n";
		}

		fwrite($socket, $query, strlen($query));

		$status = socket_get_status($socket);

		$result = '';
		while (!feof($socket) && !$status['timed_out']) {
			$result .= fgets($socket, 1024);
		}

		list($header, $body) = preg_split("/\r\n\r\n/", $result, 2);

		$headers = preg_split("/\r\n/",$header);
		
		$response_headers = array( 0 => $headers[0] );
		unset($headers[0]);
		foreach ( $headers as $pair )
		{
			list($key,$value) = preg_split("/: /",$pair,2);
			$response_headers[strtolower($key)] = $value;
		}
		
		preg_match("#HTTP/1\.. (\d+)#",$response_headers[0],$matches);
		if ($matches[1] !== '200') {
			throw new BuildException("DirectAdmin returned a wrong status code: " . $matches[1]);
		}
		parse_str($body, $parsedResponse);
		return $this->processOutput($parsedResponse);
		
	}

	/**
	 * Processes the parsed output from the directadmin api
	 * @param array $parsedResponse The parsed string returned by DirectAdmin's API 
	 * @return null|array<string> Incase a lists is supplied by the api, this list will be returned. Otherwise null is returned.
	 * @throws BuildException Throws BuildExceptions if the API returned an error
	 */
	private function processOutput($parsedResponse) {
		//Got an error
		if (isset($parsedResponse['error']) && $parsedResponse['error'] === '1') {
			if (isset($parsedResponse['text']) && isset($parsedResponse['details'])) {
				$exception = "DirectAdmin returned the following error:\n" . $parsedResponse['text'] . "\nDetails: " . $parsedResponse['details'];
			} elseif (isset($parsedResponse['text'])) {
				$exception = "DirectAdmin returned the following error:\n" . $parsedResponse['text'];
			} elseif (isset($parsedResponse['details'])) {
				$exception = "DirectAdmin returned the following error:\n" . $parsedResponse['details'];
			} else {
				$exception = "DirectAdmin returned an error.";
			}
			throw new BuildException($exception);
		} elseif (isset($parsedResponse['error']) && $parsedResponse['error'] === '0') {
			if (isset($parsedResponse['text']) && !empty($parsedResponse['text']) && isset($parsedResponse['details']) && !empty($parsedResponse['details'])) {
				$msg = "DirectAdmin returned the following:\n" . $parsedResponse['text'] . "\nDetails: " . $parsedResponse['details'];
			} elseif (!empty($parsedResponse['text'])) {
				$msg = "DirectAdmin returned the following:\n" . $parsedResponse['text'];
			} elseif (!empty($parsedResponse['details'])) {
				$msg = "DirectAdmin returned the following:\n" . $parsedResponse['details'];
			} else {
				$msg = "DirectAdmin returned successfull.";
			}
			$this->log($msg);
			return null;
		} elseif (isset($parsedResponse['list'])) {
			$this->log("DirectAdmin returned a list:\n - " . implode("\n - ", $parsedResponse['list']));
			return $parsedResponse['list'];
		} else {
			$this->log("DirectAdmin returned nothing?");
			return null;
		}
	}

}