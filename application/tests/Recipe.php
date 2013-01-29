<?php

class Test_Recipe {

	public function pack() {
		echo 'Testing Model Recipe:' . PHP_EOL;
		$config = Zend_Registry::get('config');
		$config->directories->buildscript = '/tests/files/buildscripts/';
		$this->construct();
		$this->staticFunction();
		$this->validateFolder();
	}
	
		
	public function staticFunction() {
		echo '- Testing Recipe Fetching' . PHP_EOL;
		$oldConfig = Zend_Registry::get('config');
		$config = new Zend_Config(array('directories' => array('buildscript' => APPLICATION_PATH.'/tests/files/buildscripts/')));
		Zend_Registry::set('config', $config);
		
		$recipe = Model_Recipe::getRecipe('recipe0.xml');
		_equals($recipe, null);
		$recipe = Model_Recipe::getRecipe('recipe1.xml');
		_equals($recipe instanceof Model_Recipe, true);
		$recipes = Model_Recipe::getRecipes();
		_equals(count($recipes), 1);
		_equals(current($recipes)->getPath(), $recipe->getPath());
		echo PHP_EOL;
		
		Zend_Registry::set('config', $oldConfig);
	}

	public function construct() {
		echo '- Testing Recipe Loading' . PHP_EOL;
		$oldConfig = Zend_Registry::get('config');
		$config = new Zend_Config(array('directories' => array('buildscript' => APPLICATION_PATH.'/tests/files/buildscripts/')));
		Zend_Registry::set('config', $config);
		try {
			new Model_Recipe(null, null);
		} catch (Exception $e) {
		}
		_equals($e->getMessage(), 'Invalid input; Either filename or path is required!');
		
		
		try {
			$recipe = new Model_Recipe('recipe0.xml');
		} catch (Exception $e) {
		}
		_equals($e->getMessage(), 'File does not exist, or is unreadable.');
		
		try {
			$recipe = new Model_Recipe('recipe1.xml', $config->directories->buildscript);
		} catch (Exception $e) {
			_equals($e->getMessage(), null);
		}
		
		try {
			$recipe = new Model_Recipe(null, $config->directories->buildscript.'recipe1.xml');
		} catch (Exception $e) {
			_equals($e->getMessage(), null);
		}
		
		try {
			$recipe = new Model_Recipe('recipe1.xml');
		} catch (Exception $e) {
			_equals($e->getMessage(), null);
		}
		
		_equals($recipe instanceof Model_Recipe, true);
		_equals($recipe->getFileName(), 'recipe1.xml');
		_equals($recipe->getPath(), APPLICATION_PATH.'/tests/files/buildscripts/recipe1.xml');
		_equals($recipe->getAuthor(), 'TestAuthor');
		_equals($recipe->getName(), 'TestName');
		_equals($recipe->getVersion(), '9665664645535345654.0');
		_equals($recipe->getNote(), 'Note line 1'."\n".'Note line 2');
		_equals($recipe->getTargets(), array('t1', 't2', 't3', 't4', 't5'));
		echo PHP_EOL;
		
		Zend_Registry::set('config', $oldConfig);
	}
	
	public function validateFolder() {
		echo '- Testing Recipe Validate App folder' . PHP_EOL;
		$oldConfig = Zend_Registry::get('config');
		$config = new Zend_Config(array('directories' => array('buildscript' => APPLICATION_PATH.'/tests/files/buildscripts/')));
		Zend_Registry::set('config', $config);
		
		$recipe = Model_Recipe::getRecipe('recipe1.xml');
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app1');
		_equals($recipe->validateFolder($filesystem), false);
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app2');
		_equals($recipe->validateFolder($filesystem), false);
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app3');
		_equals($recipe->validateFolder($filesystem), false);
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app4');
		_equals($recipe->validateFolder($filesystem), false);
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app5');
		_equals($recipe->validateFolder($filesystem), true);
		echo PHP_EOL;
		
		Zend_Registry::set('config', $oldConfig);
	}

}
