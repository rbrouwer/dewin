<?php

/**
 * A very limited abstract filesystem model.
 * It is only used by the recipe to do check for the existance of files and directories.
 * This extension implements the check on the local filesystem.
 */
class Model_Filesystem_Local extends Model_Filesystem_Abstract {

	/**
	 * Checks for the existance of the dir
	 * @param string $dir Name of the directory
	 * @return boolean True when the dir exists.
	 */
	public function is_dir($dir) {
		// If the directory does not start with the directory separator, 
		// prepend the path and check if the directory exist.
		if (substr($dir, 0, 1) !== DIRECTORY_SEPARATOR) {
			if (!@is_dir($this->path . DIRECTORY_SEPARATOR . $dir)) {
				return false;
			}
		// Otherwise do not append the path and start from root.
		} else {
			if (!@is_dir($dir)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks for the existance of the file
	 * @param string $file Name of the file
	 * @return boolean True when the file exists.
	 */
	public function is_file($file) {
		// If the directory does not start with the directory separator, 
		// prepend the path and check if the directory exist.
		if (substr($file, 0, 1) !== DIRECTORY_SEPARATOR) {
			if (!@is_file($this->path . DIRECTORY_SEPARATOR . $file)) {
				return false;
			}
		// Otherwise do not append the path and start from root.
		} else {
			if (!@is_file($file)) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Creates a hash unique of the contains of that directory.
	 * @param string $directory Directory to get the hash for. Path 
	 * @param boolean $recursive Include contents of directories of directory in the hash
	 * @param boolean $listDirs Add directories to the hash
	 * @param boolean $listFiles Add files to the hash
	 * @param boolean $listLinks Add symlinks to the hash
	 * @param string $exclude Regex pattern containing which files should be excluded in the hash
	 */
	public function getHash($directory, $recursive = true, $listDirs = false, $listFiles = true, $listLinks = false, $exclude = '') {
		$items = $this->getHashOfDir($directory, $recursive, $listDirs, $listFiles, $listLinks, $exclude);
		return sha1(implode(PHP_EOL, $items));
	}

	/**
	 * Creates an array of directory/files/symlinks with their hashes.
	 * @param string $directory Directory to get the hash for. Path 
	 * @param boolean $recursive Include contents of directories of directory in the hash
	 * @param boolean $listDirs Add directories to the hash
	 * @param boolean $listFiles Add files to the hash
	 * @param boolean $listLinks Add symlinks to the hash
	 * @param string $exclude Regex pattern containing which files should be excluded in the hash
	 */
	private function getHashOfDir($directory, $recursive = true, $listDirs = false, $listFiles = true, $listLinks = false, $exclude = '') {
		// Init some variables
		$arrayItems = array();
		$skipByExclude = false;
		// Add Directory separator 
		if (strlen($directory) !== 0 && substr($this->path . DIRECTORY_SEPARATOR . $directory, -1) !== DIRECTORY_SEPARATOR) {
			$directory .= DIRECTORY_SEPARATOR;
		}
		
		// Scandir suppplied the directory.
		$files = scandir($this->path . DIRECTORY_SEPARATOR. $directory);
		// If files are returned, look at each file
		if ($files) {
			foreach($files as $file) {
				// Check if file is one of the common files to ignore 
				preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);
				
				// Check if the file matches the exclude pattern
				if ($exclude) {
					preg_match($exclude, $directory . $file, $skipByExclude);
				}
				
				// Only do something with the file, if it isn't skipped.
				if (!$skip && !$skipByExclude) {
					// Create easy accessible values.
					$path = $this->path . DIRECTORY_SEPARATOR. $directory . $file;
					$file = $directory . $file;
					
					// If the found file is a directory, process that directory if is recursive is true
					// and if listDirs is true, list the directory in the to hash filelist
					if (is_dir($path)) {
						if ($recursive) {
							$items = $this->getHashOfDir($file, $recursive, $listDirs, $listFiles, $listLinks, $exclude);
							$arrayItems = array_merge($arrayItems, $items);
						}
						if ($listDirs) {
							$arrayItems[] = $file.':DIRECTORY';
						}
					// If the found file is a file, listFiles is true AND it is readable
					// add the file to the filelist with its hash.
					} elseif(is_file($path)) {
						if ($listFiles && is_readable($path)) {
							$arrayItems[] = $file . ':' .  sha1_file($path);
						}
					// If the found file is a link and listLinks is true
					// add the file to the filelist with LINK as hash
					} elseif(is_link($path)) {
						if ($listLinks) {
							$arrayItems[] = $file . ':LINK';
						}
					}
				}
			}
		}
		return $arrayItems;
	}

}
