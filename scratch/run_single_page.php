<?php
/**
 * Single Page Test Runner for System Crawler
 * Executes a target PHP script in an isolated authenticated Super Admin context
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$targetFile = $argv[1] ?? '';
$queryString = $argv[2] ?? '';

if (!$targetFile || !file_exists($targetFile)) {
    echo json_encode(['status' => 'error', 'message' => "File not found: {$targetFile}"]);
    exit(1);
}

// Parse query string into $_GET
if ($queryString) {
    parse_str($queryString, $_GET);
}

// Setup Server Variables
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_FILENAME'] = realpath($targetFile);
$_SERVER['SCRIPT_NAME'] = '/' . ltrim($targetFile, './');
$_SERVER['PHP_SELF'] = '/' . ltrim($targetFile, './');
$_SERVER['HTTP_HOST'] = '127.0.0.1:8000';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8000';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['QUERY_STRING'] = $queryString;
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . ($queryString ? '?' . $queryString : '');

// Initialize Session as Authenticated Super Admin (except for login page testing)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if ($targetFile !== 'auth/login.php') {
    $_SESSION['account_type']     = 'staff';
    $_SESSION['user_id']          = 1;
    $_SESSION['emp_id']           = 'EMP-001';
    $_SESSION['user_name']        = 'Super Administrator';
    $_SESSION['role']             = 'admin';
    $_SESSION['permission_level'] = 'super_admin';
    $_SESSION['csrf_token']       = bin2hex(random_bytes(32));
}

$capturedErrors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$capturedErrors) {
    if (!(error_reporting() & $errno)) return false;
    // Ignore benign finfo_close deprecation in test runner
    if (strpos($errstr, 'finfo_close') !== false) return false;
    
    $type = match($errno) {
        E_WARNING, E_USER_WARNING => 'Warning',
        E_NOTICE, E_USER_NOTICE => 'Notice',
        E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
        default => "Error ({$errno})"
    };
    
    $capturedErrors[] = [
        'type' => $type,
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ];
    return false; // Continue standard PHP error handling
});

$finished = false;
$output = '';
$exception = null;

register_shutdown_function(function() use (&$finished, &$capturedErrors, &$output, &$exception, $targetFile) {
    if ($finished) return;
    $finished = true;

    while (ob_get_level() > 0) {
        $output .= ob_get_clean();
    }

    $lastErr = error_get_last();
    if ($lastErr && in_array($lastErr['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $capturedErrors[] = [
            'type' => 'Fatal Error',
            'message' => $lastErr['message'],
            'file' => basename($lastErr['file']),
            'line' => $lastErr['line']
        ];
    }

    $httpCode = http_response_code() ?: 200;
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $output, $m)) {
        $title = trim($m[1]);
    }

    $status = 'ok';
    if ($exception) {
        $status = 'exception';
    } elseif ($lastErr && in_array($lastErr['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $status = 'fatal';
        $httpCode = 500;
    } elseif ($httpCode >= 300 && $httpCode < 400) {
        $status = 'redirect';
    }

    $result = [
        'file' => $targetFile,
        'status' => $status,
        'http_code' => $httpCode,
        'exception' => $exception,
        'errors' => $capturedErrors,
        'output_len' => strlen($output),
        'title' => $title
    ];

    echo "\n__CRAWLER_RESULT__\n" . json_encode($result) . "\n__END_CRAWLER_RESULT__\n";
});

ob_start();
try {
    include $targetFile;
} catch (\Throwable $e) {
    $exception = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
}
