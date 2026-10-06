<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
/**
 * Налаштування з'єднання з базою даних.
 * ЗАМІНІТЬ значення нижче на реальні дані вашого хостингу перед розгортанням,
 * і ніколи не публікуйте цей файл (з реальним паролем) у відкритому репозиторії.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'js_course_api';
$DB_USER = 'root';
$DB_PASS = 'O9xb4BZRacEu0TEZrZbFMd11Y6oEWaexTuoYTu1JEZYsCFmpOws9ba8mr21IL0Zb';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Помилка з\'єднання з базою даних'], JSON_UNESCAPED_UNICODE);
    exit;
}