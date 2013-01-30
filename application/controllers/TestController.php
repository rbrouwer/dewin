<?php

class TestController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	/**
	 * Just for demo purpose
	 * Create a process, and trigger the test command in there.
	 */
	public function indexAction() {
		if (PHP_SAPI !== 'cli') {
			// Dispense a new process.
			$process = R::dispense('process');

			// Set the command to run
			$process->command = 'php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' test exec';

			// Store the process(is needed before running it!)
			R::store($process);

			// Run the process
			$process->startProcessWrapper();

			// Redirect to the process controller to watch the action!
			$this->_helper->Redirector->gotoSimple('index', 'process', null, array('processId' => $process->id));
		
		//Goddamn newbs! You shouldn't run this action as command!
		} else {
			//Ah well, help them out then!
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);
			
			passthru('php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' test exec');
		}
	}
	
	public function phpAction() {
		if (PHP_SAPI !== 'cli') {
			// Dispense a new process.
			$process = R::dispense('process');

			// Set the command to run
			$process->command = 'php -a';

			// Store the process(is needed before running it!)
			R::store($process);

			// Run the process
			$process->startProcessWrapper();

			// Redirect to the process controller to watch the action!
			$this->_helper->Redirector->gotoSimple('index', 'process', null, array('processId' => $process->id));
		
		//Goddamn newbs! You shouldn't run this action as command!
		}
	}

	public function execAction() {
		if (PHP_SAPI === 'cli') {
			//Not a page, do not render stuff
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			//Bypass error handling
			error_reporting(E_ALL);
			ini_set('display_errors', '1');
			
			//Start Spike PHP Coverage
			// Not in the manual!
			// These globals are ONLY here to please SpikePHPCoverage's codebase
			global $util, $spc_config, $VERBOSE, $LOCAL_PHPCOVERAGE_LOCATION, $PHPCOVERAGE_HOME,
			$top, $bottom, $PHPCOVERAGE_REPORT_DIR, $PHPCOVERAGE_APPBASE_PATH;

			// Include PHPCoverage
			define('PHPCOVERAGE_HOME', realpath(APPLICATION_PATH . '/../library/SpikePHPCoverage'));
			//Do not show error here, spike coverage uses deprecated features.
			error_reporting(0);
			require_once PHPCOVERAGE_HOME . '/CoverageRecorder.php';
			require_once PHPCOVERAGE_HOME . '/reporter/HtmlCoverageReporter.php';
			error_reporting(E_ALL);
			
			// Set it up
			$reporter = new HtmlCoverageReporter("Test Code Coverage Report", "", APPLICATION_PATH . '/../public/report');
			$includePaths = array(APPLICATION_PATH . '/models/');
			$excludePaths = array(APPLICATION_PATH . '/models/RemoveThis/', APPLICATION_PATH . '/forms/', APPLICATION_PATH . '/tests/');
			$cov = new CoverageRecorder($includePaths, $excludePaths, $reporter);

			// Prepare a temporary database
			R::setup('sqlite:unit.sql'); //Use simple SQLite database
			R::addDatabase('testdb', 'sqlite:unit.sql'); //Use simple SQLite database
			R::selectDatabase('testdb');
			Zend_Registry::set("tools", R::$toolbox);
			Zend_Registry::set("db", R::$adapter);
			Zend_Registry::set("redbean", R::$redbean);
			R::freeze(false); //Fluid mode. Schema should not matter.
			
			// Start tracking
			$cov->startInstrumentation();

			// Run all test packs
			$testInstancePack = new Test_Instance();
			$testInstancePack->pack();
			$testSqlPack = new Test_Sql();
			$testSqlPack->pack();
			$testRsyncPack = new Test_Rsync();
			$testRsyncPack->pack();
			$testFilesystemPack = new Test_Filesystem();
			$testFilesystemPack->pack();
			$testRecipePack = new Test_Recipe();
			$testRecipePack->pack();
			$testDeploymentPack = new Test_Deployment();
			$testDeploymentPack->pack();

			//Stop logging
			$cov->stopInstrumentation();

			//Generate report and show summary
			$cov->generateReport();
			$reporter->printTextSummary();
		} else {
			throw new Zend_Controller_Action_Exception('This page does not exist', 404);
		}
	}

}

// The long version of the twit-able test-framework written and developed by Gabor de Mooij.
// Doesn't it deserve a 
$c = 0;

/**
 * Some handy helpers.
 */
function _equals($a, $b) {
	if ($a === $b)
		p(); else
		f();
}

function p() {
	global $c;
	echo '[' . (++$c) . ']';
}

function f() {
	echo 'FAIL!' . PHP_EOL;
	debug_print_backtrace();
	exit(1);
}

