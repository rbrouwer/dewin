<?php

class Test_Deployment {

	public function pack() {
		echo 'Testing Model Deployment:' . PHP_EOL;
		$this->instanceManagement();
		$this->magicFunctions();
	}

	private function instanceManagement() {
		echo '- Testing Instance/Server Management Functions:' . PHP_EOL;
		R::nuke();

		$d = R::dispense('deployment');
		$d = $d->box();
		_equals($d->getSourceInstance(), null);
		_equals($d->getTargetInstance(), null);
		_equals($d->getSourceServer(), null);
		_equals($d->getTargetServer(), null);
		_equals($d->source, null);
		_equals($d->remote, null);
		$a = R::dispense('application');
		$si = R::dispense('instance');
		$si->application = $a;
		$si->description = 'source instance';
		$d->source = $si;
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), null);
		_equals($d->getSourceServer(), null);
		_equals($d->getTargetServer(), null);
		_equals($d->source, $si->box());
		_equals($d->remote, null);
		$ss = R::dispense('server');
		$ss->type = 'dev';
		$ss->host = 'localhost';
		$si->server = $ss;
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), null);
		_equals($d->getSourceServer(), $ss->box());
		_equals($d->getTargetServer(), null);
		_equals($d->source, $si->box());
		_equals($d->remote, null);
		R::store($si);
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), null);
		//This is loaded from the DB, is not longer the same!
		_equals((int) $d->getSourceServer()->id, $ss->id);
		_equals($d->getTargetServer(), null);
		_equals($d->source, $si->box());
		_equals($d->remote, null);
		$ti = R::dispense('instance');
		$ti->application = $a;
		$d->remote = $ti;
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), $ti->box());
		//This is loaded from the DB, is not longer the same!
		_equals((int) $d->getSourceServer()->box()->id, $ss->id);
		_equals($d->getTargetServer(), null);
		_equals($d->source, $si->box());
		_equals($d->remote, $ti->box());
		$ts = R::dispense('server');
		$ts->type = 'demo';
		$ts->host = 'notlocalhost';
		$ti->server = $ts;
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), $ti->box());
		_equals((int) $d->getSourceServer()->box()->id, $ss->id);
		_equals($d->getTargetServer(), $ts->box());
		_equals($d->source, $si->box());
		_equals($d->remote, $ti->box());
		R::store($ti);
		_equals($d->getSourceInstance(), $si->box());
		_equals($d->getTargetInstance(), $ti->box());
		_equals((int) $d->getSourceServer()->box()->id, $ss->id);
		_equals((int) $d->getTargetServer()->box()->id, $ts->id);
		_equals($d->source, $si->box());
		_equals($d->remote, $ti->box());
		R::store($d);
		$d2 = R::load('deployment', $d->unbox()->id);
		$d2 = $d2->box();
		_equals((int) $d2->getSourceInstance()->box()->id, $si->id);
		_equals((int) $d2->getTargetInstance()->box()->id, $ti->id);
		_equals((int) $d2->getSourceServer()->box()->id, $ss->id);
		_equals((int) $d2->getTargetServer()->box()->id, $ts->id);
		_equals((int) $d2->source->box()->id, $si->id);
		_equals((int) $d2->remote->box()->id, $ti->id);
		echo PHP_EOL;
	}

	private function magicFunctions() {
		echo '- Testing Deployment Variable Management Functions:' . PHP_EOL;
		R::nuke();
		$d = R::dispense('deployment');
		$d = $d->box();
		// Testing source and remote
		_equals(isset($d->source), false);
		_equals(isset($d->remote), false);
		$a = R::dispense('application');
		$si = R::dispense('instance');
		$si->application = $a;
		$si->description = 'source instance';
		$d->source = $si;

		//Testing getting instance properties
		_equals(isset($d->source), true);
		_equals(isset($d->remote), false);
		_equals(isset($d->sourceDescription), true);
		_equals($d->sourceDescription, 'source instance');
		_equals(isset($d->remoteDescription), false);
		_equals($d->remoteDescription, null);
		$ti = R::dispense('instance');
		$ti->application = $a;
		$ti->description = 'target instance';
		$d->remote = $ti;
		_equals(isset($d->source), true);
		_equals(isset($d->remote), true);
		_equals(isset($d->sourceDescription), true);
		_equals($d->sourceDescription, 'source instance');
		_equals(isset($d->remoteDescription), true);
		_equals($d->remoteDescription, 'target instance');

		//Testing getting server properties
		$ss = R::dispense('server');
		$ss->box()->type = 'dev';
		$ss->host = 'localhost';
		$si->server = $ss;
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), false);
		_equals($d->remoteHost, null);
		$ts = R::dispense('server');
		$ts->box()->type = 'demo';
		$ts->host = 'notlocalhost';
		$ti->server = $ts;
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');

		//Testing overruling server properties
		$si->box()->host = 'tsohlacol';
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'tsohlacol');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		unset($si->box()->host);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		$d->sourceHost = 'tsohlacol';
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'tsohlacol');
		_equals($d->sourceHost, $si->box()->host);
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		unset($d->sourceHost);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals($si->box()->host, null);
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		unset($d->sourceHost);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals($si->box()->host, null);
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');

		//Still testing overruling server properties
		$ti->box()->host = 'tsohlacolton';
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'tsohlacolton');
		unset($ti->box()->host);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		$d->remoteHost = 'tsohlacolton';
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'tsohlacolton');
		_equals($d->remoteHost, $ti->box()->host);
		unset($d->remoteHost);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		_equals($ti->box()->host, null);
		unset($d->remoteHost);
		_equals(isset($d->sourceHost), true);
		_equals($d->sourceHost, 'localhost');
		_equals(isset($d->remoteHost), true);
		_equals($d->remoteHost, 'notlocalhost');
		_equals($ti->box()->host, null);

		//Testing temp properties
		_equals(isset($d->tempVar), false);
		_equals($d->tempVar, null);
		$d->tempVar = 'whatever';
		_equals(isset($d->tempVar), true);
		_equals($d->tempVar, 'whatever');
		unset($d->tempVar);
		_equals(isset($d->tempVar), false);
		_equals($d->tempVar, null);

		//Testing bean variables
		_equals(isset($d->var), false);
		_equals($d->var, null);
		$d->unbox()->var = 'column';
		_equals(isset($d->var), true);
		_equals($d->var, 'column');
		unset($d->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);
		$d->unbox()->var = 'column';
		unset($d->unbox()->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);

		//Testing deployment properties
		_equals(isset($d->var), false);
		_equals($d->var, null);
		$d->unbox()->var = 'column';
		_equals(isset($d->var), true);
		_equals($d->var, 'column');
		unset($d->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);
		$d->unbox()->var = 'column';
		unset($d->unbox()->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);

		// Testing non-bean variable...
		_equals(isset($d->setthis), false);
		_equals($d->setthis, null);
		$d->setthis = 'set';
		_equals(isset($d->setthis), true);
		_equals($d->setthis, 'set');
		unset($d->setthis);
		_equals(isset($d->setthis), false);
		_equals($d->setthis, null);

		// Testing saving to properties...
		$d->savethis = 'save';
		R::store($d);

		$properties = R::find('property', 'deployment_id = ?', array($d->id));

		// You would expect 1, but on dispence another timestamped property is created.
		// That is used to make a unique directory in demo/production build directories.
		_equals(count($properties), 2);
		$property = end($properties);
		_equals($property->name, 'savethis');
		_equals($property->value, 'save');
		$id = $property->id;

		// Testing changing...
		$d->savethis = 'saveme';
		R::store($d);
		$properties = R::find('property', 'deployment_id = ?', array($d->id));
		_equals(count($properties), 2);
		$property = end($properties);
		_equals($property->name, 'savethis');
		_equals($property->value, 'saveme');
		// Checks if it changed the setting, and not just remade it...
		_equals($property->id, $id);

		unset($d->savethis);
		R::store($d);
		$properties = R::find('property', 'deployment_id = ?', array($d->id));
		_equals(count($properties), 1);

		// Setting Bean comparison
		$dBean = $d->unbox();
		_equals(isset($d->var), false);
		_equals($d->var, null);
		_equals(isset($dBean->var), false);
		_equals($dBean->var, null);
		$dBean->var = 'bean';
		_equals(isset($d->var), true);
		_equals($d->var, 'bean');
		_equals(isset($dBean->var), true);
		_equals($dBean->var, 'bean');
		$d->var = 'deployment';

		_equals(isset($d->var), true);
		_equals($d->var, 'deployment');
		_equals(isset($dBean->var), true);
		_equals($dBean->var, 'deployment');
		unset($d->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);
		_equals(isset($dBean->var), false);
		_equals($dBean->var, null);
		$d->var = 'deployment';
		_equals(isset($d->var), true);
		_equals($d->var, 'deployment');
		_equals(isset($dBean->var), false);
		_equals($dBean->var, null);
		$dBean->var = 'bean';
		_equals(isset($d->var), true);
		_equals($d->var, 'bean');
		_equals(isset($dBean->var), true);
		_equals($dBean->var, 'bean');
		unset($d->var);
		_equals(isset($d->var), false);
		_equals($d->var, null);
		_equals(isset($dBean->var), false);
		_equals($dBean->var, null);

		// Loading the structure from the database...
		$d->test = 'deploymentProperty';
		$d->tempTest = 'deploymentTempProperty';
		$d->remoteTest = 'Target Instance Property';
		$d->sourceTest = 'Source Instance Property';
		$dBean->column = 'DeploymentColumn';
		R::store($d);
		$d2 = R::load('deployment', $d->unbox()->id);
		$d2 = $d2->box();
		
		_equals($d2->test, 'deploymentProperty');
		_equals($d2->tempTest, null);
		_equals($d2->remoteTest, 'Target Instance Property');
		_equals($d2->sourceTest, 'Source Instance Property');
		_equals($d2->column, 'DeploymentColumn');
		
		echo PHP_EOL;
	}
	
	

}