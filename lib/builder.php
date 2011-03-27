<?php
namespace phake;

class Builder
{
    public static $global;
    
    private $application;
    private $context;
    private $description;
	private $options;
	private $usage;
    
    public function __construct() {
        $this->application = new Application;
        $this->context = $this->application->root();
        $this->description = null;
		$this->options = null;
		$this->usage = 'No help text available';
    }
    
    public function get_application() {
        return $this->application;
    }
    
    public function desc($d) {
        $this->description = $d;
    }

	public function options($o) {
		$this->options = $o;
	}

	public function usage($u) {
		$this->usage = $u;
	}
    
    public function add_task($name, $work, $deps) {
        $node = $this->context->child_with_name($name);
        $task = new Task($work, $deps);
        $this->assign_description($task);
		$this->assign_options($task);
		$this->assign_usage($task);
        $node->task($task);
    }
    
    public function push_group($name) {
        $this->context = $this->context->child_with_name($name);
    }
    
    public function pop_group() {
        $this->context = $this->context->get_parent();
    }
    
    public function before($name, $lambda) {
        $this->application->resolve($name, $this->context)->before(new Task($lambda));
    }
    
    public function after($name, $lambda) {
        $this->application->resolve($name, $this->context)->after(new Task($lambda));
    }
    
    //
    //
    
    private function assign_description($thing) {
        if ($this->description !== null) {
            $thing->set_description($this->description);
            $this->description = null;
        } else {
            $thing->set_description('n/a');
		}
    }

    private function assign_options($thing) {
        if ($this->options !== null) {
            $thing->set_options($this->options);
            $this->options = null;
        }
    }

    private function assign_usage($thing) {
		$thing->set_usage($this->usage);
		$this->usage = 'No help text available';
    }
}
