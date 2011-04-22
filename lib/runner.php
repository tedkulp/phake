<?php
namespace phake;

require dirname(__FILE__) . DIRECTORY_SEPARATOR . '/getopt.php';
require dirname(__FILE__) . DIRECTORY_SEPARATOR . '/phake.php';
require dirname(__FILE__) . DIRECTORY_SEPARATOR . '/utils.php';
require dirname(__FILE__) . DIRECTORY_SEPARATOR . '/global_helpers.php';
require dirname(__FILE__) . DIRECTORY_SEPARATOR . '/builder.php';

class Runner
{
	function __construct($runfile = '', $include_phake_files = false, array $dirs = array())
	{
		Builder::$global = new Builder;

		try {

			// Defaults
			$action     = 'invoke';
			$task_names = array('default');

			$args = $GLOBALS['argv'];
			array_shift($args);

			//Show the task list if no task is given
			if (empty($args))
			{
				$action = 'list';
			}

			$options = Console_Getopt::getopt2($args, 'T', array('tasks'), true);

			foreach ($options[0] as $one_option) {
				switch ($one_option[0]) {
				case 'T':
				case '--tasks':
					$action = 'list';
					break;
				}
			}

			$cli_args = array();
			$cli_task_names = array();
			foreach ($options[1] as $option) {
				if (ltrim($option, '-') == $option) {
					if (strpos($option, '=') > 0) {
						list($k, $v) = explode('=', $option);
						$cli_args[$k] = $v;
					} else {
						if (!count($cli_task_names))
							$cli_task_names[] = $option;
					}
				}
			}

			if (count($cli_task_names)) {
				$task_names = $cli_task_names;
			}

			//
			// Locate runfile

			if (empty($runfile) || !is_file($runfile))
				$runfile = resolve_runfile(getcwd());

			if (!empty($runfile))
				$directory = dirname($runfile);
			else if (isset($_SERVER['PWD']))
				$directory = $_SERVER['PWD'];

			if (!@chdir($directory)) {
				throw new \Exception("Couldn't change to directory '$directory'");
			} else {
				echo "(in $directory)\n";
			}

			load_runfile($runfile, $include_phake_files, $dirs);

			//
			// Go, go, go

			$application = Builder::$global->get_application();
			$application->set_args($cli_args);
			$application->reset();

			switch ($action) {
			case 'list':
				$task_list = $application->get_task_list();
				if (count($task_list)) {
					$max = max(array_map('strlen', array_keys($task_list)));
					foreach ($task_list as $name => $desc) {
						echo str_pad($name, $max + 4) . $desc . "\n";
					}
				}
				break;
			case 'invoke':
				foreach ($task_names as $task_name) {
					$application->invoke($task_name);
				}
				break;
			}

		} catch (TaskNotFoundException $tnfe) {
			fatal($tnfe, "Don't know how to build task '$task_name'\n");
		} catch (\Exception $e) {
			fatal($e);
		}
	}
}
