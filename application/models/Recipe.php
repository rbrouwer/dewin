<?php

class Model_Recipe {

	/**
	 * path to the recipe file
	 * @var string
	 */
	private $path;

	/**
	 * filename of the recipe file
	 * @var string
	 */
	private $filename;

	/**
	 * Name of the recipe file (as defined in the recipe)
	 * @var string
	 */
	private $name;

	/**
	 * author of the recipe file (as defined in the recipe)
	 * @var string 
	 */
	private $author;

	/**
	 * version of the recipe file (as defined in the recipe)
	 * @var string 
	 */
	private $version;
	
	/**
	 * api-version of the recipe file (as defined in the recipe)
	 * @var string 
	 */
	private $apiVersion;
	
	/**
	 * Path to the rsyncItemizedChanges file. Recursive from the deployment path (as defined in the recipe)
	 * @var string 
	 */
	private $rsyncItemizedOutput;
	
	/**
	 * Path to the rsync file list. Recursive from the deployment path (as defined in the recipe)
	 * @var string 
	 */
	private $rsyncFileList;

	/**
	 * Activate debg mode. 0 = off, 1 = on, verbose on, 2 = on, with verbose and debug on.
	 * @var integer
	 */
	private $debugMode;
	
	/**
	 * Notes of the recipe file (as defined in the recipe)
	 * @var string
	 */
	private $note;

	/**
	 * Array with targets that are present in this recipe
	 * @var array 
	 */
	private $targetsDesc;

	/**
	 * Array with targets that are present in this recipe
	 * @var array 
	 */
	private $targets;

	/**
	 * Array with files that should be present in the application directory
	 * @var array 
	 */
	private $requiredFiles;

	/**
	 * Array with directories that should be present in the application directory
	 * @var array 
	 */
	private $requiredDirs;
	
	/**
	 * Array with databases that the recipe is managing
	 * @var array 
	 */
	private $databases;

	public function __construct($filename, $path = null) {
		if ($path === null) {
			if ($filename !== null) {
				$this->filename = $filename;
				$config = Zend_Registry::get('config');
				$this->path = $config->directories->buildscript . $filename;
			} else {
				throw new Exception('Invalid input; Either filename or path is required!');
			}
		} else {
			//Do path pre-processing here...
			if ($filename !== null) {
				$this->filename = $filename;
				$this->path = $path . $filename;
			} else {
				$this->filename = basename($this->path);
				$this->path = $path;
			}
		}
		if (is_file($this->path) && is_readable($this->path)) {
			$this->load();
		} else {
			throw new Exception('File does not exist, or is unreadable.');
		}
	}

	/**
	 * Read the targets and comments from a phing recipe.
	 */
	private function load() {
		//Get project name
		$xml = @simplexml_load_file($this->path);
		if ($xml === false) {
			throw new Exception('Invalid XML');
		}
		$this->name = (string) $xml['name'];

		//Get targets
		$this->targets = array();
		$this->targetsDesc = array();
		foreach ($xml->target as $child) {
			$target = (string) $child['name'];
			if (substr($target, 0, 2) === '__') {
				continue;
			}
			array_push($this->targets, (string) $target);
			array_push($this->targetsDesc, (string) $child['description']);
		}

		//Load version and comments etc etc...
		$this->requiredDirs = array();
		$this->requiredFiles = array();
		$this->rsyncItemizedOutput = 'rsyncPilot.txt';
		$this->rsyncFileList = 'rsyncFileList.txt';
		$this->debugMode = 0;
		$this->databases = array();
		if (preg_match_all('/\<!--(.*?)--\>/ims', file_get_contents($this->path), $matches) !== false) {
			foreach ($matches['1'] as $match) {
				$this->procesComments($match);
			}
		}
		
		// Api 1.0 fallback or for recipes that do not define their SQL data...
		if (empty($this->databases)) {
			$database = new stdClass();
			$database->name = 'Main';
			$database->pathPatch = 'sql/sql.patch.sql';
			$database->pathFull = 'sql/sql.full.sql';
			$database->pathDeploy = 'sql/sql.sql';
			$this->databases[] = $database;
		}
	}

	/**
	 * Reads a comment block and processes the contained tags.
	 * @param string $commentContent
	 */
	private function procesComments($commentContent) {
		$commentContent = explode(PHP_EOL, $commentContent);
		$previousTag = '';
		$tags = array('author', 'version', 'apiversion', 'rsyncItemizedOutput', 'rsyncFileList', 'debugMode');
		$multiLineTags = array('note');
		$arrayTags = array('requiresDir', 'requiresFile', 'database');

		foreach ($commentContent as $line) {
			$line = trim($line);
			if (preg_match('/^@(.+?) (.*)$/', $line, $matches)) {
				//Find normal tags
				if (in_array($matches['1'], $tags)) {
					$cmd = 'set' . ucfirst($matches['1']);
					$this->$cmd($matches['2']);
				}
				//Find multi-line tags
				if (in_array($matches['1'], $multiLineTags)) {
					$cmd = 'set' . ucfirst($matches['1']);
					$this->$cmd($matches['2']);
				}
				//Find array tags
				if (in_array($matches['1'], $arrayTags)) {
					$cmd = 'add' . ucfirst($matches['1']);
					$this->$cmd($matches['2']);
				}
				// save previous tag for multi-line tags
				$previousTag = $matches['1'];
			} else {
				// Append more lines to multi-line tags.
				if (in_array($previousTag, $multiLineTags)) {
					$cmd = 'append' . ucfirst($previousTag);
					$this->$cmd($line);
				}
			}
		}
	}

	/**
	 * Return an array with found recipes
	 * @return array
	 */
	public static function getRecipes() {
		$config = Zend_Registry::get('config');
		$files = scandir($config->directories->buildscript);

		$array = array();

		foreach ($files as $file) {
			if (preg_match('/\.xml$/i', $file)) {
				array_push($array, new self($file));
			}
		}
		return $array;
	}

	/**
	 * Returns a recipe based on the recipe's file name.
	 * @param string $file Name of the file
	 * @return Model_Recipe|null The recipe
	 */
	public static function getRecipe($file) {
		$recipes = Model_Recipe::getRecipes();
		$resultRecipe = null;
		foreach ($recipes as $recipe) {
			if ($recipe->getFileName() === $file) {
				$resultRecipe = $recipe;
			}
		}

		return $resultRecipe;
	}

	public function getPath() {
		return $this->path;
	}

	public function getName() {
		return $this->name;
	}

	public function getFileName() {
		return $this->filename;
	}

	public function getAuthor() {
		return $this->author;
	}

	private function setAuthor($author) {
		$this->author = $author;
	}

	public function getVersion() {
		return $this->version;
	}

	private function setVersion($version) {
		$this->version = $version;
	}
	
	public function getApiVersion() {
		return $this->apiVersion;
	}

	public function setApiVersion($apiVersion) {
		$this->apiVersion = $apiVersion;
	}
	
	public function getRsyncItemizedOutput() {
		return $this->rsyncItemizedOutput;
	}

	public function setRsyncItemizedOutput($line) {
		$this->rsyncItemizedOutput = $line;
	}
	
	public function getRsyncFileList() {
		return $this->rsyncFileList;
	}

	public function setRsyncFileList($line) {
		$this->rsyncFileList = $line;
	}
	
	public function getDebugMode() {
		return $this->debugMode;
	}

	public function setDebugMode($line) {
		$this->debugMode = $line;
	}
	
	public function getNote() {
		return $this->note;
	}

	private function setNote($note) {
		$this->note = $note;
	}

	/**
	 * Add a line to the note of the recipe, used by the procesComments function.
	 * @param string $note
	 * @param boolean $addNewLine
	 */
	private function appendNote($note, $addNewLine = true) {
		$this->note .= ($addNewLine) ? PHP_EOL . $note : $note;
	}

	/**
	 * Alias of addRequiresDir
	 * @param string $line
	 */
	private function addRequiresDir($line) {
		$this->addRequiredDir($line);
	}

	/**
	 * 
	 * @param string $line
	 */
	private function addRequiredDir($line) {
		$this->requiredDirs[] = trim($line);
	}

	/**
	 * Alias of addRequiredFile
	 * @param string $line
	 */
	private function addRequiresFile($line) {
		$this->addRequiredFile($line);
	}

	/**
	 * 
	 * @param type $line
	 */
	private function addRequiredFile($line) {
		$this->requiredFiles[] = trim($line);
	}

	/**
	 * @database main sql/sql.patch.sql sql/sql.full.sql sql/sql.sql
	 * @database version sql/sql2.patch.sql sql/sql2.full.sql sql/sql2.sql
	 * @param string $line
	 */
	private function addDatabase($line) {
		if (preg_match('/(\w+)\s+([\w\.\-\/:]+)\s+([\w\.\-\/:]+)\s+([\w\.\-\/:]+)/i', trim($line), $matches)) {
			$database = new stdClass();
			$database->name = $matches['1'];
			$database->pathPatch = $matches['2'];
			$database->pathFull = $matches['3'];
			$database->pathDeploy = $matches['4'];
			$this->databases[] = $database;
		}
	}
	
	public function getDatabases() {
		return $this->databases;
	}

		
	/**
	 * Checks if the Model_Filesystem_Abstract $path contains all required files and folders of the recipe.
	 * @param Model_Filesystem_Abstract $path
	 * @return boolean
	 */
	public function validateFolder(Model_Filesystem_Abstract $path) {
		foreach ($this->requiredDirs as $dir) {
			if (!$path->is_dir($dir)) {
				return false;
			}
		}
		foreach ($this->requiredFiles as $file) {
			if (!$path->is_file($file)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Return all targets of the recipe
	 * @return array
	 */
	public function getTargets() {
		return $this->targetsDesc;
	}

	public function hasTarget($name) {
		return (in_array($name, $this->targets));
	}

}
