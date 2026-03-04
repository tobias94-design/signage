<?php
define('DB_PATH', __DIR__ . '/../database.sqlite');

function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA foreign_keys = ON;');
        return $db;
    } catch (PDOException $e) {
        die('Errore database: ' . $e->getMessage());
    }
}