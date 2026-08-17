<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\JiraSyncService;
use App\TaskRepository;

/**
 * @OA\Post(
 *     path="/today_time_spent_breakdown",
 *     summary="Get today's time tracked per task.",
 *     description="Read-only endpoint that returns today's tracked worklogs grouped by task (task id, title, link, seconds).",
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
// задач под индикатором. К конкретной задаче не привязано, поэтому идёт мимо TaskService.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$jiraSync = JiraSyncService::createFromConfig(new TaskRepository($pdo));
if ($jiraSync === null) {
    respond(['error' => 'Интеграция с Jira не настроена — заполните config/params.ini'], 422);
}

try {
    $tasks = $jiraSync->getTodayTimeSpentBreakdown();
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['tasks' => $tasks]);
