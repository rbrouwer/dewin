<?php

//.d..t...... ./
//.d..t...... website/
//.d..t...... website/var/
//.d..t...... website/var/cache/
//<fc.t...... website/var/cache/zend_cache---internal-metadatas---pimcore_system_route_redirect
//.f..t...... website/var/cache/zend_cache---pimcore_system_route_redirect
//.d..t...... website/var/config/
//<fcst...... website/var/config/system.xml
//<fcst...... website/var/log/debug.log
class Model_Rsync_ItemizeChange {

	/**
	 * Cached preg match pattern
	 * @var string 
	 */
	private static $pattern;

	const OPERATION_SENT = '<';
	const OPERATION_RECEIVE = '>';
	const OPERATION_CREATION = 'c';
	const OPERATION_HARD_LINK = 'h';
	const OPERATION_NOT_UPDATED = '.';
	const OPERATION_OTHER = '*';

	/**
	 * Contains one of the OPERATION constants values.
	 * @var string
	 */
	protected $operation;

	const TYPE_FILE = 'f';
	const TYPE_DIRECTORY = 'd';
	const TYPE_SYMBOLIC_LINK = 'L';
	const TYPE_DEVICE = 'D';
	const TYPE_SPECIAL_FILE = 'S';

	/**
	 * Contains one of the TYPE constants values.
	 * @var string
	 */
	protected $type;

	const DIFF_NO_CHANGE = '.';
	const DIFF_NEWLY_CREATED_ITEM = '+';
	const DIFF_IDENTICAL = ' ';
	const DIFF_UNKNOWN = '?';

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffChecksum;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffSize;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffTime;

	/**
	 * Whenever time is set to transfer time if time differs
	 * @var boolean
	 */
	protected $timeToTransferTime;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffPermissions;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffOwner;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffGroup;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffACLInfo;

	/**
	 * Contains either a boolean when a or no difference is detected or one of the exception DIFF_ constants
	 * @var boolean|string
	 */
	protected $diffExtendedAttributeInfo;

	/**
	 * path of the file
	 * @var string
	 */
	protected $path;

	/**
	 * Checks if a line is a itemize change line.
	 * @param string $line
	 * @return boolean
	 */
	public static function isChangelogLine($line) {
		if (preg_match(self::makePattern(), $line)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Builds the preg match pattern and returns that.
	 * @return string The preg match pattern
	 */
	public static function getPattern() {
		if (self::$pattern === null) {
			$pattern = '/^';

			//Operation:
			$pattern .= '(';
			$pattern .= preg_quote(self::OPERATION_SENT) . '|';
			$pattern .= preg_quote(self::OPERATION_RECEIVE) . '|';
			$pattern .= preg_quote(self::OPERATION_CREATION) . '|';
			$pattern .= preg_quote(self::OPERATION_HARD_LINK) . '|';
			$pattern .= preg_quote(self::OPERATION_NOT_UPDATED) . '|';
			$pattern .= preg_quote(self::OPERATION_OTHER) . ')';

			//Type
			$pattern .= '(';
			$pattern .= preg_quote(self::TYPE_FILE) . '|';
			$pattern .= preg_quote(self::TYPE_DIRECTORY) . '|';
			$pattern .= preg_quote(self::TYPE_SYMBOLIC_LINK) . '|';
			$pattern .= preg_quote(self::TYPE_DEVICE) . '|';
			$pattern .= preg_quote(self::TYPE_SPECIAL_FILE) . ')';

			//Prepare for diff values
			$diffNoChange = preg_quote(self::DIFF_NO_CHANGE);
			$diffNewlyCreatedItem = preg_quote(self::DIFF_NEWLY_CREATED_ITEM);
			$diffIdentical = preg_quote(self::DIFF_IDENTICAL);
			$diffUnknown = preg_quote(self::DIFF_UNKNOWN);
			$diff = $diffNoChange . '|' . $diffNewlyCreatedItem . '|' . $diffIdentical . '|' . $diffUnknown . '|';

			//DiffChecksum
			$pattern .= '(' . $diff . 'c)';
			//DiffSize
			$pattern .= '(' . $diff . 's)';
			//DiffTime
			$pattern .= '(' . $diff . 't|T)';
			//DiffPermissions
			$pattern .= '(' . $diff . 'p)';
			//DiffOwner
			$pattern .= '(' . $diff . 'o)';
			//DiffGroup
			$pattern .= '(' . $diff . 'g)';
			//Useless dot.../exception
			$pattern .= '(' . $diff . '\.)';
			//diffACLInfo
			$pattern .= '(' . $diff . 'a)';
			//diffExtendedAttributeInfo
			$pattern .= '(' . $diff . 'x)';
			//Path
			$pattern .= ' (.+)$/';
			self::$pattern = $pattern;
		}
		return self::$pattern;
	}

	/**
	 * Read the itemizechange line and set the values.
	 * @param string $line
	 * @throws Exception
	 */
	public function __construct($line) {
		if (preg_match(self::getPattern(), $line, $matches)) {
			//Set the values
			$this->operation = $matches['1'];
			$this->type = $matches['2'];
			$this->setDiffValue('diffChecksum', $matches['3'], 'c');
			$this->setDiffValue('diffSize', $matches['4'], 's');
			
			if ($matches['5'] === 't') {
				$this->diffTime = true;
				$this->timeToTransferTime = false;
			} elseif ($matches['5'] === 'T') {
				$this->diffTime = true;
				$this->timeToTransferTime = true;
			} elseif ($matches['5'] === self::DIFF_NO_CHANGE) {
				$this->diffTime = false;
				$this->timeToTransferTime = false;
			} else {
				$this->diffTime = $matches['5'];
				$this->timeToTransferTime = false;
			}
			
			$this->setDiffValue('diffPermissions', $matches['6'], 'p');
			$this->setDiffValue('diffOwner', $matches['7'], 'o');
			$this->setDiffValue('diffGroup', $matches['8'], 'g');
			//9 is the useless dot/exception, remember?
			$this->setDiffValue('diffACLInfo', $matches['10'], 'a');
			$this->setDiffValue('diffExtendedAttributeInfo', $matches['11'], 'x');

			$this->path = $matches['12'];
		} else {
			throw new Exception('Supplied invalid ItemizeChange Line!');
		}
	}

	/**
	 * Code re-use to set many variables to the right value without repeating these if-statements/
	 * @param string $var Variable to set
	 * @param string $value input
	 * @param string $expected character that is expected when a difference is detected
	 */
	private function setDiffValue($var, $value, $expected) {
		if ($value === $expected) {
			$this->$var = true;
		} elseif ($value === self::DIFF_NO_CHANGE) {
			$this->$var = false;
		} else {
			$this->$var = $value;
		}
	}

	/**
	 * First attempt at deciding whenever this file has been 'transferred' according to rsync.
	 * @return boolean
	 */
	public function isTransferred() {
		return ($this->operation != self::OPERATION_NOT_UPDATED);
	}

	/**
	 * Return the name of the file
	 * @return string
	 */
	public function getFileName() {
		return basename($this->path);
	}

	/**
	 * return the path of the file
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

}
