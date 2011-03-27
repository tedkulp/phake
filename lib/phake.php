<?php
namespace phake;

class TaskNotFoundException extends \Exception {};
class TaskCollisionException extends \Exception {};

class Application implements \ArrayAccess, \IteratorAggregate
{
    private $root;
    private $args;
	public $cmd_args = null;
	public $called_task = '';
    
	public function __construct() {
		$this->root = new Node(null, '');
		$this->args = array();
	}
    
    public function root() {
        return $this->root;
    }
    
    public function invoke($task_name, $relative_to = null) {
        $this->resolve($task_name, $relative_to)->invoke($this);
    }
    
    public function reset() {
        $this->root->reset();
    }
    
    public function resolve($task_name, $relative_to = null) {
		$this->called_task = $task_name;
        if ($task_name[0] != ':') {
            if ($relative_to) {
                try {
                    return $relative_to->resolve(explode(':', $task_name));
                } catch (TaskNotFoundException $tnfe) {}
            }
        } else {
            $task_name = substr($task_name, 1);
        }
        return $this->root->resolve(explode(':', $task_name));
    }
    
    public function get_task_list() {
        $list = array();
        $this->root->fill_task_list($list);
        ksort($list);
        return $list;
    }
    
    public function __toString() {
        return '<' . get_class($this) . '>';
    }

    //
    // ArrayAccess/IteratorAggregate - for argument support
    
    public function set_args(array $args) {
        $this->args = $args;
    }

	public function set_cmd_args(array $args) {
		$this->cmd_args = $args;
	}

	public function clear_cmd_args() {
		$this->cmd_args = null;
	}
    
    public function offsetExists($k) {
		$ary = $this->args;
		if (is_array($this->cmd_args))
			$ary = array_merge($ary, $this->cmd_args);
        return array_key_exists($k, $ary);
    }
    
    public function offsetGet($k) {
		$ary = $this->args;
		if (is_array($this->cmd_args))
			$ary = array_merge($ary, $this->cmd_args);
        return $ary[$k];
    }
    
    public function offsetSet($k, $v) {
        $this->args[$k] = $v;
    }
    
    public function offsetUnset($k) {
        unset($this->args[$k]);
    }
    
    public function getIterator() {
        return new \ArrayIterator($this->args);
    }
}

class Node
{
    private $parent;
    private $name;

    private $before     = array();
    private $tasks      = array();
    private $after      = array();
    
    private $children   = array();
    
    public function __construct($parent, $name) {
        $this->parent = $parent;
        $this->name = $name;
    }
    
    public function get_name($name) {
        return $this->name;
    }
    
    public function get_parent() {
        return $this->parent;
    }
    
    public function child_with_name($name) {
        if (!isset($this->children[$name])) {
            $this->children[$name] = new Node($this, $name);
        }
        return $this->children[$name];
    }
    
    public function resolve($task_name_parts) {
        if (count($task_name_parts) == 0) {
            return $this;
        } else {
            $try = array_shift($task_name_parts);
            if (isset($this->children[$try])) {
                return $this->children[$try]->resolve($task_name_parts);
            } else {
                throw new TaskNotFoundException;
            }
        }
    }
    
    public function before(Task $task) { $this->before[] = $task; }
    public function task(Task $task) { $this->tasks[] = $task; }
    public function after(Task $task) { $this->after[] = $task; }
    
    public function dependencies() {
        $deps = array();
        foreach ($this->tasks as $t) {
            $deps = array_merge($deps, $t->dependencies());
        }
        return $deps;
    }
    
    public function get_description() {
        foreach ($this->tasks as $t) {
            if ($desc = $t->get_description()) return $desc;
        }
        return null;
    }
    
    public function reset() {
        foreach ($this->before as $t) $t->reset();
        foreach ($this->tasks as $t) $t->reset();
        foreach ($this->after as $t) $t->reset();
        foreach ($this->children as $c) $c->reset();
    }
    
    public function invoke($application) {
        foreach ($this->dependencies() as $d) $application->invoke($d, $this->get_parent());
        foreach ($this->before as $t) $t->invoke($application);
		foreach ($this->tasks as $t) {
			$t->set_name($this->name, $application->called_task);
			$t->invoke($application);
			$t->clear_name();
		}
        foreach ($this->after as $t) $t->invoke($application);
    }
    
    public function fill_task_list(&$out, $prefix = '') {
        foreach ($this->children as $name => $child) {
            if ($desc = $child->get_description()) {
                $out[$prefix . $name] = $desc;
            }
            $child->fill_task_list($out, "{$prefix}{$name}:");
        }
    }
}

// Single unit of work
class Task
{
    private $lambda;
    private $deps;
    private $desc       = null;
	private $options	= null;
	private $usage		= null;
    private $has_run    = false;
	private $task_name	= null;
	private $called_task = null;
    
    public function __construct($lambda = null, $deps = array()) {
        $this->lambda = $lambda;
        $this->deps = $deps;
		$this->options = array('', array());
    }

	public function set_name($name, $called_task) {
		$this->task_name = $name;
		$this->called_task = $called_task;
	}

	public function clear_name() {
		$this->task_name = null;
		$this->called_task = null;
	}
    
    public function get_description() {
        return $this->desc;
    }
    
    public function set_description($d) {
        $this->desc = $d;
    }

    public function get_usage() {
        return $this->usage;
    }

    public function set_usage($d) {
        $this->usage = $d;
    }

    public function get_options() {
        return $this->options;
    }
    
    public function set_options($d) {
        $this->options = $d;
    }
    
    public function dependencies() {
        return $this->deps;
    }
    
    public function reset() {
        $this->has_run = false;
    }
    
    public function invoke($application) {
        if (!$this->has_run) {
            if ($this->lambda) {
				if ($this->options != null)
				{
					$args = $GLOBALS['argv'];
					array_shift($args);
					$opts = $this->parse_options($args);
					if (array_key_exists('help', $opts)) {
						echo "\nTask: " . $this->called_task . "\n";
						echo $this->usage . "\n\n";
						exit();
					}
					else {
						$application->set_cmd_args($opts);
					}
				}
                $lambda = $this->lambda;
                $lambda($application);
				$application->clear_cmd_args();
            }
            $this->has_run = true;
        }
    }

	public function parse_options($args) {
		if (in_array($this->called_task, $args))
			unset($args[array_search($this->called_task, $args)]);

		$options = \Console_Getopt::getopt2($args, 'T'.$this->options[0], array_merge($this->options[1], array('tasks', 'help')), true);
		$args = array();

		foreach ($options[0] as $option) {
			$name = ltrim($option[0], '-');
			$value = $option[1];
			if ($value === null)
				$value = true;
			$args[$name] = $value;
		}

		foreach ($options[1] as $option) {
			$name = ltrim($option[0], '-');
			if (array_key_exists($name, $args))
				$args[$name] = true;
		}

		return $args;
	}
}
?>
