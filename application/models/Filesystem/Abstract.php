<?php
/**
 * A very limited abstract filesystem model.
 * It is only used by the recipe to do check for the existance of files and directories.
 * Extensions will implement protocols such as ftp, sftp or just the local filesystem.
 */
abstract class Model_Filesystem_Abstract {

	/**
	 * Path in which to look for the files and directories
	 * @var string
	 */
	protected $path;

	/**
	 * Simple constructor, requires only the path
	 * @param string $path
	 */
	public function __construct($path) {
		$this->path = ltrim($path);
	}

	/**
	 * Checks for the existance of the dir
	 * @param string $dir Name of the directory
	 * @return boolean True when the dir exists.
	 */
	public abstract function is_dir($dir);

	/**
	 * Checks for the existance of the file
	 * @param string $file Name of the file
	 * @return boolean True when the file exists.
	 */
	public abstract function is_file($file);
	
	/**
	 * Creates a hash unique of the contains of that directory.
	 * @param string $directory Directory to get the hash for. Path 
	 * @param boolean $recursive Include contents of directories of directory in the hash
	 * @param boolean $listDirs Add directories to the hash
	 * @param boolean $listFiles Add files to the hash
	 * @param boolean $listLinks Add symlinks to the hash
	 * @param string $exclude Regex pattern containing which files should be excluded in the hash
	 */
	public abstract function getHash($directory, $recursive = true, $listDirs = false, $listFiles = true, $listLinks = false, $exclude = '');
}