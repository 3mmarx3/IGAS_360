<?php
/**
 * Database Configuration & Connection
 * Supports .env configuration, SQLite local emulation, and production MySQL.
 */

// 1. Load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}

$driver   = getenv('DB_DRIVER') ?: 'sqlite';
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '3306';
$db       = getenv('DB_NAME') ?: 'u596103429_crm_gases';
$user     = getenv('DB_USER') ?: 'root';
$pass     = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset  = getenv('DB_CHARSET') ?: 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

/**
 * Configure SQLite with MySQL Compatibility Functions
 */
function init_sqlite_pdo(string $sqlitePath, array $options): PDO {
    if (class_exists('\Pdo\Sqlite')) {
        $pdo = new \Pdo\Sqlite("sqlite:" . $sqlitePath, null, null, $options);
    } else {
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $options);
    }
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Compatibility SQL Functions helper
    $reg = function(string $name, callable $fn) use ($pdo) {
        if (method_exists($pdo, 'createFunction') || is_callable([$pdo, 'createFunction'])) {
            $pdo->createFunction($name, $fn);
        } else {
            @$pdo->sqliteCreateFunction($name, $fn);
        }
    };

    if (method_exists($pdo, 'createCollation') || is_callable([$pdo, 'createCollation'])) {
        $pdo->createCollation('utf8mb4_unicode_ci', 'strcasecmp');
        $pdo->createCollation('utf8mb4_general_ci', 'strcasecmp');
    } else {
        @$pdo->sqliteCreateCollation('utf8mb4_unicode_ci', 'strcasecmp');
        @$pdo->sqliteCreateCollation('utf8mb4_general_ci', 'strcasecmp');
    }

    $reg('NOW', fn() => date('Y-m-d H:i:s'));
    $reg('CURDATE', fn() => date('Y-m-d'));
    $reg('CURRENT_DATE', fn() => date('Y-m-d'));
    $reg('CONCAT', fn(...$args) => implode('', $args));
    $reg('CONCAT_WS', function($sep, ...$args) {
        $filtered = array_filter($args, fn($a) => $a !== null);
        return implode($sep, $filtered);
    });
    $reg('MONTH', fn($d) => $d ? (int)date('m', strtotime($d)) : null);
    $reg('YEAR', fn($d) => $d ? (int)date('Y', strtotime($d)) : null);
    $reg('DAY', fn($d) => $d ? (int)date('d', strtotime($d)) : null);
    $reg('DATE_FORMAT', function($d, $fmt) {
        if (!$d) return null;
        $time = strtotime($d);
        $map = [
            '%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%c' => 'n', '%d' => 'd', '%e' => 'j',
            '%H' => 'H', '%h' => 'h', '%i' => 'i', '%s' => 's', '%p' => 'A', '%M' => 'F',
            '%b' => 'M', '%W' => 'l', '%a' => 'D',
        ];
        return date(strtr($fmt, $map), $time);
    });
    $reg('DATEDIFF', function($d1, $d2) {
        if (!$d1 || !$d2) return null;
        return (int)round((strtotime($d1) - strtotime($d2)) / 86400);
    });
    $reg('DATE_ADD', function($d, $expr) {
        if (!$d) return null;
        return date('Y-m-d H:i:s', strtotime("{$d} + {$expr}"));
    });
    $reg('DATE_SUB', function($d, $expr) {
        if (!$d) return null;
        return date('Y-m-d H:i:s', strtotime("{$d} - {$expr}"));
    });
    $reg('FIND_IN_SET', function($val, $str) {
        if ($val === null || $str === null) return 0;
        $arr = explode(',', (string)$str);
        $pos = array_search((string)$val, array_map('trim', $arr), true);
        return $pos !== false ? $pos + 1 : 0;
    });

    return $pdo;
}

try {
    if ($driver === 'sqlite') {
        $sqlitePath = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/../crm.sqlite');
        if (!empty($sqlitePath) && $sqlitePath[0] !== '/' && $sqlitePath[0] !== '\\') {
            $sqlitePath = __DIR__ . '/../' . ltrim($sqlitePath, './');
        }
        $pdo = init_sqlite_pdo($sqlitePath, $options);
    } else {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $myEx) {
            // If MySQL is requested but unreachable in development, fallback to SQLite
            $fallbackPath = __DIR__ . '/../crm.sqlite';
            if (file_exists($fallbackPath) || getenv('APP_ENV') === 'development' || !getenv('APP_ENV')) {
                error_log("Notice: MySQL connection failed ({$myEx->getMessage()}). Falling back to SQLite local database.");
                $pdo = init_sqlite_pdo($fallbackPath, $options);
            } else {
                throw $myEx;
            }
        }
    }
} catch (\PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    if (getenv('APP_ENV') === 'development' || (defined('ENVIRONMENT') && ENVIRONMENT === 'development')) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    } else {
        http_response_code(500);
        die("A database connection error occurred. Please contact the system administrator.");
    }
}

if (!function_exists('get_db')) {
    function get_db(): PDO {
        global $pdo;
        return $pdo;
    }
}