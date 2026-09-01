<?php

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\Flag;
use BlueFission\Net\HTTP;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Utils\File;

if (!function_exists('blueCoreDebugMode')) {
    function blueCoreDebugMode(): bool
    {
        return Flag::parseBool(env('DEBUG_MODE'), false);
    }
}

if (!function_exists('blueCoreSetErrorStatus')) {
    function blueCoreSetErrorStatus(): void
    {
        if (Str::match(PHP_SAPI, 'cli') || headers_sent()) {
            return;
        }

        $status = HTTP::statusLine(500);
        if (Str::isNotEmpty($status)) {
            header($status);
        }
    }
}

if (!function_exists('customErrorHandler')) {
    function customErrorHandler($errno, $errstr, $errfile, $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        error_log("Error: [{$errno}] {$errstr} in {$errfile} on line {$errline}");
        blueCoreReportLifecycleError(
            HelperLifecycleHooks::HOOK_ERROR_REPORTED,
            Arr::make([
                'kind' => 'error',
                'code' => Num::int($errno),
                'debug' => blueCoreDebugMode(),
                'sapi' => PHP_SAPI,
            ])
        );

        if (!blueCoreDebugMode()) {
            return true;
        }

        if (Str::match(PHP_SAPI, 'cli')) {
            echo "Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}\n";
            sourceCodeWindow($errfile, $errline);

            return true;
        }

        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; font-family: Arial, sans-serif;'>";
        echo '<h2>An error occurred!</h2>';
        echo '<p><strong>Error Code:</strong> ' . htmlspecialchars((string)$errno) . '</p>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars((string)$errstr) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars((string)$errfile) . '</p>';
        echo '<p><strong>Line:</strong> ' . htmlspecialchars((string)$errline) . '</p>';
        sourceCodeWindow($errfile, $errline);
        echo '</div>';

        return true;
    }

    set_error_handler('customErrorHandler');
}

if (!function_exists('customExceptionHandler')) {
    function customExceptionHandler(Throwable $exception): void
    {
        error_log(
            'Exception: ' . $exception->getMessage()
            . ' in ' . $exception->getFile()
            . ' on line ' . $exception->getLine()
        );
        blueCoreReportLifecycleError(
            HelperLifecycleHooks::HOOK_EXCEPTION_REPORTED,
            Arr::make([
                'kind' => 'exception',
                'code' => Num::int($exception->getCode()),
                'exception_type' => $exception::class,
                'debug' => blueCoreDebugMode(),
                'sapi' => PHP_SAPI,
            ])
        );
        blueCoreSetErrorStatus();

        if (blueCoreDebugMode()) {
            if (Str::match(PHP_SAPI, 'cli')) {
                echo 'Exception: ' . $exception->getMessage()
                    . ' in ' . $exception->getFile()
                    . ' on line ' . $exception->getLine() . "\n";
                sourceCodeWindow($exception->getFile(), $exception->getLine());
            } else {
                echo "<div style='background-color: #fff3cd; color: #721c24; border: 1px solid #ffeeba; padding: 20px; font-family: Arial, sans-serif;'>";
                echo '<h2>An exception occurred!</h2>';
                echo '<p><strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
                echo '<p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . '</p>';
                echo '<p><strong>Line:</strong> ' . htmlspecialchars((string)$exception->getLine()) . '</p>';
                sourceCodeWindow($exception->getFile(), $exception->getLine());
                echo '</div>';
            }
        }

        if (Str::match(PHP_SAPI, 'cli')) {
            exit(1);
        }
    }

    set_exception_handler('customExceptionHandler');
}

if (!function_exists('shutdownHandler')) {
    function shutdownHandler(): void
    {
        $error = error_get_last();
        $fatalTypes = Arr::make([E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);

        if (!Arr::is($error) || !$fatalTypes->has($error['type'] ?? null, true)) {
            return;
        }

        error_log(
            'Fatal Error: [' . $error['type'] . '] ' . $error['message']
            . ' in ' . $error['file']
            . ' on line ' . $error['line']
        );
        blueCoreReportLifecycleError(
            HelperLifecycleHooks::HOOK_FATAL_REPORTED,
            Arr::make([
                'kind' => 'fatal',
                'code' => Num::int($error['type']),
                'debug' => blueCoreDebugMode(),
                'sapi' => PHP_SAPI,
            ])
        );
        blueCoreSetErrorStatus();

        if (!blueCoreDebugMode()) {
            return;
        }

        if (Str::match(PHP_SAPI, 'cli')) {
            echo 'Fatal Error: ' . $error['message']
                . ' in ' . $error['file']
                . ' on line ' . $error['line'] . "\n";
            sourceCodeWindow($error['file'], $error['line']);

            return;
        }

        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; font-family: Arial, sans-serif;'>";
        echo '<h2>A fatal error occurred!</h2>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars((string)$error['message']) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars((string)$error['file']) . '</p>';
        echo '<p><strong>Line:</strong> ' . htmlspecialchars((string)$error['line']) . '</p>';
        sourceCodeWindow($error['file'], $error['line']);
        echo '</div>';
    }

    register_shutdown_function('shutdownHandler');
}

if (!function_exists('blueCoreReportLifecycleError')) {
    function blueCoreReportLifecycleError(string $hook, Arr $summary): void
    {
        try {
            HelperLifecycleHooks::action($hook, [$summary]);
        } catch (Throwable) {
            error_log("BlueCore lifecycle hook '{$hook}' failed during error reporting.");
        }
    }
}

if (!function_exists('sourceCodeWindow')) {
    function sourceCodeWindow($file, $line): void
    {
        if (!(new File())->isReachable($file)) {
            return;
        }

        $contents = Str::replace(File::readContents($file), "\r\n", "\n");
        $lines = Str::make($contents)->split("\n");
        $start = Num::max((int)$line - 5, 0);
        $end = Num::min($lines->count() - 1, (int)$line + 5);
        $isCli = Str::match(PHP_SAPI, 'cli');

        if (!$isCli) {
            echo '<pre>';
        }

        for ($index = $start; $index <= $end; $index++) {
            $source = (string)$lines[$index];
            if ($isCli) {
                echo ($index === (int)$line - 1 ? '> ' : '  ') . $source . "\n";
            } else {
                $source = htmlspecialchars($source) . "\n";
                echo $index === (int)$line - 1
                    ? "<strong style='color: red;'>{$source}</strong>"
                    : $source;
            }
        }

        if (!$isCli) {
            echo '</pre>';
        }
    }
}
