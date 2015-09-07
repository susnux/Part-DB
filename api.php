<?php
require_once('start_session.php');

// Autoload correct class
function apiAutoload($classname)
{
    $to_load = "";
    if (preg_match('/[a-zA-Z]+Controller$/', $classname))
    {
        $to_load = __DIR__ . '/controllers/' . $classname . '.php';
    }
    elseif (preg_match('/[a-zA-Z]+Model$/', $classname))
    {
        $to_load = __DIR__ . '/models/' . $classname . '.php';
    }
    elseif (preg_match('/[a-zA-Z]+View$/', $classname))
    {
        $to_load = __DIR__ . '/views/' . $classname . '.php';
    }
    if ($to_load != "" && file_exists($to_load))
    {
        include_once($to_load);
        return true;
    }
    return false;
}
spl_autoload_register('apiAutoload');

$request = new Request();

$controller_name = null;
// Check if requested other Controller and grab correct classname
if (array_key_exists(1, $request->url_elements)) {
    $controller_name = ucfirst($request->url_elements[1]) . 'Controller';
}

// Check if class exsist -> then handle action 
// If not return 404, we have no such controller
if (class_exists($controller_name)) {
    $controller = new $controller_name();
    $action_name = strtolower($request->method) . '_action';
    if (!method_exists($controller, $action_name)) {
        /* Our server knows this method, but our controller does not.
         * So it is not allowed on this resource 
         * -> 405 NOT 501 (which means that our server would not know this typ).
         * For further reading: RFC 2616 10.4.6 
         */
        http_response_code(405);
        header('Allow: ' . strtoupper(implode(", ", $controller->get_supported_methods())));
        exit();
    }
    $result = $controller->$action_name($request);
    if (isset($result['status']))
        http_response_code($result['status']);
    if (isset($result['body']))
        print(json_encode($result['body']));
} else {
    http_response_code(404);
    print("Requested resource not found.");
}
?>