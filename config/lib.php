<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Seoul');

if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Forbidden');
}

/* DB 설정 */
define('DB_HOST', '72.60.237.149');
define('DB_PORT', '37722');
define('DB_NAME', 'THXDEAL_DB');
define('DB_USER', 'thxdeal');
define('DB_PASS', 'dealThx11223@#');

/* SSL */
define('DB_SSL_KEY',  '/home/thxdeal/mysql_certs/client-key.pem');
define('DB_SSL_CERT', '/home/thxdeal/mysql_certs/client-cert.pem');
define('DB_SSL_CA',   '/home/thxdeal/mysql_certs/ca.pem');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            PDO::MYSQL_ATTR_SSL_KEY  => DB_SSL_KEY,
            PDO::MYSQL_ATTR_SSL_CERT => DB_SSL_CERT,
            PDO::MYSQL_ATTR_SSL_CA   => DB_SSL_CA,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('DB Connection Error');
}

?>