<?php

class Zend_View_Helper_LinearPath extends Zend_View_Helper_Abstract {

	/**
	 * Creates a linear path bar containing the steps of the 'deployment'-process
	 * @return string
	 */
	public function LinearPath() {
		$output = null;
		$controllerName = Zend_Controller_Front::getInstance()->getRequest()->getControllerName();
		if (in_array($controllerName, array('index', 'deployment', 'upgrade', 'rollback', 'snapshot'))) {
			$actionName = Zend_Controller_Front::getInstance()->getRequest()->getActionName();
			$output = '<ul class="linearpath">';
			$method = 'get' . ucfirst($controllerName) . 'Actions';
			$actions = $this->$method($actionName, $controllerName);
			$output .= $this->fillBar($actions, $actionName, $controllerName);
			$output .= '</ul>';
		}
		return $output;
	}

	/**
	 * Fills the linear bar with actions from the actions array.
	 * @param array $actions actions as supplied by the get###Actions
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return string Content of the linear bar
	 */
	private function fillBar($actions, $actionName, $controllerName) {
		$output = '';
		$current = array_search($controllerName . '->' . $actionName, array_keys($actions), true);

		if ($current === false) {
			$output = '<li class="unavailable"><span>Unavailable</span></li>';
		} else {
			$i = 0;
			foreach ($actions as $currentActionName => $currentActionTitle) {
				if ($i < $current) {
					$output .= '<li><span>' . $this->view->escape($currentActionTitle) . '</span></li>';
				} elseif ($i === $current) {
					$output .= '<li class="current"><span>' . $this->view->escape($currentActionTitle) . '</span></li>';
				} else {
					$output .= '<li class="unavailable"><span>' . $this->view->escape($currentActionTitle) . '</span></li>';
				}
				$i++;
			}
		}
		return $output;
	}

	/**
	 * Creates an array containing the steps of this controller.
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return array containing an array with #controller#->#action# as keys and action title as values
	 */
	private function getIndexActions($actionName, $controllerName) {
		$actions = array();
		$actions['index->index'] = 'Home';
		$actions['index->recipe'] = 'Recipe';
		$actions['index->application'] = 'Application';
		$actions['index->target'] = 'Target';
		$actions['index->detection'] = 'Detection';
		$actions['->'] = '...';
		return $actions;
	}

	/**
	 * Creates an array containing the steps of this controller.
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return array containing an array with #controller#->#action# as keys and action title as values
	 */
	private function getDeploymentActions($actionName, $controllerName) {
		// Get the general bar
		$actions = $this->getIndexActions($actionName, $controllerName);
		// Pop of the ...-element
		array_pop($actions);
		// Add the remaining steps
		$actions['deployment->deployment'] = 'Deployment';
		$actions['deployment->complete'] = 'Complete';
		return $actions;
	}

	/**
	 * Creates an array containing the steps of this controller.
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return array containing an array with #controller#->#action# as keys and action title as values
	 */
	private function getUpgradeActions($actionName, $controllerName) {
		// Get the general bar
		$actions = $this->getIndexActions($actionName, $controllerName);
		// Pop of the ...-element
		array_pop($actions);
		// Add the remaining steps
		$actions['upgrade->pilot'] = 'Pilot';
		$actions['upgrade->database'] = 'Database';
		$actions['upgrade->file'] = 'File';
		$actions['upgrade->deployment'] = 'Deployment';
		$actions['upgrade->complete'] = 'Complete';
		return $actions;
	}

	/**
	 * Creates an array containing the steps of this controller.
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return array containing an array with #controller#->#action# as keys and action title as values
	 */
	private function getRollbackActions($actionName, $controllerName) {
		$actions = array();
		$actions['rollback->index'] = 'History';
		$actions['rollback->pilot'] = 'Pilot';
		$actions['rollback->rollback'] = 'Database';
		$actions['rollback->application'] = 'Rollback';
		$actions['rollback->target'] = 'Complete';
		return $actions;
	}

	/**
	 * Creates an array containing the steps of this controller.
	 * @param string $actionName The current action
	 * @param string $controllerName The current controller
	 * @return array containing an array with #controller#->#action# as keys and action title as values
	 */
	private function getSnapshotActions($actionName, $controllerName) {
		$actions = array();
		$actions['snapshot->index'] = 'Server';
		$actions['snapshot->application'] = 'Application';
		$actions['snapshot->target'] = 'Team';
		$actions['snapshot->snapshotting'] = 'Deployment';
		$actions['snapshot->complete'] = 'Complete';
		return $actions;
	}

}