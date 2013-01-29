<?php

class SnapshotController extends Zend_Controller_Action {

	public function init() {
		/* Initialize action controller here */
	}

	public function indexAction() {
		if ($this->getRequest()->isPost()) {
			$serverId = $this->getRequest()->getParam('server');
			$this->_helper->Redirector->gotoSimple('application', null, null, array('server' => $serverId));
		}
		$serverId = $this->getRequest()->getParam('server');
		if ($serverId === null) {
			$this->view->selectedServer = null;
		} else {
			$this->view->selectedServer = $serverId;
		}
		$this->view->servers = R::find('server', ' id != 1');
	}

	public function applicationAction() {
		// Get a session to store the upcoming deployment.
		$s = new Zend_Session_Namespace('s');

		// Start preparing for a deployment
		if ($this->getRequest()->isPost()) {
			// Get instance id
			$instanceId = $this->getRequest()->getParam('instance');

			// Load it...
			$instance = R::load('instance', $instanceId);

			// Start building a deployment
			$s->model = R::dispense('deployment');

			// Search previous successfull deployment to get its recipe
			$recipe = $instance->getDeploymentRecipe();

			// If somehow not found, cannot deploy without one
			if ($recipe === null) {
				throw new Exception('Instance is not deployed using any recipe!');
			}

			// When found, get deployment recipe
			$s->model->setRecipe(new Model_Recipe(null, $recipe));

			// Set source to selected instance
			$s->model->source = $instance->unbox();

			// 
			$this->_helper->Redirector->gotoSimple('target');
		}

		// Check if the serverId is set, otherwise redirect.
		$serverId = $this->getRequest()->getParam('server');
		if ($serverId === null) {
			$this->_helper->Redirector->gotoSimple('index');
		}

		// Look for previously set data
		if (isset($s->model->source->id)) {
			$this->view->selectedInstance = $s->model->source->id;
		} else {
			$this->view->selectedInstance = null;
		}

		// Get the instances of the server
		$this->view->instances = R::find('instance', ' server_id = ? ORDER BY application_id ', array($serverId));
	}

	public function targetAction() {
		$s = new Zend_Session_Namespace('s');

		// Very specific bit of code that should NOT be in here.
		// Get the names of the devs.
		$config = Zend_Registry::get('config');
		$appDir = $config->servertypes->dev->applications;
		
		$devs = scandir($appDir);
		$team = array();
		foreach ($devs as $dev) {
			if (preg_match('/^(.+).dev$/', $dev, $matches)) {
				$team[] = $matches['1'];
			}
		}

		// Prepare several deployments
		if ($this->getRequest()->isPost()) {
			$devs = $this->getRequest()->getParam('team');

			// Get all already taken subdomain name from the selected devs.
			$takenNames = array();
			foreach ($devs as $dev) {
				// Find new devs locations
				$devApps = scandir($appDir . '/' . $team[$dev] . '.dev');
				foreach ($devApps as $devApp) {
					if (!is_dir($appDir . '/' . $team[$dev] . '.dev/' . $devApp)) {
						continue;
					}
					if (!in_array($devApp, $takenNames)) {
						$takenNames[] = $devApp;
					}
				}
			}

			// Get the original dev project name and add -s<number> behind it.
			$gotName = false;
			$i = 1;
			$projectName = explode('/', $s->model->source->unbox()->application->path);
			$projectName = end($projectName);
			// Continue to increase the number until a free subdomain for all devs is found.
			while ($gotName === false) {
				if (!in_array($projectName . '-s' . $i, $takenNames)) {
					$targetName = $projectName . '-s' . $i;
					$gotName = true;
				}
				$i++;
			}

			// Get the dev server and application
			$server = R::load('server', 1);
			$application = $s->model->source->unbox()->application;
			$deployments = array();

			foreach ($devs as $dev) {
				//Prepare deployment with stuff of the prepared deployment.
				$d = R::dispense('deployment');
				$d->setRecipe($s->model->getRecipe());
				$d->success = false;
				$d->source = $s->model->source;
				// Create new instance
				$instance = $server->box()->addApplication($application->box(), $targetName . '.' . $team[$dev] . '.dev', array());
				// Attach to NEW deployment
				$d->target = $instance;
				$d->type = 'deploy';
				$d->prepareDeployment();
				$d->rollback = 0;
				$deployments[] = $d;
			}
			//$s->model = $deployments;
			// (How to make the deployment @ end trigger other deployments)
			// Possible: deployment property? Filter from rollback.
			$prevDeployment = null;
			foreach ($deployments as $deployment) {
				if ($prevDeployment !== null && $prevDeployment->getRecipe()->getDebugMode() == 0) {
					$prevDeployment->box()->triggerDeployment = $deployment->id;
				}
				$prevDeployment = $deployment;
			}
			R::storeAll($deployments);

			// For first deployment start it.
			reset($deployments);
			$deployment = current($deployments);
			$deployment->initiateDeployment();
			$s->model = $deployments;
			// And redirect to the progress page.
			$this->_helper->Redirector->gotoSimple('snapshotting');
		}

		$this->view->team = $team;
		$this->view->selectedTeam = array();

		// Set server id for the application page...
		$this->view->serverId = $s->model->source->server_id;
	}

	public function snapshottingAction() {
		$s = new Zend_Session_Namespace('s');
		if (!isset($s->model) && !is_array($s->model)) {
			if ($this->getRequest()->isXmlHttpRequest()) {
				$this->_helper->layout()->disableLayout();
				$this->_helper->viewRenderer->setNoRender(true);
				print Zend_Json::encode(array('error' => 'Session expired!'));
				return;
			} else {
				$this->_helper->Redirector->gotoSimple('index');
			}
		}
		$currentDeploymentNum = 0;
		$currentDeployment = null;
		// Find running deployment
		foreach ($s->model as $deployment) {
			if ($deployment->getRunningProcess() !== false || $currentDeployment === null) {
				$currentDeploymentNum++;
				$currentDeployment = $deployment;
			}
		}
		$deploymentCount = count($s->model);

		// Handle the restart request from the debug button
		if ($this->getRequest()->isPost() && $this->getRequest()->getParam('restart') === 'Restart') {
			// Reset the 'running' Process. This is usually finished when the debug button is used.
			$process = $d->model->getRunningProcess();

			// Remove the input and output from and to the proces.
			$process->ownStdin = array();
			$process->ownStdout = array();

			// Set status to be the next to be executed files
			$process->status = 'Queued';

			// Save
			R::store($process);

			// Start and continue showing the normal waiting page!
			$d->model->initiateDeployment();
		}

		// Handle the next request from the debug button
		if ($this->getRequest()->isPost() && substr($this->getRequest()->getParam('next'), 0, 4) === 'Next') {
			// Seek to the current deployment
			reset($s->model);
			for ($i = 0; $i <= $deploymentCount; $i++) {
				next($s->model);
			}

			//Get the next deployment
			$next = next($s->model);
			if ($next !== false) {
				$currentDeploymentNum++;
				$currentDeployment = $next;
				$currentDeployment->initiateDeployment();
			} else {
				$this->_helper->Redirector->gotoSimple('index', 'index');
			}
		}

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout()->disableLayout();
			$this->_helper->viewRenderer->setNoRender(true);

			$process = $currentDeployment->getRunningProcess();

			if ($process !== false) {
				$minId = $this->getRequest()->getParam('minId');
				$resultArray = $process->getStdoutArray($minId);
			} else {
				$resultArray = array();
			}

			if (empty($process->msg)) {
				$resultArray['status'] = '';
			} else {
				$resultArray['status'] = $currentDeploymentNum . '/' . $deploymentCount . ': ' . $this->view->escape($process->msg);
			}

			// Busy with the x th deployment, so percentage offset is 
			$resultArray['percent'] = ($currentDeploymentNum - 1) / $deploymentCount * 100;
			if ($process->percent !== null) {
				$resultArray['percent'] += intval($process->percent) / $deploymentCount;
			}

			// There is no exit state when the process in not finished of when it is finished and but it isn't the last task and debug mode is off. 
			if ($process->status === 'Finished' || ($process->status !== 'Finished' && ($currentDeploymentNum == $deploymentCount || $currentDeployment->getRecipe()->getDebugMode() == 0))) {
				// When one snapshot (but not the last!) finished (succesfull or unsuccessfull!) and debug mode is on, return state Debug
				if ($currentDeploymentNum != $deploymentCount && $currentDeployment->getRecipe()->getDebugMode() > 0) {
					$resultArray['exit'] = 'Debug';
				// When the last snapshot finished successfully and debug mode is of return exitstate success
				} elseif ($process->exitcode == 0 && $currentDeploymentNum == $deploymentCount && $currentDeployment->getRecipe()->getDebugMode() == 0) {
					$resultArray['exit'] = 'Success';
				// When the last snapshot failed and debug mode is on set state DebugFail
				} elseif ($process->exitcode != 0 && $currentDeploymentNum == $deploymentCount && $currentDeployment->getRecipe()->getDebugMode() > 0) {
					$resultArray['exit'] = 'DebugFail';
				// When the last snapshot finished successfully and debug mode is on set state DebugSuccess
				} elseif ($process->exitcode == 0 && $currentDeploymentNum == $deploymentCount && $currentDeployment->getRecipe()->getDebugMode() > 0) {
					$resultArray['exit'] = 'DebugSuccess';
				// Remaining: When the last snapshot failed and debug mode is off, set state return Fail.
				} else {
					$resultArray['exit'] = 'Fail';
				}
			}


			//Assumption that the target server is a dev server...
			$targetUrl = $currentDeployment->box()->remoteUrl;
			$resultArray['member'] = ucwords($currentDeployment->getTargetServer()->getDevNameFromUrl($targetUrl));

			print Zend_Json::encode($resultArray);
		} else {
			if (preg_match('/^(.*)-s(\d+)$/', $currentDeployment->box()->remoteProject, $matches)) {
				$name = $matches['1'];
			} else {
				$name = $currentDeployment->box()->remoteProject;
			}
			$this->view->title = 'Deploying';
			$this->view->text = 'Creating a snapshot of ' . $name . ' for ' . $currentDeployment->getTargetServer()->getDevNameFromUrl($currentDeployment->box()->remoteUrl) . '.';
			$this->view->initialProgressText = 'Starting deployment process';
			$this->view->callbackUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('snapshotting', 'snapshot', 'default');
			$this->view->failUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'snapshot', 'default');
			$this->view->successUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost() . $this->_helper->url('complete', 'snapshot', 'default');
			$this->view->defaultProcessText = 'Deploying...';
			$this->view->name = $name;
		}
	}

	public function completeAction() {
		$s = new Zend_Session_Namespace('s');
		if (!isset($s->model) && !is_array($s->model)) {
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		if ($this->getRequest()->isPost()) {
			Zend_Session::namespaceUnset('s');
			$this->_helper->Redirector->gotoSimple('index', 'index');
		}

		$stdouts = array();
		$viewDeployments = array();
		$countSuccess = 0;
		$debugMode = 0;
		foreach ($s->model as $deployment) {
			$viewDeployment = array();

			$viewDeployment['name'] = ucwords($deployment->getTargetServer()->getDevNameFromUrl($deployment->box()->remoteUrl));

			// Although .dev should work, .dev.schuttelaar.local might be better as URL.
			$viewDeployment['url'] = 'http://' . str_replace('.dev', '.dev.schuttelaar.local', $deployment->box()->remoteUrl);

			$processes = $deployment->getProcesses();
			$viewDeployment['success'] = true;
			$viewDeployment['user'] = $deployment->box()->remoteUser;
			$viewDeployment['group'] = $deployment->box()->remoteGroup;
			$viewDeployment['path'] = $deployment->box()->remoteWebroot;
			$countSuccess++;
			if ($processes) {
				R::preload($processes, array('stdout'));
				foreach ($processes as $process) {
					$procStdouts = $process->ownStdout;
					foreach ($procStdouts as $stdout) {
						$stdouts[] = $stdout;
					}
					if ($process->exitcode != 0) {
						$viewDeployment['success'] = false;
						$countSuccess--;
					}
				}
			}
			if ($deployment->getRecipe()->getDebugMode() > $debugMode) {
				$debugMode = $deployment->getRecipe()->getDebugMode();
			}
			$viewDeployments[] = $viewDeployment;
		}

		$this->view->debugMode = $debugMode;
		$this->view->deployments = $viewDeployments;
		$this->view->countSuccess = $countSuccess;
		$this->view->countTotal = count($viewDeployments);

		if ($stdouts) {
			$this->view->stdouts = $stdouts;
		} else {
			$this->view->stdouts = null;
		}
	}

}