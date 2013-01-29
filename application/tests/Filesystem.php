<?php

class Test_Filesystem {

	public function pack() {
		echo 'Testing Model Filesystem:' . PHP_EOL;
		
		$this->validateHash();
	}
	
	public function validateHash() {
		echo '- Testing Recipe Validate App folder' . PHP_EOL;
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app1');
		_equals($filesystem->getHash(''), 'ddd06eeffec78e8cd90e80210bc8d29b0aa4a9ce');
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app2');
		_equals($filesystem->getHash(''), 'f6f369f1e780f5d4a8b2220936b9141617f452b7');
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app3');
		_equals($filesystem->getHash(''), '11083e95da031e1e5484dbfa9410d5a97d5e06b5');
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app4');
		_equals($filesystem->getHash(''), '051cf108138d32ad74112c812ee18d0802b20435');
		$filesystem = new Model_Filesystem_Local(APPLICATION_PATH.'/tests/files/appsdir/app5');
		_equals($filesystem->getHash(''), 'd125b1b35df0556a6cc700f6810d2ae5c6d512e9');
		echo PHP_EOL;
	}

}
