<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;

class NoCsrfGateway extends Gateway {

    public function __construct() {}

    public function process(Request $request, &$arguments) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Generate a CSRF token if it doesn't exist
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        // Inject the CSRF token into the request
        if (count($_POST) > 0) {
            $_POST['_token'] = $_SESSION['_token'];
        } else {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['_token'];
        }
    }
}
