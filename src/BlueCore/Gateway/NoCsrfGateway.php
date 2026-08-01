<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Arr;
use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Val;

class NoCsrfGateway extends Gateway {

    public function __construct() {}

    public function process(Request $request, &$arguments) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Generate a CSRF token if it doesn't exist
        if (!Arr::hasKey($_SESSION ?? [], '_token') || Val::isEmpty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        // Inject the CSRF token into the request
        if (Arr::isNotEmpty($_POST)) {
            $_POST['_token'] = $_SESSION['_token'];
        } else {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['_token'];
        }
    }
}
