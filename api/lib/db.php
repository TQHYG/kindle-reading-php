<?php
/**
 * 统一的 PDO 数据访问层。
 * 所有 SQL 均通过预处理执行，禁止把用户输入拼进 SQL。
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string)kykky_cfg('db.host', '127.0.0.1');
    $port = (int)kykky_cfg('db.port', 3306);
    $name = (string)kykky_cfg('db.dbname', 'kykky_db');
    $charset = (string)kykky_cfg('db.charset', 'utf8mb4');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    $pdo = new PDO($dsn, (string)kykky_cfg('db.user', ''), (string)kykky_cfg('db.password', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    // 让数据库的 NOW() 与应用时区一致。
    $offset = (new DateTimeImmutable('now', new DateTimeZone((string)kykky_cfg('app.timezone', 'Asia/Shanghai'))))->format('P');
    $stmt = $pdo->prepare('SET time_zone = ?');
    $stmt->execute([$offset]);

    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int)db()->lastInsertId();
}

/**
 * 在事务中执行回调；异常时回滚。
 */
function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
