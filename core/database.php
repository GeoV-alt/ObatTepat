<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require ROOT_PATH . '/config/database.php';

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}

function db_transaction(callable $callback): mixed
{
    $pdo = db();

    if ($pdo->inTransaction()) {
        return $callback($pdo);
    }

    $pdo->beginTransaction();

    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function db_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $value = $statement->fetchColumn();

    return $value === false ? null : $value;
}

function db_run(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount();
}

function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);

    return (int) db()->lastInsertId();
}
