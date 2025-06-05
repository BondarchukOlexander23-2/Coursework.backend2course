<?php

class SurveyRetakeController extends BaseController
{
    /**
     * Сторінка управління повторними спробами
     */
    public function managementPage(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('survey_id');

            if ($surveyId <= 0) {
                $this->notFound('Невірний ID опитування');
                return;
            }

            $survey = Survey::findById($surveyId);
            if (!$survey) {
                $this->notFound('Опитування не знайдено');
                return;
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                $this->forbidden('У вас немає прав для управління цим опитуванням');
                return;
            }

            // Отримуємо користувачів які проходили опитування
            $users = $this->getUsersWhoCompleted($surveyId);
            $stats = $this->getRetakeStats($surveyId);

            // Рендеримо простий HTML
            $content = $this->renderManagementHTML($survey, $users, $stats);

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Надати дозвіл на повторне проходження
     */
    public function grantRetake(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userId = (int)$this->postParam('user_id', 0);

            // Базова валідація
            if ($surveyId <= 0 || $userId <= 0) {
                $this->sendAjaxResponse(false, ['Невірні параметри'], 'Помилка');
                return;
            }

            // Перевіряємо права
            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                $this->sendAjaxResponse(false, ['Немає прав'], 'Доступ заборонено');
                return;
            }

            // Перевіряємо чи користувач проходив опитування
            $hasResponded = Database::selectOne(
                "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ? AND user_id = ?",
                [$surveyId, $userId]
            );

            if (($hasResponded['count'] ?? 0) === 0) {
                $this->sendAjaxResponse(false, ['Користувач не проходив це опитування'], 'Помилка');
                return;
            }

            // Перевіряємо чи немає активного дозволу
            $hasActiveRetake = Database::selectOne(
                "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ? AND user_id = ? AND used_at IS NULL",
                [$surveyId, $userId]
            );

            if (($hasActiveRetake['count'] ?? 0) > 0) {
                $this->sendAjaxResponse(false, ['Користувач вже має активний дозвіл'], 'Помилка');
                return;
            }

            // Надаємо дозвіл
            try {
                $query = "INSERT INTO survey_retakes (survey_id, user_id, allowed_by) VALUES (?, ?, ?)";
                Database::insert($query, [$surveyId, $userId, Session::getUserId()]);

                $this->sendAjaxResponse(true, null, 'Дозвіл надано успішно');
            } catch (Exception $e) {
                error_log("Error granting retake: " . $e->getMessage());
                $this->sendAjaxResponse(false, ['Помилка при наданні дозволу'], 'Помилка');
            }
        });
    }

    /**
     * Масове надання дозволів
     */
    public function grantBulkRetakes(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userIds = $this->postParam('user_ids', []);

            // Валідація
            if ($surveyId <= 0) {
                $this->sendAjaxResponse(false, ['Невірний ID опитування'], 'Помилка');
                return;
            }

            if (!is_array($userIds) || empty($userIds)) {
                $this->sendAjaxResponse(false, ['Не вибрано користувачів'], 'Помилка');
                return;
            }

            // Перевіряємо права
            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                $this->sendAjaxResponse(false, ['Немає прав'], 'Доступ заборонено');
                return;
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $userId = (int)$userId;

                if ($userId <= 0) {
                    $errorCount++;
                    continue;
                }

                try {
                    // Перевіряємо чи користувач проходив опитування
                    $hasResponded = Database::selectOne(
                        "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ? AND user_id = ?",
                        [$surveyId, $userId]
                    );

                    if (($hasResponded['count'] ?? 0) === 0) {
                        $errorCount++;
                        continue;
                    }

                    // Перевіряємо чи немає активного дозволу
                    $hasActiveRetake = Database::selectOne(
                        "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ? AND user_id = ? AND used_at IS NULL",
                        [$surveyId, $userId]
                    );

                    if (($hasActiveRetake['count'] ?? 0) > 0) {
                        $errorCount++;
                        continue;
                    }

                    // Надаємо дозвіл
                    $query = "INSERT INTO survey_retakes (survey_id, user_id, allowed_by) VALUES (?, ?, ?)";
                    Database::insert($query, [$surveyId, $userId, Session::getUserId()]);
                    $successCount++;

                } catch (Exception $e) {
                    error_log("Error granting bulk retake for user {$userId}: " . $e->getMessage());
                    $errorCount++;
                }
            }

            $message = "Успішно надано дозволів: {$successCount}";
            if ($errorCount > 0) {
                $message .= ", помилок: {$errorCount}";
            }

            $this->sendAjaxResponse(
                $errorCount === 0,
                ['success' => $successCount, 'errors' => $errorCount],
                $message
            );
        });
    }

    /**
     * Скасувати дозвіл
     */
    public function revokeRetake(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userId = (int)$this->postParam('user_id', 0);

            if ($surveyId <= 0 || $userId <= 0) {
                $this->sendAjaxResponse(false, ['Невірні параметри'], 'Помилка');
                return;
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                $this->sendAjaxResponse(false, ['Немає прав'], 'Доступ заборонено');
                return;
            }

            try {
                $query = "DELETE FROM survey_retakes WHERE survey_id = ? AND user_id = ? AND used_at IS NULL";
                $affected = Database::execute($query, [$surveyId, $userId]);

                if ($affected > 0) {
                    $this->sendAjaxResponse(true, null, 'Дозвіл скасовано');
                } else {
                    $this->sendAjaxResponse(false, ['Дозвіл не знайдено'], 'Помилка');
                }
            } catch (Exception $e) {
                error_log("Error revoking retake: " . $e->getMessage());
                $this->sendAjaxResponse(false, ['Помилка при скасуванні'], 'Помилка');
            }
        });
    }

    /**
     * Отримати користувачів які проходили опитування
     */
    private function getUsersWhoCompleted(int $surveyId): array
    {
        $query = "SELECT DISTINCT sr.user_id, u.name, u.email, 
                         COUNT(sr.id) as attempts_count,
                         MAX(sr.created_at) as last_attempt,
                         MAX(sr.total_score) as best_score,
                         MAX(sr.max_score) as max_possible_score,
                         (SELECT COUNT(*) FROM survey_retakes rt 
                          WHERE rt.survey_id = sr.survey_id 
                          AND rt.user_id = sr.user_id 
                          AND rt.used_at IS NULL) as has_retake_permission
                  FROM survey_responses sr
                  JOIN users u ON sr.user_id = u.id
                  WHERE sr.survey_id = ? AND sr.user_id IS NOT NULL
                  GROUP BY sr.user_id, u.name, u.email
                  ORDER BY last_attempt DESC";

        return Database::select($query, [$surveyId]);
    }

    /**
     * Статистика повторних спроб
     */
    private function getRetakeStats(int $surveyId): array
    {
        $stats = Database::selectOne(
            "SELECT 
                COUNT(*) as total_retakes,
                COUNT(CASE WHEN used_at IS NOT NULL THEN 1 END) as used_retakes,
                COUNT(CASE WHEN used_at IS NULL THEN 1 END) as active_retakes
             FROM survey_retakes 
             WHERE survey_id = ?",
            [$surveyId]
        );

        return [
            'total_retakes' => $stats['total_retakes'] ?? 0,
            'used_retakes' => $stats['used_retakes'] ?? 0,
            'active_retakes' => $stats['active_retakes'] ?? 0
        ];
    }

    /**
     * Рендер HTML сторінки
     */
    private function renderManagementHTML(array $survey, array $users, array $stats): string
    {
        $usersHtml = '';

        if (empty($users)) {
            $usersHtml = '<tr><td colspan="6">Ще немає користувачів які проходили це опитування</td></tr>';
        } else {
            foreach ($users as $user) {
                $percentage = $user['max_possible_score'] > 0
                    ? round(($user['best_score'] / $user['max_possible_score']) * 100, 1)
                    : 0;

                $hasPermission = $user['has_retake_permission'] > 0;
                $statusBadge = $hasPermission
                    ? '<span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 12px; font-size: 12px;">Є дозвіл</span>'
                    : '<span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 12px; font-size: 12px;">Немає дозволу</span>';

                $actionButton = $hasPermission
                    ? "<button onclick='revokeRetake({$user['user_id']})' style='background: #ffc107; color: #212529; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;'>Скасувати</button>"
                    : "<button onclick='grantRetake({$user['user_id']})' style='background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;'>Надати дозвіл</button>";

                $usersHtml .= "
                    <tr>
                        <td>
                            <input type='checkbox' name='user_ids[]' value='{$user['user_id']}' class='user-checkbox'>
                        </td>
                        <td>" . htmlspecialchars($user['name']) . "</td>
                        <td>" . htmlspecialchars($user['email']) . "</td>
                        <td>{$user['attempts_count']}</td>
                        <td>{$user['best_score']}/{$user['max_possible_score']} ({$percentage}%)</td>
                        <td>" . date('d.m.Y H:i', strtotime($user['last_attempt'])) . "</td>
                        <td>{$statusBadge}</td>
                        <td>{$actionButton}</td>
                    </tr>";
            }
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Управління повторними спробами</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
            <style>
                .stats { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .stats div { display: inline-block; margin-right: 30px; }
                .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .table th, .table td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
                .table th { background: #e9ecef; }
                .bulk-actions { margin: 20px 0; }
                .bulk-actions button { margin-right: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Управління повторними спробами</h1>
                <h2>" . htmlspecialchars($survey['title']) . "</h2>
                
                <div class='stats'>
                    <div><strong>Всього дозволів:</strong> {$stats['total_retakes']}</div>
                    <div><strong>Використано:</strong> {$stats['used_retakes']}</div>
                    <div><strong>Активних:</strong> {$stats['active_retakes']}</div>
                </div>
                
                <div class='bulk-actions'>
                    <button onclick='selectAll()' style='background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;'>Вибрати всіх</button>
                    <button onclick='grantBulkRetakes()' style='background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;'>Надати дозволи вибраним</button>
                </div>
                
                <form id='bulk-form'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    <table class='table'>
                        <thead>
                            <tr>
                                <th>Вибрати</th>
                                <th>Користувач</th>
                                <th>Email</th>
                                <th>Спроб</th>
                                <th>Найкращий результат</th>
                                <th>Остання спроба</th>
                                <th>Статус</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$usersHtml}
                        </tbody>
                    </table>
                </form>
                
                <div style='margin: 30px 0;'>
                    <a href='/surveys/edit?id={$survey['id']}' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Назад до редагування</a>
                    <a href='/surveys/results?id={$survey['id']}' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>Результати</a>
                </div>
            </div>
            
            <script>
                function selectAll() {
                    const checkboxes = document.querySelectorAll('.user-checkbox');
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    checkboxes.forEach(cb => cb.checked = !allChecked);
                }
                
                function grantRetake(userId) {
                    if (confirm('Надати дозвіл на повторне проходження?')) {
                        fetch('/retake/grant', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'survey_id={$survey['id']}&user_id=' + userId
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message || 'Готово');
                            location.reload();
                        });
                    }
                }
                
                function revokeRetake(userId) {
                    if (confirm('Скасувати дозвіл?')) {
                        fetch('/retake/revoke', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'survey_id={$survey['id']}&user_id=' + userId
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message || 'Готово');
                            location.reload();
                        });
                    }
                }
                
                function grantBulkRetakes() {
                    const form = document.getElementById('bulk-form');
                    const formData = new FormData(form);
                    const selectedUsers = formData.getAll('user_ids[]');
                    
                    if (selectedUsers.length === 0) {
                        alert('Оберіть користувачів');
                        return;
                    }
                    
                    if (confirm('Надати дозвіл ' + selectedUsers.length + ' користувачам?')) {
                        fetch('/retake/grant-bulk', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message || 'Готово');
                            location.reload();
                        });
                    }
                }
            </script>
        </body>
        </html>";
    }
}