<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
/**
 * Універсальний навчальний REST API для курсу "Web-розробка мовою JavaScript".
 *
 * Один файл обслуговує будь-яку сутність будь-якого варіанта ER-діаграми —
 * назва сутності передається параметром type (напр. book_ivanenko), а самі
 * атрибути об'єкта зберігаються як JSON у базі, тому таблицю не потрібно
 * змінювати під кожен варіант.
 *
 * Ендпоінти:
 *   GET    api.php?type=book_ivanenko            -> список усіх обʼєктів цього типу
 *   GET    api.php?type=book_ivanenko&id=5        -> один обʼєкт за id
 *   POST   api.php?type=book_ivanenko             -> створити (тіло запиту — JSON)
 *   PUT    api.php?type=book_ivanenko&id=5        -> оновити (тіло запиту — JSON)
 *   PATCH  api.php?type=book_ivanenko&id=5        -> оновити (тіло запиту — JSON)
 *   DELETE api.php?type=book_ivanenko&id=5        -> видалити
 *
 * ВАЖЛИВО ДЛЯ СТУДЕНТІВ: сервер спільний для всієї групи. Обирайте type,
 * унікальний для вас (наприклад, додайте своє прізвище: book_ivanenko),
 * інакше ваші дані змішаються з даними інших студентів.
 */

// --- CORS: дозволяємо запити з будь-якого джерела (навчальний сервер) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Браузер надсилає попередній OPTIONS-запит (preflight) перед PUT/PATCH/DELETE
// та перед POST з Content-Type: application/json — відповідаємо порожнім 204.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php'; // надає підключений $pdo

$method = $_SERVER['REQUEST_METHOD'];
$type   = isset($_GET['type']) ? trim($_GET['type']) : null;
$id     = isset($_GET['id']) ? $_GET['id'] : null;

// --- Базова валідація параметра type: лише літери, цифри, підкреслення ---
if (!$type || !preg_match('/^[a-zA-Z0-9_]{1,100}$/', $type)) {
    sendError(400, 'Параметр type є обов\'язковим і повинен містити лише латинські літери, цифри та підкреслення. Приклад: api.php?type=book_ivanenko');
}

if ($id !== null && !ctype_digit((string)$id)) {
    sendError(400, 'Параметр id повинен бути цілим числом');
}

switch ($method) {
    case 'GET':
        if ($id !== null) {
            handleGetOne($pdo, $type, (int)$id);
        } else {
            handleGetAll($pdo, $type);
        }
        break;

    case 'POST':
        handleCreate($pdo, $type);
        break;

    case 'PUT':
    case 'PATCH':
        if ($id === null) {
            sendError(400, 'Для оновлення потрібен параметр id, напр. api.php?type=book_ivanenko&id=5');
        }
        handleUpdate($pdo, $type, (int)$id);
        break;

    case 'DELETE':
        if ($id === null) {
            sendError(400, 'Для видалення потрібен параметр id, напр. api.php?type=book_ivanenko&id=5');
        }
        handleDelete($pdo, $type, (int)$id);
        break;

    default:
        sendError(405, 'Метод ' . $method . ' не підтримується');
}

// ---------------------------------------------------------------------
// Обробники запитів
// ---------------------------------------------------------------------

function handleGetAll(PDO $pdo, string $type): void {
    $stmt = $pdo->prepare('SELECT id, data FROM entities WHERE entity_type = :type ORDER BY id');
    $stmt->execute(['type' => $type]);
    $rows = $stmt->fetchAll();

    $result = array_map(fn($row) => mergeRowWithId($row), $rows);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

function handleGetOne(PDO $pdo, string $type, int $id): void {
    $row = fetchRow($pdo, $type, $id);
    if (!$row) {
        sendError(404, 'Обʼєкт не знайдено');
    }
    echo json_encode(mergeRowWithId($row), JSON_UNESCAPED_UNICODE);
}

function handleCreate(PDO $pdo, string $type): void {
    $input = readJsonBody();

    $stmt = $pdo->prepare('INSERT INTO entities (entity_type, data) VALUES (:type, :data)');
    $stmt->execute([
        'type' => $type,
        'data' => json_encode($input, JSON_UNESCAPED_UNICODE),
    ]);

    $newId = (int)$pdo->lastInsertId();
    http_response_code(201);
    echo json_encode(array_merge($input, ['id' => $newId]), JSON_UNESCAPED_UNICODE);
}

function handleUpdate(PDO $pdo, string $type, int $id): void {
    $input = readJsonBody();

    $row = fetchRow($pdo, $type, $id);
    if (!$row) {
        sendError(404, 'Обʼєкт не знайдено');
    }

    $existing = json_decode($row['data'], true);
    $merged   = array_merge($existing, $input); // нові поля перекривають старі

    $update = $pdo->prepare('UPDATE entities SET data = :data WHERE entity_type = :type AND id = :id');
    $update->execute([
        'data' => json_encode($merged, JSON_UNESCAPED_UNICODE),
        'type' => $type,
        'id'   => $id,
    ]);

    echo json_encode(array_merge($merged, ['id' => $id]), JSON_UNESCAPED_UNICODE);
}

function handleDelete(PDO $pdo, string $type, int $id): void {
    $stmt = $pdo->prepare('DELETE FROM entities WHERE entity_type = :type AND id = :id');
    $stmt->execute(['type' => $type, 'id' => $id]);

    if ($stmt->rowCount() === 0) {
        sendError(404, 'Обʼєкт не знайдено');
    }

    echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
}

// ---------------------------------------------------------------------
// Допоміжні функції
// ---------------------------------------------------------------------

function fetchRow(PDO $pdo, string $type, int $id): array|false {
    $stmt = $pdo->prepare('SELECT id, data FROM entities WHERE entity_type = :type AND id = :id');
    $stmt->execute(['type' => $type, 'id' => $id]);
    return $stmt->fetch();
}

function mergeRowWithId(array $row): array {
    $data = json_decode($row['data'], true);
    if (!is_array($data)) {
        $data = [];
    }
    return array_merge($data, ['id' => (int)$row['id']]);
}

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        sendError(400, 'Тіло запиту повинно бути коректним JSON-обʼєктом');
    }

    return $input;
}

function sendError(int $statusCode, string $message): void {
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
