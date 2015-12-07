<?php
final class Loader {
	protected $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}
	
	public function controller($route) {
		// Get args by reference
		$trace = debug_backtrace();

		$args = $trace[0]['args'];
		
		// Sanitize the call
		$route = str_replace('../', '', (string)$route);
		
		// Trigger the pre events
		$result = $this->registry->get('event')->trigger('controller/' . $route . '/before', $args);
		
		if (!is_null($result)) {
			return $result;
		}
		
		array_shift($args);
		
		$action = new Action($route);
		$output = $action->execute($this->registry, $args);
			
		// Trigger the post events
		$result = $this->registry->get('event')->trigger('controller/' . $route . '/after', array(&$output));
		
		if (!is_null($result)) {
			return $result;
		}
		
		return $output;		
	}
	
	public function model($route) {
		// Sanitize the call
		$route = str_replace('../', '', (string)$route);
		
		$file  = DIR_APPLICATION . 'model/' . $route . '.php';
		$class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', $route);

		if (is_file($file)) {
			include_once($file);
			
			$observer = new Observer($this->registry);

			foreach (get_class_methods($class) as $method) {
				$observer->attach($method, $this->closure($this->registry, $route . '/' . $method));
			}
			
			$this->registry->set('model_' . str_replace('/', '_', (string)$route), $observer);
		} else {
			trigger_error('Error: Could not load model ' . $route . '!');
			exit();
		}
	}

	public function view($route, $data) {
		// Sanitize the call
		$route = str_replace('../', '', (string)$route);
		
		// Trigger the pre events
		$result = $this->registry->get('event')->trigger('view/' . $route . '/before', array(&$route, &$data));
		
		if (!is_null($result)) {
			return $result;
		}
		
		$template = new Template('basic');
		
		foreach ($data as $key => $value) {
			$template->set($key, $value);
		}
		
		$output = $template->render($route . '.tpl');
		
		// Trigger the post events
		$result = $this->registry->get('event')->trigger('view/' . $route . '/after', array(&$output));
		
		if (!is_null($result)) {
			return $result;
		}
		
		return $output;
	}

	public function helper($route) {
		$file = DIR_SYSTEM . 'helper/' . str_replace('../', '', (string)$route) . '.php';

		if (is_file($file)) {
			include_once($file);
		} else {
			trigger_error('Error: Could not load helper ' . $route . '!');
			exit();
		}
	}

	public function config($route) {
		$this->registry->get('event')->trigger('config/' . $route . '/before', $route);
		
		$this->registry->get('config')->load($route);
		
		$this->registry->get('event')->trigger('config/' . $route . '/after', $route);
	}

	public function language($route) {
		$this->registry->get('event')->trigger('language/' . $route . '/before', $route);
		
		$this->registry->get('language')->load($route);
		
		$this->registry->get('event')->trigger('language/' . $route . '/after', $route);
	}
	
	protected function closure($registry, $route) {
		return function($args) use($registry, $route) {
			// Trigger the pre events
			$result = $registry->get('event')->trigger('model/' . $route . '/before', array(&$route, &$args));
			
			if (!is_null($result)) {
				return $result;
			}
			
			$file  = DIR_APPLICATION . 'model/' .  substr($route, 0, strrpos($route, '/')) . '.php';
			$class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', substr($route, 0, strrpos($route, '/')));
			$method = substr($route, strrpos($route, '/') + 1);
	
			if (is_file($file)) {
				include_once($file);
			
				$model = new $class($registry);
			} else {
				trigger_error('Error: Could not load model ' . substr($route, 0, strrpos($route, '/')) . '!');
				exit();
			}
						
			$output = call_user_func_array(array($model, $method), $args);
			
			// Trigger the post events
			$result = $registry->get('event')->trigger('model/' . $route . '/after', array(&$output));
			
			if (!is_null($result)) {
				return $result;
			}
			
			return $output;
		};
	}	
}