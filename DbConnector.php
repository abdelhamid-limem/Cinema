<?php
class DbConnector extends PDO {
    public function __construct(string $host, string $user, string $pass, string $db) {
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        parent::__construct($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
