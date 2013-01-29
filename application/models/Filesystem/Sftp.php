<?php

class Model_Filesystem_Sftp extends Model_Filesystem_Abstract {

	/**
	 * The sftp connection resource.
	 * @var resource
	 */
	private $sftp;

	/**
	 * protocol constructor. Requires host, user and path. Port is optional.
	 * The implementation asumes the usage of unpassworded private ssh keys
	 * @param string $host Ip or hostname of the sftp server
	 * @param string $user Username to use while connection to the sftp server
	 * @param string $path Path in which to look for the files and directories
	 * @param int $port Port of the sftp server
	 * @throws Exception An exception is thrown when the ssh2 php extension is not available.
	 * @throws Exception An exception is thrown when no connection could be established.
	 */
	public function __construct($host, $user, $path, $password = null, $port = 22) {
		parent::__construct($path);

		//Check if possible to use ssh2 functions.
		if (!extension_loaded('ssh2')) {
			throw new Exception('The ssh2 PHP extension is not available!');
		}

		$this->sftp = ssh2_connect($host, $port);
		if (is_null($this->sftp) || $this->sftp === false) {
			throw new Exception('Could not establish connection to ' . $host . ':' . $port . '!');
		}

		$could_auth = false;
		$home = posix_getpwuid(posix_getuid());
		$home = $home['dir'];
		if (is_file($home . DIRECTORY_SEPARATOR . '.ssh' . DIRECTORY_SEPARATOR . 'id_rsa.pub') && $password === null) {
			$could_auth = ssh2_auth_pubkey_file($this->sftp, $user, $home . DIRECTORY_SEPARATOR . '.ssh' . DIRECTORY_SEPARATOR . 'id_rsa.pub', $home . DIRECTORY_SEPARATOR . '.ssh' . DIRECTORY_SEPARATOR . 'id_rsa');
		} elseif ($password !== null) {
			$could_auth = ssh2_auth_password($this->sftp, $user, $password);
		} else {
			$could_auth = false;
		}

		if ($could_auth === false) {
			throw new Exception('Could not authenticate with ' . $user . '@' . $host . ':' . $port . '!');
		}
	}

	/**
	 * Checks for the existance of the dir
	 * @param string $dir Name of the directory
	 * @return boolean True when the dir exists.
	 */
	public function is_dir($dir) {
		if (substr($dir, 0, 1) !== DIRECTORY_SEPARATOR) {
			if (!@is_dir('ssh2.sftp://' . $this->sftp . $this->path . DIRECTORY_SEPARATOR . $dir)) {
				return false;
			}
		} else {
			if (!@is_dir('ssh2.sftp://' . $this->sftp . $dir)) {
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
		if (substr($file, 0, 1) !== DIRECTORY_SEPARATOR) {
			if (!@is_file('ssh2.sftp://' . $this->sftp . $this->path . DIRECTORY_SEPARATOR . $file)) {
				return false;
			}
		} else {
			if (!@is_file('ssh2.sftp://' . $this->sftp . $file)) {
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
		if (strlen($directory) !== 0 && substr('ssh2.sftp://' . $this->sftp . $this->path . DIRECTORY_SEPARATOR . $directory, -1) !== DIRECTORY_SEPARATOR) {
			$directory .= DIRECTORY_SEPARATOR;
		}
		
		// Scandir suppplied the directory.
		// Concatting dir first is required because the wrappers are very picky.
		$dir = $this->path . DIRECTORY_SEPARATOR . $directory;
		$files = @scandir('ssh2.sftp://' . $this->sftp . $dir);

		// If files are returned, look at each file
		if ($files) {
			foreach ($files as $file) {
				// Check if file is one of the common files to ignore 
				preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);

				// Check if the file matches the exclude pattern
				if ($exclude) {
					preg_match($exclude, $directory . $file, $skipByExclude);
				}
				
				// Only do something with the file, if it isn't skipped.
				if (!$skip && !$skipByExclude) {
					// Create easy accessible values.
					$path = $dir . $file;
					$file = $directory . $file;
					
					// If the found file is a directory, process that directory if is recursive is true
					// and if listDirs is true, list the directory in the to hash filelist
					if (@is_dir('ssh2.sftp://' . $this->sftp . $path)) {
						if ($recursive) {
							$items = $this->getHashOfDir($file, $recursive, $listDirs, $listFiles, $listLinks, $exclude);
							$arrayItems = array_merge($arrayItems, $items);
						}
						if ($listDirs) {
							$arrayItems[] = $file . ':DIRECTORY';
						}
					// If the found file is a file, listFiles is true AND it is readable
					// add the file to the filelist with its hash.
					} elseif (@is_file('ssh2.sftp://' . $this->sftp . $path)) {
						if ($listFiles && @is_readable('ssh2.sftp://' . $this->sftp . $path)) {
							$arrayItems[] = $file . ':' . @sha1_file('ssh2.sftp://' . $this->sftp . $path);
						}
					// If the found file is a link and listLinks is true
					// add the file to the filelist with LINK as hash
					} elseif (@is_link('ssh2.sftp://' . $this->sftp . $path)) {
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

