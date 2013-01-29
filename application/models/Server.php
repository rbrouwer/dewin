<?php
/**
 * This class' functions are being interface for one of the Model_Server_ classes 
 * and form a interface between redbean and the actual class
 */
class Model_Server extends RedBean_SimpleModel {
	/**
	 * Hold the instance of this class
	 * @var Model_Server_Abstract
	 */
	private $instance;

	/**
	 * Initializes the type of a server.
	 * @throws Exception Throws an exception when a server has a type that is not known.
	 */
	public function open() {
		$type = $this->bean->type;
		$class = get_class() . '_' . ucfirst($type);
		if (class_exists($class)) {
			$this->instance = new $class($this);
			if (method_exists($this->instance, 'open')) {
				$this->instance->open();
			}
		} elseif ($class !== null) {
			throw new Exception('Server "'.ucfirst($type).'" type does not exist');
		}
	}
	
	/**
	 * Sets the type of a server and initializes an instance for that.
	 * @param string $value
	 * @throws Exception
	 */
	private function setType($value) {
		$class = get_class() . '_' . ucfirst($value);
		if (class_exists($class)) {
			$this->instance = new $class($this);
			if (method_exists($this->instance, 'open')) {
				$this->instance->open();
			}
			$this->bean->type = $value;
		} else {
			throw new Exception('Server "'.ucfirst($value).'" type does not exist');
		}
	}
	
	/**
	 * Magic Getter which forwards all requests to the server instance
	 *
	 * @param string $prop property
	 *
	 * @return mixed property's value
	 */
	public function __get( $prop ) {
		if (isset($this->instance->$prop)) {
			return $this->instance->$prop;
		}
	}
	
	/**
	 * Magic Setter which forwards all requests to the server instance
	 * The type property is handled in this instance
	 * @param string $prop  property
	 * @param mixed  $value value
	 */
	public function __set( $prop, $value ) {
		if ($prop === 'type') {
			$this->setType($value);
		} else {
			$this->instance->$prop = $value;
		}
	}

	/**
	 * Magic Setter which forwards all requests to the server instance
	 * 
	 * @param  string $key key
	 *
	 * @return
	 */
	public function __isset($key) {
		if ($key === 'type') {
			return true;
		} else {
			return (isset($this->instance->$key));
		}
	}
	
	/**
	 * Isset implementation which forwards all requests to the server instance
	 *
	 * @param  string $key key
	 *
	 * @return
	 */
	public function __unset($key) {
		if ($key === 'type') {
			$this->instance = null;
			unset($this->unbox()->$key);
		} else {
			unset($this->instance->$key);
		}
	}
	
	/**
	 * Call implementation which forwards all requests to the server instance.
	 * @param string $name
	 * @param string $arguments
	 */
	public function __call($name, $arguments) {
		return call_user_func_array(array($this->instance, $name), $arguments);
	}
	
	/**
	 * Returns all properties of this server.
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
}
