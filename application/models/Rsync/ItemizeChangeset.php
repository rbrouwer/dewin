<?php

class Model_Rsync_ItemizeChangeset {
	/**
	 * Array with changes detected by rsync
	 * @var array
	 */
	private $itemizeChangeset;
	/**
	 * Number of files in rsync path
	 * @var int
	 */
	private $numberOfFiles;
	/**
	 * Number of files which are/should be transfered
	 * @var int
	 */
	private $numberOfFilesTransferred;
	/**
	 * Size of all files in rsync path together
	 * @var type 
	 */
	private $totalFilesize;
	/**
	 * Size of all files which are/should be transfered together
	 * @var int
	 */
	private $totalTransferredFileSize;
	/**
	 * I dunno, just got it from the stats I did read! ok?
	 * @var int
	 */
	private $literalData;
	/**
	 * Probably the meaning can be found in rsync's documentation...
	 * @var int
	 */
	private $matchedData;
	/**
	 * Size of rsync's file list in bytes
	 * @var int
	 */
	private $fileListSize;
	/**
	 * Time in sec it took rsync to generate the file list
	 * @var float
	 */
	private $fileListGenerationTime;
	/**
	 * Time in sec it took rsync to transfer the file list
	 * @var float
	 */
	private $fileListTransferTime;
	/**
	 * The amount of bytes sent by rsync
	 * @var int
	 */
	private $totalBytesSent;
	/**
	 * The amount of bytes received by rsync
	 * @var int
	 */
	private $totalBytesReceived;
	/**
	 * The bytes/sec of this rsync operation
	 * @var float
	 */
	private $bytesPerSecond;
	/**
	 * It's a float and it lies when in dry-run mode
	 * @var float
	 */
	private $speedup;
	
	/**
	 * Map that recognizes lines and gets them to the right method
	 * @var array
	 */
	private $map = array(
			'lineNumberOfFiles' => '/^Number of files\: (\d+)$/',
			'lineNumberOfFilesTransferred' => '/^Number of files transferred\: (\d+)$/',
			'lineTotalFilesize' => '/^Total file size\: (\d+) bytes$/',
			'lineTotalTransferredFileSize' => '/^Total transferred file size\: (\d+) bytes$/',
			'lineLiteralData' => '/^Literal data\: (\d+) bytes$/',
			'lineMatchedData' => '/^Matched data\: (\d+) bytes$/',
			'lineFileListSize' => '/^File list size\: (\d+)$/',
			'lineFileListGenerationTime' => '/^File list generation time\: ([0-9]*\.[0-9]+|[0-9]+) seconds$/',
			'lineFileListTransferTime' => '/^File list transfer time\: ([0-9]*\.[0-9]+|[0-9]+) seconds$/',
			'lineTotalBytesSent' => '/^Total bytes sent\: (\d+)$/',
			'lineTotalBytesReceived' => '/^Total bytes received\: (\d+)$/',
			'lineBytesPerSecond' => '`^sent (\d+) bytes  received (\d+) bytes  ([0-9]*\.[0-9]+|[0-9]+) bytes/sec$`',
			'lineSpeedup' => '/^total size is (\d+)  speedup is ([0-9]*\.[0-9]+|[0-9]+)( \(DRY RUN\)|)$/',
	);

	/**
	 * Accepts a path to a itemized transferlist of rsync and reads it...
	 * Command: rsync --dry-run -aztci --stats
	 * @param string $path
	 */
	public function __construct($path) {
		$this->map['createItemizeChange'] = Model_Rsync_ItemizeChange::getPattern();
		$lines = file($path);
		if ($lines === false) {
			throw new Exception('Rsync file list(' . $path . ') could not be opened.');
		}
		$this->itemizeChangeset = array();
		foreach ($lines as $line) {
			foreach ($this->map as $method => $regex) {
				if (preg_match($regex, $line, $matches)) {
					$this->$method($matches);
				}
			}
		}
	}
	
	/**
	 * Sets the number of files
	 * @param array $matches Matches array of preg_match
	 */
	private function lineNumberOfFiles($matches) {
		$this->numberOfFiles = (int)$matches['1'];
	}
	
	/**
	 * Sets
	 * @param array $matches Matches array of preg_match
	 */
	private function lineNumberOfFilesTransferred($matches) {
		$this->numberOfFilesTransferred = (int)$matches['1'];
	}
	
	/**
	 * Sets the total file size of the files transferred
	 * @param array $matches Matches array of preg_match
	 */
	private function lineTotalTransferredFileSize($matches) {
		$this->totalTransferredFileSize = (int)$matches['1'];
	}
	
	/**
	 * Sets the total file size
	 * @param array $matches Matches array of preg_match
	 */
	private function lineTotalFilesize($matches) {
		$this->totalFilesize = (int)$matches['1'];
	}
	
	/**
	 * Sets the literal data value
	 * @param array $matches Matches array of preg_match
	 */
	private function lineLiteralData($matches) {
		$this->literalData = (int)$matches['1']; 
	}
	
	/**
	 * Sets the matched data value
	 * @param array $matches Matches array of preg_match
	 */
	private function lineMatchedData($matches) {
		$this->matchedData = (int)$matches['1'];
	}
	
	/**
	 * Sets the file list size in bytes
	 * @param array $matches Matches array of preg_match
	 */
	private function lineFileListSize($matches) {
		$this->fileListSize = (int)$matches['1'];
	}
	
	/**
	 * Sets how long it took to generate the file list
	 * @param array $matches Matches array of preg_match
	 */
	private function lineFileListGenerationTime($matches) {
		$this->fileListGenerationTime = (float)$matches['1'];
	}
	
	/**
	 * Sets how long it took to transfer the file list
	 * @param array $matches Matches array of preg_match
	 */
	private function lineFileListTransferTime($matches) {
		$this->fileListTransferTime = (float)$matches['1'];
	}
	
	/**
	 * Sets how much bytes were sent
	 * @param array $matches Matches array of preg_match
	 */
	private function lineTotalBytesSent($matches) {
		$this->totalBytesSent = (int)$matches['1'];
	}
	
	/**
	 * Sets how many bytes were received
	 * @param array $matches Matches array of preg_match
	 */
	private function lineTotalBytesReceived($matches) {
		$this->totalBytesReceived = (int)$matches['1'];
	}
	
	/**
	 * Sets the bandwidth of the session
	 * @param array $matches Matches array of preg_match
	 */
	private function lineBytesPerSecond($matches) {
		$this->bytesPerSecond = (float)$matches['3'];
	}
	
	/**
	 * Sets the speedup from the line that is in...
	 * @param array $matches Matches array of preg_match
	 */
	private function lineSpeedup($matches) {
		$this->speedup = (float)$matches['2'];
	}
	
	/**
	 * Adds an item to the Itemize Changeset
	 * @param array $matches Matches array of preg_match
	 */
	private function createItemizeChange($matches) {
		array_push($this->itemizeChangeset, new Model_Rsync_ItemizeChange($matches['0']));
	}
	
	/**
	 * Returns all items of this changeset
	 * @return array
	 */
	public function getItemizeChangeset() {
		return $this->itemizeChangeset;
	}
	
	/**
	 * Returns the number of files that were transferred during this pilot.
	 * @return int
	 */
	public function getNumberOfFilesTransferred() {
		return $this->numberOfFilesTransferred;
	}
	
	/**
	 * Creates a file list at $path for rsync. That way rsync only has to check only those files.
	 * rsync --files-from="$path" -aztc
	 * @param array $fileIds
	 * @param string $path
	 * @throws Exception
	 */
	public function buildFileList($fileIds, $path) {
		$files = array();
		foreach($fileIds as $id) {
			if (isset($this->itemizeChangeset[$id])) {
				$itemizeChange = $this->itemizeChangeset[$id];
				$files[] = $itemizeChange->getPath();
			}
		}
		$bytes = file_put_contents($path, implode("\n", $files));
		if ($bytes == false) {
			throw new Exception('Rsync file list(' . $path . ') could not be created.');
		}
	}
					

}