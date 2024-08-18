<?php

if (!function_exists('customErrorHandler')) {
    function customErrorHandler($errno, $errstr, $errfile, $errline) {
        // Prevent default error handler
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Check if running in CLI mode
        $isCli = php_sapi_name() === 'cli';

        // Determine the background and border color based on the error type
        switch ($errno) {
            case E_WARNING:
            case E_NOTICE:
                $backgroundColor = '#cce5ff';
                $borderColor = '#b8daff';
                break;
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $backgroundColor = '#fff3cd';
                $borderColor = '#ffeeba';
                break;
            default:
                $backgroundColor = '#f8d7da';
                $borderColor = '#f5c6cb';
        }

        // Customize the error display based on DEBUG_MODE and CLI
        if (env('DEBUG_MODE') === 'true') {
            if ($isCli) {
                // CLI output
                echo "Error [$errno]: $errstr in $errfile on line $errline\n";
            } else {
                // HTML output
                echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                echo "<h2 style='color: #721c24;'>An error occurred!</h2>";
                echo "<p><strong>Error Code:</strong> $errno</p>";
                echo "<p><strong>Message:</strong> $errstr</p>";
                echo "<p><strong>File:</strong> $errfile</p>";
                echo "<p><strong>Line:</strong> $errline</p>";
                echo "<hr>";
                echo "<p>Please contact support or try again later.</p>";
                echo "</div>";
            }
        } else {
            if ($isCli) {
                // CLI output without sensitive information
                echo "Error [$errno]: An error occurred. Please contact your administrator.\n";
            } else {
                // HTML output without sensitive information
                echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                echo "<h2 style='color: #721c24;'>An error occurred!</h2>";
                echo "<p>Please contact your administrator.</p>";
                echo "</div>";
            }
        }

        // Log the error to a file
        error_log("Error: [$errno] $errstr in $errfile on line $errline", 3, env('ERROR_LOG_FILE', 'storage/error.log'));

        /* Don't execute PHP internal error handler */
        return true;
    }

    // Set custom error handler
    set_error_handler("customErrorHandler");
}

if (!function_exists('customExceptionHandler')) {
    function customExceptionHandler($exception) {
        $isCli = php_sapi_name() === 'cli';
        $backgroundColor = '#fff3cd';
        $borderColor = '#ffeeba';

        if (env('DEBUG_MODE') === 'true') {
            if ($isCli) {
                // CLI output
                echo "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
            } else {
                // HTML output
                echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                echo "<h2 style='color: #721c24;'>An exception occurred!</h2>";
                echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
                echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
                echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
                echo "<hr>";
                echo "<p>Please contact support or try again later.</p>";
                echo "</div>";
            }
        } else {
            if ($isCli) {
                // CLI output without sensitive information
                echo "Exception: An error occurred. Please contact your administrator.\n";
            } else {
                // HTML output without sensitive information
                echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                echo "<h2 style='color: #721c24;'>An exception occurred!</h2>";
                echo "<p>Please contact your administrator.</p>";
                echo "</div>";
            }
        }

        // Log the exception to a file
        error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine(), 3, env('ERROR_LOG_FILE', 'storage/error.log'));
    }

    // Set custom exception handler
    set_exception_handler("customExceptionHandler");
}

if (!function_exists('shutdownHandler')) {
    function shutdownHandler() {
        $error = error_get_last();
        if ($error !== NULL) {
            $isCli = php_sapi_name() === 'cli';
            $backgroundColor = '#f8d7da';
            $borderColor = '#f5c6cb';

            if (env('DEBUG_MODE') === 'true') {
                if ($isCli) {
                    // CLI output
                    echo "Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'] . "\n";
                } else {
                    // HTML output
                    echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                    echo "<h2 style='color: #721c24;'>A fatal error occurred!</h2>";
                    echo "<p><strong>Error Type:</strong> " . $error['type'] . "</p>";
                    echo "<p><strong>Message:</strong> " . $error['message'] . "</p>";
                    echo "<p><strong>File:</strong> " . $error['file'] . "</p>";
                    echo "<p><strong>Line:</strong> " . $error['line'] . "</p>";
                    echo "<hr>";
                    echo "<p>Please contact support or try again later.</p>";
                    echo "</div>";
                }
            } else {
                if ($isCli) {
                    // CLI output without sensitive information
                    echo "Fatal Error: An error occurred. Please contact your administrator.\n";
                } else {
                    // HTML output without sensitive information
                    echo "<div style='background-color: $backgroundColor; color: #721c24; border: 1px solid $borderColor; padding: 20px; font-family: Arial, sans-serif;'>";
                    echo "<h2 style='color: #721c24;'>A fatal error occurred!</h2>";
                    echo "<p>Please contact your administrator.</p>";
                    echo "</div>";
                }
            }

            // Log the fatal error
            error_log("Fatal Error: [" . $error['type'] . "] " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'], 3, env('ERROR_LOG_FILE', 'storage/error.log'));
        }
    }

    // Register shutdown function for fatal errors
    register_shutdown_function('shutdownHandler');
}
