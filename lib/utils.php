<?php
namespace phake;

function resolve_runfile($directory) {
	$count = 0;
    $runfiles = array('Phakefile', 'Phakefile.php');
    do {
        foreach ($runfiles as $r) {
            $candidate = $directory . '/' . $r;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        if ($directory == '/') {
			return '';
        }
        $directory = dirname($directory);
		$count++;
    } while ($count < 30);
}

function load_runfile($file, $include_phake_files = false, array $alternate_dirs = array()) {
	$files = array($file);
	if ($include_phake_files) {
		$files = array_merge($files, find_phake_files(dirname($file)));
		if (count($alternate_dirs)) {
			foreach ($alternate_dirs as $one_dir) {
				$files = array_merge($files, find_phake_files($one_dir));
			}
		}
	}
	$files = array_unique($files);
	foreach ($files as $file)
	{
		if (!empty($file))
			require $file;
	}
}

function find_phake_files($dir) {
	$result = array();
	$extension = '.phake.php';

	if (empty($dir) || !@is_dir($dir) || !@is_readable($dir))
		return $result;

	try
	{
		$dir_iterator = new \RecursiveDirectoryIterator($dir);
		$iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

		foreach ($iterator as $file)
		{
			$filename = $file->getFilename();
			if (strrpos($filename, $extension, 0) === strlen($filename) - strlen($extension)) { //ends with
				$result[] = $file->getPathname();
			}
		}
	}
	catch (Exception $e)
	{
	}

	return $result;
}

function fatal($exception, $message = null) {
    echo "aborted!\n";
    if (!$message) $message = $exception->getMessage();
    if (!$message) $message = get_class($exception);
    echo $message . "\n\n";
    global $trace;
    if ($trace) {
        echo $exception->getTraceAsString() . "\n";
    } else {
        echo "(See full trace by running task with --trace)\n";
    }
    die(1);
}
?>
