<?php

if (!function_exists('customErrorHandler')) {
    function customErrorHandler($errno, $errstr, $errfile, $errline) {
        // Prevent default error handler
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Customize the error display
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; font-family: Arial, sans-serif;'>";
        echo "<h2 style='color: #721c24;'>An error occurred!</h2>";
        echo "<p><strong>Error Code:</strong> $errno</p>";
        echo "<p><strong>Message:</strong> $errstr</p>";
        echo "<p><strong>File:</strong> $errfile</p>";
        echo "<p><strong>Line:</strong> $errline</p>";
        echo "<hr>";
        echo "<p>Please contact support or try again later.</p>";
        echo "</div>";

        // Optionally log the error to a file
        error_log("Error: [$errno] $errstr in $errfile on line $errline", 3, "/path/to/your/logfile.log");

        /* Don't execute PHP internal error handler */
        return true;
    }

    // Set custom error handler
    set_error_handler("customErrorHandler");
}

if (!function_exists('customExceptionHandler')) {
    function customExceptionHandler($exception) {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; font-family: Arial, sans-serif;'>";
        echo "<h2 style='color: #721c24;'>An exception occurred!</h2>";
        echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<hr>";
        echo "<p>Please contact support or try again later.</p>";
        echo "</div>";

        // Optionally log the exception
        error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine(), 3, "/path/to/your/logfile.log");
    }

    // Set custom exception handler
    set_exception_handler("customExceptionHandler");
}

if (!function_exists('shutdownHandler')) {
    function shutdownHandler() {
        $error = error_get_last();
        if ($error !== NULL) {
            echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; font-family: Arial, sans-serif;'>";
            echo "<h2 style='color: #721c24;'>A fatal error occurred!</h2>";
            echo "<p><strong>Error Type:</strong> " . $error['type'] . "</p>";
            echo "<p><strong>Message:</strong> " . $error['message'] . "</p>";
            echo "<p><strong>File:</strong> " . $error['file'] . "</p>";
            echo "<p><strong>Line:</strong> " . $error['line'] . "</p>";
            echo "<hr>";
            echo "<p>Please contact support or try again later.</p>";
            echo "</div>";

            // Optionally log the fatal error
            error_log("Fatal Error: [" . $error['type'] . "] " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'], 3, "/path/to/your/logfile.log");
        }
    }

    // Register shutdown function for fatal errors
    register_shutdown_function('shutdownHandler');
}