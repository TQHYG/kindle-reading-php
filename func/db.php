<?php
// func/db.php
$config = require __DIR__ . '/../config.php';

function get_db() {
    global $config;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

function db_query($sql, $params = []) {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    if (stripos($sql, 'SELECT') === 0) return $stmt->fetchAll();
    if (stripos($sql, 'INSERT') === 0) return get_db()->lastInsertId();
    return $stmt->rowCount();
}
