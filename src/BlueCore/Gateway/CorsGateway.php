<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;

class CorsGateway extends Gateway {

    public function __construct() {}

    public function process(Request $request, &$arguments) {
        // Set CORS headers
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        // Handle preflight requests
        if ($request->type() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Continue processing the request
        return parent::process($request, $arguments);
    }
}
