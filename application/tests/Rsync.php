<?php

class Test_Rsync {

	public function pack() {
		echo 'Testing Model Rsync:' . PHP_EOL;
		$this->validateRsync();
	}
	
	public function validateRsync() {
		$rsyncOutput = new Model_Rsync_ItemizeChangeset(APPLICATION_PATH.'/tests/files/rsync/output1.txt');
		_equals($rsyncOutput->getNumberOfFilesTransferred(), 178767);
		
		$rsyncOutput->buildFileList(array(0,2,4,5), APPLICATION_PATH.'/tests/files/rsync/filelist.txt');
		_equals(sha1_file(APPLICATION_PATH.'/tests/files/rsync/filelist.txt'), '04a7cb2cea9ff0d26b2fcdfd4012097cc8ff59a3');
		unlink(APPLICATION_PATH.'/tests/files/rsync/filelist.txt');
		
		$rsyncArray = $rsyncOutput->getItemizeChangeset();
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),true);
		_equals($item->getFileName(),'index.php');
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),true);
		_equals($item->getFileName(),'system.xml');
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),true);
		_equals($item->getFileName(),'public');
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),true);
		_equals($item->getFileName(),'asserts');
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),false);
		_equals($item->getFileName(),'device');
		$item = array_shift($rsyncArray);
		_equals($item->isTransferred(),true);
		_equals($item->getFileName(),'whatevers');
		_equals(count($rsyncArray),0);
		
		$rsyncOutput = new Model_Rsync_ItemizeChangeset(APPLICATION_PATH.'/tests/files/rsync/output2.txt');
		_equals($rsyncOutput->getNumberOfFilesTransferred(), 1);
		_equals($rsyncOutput->getItemizeChangeset(), array());
		echo PHP_EOL;
	}

}