<?php
/**
 * Database connection script using PDO
 */

$host = 'mysql-4aee093-rsubbu931-5d6b.j.aivencloud.com';
$port = '26384';
$db   = 'defaultdb';
$user = 'avnadmin';
$pass = 'AVNS_ShCOcOAwQueWHwvVZzR';
$charset = 'utf8mb4';

if (!in_array('mysql', PDO::getAvailableDrivers())) {
    die("Error: PDO MySQL driver is not enabled. Please enable 'extension=pdo_mysql' in your php.ini.");
}

// Define SSL constants if they are missing (occurs in some PHP environments)
if (!defined('PDO::MYSQL_ATTR_SSL_CA')) {
    define('PDO::MYSQL_ATTR_SSL_CA', 1007);
}
if (!defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
    define('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', 1014);
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => __DIR__ . '/ca.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}
