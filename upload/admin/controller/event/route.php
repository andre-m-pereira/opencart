<?php
class ControllerEventRoute extends Controller {
	public function index() {
		// Route
		if (isset($this->request->get['route'])) {
			$route = $this->request->get['route'];
		} else {
			$route = 'common/dashboard';
		}
		
		$data = array();
		
		// Sanitize the call
		$route = str_replace('../', '', (string)$route);
		
		// Trigger the pre events
		$result = $this->event->trigger('controller/' . $route . '/before', array(&$route, &$data));
		
		if (!is_null($result)) {
			return $result;
		}
		
		$action = new Action($route);
		
		// Any output needs to be another Action object. 
		$output = $action->execute($this->registry, $data);
		
		// Trigger the post events
		$result = $this->event->trigger('controller/' . $route . '/after', array(&$route, &$output));
		
		if (!is_null($result)) {
			return $result;
		}
		
		return $output;
	}
}
