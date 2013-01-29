<?php

require_once 'phing/tasks/dewin/directadmin/DirectAdminTask.php';

class DirectAdminCondition extends DirectAdminTask implements Condition {

	protected $_regex;
	protected $_needle;
	protected $_property;
	protected $_value = 'true';

	public function setRegex($regex) {
		$this->_regex = $regex;
	}

	public function setNeedle($needle) {
		$this->_needle = $needle;
	}

	public function setProperty($property) {
		$this->_property = $property;
	}

	public function setValue($value) {
		$this->_value = $value;
	}
	
	public function evaluate() {
		if (empty($this->_regex) && empty($this->_needle)) {
			throw new BuildException("Required attribute 'regex' or 'needle' not set");
		}

		$list = parent::main();
		if ($list !== null) {
			if (!empty($this->_regex)) {
				foreach ($list as $item) {
					$matches = array();
					preg_match($this->_regex, $item, $matches);

					if (count($matches) !== 0) {
						$this->log('The item "'.$item.'" of that list matched the supplied regex.');
						return true;
					}
				}
			}
			if (!empty($this->_needle) && in_array($this->_needle, $list)) {
				$this->log('The item "'.$this->_needle.'" is found in that list.');
				return true;
			}
			if (!empty($this->_regex) && !empty($this->_needle)) {
				$this->log('No items matched the supplied regex and "'.$this->_needle.'" is not found in that list.');
			} elseif(!empty($this->_regex)) {
				$this->log('No items matched the supplied regex in that list.');
			} else {
				$this->log('"'.$this->_needle.'" is not found in that list.');
			}
		} else {
			$this->log('Nothing can be found in a empty list.');
		}
		return false;
	}

	public function main() {
		if (empty($this->_property)) {
			throw new BuildException("Required attribute 'property' not set");
		}
		
		if ($this->evaluate()) {
			$this->project->setProperty($this->_property, $this->_value);
			$this->log('Property "'.$this->_property.'" has been set to: "'.$this->_value.'".');
		}
	}

}
