<?php
namespace phake;

function resolve_runfile($directory) {
    $runfiles = array('Phakefile', 'Phakefile.php');
    do {
        foreach ($runfiles as $r) {
            $candidate = $directory . '/' . $r;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        if ($directory == '/') {
            throw new \Exception("No Phakefile found");
        }
        $directory = dirname($directory);
    } while (true);
}

function load_runfile($file, $include_phake_files = true, array $alternate_dirs = array()) {
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
		require $file;
}

function find_phake_files($dir) {
	$result = array();
	$extension = '.phake.php';

	$dir_iterator = new \RecursiveDirectoryIterator($dir);
	$iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);

	foreach ($iterator as $file) {
		$filename = $file->getFilename();
		if (strrpos($filename, $extension, 0) === strlen($filename) - strlen($extension)) { //ends with
			$result[] = $file->getPathname();
		}
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
