<?php
/**
 * NuriBoard - 한국형 커뮤니티 CMS
 * Copyright (c) 2026 NuriBoard
 * License: GPL-3.0
 *
 * DB.php - PDO 데이터베이스 래퍼
 */

class DB
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/config.php';
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            self::$instance = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $stmt = self::query($sql, [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $stmt = self::query("DELETE FROM {$table} WHERE {$where}", $params);
        return $stmt->rowCount();
    }

    public static function count(string $table, string $where = '1', array $params = []): int
    {
        $row = self::fetch("SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}", $params);
        return (int) ($row['cnt'] ?? 0);
    }

    public static function getPrefix(): string
    {
        static $prefix = null;
        if ($prefix === null) {
            $config = require __DIR__ . '/../config/config.php';
            $prefix = $config['db_prefix'] ?? 'nb_';
        }
        return $prefix;
    }
}
