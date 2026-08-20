<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

/**
 * @OA\Post(
 *     path="/today_time_spent_breakdown",
 *     summary="Get today's time tracked per task.",
 *     description="Read-only endpoint that returns today's tracked worklogs grouped by task (task id, title, link, seconds).",
 *     @OA\RequestBody(
 *         required=false,
 *         description="Optional currently opened task (tasks.id) that must be present in the result even if Jira search has not indexed its fresh worklog yet",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 type="object",
 *                 example={
 *                     "task_id": "1"
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *          response=200,
 *          description="Successful operation",
 *          @OA\JsonContent(
 *              @OA\Property(property="tasks", type="array", @OA\Items(
 *                  @OA\Property(property="task_id", type="string", example="PROJ-123"),
 *                  @OA\Property(property="title", type="string", example="Fix login bug"),
 *                  @OA\Property(property="status", type="string", example="In Progress"),
 *                  @OA\Property(property="link", type="string", example="https://example.atlassian.net/browse/PROJ-123"),
 *                  @OA\Property(property="seconds", type="integer", example=3600)
 *              ))
 *          )
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Jira integration is not configured"
 *      ),
 *      @OA\Response(
 *          response=502,
 *          description="Jira request failed"
 *      )
 * )
 */

// Read-only разбивка сегодняшнего затреканного времени по задачам — для модалки со списком
// задач под индикатором и для модалки поздравления. Необязательный task_id — открытая сейчас
// задача: её нужно показать в списке, даже если JQL-поиск Jira ещё не проиндексировал только
// что добавленный worklog.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$ensureTaskId = (int) ($input['task_id'] ?? 0);

$taskRepository = new TaskRepository($pdo);
$jiraSync = JiraSyncService::createFromConfig($taskRepository);
if ($jiraSync === null) {
    respond(['error' => 'Интеграция с Jira не настроена — заполните config/params.ini'], 422);
}

$service = new TaskService($taskRepository, new ChecklistRepository($pdo), $jiraSync);

try {
    $tasks = $service->getTodayTimeSpentBreakdown($ensureTaskId);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['tasks' => $tasks]);
