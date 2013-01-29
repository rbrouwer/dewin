<?php

class StatusListener implements BuildListener {

	/**
	 * Contains the different sections of the processbar
	 * @var type 
	 */
	private $range;
	/**
	 * Contains the current active range
	 * @var stdClass 
	 */
	private $selectedRange;

	public function __construct() {
		// Make a master range, for the entire progress bar.
		$this->range = $this->getRange('project', 0, 100, false, false, 1);
		// Set it as active, so the next starting target will be part of this master range.
		$this->selectedRange = $this->range;
	}

	/**
	 * Creates a section element of the progress bar.
	 * @param string $name Type of the task (Easy for debugging purposes)
	 * @param null|int|double $min Begin of the range
	 * @param null|int|double $max End of the range
	 * @param boolean $lockedMin Whenever 'Min' setting is locked. 
	 * @param boolean $lockedMax Whenever 'Max' setting is locked. 
	 * @param int|double $weight Weight of this part compared to the rest.
	 * @param stdClass $parent The parent of this range
	 * @return stdClass A range
	 */
	private function getRange($name, $min, $max, $lockedMin, $lockedMax, $weight, $parent = null) {
		// Just need something to store some vars
		$range = new stdClass();
		$range->name = $name;
		$range->min = $this->validatePercentage($min);
		$range->max = $this->validatePercentage($max);
		$range->lockedMin = $lockedMin;
		$range->lockedMax = $lockedMax;
		$range->weight = $weight;
		$range->children = array();
		$range->parent = $parent;
		$range->executed = false;
		return $range;
	}

	/**
	 * Correct percentages when they are not percentages.
	 * @param int|double|null $percentage
	 * @return int|double|null
	 */
	private function validatePercentage($percentage) {
		//Percentage are higher or equal to 0 and lower then 100
		if ($percentage < 0) {
			return 0;
		} elseif ($percentage <= 100 || $percentage === null) {
			return $percentage;
		} else {
			return 100;
		}
	}

	/**
	 * Probably doesn't ever fire, listener is initialized to late
	 * Part of the listener doing nothing!
	 * @param BuildEvent $event The BuildEvent
	 */
	public function buildStarted(BuildEvent $event) {
		
	}

	/**
	 * On build finished, print 100%
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent::getException()
	 */
	public function buildFinished(BuildEvent $event) {
		$error = $event->getException();
		$this->selectedRange->completed = true;
		// onSuccess
		if ($error === null) {
			//echo '!**#STATUSSTATE#=SUCCESS' . '*!' . PHP_EOL;
			$this->printPercentage(100, $event->getProject());
			// onFail
		} else {
			//echo '!**#STATUSSTATE#=FAILED' . '*!' . PHP_EOL;
			$this->printPercentage(100, $event->getProject());
		}
	}

	/**
	 * Fired when a target is started.
	 * Scans the target for other status tasks
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent::getTarget()
	 */
	public function targetStarted(BuildEvent $event) {
		//Phing accidently calls endTasks before starting new targets.
		if ($this->selectedRange->parent !== null) {
			$this->prevTask();
		}

		//Get target
		$target = $event->getTarget();
		$this->processTarget($target);
		$this->calculatePercentages($this->selectedRange);
		
		//Select first not executed task
		foreach($this->selectedRange->children as $child) {
			if ($child->executed === false) {
				$this->selectedRange = $child;
				break;
			}
		}
	}

	/**
	 * Reads the contains of a target and adjusts the progress-bar accordingly.
	 * @param Target $target
	 */
	private function processTarget(Target $target) {
		$this->preconfigureStatusTasks($target);

		// Target clones tasks when you get them.
		// After configuring them, get the tasks again so the tasks are configured.
		$tasks = $target->getTasks();

		// Default weight is 1
		$weight = 1;
		foreach ($tasks as $task) {
			// If the task is a statusTask, read its config.
			if (get_class($task) === 'StatusTask') {
				$weight = $task->weight;

				// If the status task requests a certain percentage.
				if ($task->forcedPercentage !== null) {
					$percentage = ($this->selectedRange->max - $this->selectedRange->min) / 100 * $task->forcedPercentage;

					//Set the max of the previous range, if it exists.
					$range = end($this->selectedRange->children);
					if ($range !== false) {
						$range->max = $percentage;
						$range->lockedMax = true;
					}

					// Create a range for this task with force percentage as minimum.
					$this->selectedRange->children[] = $this->getRange(get_class($task), $percentage, null, true, false, $weight, $this->selectedRange);
				} else {
					// Create a range for this task
					$this->selectedRange->children[] = $this->getRange(get_class($task), null, null, false, false, $weight, $this->selectedRange);
				}
			} elseif (get_class($task) === 'UnknownElement') {
				// Create a range for this unknown task
				$this->selectedRange->children[] = $this->getRange($task->getTag(), null, null, false, false, $weight, $this->selectedRange);
			} else {
				// Create a range for this task
				$this->selectedRange->children[] = $this->getRange(get_class($task), null, null, false, false, $weight, $this->selectedRange);
			}
		}

		//Empty tasks need atleast one child, else there will be trouble when starting and ending a task.
		if (empty($this->selectedRange->children)) {
			$this->selectedRange->children[] = $this->getRange('task', $this->selectedRange->min, $this->selectedRange->max, 1, $this->selectedRange);
		}
	}

	/**
	 * Pre-configure status tasks in this target, so values can be used for later calculations
	 * @param Target $target
	 */
	private function preconfigureStatusTasks(Target $target) {
		// Get tasks
		$tasks = $target->getTasks();

		//Foreach task detect type. Incase is is unknown  
		foreach ($tasks as $task) {
			// Print a warning when the status tasktype is not defined.
			if (get_class($task) === 'UnknownElement' && $task->getTag() === 'Status') {
				$event->getProject()->log('Try defining status tasks, if you want a properly functioning progressbar. Predicting the future when values are initializes at runtime is hard!', Project::MSG_WARN);
				// If it is a StatusTask, force it to configure.
			} elseif (get_class($task) === 'StatusTask') {
				$task->maybeConfigure();
			}
		}
	}

	/**
	 * Fill in min and max of a section of the progress-bar
	 * @param stdClass $range The section of progress-bar that needs to be calculated
	 */
	private function calculatePercentages(stdClass $range) {
		$min = $range->min;
		$max = $range->max;
		$totalWeight = 0;
		$children = array();
		foreach ($range->children as $child) {
			$children[] = $child;
			$totalWeight += $child->weight;
			if ($child->max !== null && $child->lockedMax === true) {
				$this->setRanges($min, $child->max, $children, $totalWeight);
				$min = $child->max;
				$children = array();
				$totalWeight = 0;
			}
		}
		$this->setRanges($min, $max, $children, $totalWeight);
	}

	/**
	 * 
	 * @param int|double $min
	 * @param int|double $max
	 * @param array $ranges
	 * @param int|double $totalWeight
	 */
	private function setRanges($min, $max, $ranges, $totalWeight) {
		$part = ($max - $min) / $totalWeight;
		$lastMax = $min;
		foreach ($ranges as $range) {
			if ($range->min === null || ($range->min !== null && $range->lockedMin !== true)) {
				$range->min = $lastMax;
			}
			if ($range->max === null || ($range->max !== null && $range->lockedMax !== true)) {
				$lastMax = $range->max = $lastMax + ($part * $range->weight);
			}
		}
	}

	/**
	 * Fired when a target has finished.
	 *
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent#getException()
	 */
	public function targetFinished(BuildEvent $event) {
		$this->selectedRange = $this->selectedRange->parent;
	}

	/**
	 * Fired when a task is started.
	 *
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent::getTask()
	 */
	public function taskStarted(BuildEvent $event) {
		if ($this->selectedRange->executed === false) {
			$this->printPercentage($this->selectedRange->min, $event->getProject());
		}
	}

	/**
	 * 
	 * @param int|double $percentage
	 * @param Project $project
	 */
	private function printPercentage($percentage, $project = null) {
		if ($project !== null) {
			$bar = '[ ';
			$bar .= str_repeat('#', round(0.5 * $percentage));
			$bar = str_pad($bar, 52, ' ');
			$bar .= ' ] ' . round($percentage, 2). '%';
			$project->log($bar);
		}
		echo '!**#STATUSPERCENT#=' . $percentage . '*!' . PHP_EOL;
	}

	/**
	 * Fired when a task has finished.
	 *
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent::getException()
	 */
	public function taskFinished(BuildEvent $event) {
		$this->selectedRange->executed = true;
		$this->nextTask();
	}

	/**
	 * Fetch the next task
	 */
	private function nextTask() {
		$parent = $this->selectedRange->parent;
		$count = 0;
		foreach ($parent->children as $child) {
			$count++;
			if ($this->selectedRange->max === $child->min) {
				$this->selectedRange = $child;
				break;
			}
		}
	}

	/**
	 * Sets previous executed range as current range.
	 * Phing accidently calls taskFinished before starting new targets.
	 */
	private function prevTask() {
		$selectedRange = $this->selectedRange;
		$children = $this->selectedRange->parent->children;
		foreach ($children as $child) {
			if ($child->max <= $selectedRange->min) {
				$this->selectedRange = $child;
			}
		}
	}

	/**
	 * Fired whenever a message is logged. Logging is not really the task of this
	 * buildlistener in for, so it's empty.
	 *
	 * @param BuildEvent $event The BuildEvent
	 * @see BuildEvent::getMessage()
	 */
	public function messageLogged(BuildEvent $event) {
		
	}

}

class StatusTask extends Task {

	private static $setListener;
	private $msg;
	public $forcedPercentage = null;
	public $weight = 1;

	public function setMsg($message) {
		$this->msg = $message;
	}

	public function setPercentage($forcedPercentage) {
		if ($forcedPercentage < 0) {
			$this->forcedPercentage = 0;
		} elseif ($forcedPercentage <= 100) {
			$this->forcedPercentage = (int) $forcedPercentage;
		} else {
			$this->forcedPercentage = 100;
		}
	}

	public function setWeight($weight) {
		$this->weight = $weight;
	}

	public function init() {
		if (self::$setListener === null) {
			self::$setListener = new StatusListener();
			$this->getProject()->addBuildListener(self::$setListener);
		}
	}

	public function main() {
		if ($this->msg) {
			$this->log($this->msg);
			echo '!**#STATUSMSG#=' . $this->msg . '*!' . PHP_EOL;
		}
	}

}