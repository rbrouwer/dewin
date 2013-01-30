<?php

class Test_Instance {
	
	public function pack() {
		echo 'Testing Model Instance:'.PHP_EOL;
		$this->magicFunctions();
		$this->getProperties();
		$this->validateDbTest();
		$this->getDeploymentRecipe();
	}
	
		
	private function magicFunctions() {
		echo '- Testing Instance Variable Management Functions:'.PHP_EOL;
		R::nuke();
		$s = R::dispense('server');
		$s->host = 'localhost';
		$a = R::dispense('application');
		$a->path = '/var/www/';
		$i = R::dispense('instance');
		$i->application = $a;
		$i->server = $s;
		R::store($i);
		$i = $i->box();

		// Testing non-bean variable...
		_equals(isset($i->setthis), false);
		_equals($i->setthis, null);
		$i->setthis = 'set';
		_equals(isset($i->setthis), true);
		_equals($i->setthis, 'set');
		unset($i->setthis);
		_equals(isset($i->setthis), false);
		_equals($i->setthis, null);
		
		// Testing saving to properties...
		$i->savethis = 'save';
		R::store($i);
		
		$settings = R::find('setting', 'instance_id = ?', array($i->id));
		_equals(count($settings), 1);
		$setting = current($settings);
		_equals($setting->name, 'savethis');
		_equals($setting->value, 'save');
		$id = $setting->id;
		
		// Testing changing...
		$i->savethis = 'saveme';
		R::store($i);
		$settings = R::find('setting', 'instance_id = ?', array($i->id));
		_equals(count($settings), 1);
		$setting = current($settings);
		_equals($setting->name, 'savethis');
		_equals($setting->value, 'saveme');
		// Checks if it changed the setting, and not just remade it...
		_equals($setting->id, $id);
		
		unset($i->savethis);
		R::store($i);
		$settings = R::find('setting', 'instance_id = ?', array($i->id));
		_equals(count($settings), 0);
		
		// Setting Bean comparison (apple will sue me for this?)
		$iBean = $i->unbox();
		_equals(isset($i->var), false);
		_equals($i->var, null);
		_equals(isset($iBean->var), false);
		_equals($iBean->var, null);
		$iBean->var = 'bean';
		_equals(isset($i->var), true);
		_equals($i->var, 'bean');
		_equals(isset($iBean->var), true);
		_equals($iBean->var, 'bean');
		$i->var = 'instance';
		_equals(isset($i->var), true);
		_equals($i->var, 'instance');
		_equals(isset($iBean->var), true);
		_equals($iBean->var, 'instance');
		unset($i->var);
		_equals(isset($i->var), false);
		_equals($i->var, null);
		_equals(isset($iBean->var), false);
		_equals($iBean->var, null);
		$i->var = 'instance';
		_equals(isset($i->var), true);
		_equals($i->var, 'instance');
		_equals(isset($iBean->var), false);
		_equals($iBean->var, null);
		$iBean->var = 'bean';
		_equals(isset($i->var), true);
		_equals($i->var, 'instance');
		_equals(isset($iBean->var), true);
		_equals($iBean->var, 'bean');
		
		// First the setting will be unset, but bean column remains
		unset($i->var);
		_equals(isset($i->var), true);
		_equals($i->var, 'bean');
		_equals(isset($iBean->var), true);
		_equals($iBean->var, 'bean');
		unset($i->var);
		_equals(isset($i->var), false);
		_equals($i->var, null);
		_equals(isset($iBean->var), false);
		_equals($iBean->var, null);
		
		echo PHP_EOL;
	}
	
	private function getProperties() {
		echo '- Testing Instance getProperties Function:'.PHP_EOL;
		R::nuke();
		$s = R::dispense('server');
		$s->host = 'localhost';
		$a = R::dispense('application');
		$a->path = '/var/www/';
		$i = R::dispense('instance');
		$i->application = $a;
		$i->server = $s;
		R::store($i);
		$iBean = $i;
		$i = $i->box();
		
		// Make sure is ignores the default bean properties: id, server_id, application_id
		$a1 = $i->getProperties();
		_equals($a1, array());
		_equals(count($a1), 0);
		
		// Set settings
		$i->hello = 'world';
		$a2 = $i->getProperties();
		_equals($a2, array('hello' => 'world'));
		_equals(count($a2), 1);
		
		// Settings > bean properties.
		$iBean->hello = 'bar';
		$a3 = $i->getProperties();
		_equals($a3, array('hello' => 'world'));
		_equals(count($a3), 1);
		
		$iBean->foo = 'bar';
		$a4 = $i->getProperties();
		_equals($a4, array('hello' => 'world', 'foo' => 'bar'));
		_equals(count($a4), 2);
		
		echo PHP_EOL;
	}
	
	private function validateDbTest() {
		echo '- Testing Instance validateDb function:'.PHP_EOL;
		R::nuke();
		$s = R::dispense('server');
		$s->host = 'localhost';
		$i = R::dispense('instance');
		$i->server = $s;
		$i = $i->box();

		// Wrong database name
		$i->databaseName = 'doesnotexist';
		$i->databaseUser = 'dewin_unittests';
		$i->databasePassword = 'dewin_unittests';
		_equals($i->validateDB(), false);

		// Wrong username
		$i->databaseName = 'dewin_unittests';
		$i->databaseUser = 'doesnotexist';
		_equals($i->validateDB(), false);
		
		// Wrong password
		$i->databaseUser = 'dewin_unittests';
		$i->databasePassword = 'doesnotexist';
		_equals($i->validateDB(), false);
		
		// Correct details
		$i->databasePassword = 'dewin_unittests';
		_equals($i->validateDB(), true);
		
		// Wrong host
		$s->host = 'doesnotexisthopefully';
		_equals($i->validateDB(), false);
		echo PHP_EOL;
	}
	
	private function getDeploymentRecipe() {
		echo '- Testing Instance getDeploymentRecipe function:'.PHP_EOL;
		R::nuke();
		
		$i = R::dispense('instance');
		$i = $i->box();
		
		_equals($i->getDeploymentRecipe(), null);
		
		$d = R::dispense('instance');
		$d->recipe = 'Upgrade Recipe';
		$d->target = $i;
		$d->type = 'upgrade';
		$d->created = R::isoDateTime(time()-10);
		$d->success = 1;
		R::store($d);
		
		_equals($i->getDeploymentRecipe(), null);
		
		$d = R::dispense('deployment');
		$d->recipe = 'Deploy recipe of another deployment';
		$d->target = R::dispense('instance');
		$d->type = 'deploy';
		$d->created = R::isoDateTime(time()-10);
		$d->success = 1;
		R::store($d);
		_equals($i->getDeploymentRecipe(), null);
		
		$d = R::dispense('deployment');
		$d->recipe = 'Failing deployment recipe';
		$d->target = $i;
		$d->type = 'deploy';
		$d->created = R::isoDateTime(time()-10);
		$d->success = 0;
		R::store($d);
		_equals($i->getDeploymentRecipe(), null);
		
		$d = R::dispense('deployment');
		$d->recipe = 'The deployment recipe';
		$d->target = $i;
		$d->type = 'deploy';
		$d->created = R::isoDateTime(time()-10);
		$d->success = 1;
		R::store($d);
		_equals($i->getDeploymentRecipe(), 'The deployment recipe');
		
		$d = R::dispense('deployment');
		$d->recipe = 'The new deployment recipe';
		$d->target = $i;
		$d->type = 'deploy';
		$d->created = R::isoDateTime(time()-5);
		$d->success = 1;
		R::store($d);
		_equals($i->getDeploymentRecipe(), 'The new deployment recipe');
		
		echo PHP_EOL;
	}	

}
