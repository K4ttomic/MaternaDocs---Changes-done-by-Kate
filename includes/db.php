<?php
// XAMPP defaults. Change these (and set a MySQL root password) before any real deployment.
const DB_HOST = 'localhost', DB_NAME = 'maternadocs', DB_USER = 'root', DB_PASS = '';

function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    } catch (PDOException $e) {
      http_response_code(500);
      exit('Database unavailable. Is MySQL running and has setup.php been run?');
    }
  }
  return $pdo;
}
