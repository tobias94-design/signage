<?php
define('DB_PATH', __DIR__ . '/../database.sqlite');

function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
        $db->exec('PRAGMA foreign_keys = ON;');
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec('PRAGMA busy_timeout = 10000;');
        $db->exec('PRAGMA synchronous = NORMAL;');
        return $db;
    } catch (PDOException $e) {
        die('Errore database: ' . $e->getMessage());
    }
}