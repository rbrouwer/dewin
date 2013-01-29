<?php

class Test_Sql {

	public function pack() {
		echo 'Testing Model SqlPatch:' . PHP_EOL;
		$this->validateSql();
	}
	
	public function validateSql() {
		copy(APPLICATION_PATH.'/tests/files/sql/test_original.sql', APPLICATION_PATH.'/tests/files/sql/test.sql');
		$sqlPatch = new Model_SqlPatch(APPLICATION_PATH.'/tests/files/sql/test.sql');
		$sql = $sqlPatch->nextQuery();
		_equals($sql->IsCreateStatement(), false);
		_equals($sql->getDescription(), 'Set @OLD_CHARACTER_SET_CLIENT to @@CHARACTER_SET_CLIENT. [Conditional MySQL version: 40101]');
		_equals($sql->getSql(), '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;');
		_equals($sql->getVisible(), false);
		
		for($i=1;$i<=10;$i++) {
			$sql = $sqlPatch->nextQuery();
		}
		_equals($sql->getSql(), "\n".'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `deploytool` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;');
		_equals($sql->IsCreateStatement(), true);
		_equals($sql->getDescription(), 'Create database `deploytool`. [Conditional MySQL version: 32312] [Conditional MySQL version: 40100]');
		_equals($sql->getVisible(), true);
		
		$sqlPatch->buildAddMissingSqlPatchFile();
		_equals(sha1_file(APPLICATION_PATH.'/tests/files/sql/test.sql'), '20af3a00ea979961cd105a26f40118631c3cf25d');
		$sqlPatch->removePatch();
		copy(APPLICATION_PATH.'/tests/files/sql/test_original.sql', APPLICATION_PATH.'/tests/files/sql/test2.sql');
		$sqlPatch = new Model_SqlPatch(APPLICATION_PATH.'/tests/files/sql/test2.sql');
		$sqlPatch->buildSqlPatchFile(array(1,2,4,5,7,2,5,8,12,45,1), 'DROP DATABASE `deploytool`;');
		_equals(sha1_file(APPLICATION_PATH.'/tests/files/sql/test2.sql'), '28c9950b0d36b970128ae09c22cfb77f5c787132');
		$sqlPatch->movePatch(APPLICATION_PATH.'/tests/files/sql/test3.sql');
		_equals(sha1_file(APPLICATION_PATH.'/tests/files/sql/test3.sql'), '28c9950b0d36b970128ae09c22cfb77f5c787132');
		$sqlPatch->removePatch();
		echo PHP_EOL;
	}

}
