<?php
/**
 * Servers responsible for instance management on them
 */
abstract class Model_Server_Abstract {
	/**
	 * Holds the Redbean model of this server.
	 * @var Model_Server
	 */
	protected $server;
	
	/**
	 * Constructor which adds the redbean model
	 * @param Model_Server $server
	 */
	public function __construct($server) {
		$this->server = $server;
	}
	
	/**
	 * Shortcut to the RedBean Bean
	 * @return RedBean_OODBBean
	 */
	public function unbox() {
		return $this->server->unbox();
	}
	
	/**
	 * returns the actual instance of the server
	 * @return Model_Server_Abstract
	 */
	public function serverBox() {
		return $this;
	}
	
	/**
	 * Returns an associative array
	 * @return array Associative array with input fields as key and full names as value
	 */
	public function getAdditionalFields() {
		return array();
	}
	
	/**
	 * Checks the input from the target screen.
	 * @param Model_Application $application
	 * @param string $url The desired URL
	 * @param string $options 
	 * @return array|null Array with error or null when the parameters are valid.
	 */
	abstract function validateOptions(Model_Application $application, $url, $options);
	
	/**
	 * Creates an instance of an application and prepares it with settings of the server
	 * @return Model_Instance The instance of the application on that server
	 */
	abstract public function addApplication(Model_Application $application, $url, $options=array());
	
	/**
	 * Lists all applications for that recipe on that server.
	 * @param Model_Recipe $recipe The recipe which checks the application before returning it.
	 * @return array Array with Model_Application
	 */
	abstract public function getInstances($recipe = null);
	
	/**
	 * Get an instance using an indentifier. This allows instances which are not saved 
	 * in the database to be selected in the application screen.
	 * @param String $uniqueIdentifier The unique identifier
	 * @param Model_Recipe $recipe The recipe which checks the application before returning it.
	 * @return Model_Instances The Model of the Instance.
	 */
	abstract public function getInstance($uniqueIdentifier, $recipe = null);
	
	
	/**
	 * Generates a name for an instance. This could be saved in the instance, 
	 * but that would mean manually naming all currently existing instances.
	 * @param Model_Instance $instance The instance to generate a name for.
	 * @return String The name
	 */
	abstract public function getInstanceName(Model_Instance $instance);
	
	/**
	 * Returns the properties of this server
	 * @return array
	 */
	public function getProperties() {
		$bean = $this->unbox();
		$properties = array();
		$props = $bean->getProperties();
		foreach ($props as $k => $v) {
			if (!is_array($v) && !is_object($v) && $k !== 'id') {
				$properties[$k] = $v;
			}
		}
		return $properties;
	}
	
	/**
	 * Magic Getter to make the bean properties available from
	 * the $this-scope.
	 *
	 * @param string $prop property
	 *
	 * @return mixed $propertyValue value
	 */
	public function __get( $prop ) {
		return $this->unbox()->$prop;
	}

	/**
	 * Magic Setter to make the bean properties changable from
	 * the $this-scope.
	 *
	 * @param string $prop  property
	 * @param mixed  $value value
	 */
	public function __set( $prop, $value ) {
		$this->unbox()->$prop = $value;
	}

	/**
	 * Isset implementation to check for the existing of properties
	 * in the $this-scope
	 *
	 * @param  string $key key
	 *
	 * @return boolean Existance of the property
	 */
	public function __isset($prop) {
		return (isset($this->unbox()->$prop));
	}
	
}
