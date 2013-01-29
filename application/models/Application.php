<?php
/**
 * An application... It doesn't do much yet!
 */
class Model_Application extends RedBean_SimpleModel {

	/**
	 * Returns a nicer formatter name for the select-box base in the application path(path on the dev server)
	 * @return string
	 */
	public function getName() {
		$a = explode('/', $this->path);
		return $a[(count($a) - 2)] . ' - ' . $a[(count($a) - 1)];
	}
	
	/**
	 * 
	 */
	public function getProjectName() {
		$a = explode('/', $this->path);
		return $a[(count($a) - 1)];
	}
}