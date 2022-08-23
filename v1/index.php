<?php
require 'autoload.php';

// Get the verb
$route = API\API::getroutekey();
if (!API\API::isRoute($route)) {
    // Throw an error
    header('HTTP/1.0 404 Not Found');
    echo json_encode(array("code" => 404, "status" => "failed", "message" => "Resource not found"));
    exit;
}
switch (API\API::getroutekey()) {
    case "{$route}":
        // Get the route
        API\API::getRoute($route);
        break;

    default:
        // Throw an error
        header('HTTP/1.0 404 Not Found');
        echo json_encode(array("code" => 404, "status" => "failed", "message" => "Resource does not exist"));
        break;
}