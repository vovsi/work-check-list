<?php

declare(strict_types=1);

// Общий бутстрап для всех API-эндпоинтов: автозагрузка, JSON-заголовки, разбор входных данных

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;

header('Content-Type: application/json; charset=utf-8');

/** Читает и декодирует JSON-тело запроса */
function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

/** Отправляет JSON-ответ и завершает скрипт */
function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = Database::connection();
