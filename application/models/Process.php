<?php

class Model_Process extends RedBean_SimpleModel {

	private $process;
	private $pipes;
	public $procStatus;
	public $meta;

	/**
	 * Destructor makes sure processes are closed.
	 * @return boolean Returns true on success, otherwise false.
	 */
	public function __destruct() {
		return $this->close();
	}

	/**
	 * Checks status of a PID.
	 * @param int $pid The pid to be checked.
	 * @return boolean Returns true when running, otherwise false.	
 */
	public static function isRunning($pid) {
		try {
			$pid = intval($pid);
			if ($pid === 0) {
				$result = shell_exec(sprintf("ps -a -o pid --no-header -p %d", $pid));
				if ($result !== null) {
					return true;
				}
			}
		} catch (Exception $e) {
			
		}

		return false;
	}

	/**
	 * Starts the wrapper around a process. The wrapper will execute the command in the process.
	 * @return int PID of the started process.
	 */
	public function startProcessWrapper() {
		$pid = trim(shell_exec('nohup php -f ' . $_SERVER["SCRIPT_FILENAME"] . ' process cli ' . $this->id . ' 2> /dev/null > /dev/null & echo $!'));
		return $pid;
	}

	/**
	 * Open the process and start the main loop.
	 * @return bool - true if runs
	 */
	public function run() {

		$this->start();

		//Main loop
		do {
			$out = $this->readOutput();
			// Detect "blocking" (wait for stdin)
			if (sizeof($out) == 1 && ord($out) == 0) {
				$this->processInput();
			} else {
				$this->processOutput($out);
			}
			usleep(500000);
			$this->updateStatus();
			R::store($this);
		} while ($this->meta['eof'] === false);
		
		if (!$this->close()) {
			$this->status = 'Unknown';
		} else {
			$this->status = 'Finished';
			$this->percent = 100;
		}
		R::store($this);

		$this->postProcess();
		return true;
	}

	/**
	 * Starts the process
	 * Uses the class Process.
	 * @return true if runs
	 */
	private function start() {
		$spec = array(
				0 => array("pipe", "r"), // PHP -> phing (stdin)
				1 => array("pipe", "w"), // Phing -> php (stdout)
				2 => array("pipe", "a") // Piped to above (would be stderror Phing -> php)
		);

		// chdir to working directory
		$config = Zend_Registry::get('config');
		chdir($config->directories->deployment.$this->deployment_id);

		// Set home enviroment setting
		$home = null;
		if (isset($_ENV['HOME'])) {
			$home = $_ENV['HOME'];
		} elseif (isset($_SERVER['HOME'])) {
			$home = $_SERVER['HOME'];
		} elseif(function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
			$home = posix_getpwuid(posix_getuid());
			$home = $home['dir'];
		}
		
		if (isset($home)) {
			putenv('HOME=' . $home);
		}

		// Start process
		$this->process = proc_open($this->command . ' 2>&1', $spec, $this->pipes);
		//$this->process = proc_open('php -a' . ' 2>&1', $spec, $this->pipes);
		//Need non-blocking so multiple streams can be monitored.
		$this->setBlocking(0);

		if (!is_resource($this->process)) {
			$this->status = 'Failed';
			R::store($this);
			throw new Exception("RESOURCE NOT AVAIBLE");
			return false;
		}

		$this->status = 'Running';
		$this->percent = 0;
		R::store($this);
	}

	/**
	 * Sets stream blocking of all streams to and from the running process
	 * @param int $blocking 0 for non-blocking, 1 for blocking
	 * @return boolean Returns true when running, otherwise false.
	 */
	private function setBlocking($blocking = 1) {
		return stream_set_blocking($this->pipes[0], $blocking)
						&& stream_set_blocking($this->pipes[1], $blocking)
						&& stream_set_blocking($this->pipes[2], $blocking);
	}

	/**
	 * Update the meta data that this application uses.
	 */
	private function updateStatus() {
		$status = proc_get_status($this->process);
		if (!isset($this->exitcode) && $status["running"] !== true) {
			$this->exitcode = $status["exitcode"];
		}
		$this->procStatus = $status;
		$this->meta = stream_get_meta_data($this->pipes[1]);
	}

	/**
	 * Reads the output.
	 * @return string The raw output.
	 */
	private function readOutput() {
		$out = stream_get_contents($this->pipes[1]);
		return $out;
	}

	/**
	 * Sents a command to the process.
	 * @param string $command
	 */
	private function sentInput($command) {
		if (is_resource($this->pipes[0])) {
			//fwrite($this->pipes[0], chr(21));
			//fflush($this->pipes[0]);
			fwrite($this->pipes[0], $command . "\n");
			fflush($this->pipes[0]);
			//fwrite($this->pipes[0], chr(13));
			//fflush($this->pipes[0]);
		}
	}

	/**
	 * Closes all pipes to and from the process and then stops the process.
	 * @return boolean Returns true when close and when it was not running, otherwise false.
	 */
	private function close() {
		if (is_resource($this->process)) {
			if (is_resource($this->pipes[0])) {
				fclose($this->pipes[0]);
			}
			if (is_resource($this->pipes[1])) {
				fclose($this->pipes[1]);
			}
			if (is_resource($this->pipes[2])) {
				fclose($this->pipes[2]);
			}
			if (is_resource($this->process)) {
				return proc_close($this->process);;
			} else {
				return true;
			}
		}
	}

	/**
	 * Returns the (partial) output of the running process.
	 * @param int $minStdoutId To prevent duplicate output, the highest returned Stdout ID can be supplied.
	 * @return array Returns a array containing highest returned Stdout ID(maxId), and the output of those Stdout in a array(stdout).
	 */
	public function getStdoutArray($minStdoutId = null) {
		if ($minStdoutId !== null || $minStdoutId === 0) {
			$stdouts = $this->unbox()->withCondition(' id > ' . $minStdoutId . ' ')->ownStdout;
		} else {
			$stdouts = $this->ownStdout;
		}
		$stdoutOutput = array();
		$maxId = $minStdoutId;
		if ($stdouts) {
			foreach ($stdouts as $stdout) {
				$stdoutOutput[] = $stdout->output;
				if ($maxId < $stdout->id) {
					$maxId = $stdout->id;
				}
			}
		}
		return array('maxId' => $maxId, 'stdout' => $stdoutOutput);
	}

	/**
	 * Saves output at Stdout
	 * @param type $output 
	 */
	private function processOutput($output) {
		$lines = explode("\n", $output);
		foreach ($lines as $key => $line) {
			if (preg_match('/!\*\*#STATUSMSG#=(.*)\*!$/', $line, $matches)) {
				$this->msg = $matches['1'];
				unset($lines[$key]);
				continue;
			}
			if (preg_match('/!\*\*#STATUSPERCENT#=(\d*)(|.(\d)*)\*!$/', $line, $matches)) {
				if ($matches['2'] !== '') {
					$this->percent = floatval($matches['1'] . $matches['2']);
				} else {
					$this->percent = intval($matches['1']);
				}
				unset($lines[$key]);
				continue;
			}
		}
		
		$stdout = R::dispense('stdout');
		$stdout->output = Model_Stdout::parseToHTML(implode("\n", $lines));
		$stdout->process = $this;
		if ($output !== '') { 
			R::store($stdout);
		}
	}

	/**
	 * Gets all new Stdin.
	 * @return array Returns an array with new Stdin commands.
	 */
	private function getNewStdin() {
		$stdins = $this->unbox()->withCondition(' new = 1 ')->ownStdin;
		return $stdins;
	}

	/**
	 * Sents a command to a process.
	 * @param type $command
	 */
	public function addStdin($command) {
		$stdin = R::dispense('stdin');
		$stdin->command = $command;
		$stdin->new = 1;
		$this->unbox()->ownStdin[] = $stdin;
		R::store($this);
	}

	/**
	 * Gets new input from the database and sent them to the actual input function
	 */
	private function processInput() {
		$stdins = $this->getNewStdin();
		foreach ($stdins as $stdin) {
			$stdin->new = 0;
			/**
			 * @todo Intercept signal commandos.
			 */
			$this->sentInput($stdin->command);
		}
	}
	
	/**
	 * Perform post process actions:
	 * - Mark deployments successfull if all processes ended successfully
	 * - Disable rollback possibility if a rollback deployment was successfull
	 */
	private function postProcess() {
		$deployment = $this->bean->deployment;
		$processes = $deployment->ownProcess;
		$success = true;
		
		foreach($processes as $process) {
			if($process->exitcode != 0 || $process->status != 'Finished') {
				$success = false;
			}
		}
		
		$deployment->success = $success;
		R::store($deployment);
		
		if ($success) {
			$this->processOutput('The deployment is finished.('.count($processes).' processes)');
		} else {
			$this->processOutput('(Still) unsuccessfull.');
		}
		
		if ($success && $deployment->type == 'rollback') {
			$rolledbackDeployment = $deployment->deployment;
			if ($rolledbackDeployment->id !== 0) {
				$rolledbackDeployments = R::find('deployment', ' id > ? AND target_id = ? AND success = 1 AND rollback=1', array($rolledbackDeployment->id, $rolledbackDeployment->target_id));
				foreach ($rolledbackDeployments as $deployment) {
					$deployment->rollback = 0;
				}
				$rolledbackDeployment->rollback = 0;
				$rolledbackDeployments[] = $rolledbackDeployment;
				R::storeAll($rolledbackDeployments);
			}
		}
		
		if ($deployment->type == 'deploy' && isset($deployment->box()->triggerDeployment)) {
			$nextDeployment = R::load('deployment', $deployment->box()->triggerDeployment);
			if ($nextDeployment->id !== 0) {
				$nextDeployment->initiateDeployment();
			}
			unset($deployment->box()->triggerDeployment);
			R::store($deployment);
		}
	}

}