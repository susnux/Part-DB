<?php
require_once('lib/class.Request.php');
include_once('start_session.php');

// Autoload correct class
spl_autoload_register('apiAutoload');
function apiAutoload($classname) {
    if (preg_match('/[a-zA-Z]+Controller$/', $classname)) {
        $to_load = __DIR__ . '/controllers/' . $classname . '.php';
    } elseif (preg_match('/[a-zA-Z]+Model$/', $classname)) {
        $to_load = __DIR__ . '/models/' . $classname . '.php';
    } elseif (preg_match('/[a-zA-Z]+View$/', $classname)) {
        $to_load = __DIR__ . '/views/' . $classname . '.php';
    }
    if (isset($to_load) && file_exists($to_load)) {
        include "$to_load";
        return true;
    }
    return false;
}

$request = new Request();

// route the request to the right place
// Initialize Controller with IndexController (show index page)
$controller_name = 'IndexController';
// Check if requested other Controller and grab correct classname
if (array_key_exists(1, $request->url_elements)) {
    $controller_name = ucfirst($request->url_elements[1]) . 'Controller';
}

// Check if class exsist -> then handle action 
// If not return 404, we have no such controller
if (class_exists($controller_name)) {
    $controller = new $controller_name();
    $action_name = strtolower($request->method) . 'Action';
    $result = $controller->$action_name($request);
    print_r($result);
} else {
    http_response_code(404);
    print("Requested resource not found.");
}
?>