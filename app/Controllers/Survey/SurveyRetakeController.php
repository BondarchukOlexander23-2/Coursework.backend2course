<?php

class SurveyRetakeController extends BaseController
{
    public function managementPage(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('survey_id');
            $this->validateSurveyAccess($surveyId);

            $survey = Survey::findById($surveyId);
            $users = $this->getUsersWhoCompleted($surveyId);
            $stats = $this->getRetakeStats($surveyId);

            $content = $this->renderManagementHTML($survey, $users, $stats);
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    public function grantRetake(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userId = (int)$this->postParam('user_id', 0);

            if (!$this->validateBasicParams($surveyId, $userId)) return;
            if (!$this->validateAuthorAccess($surveyId)) return;
            if (!$this->validateUserEligibility($surveyId, $userId)) return;

            $this->processRetakeGrant($surveyId, $userId);
        });
    }

    public function grantBulkRetakes(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userIds = $this->postParam('user_ids', []);

            if (!$this->validateBulkParams($surveyId, $userIds)) return;
            if (!$this->validateAuthorAccess($surveyId)) return;

            $this->processBulkRetakes($surveyId, $userIds);
        });
    }

    public function revokeRetake(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $userId = (int)$this->postParam('user_id', 0);

            if (!$this->validateBasicParams($surveyId, $userId)) return;
            if (!$this->validateAuthorAccess($surveyId)) return;

            $this->processRetakeRevoke($surveyId, $userId);
        });
    }

    private function validateSurveyAccess(int $surveyId): void
    {
        if ($surveyId <= 0) {
            $this->notFound('Невірний ID опитування');
        }

        $survey = Survey::findById($surveyId);
        if (!$survey) {
            $this->notFound('Опитування не знайдено');
        }

        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            $this->forbidden('У вас немає прав для управління цим опитуванням');
        }
    }

    private function validateBasicParams(int $surveyId, int $userId): bool
    {
        if ($surveyId <= 0 || $userId <= 0) {
            $this->sendAjaxResponse(false, ['Невірні параметри'], 'Помилка');
            return false;
        }
        return true;
    }

    private function validateBulkParams(int $surveyId, array $userIds): bool
    {
        if ($surveyId <= 0) {
            $this->sendAjaxResponse(false, ['Невірний ID опитування'], 'Помилка');
            return false;
        }

        if (empty($userIds)) {
            $this->sendAjaxResponse(false, ['Не вибрано користувачів'], 'Помилка');
            return false;
        }
        return true;
    }

    private function validateAuthorAccess(int $surveyId): bool
    {
        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            $this->sendAjaxResponse(false, ['Немає прав'], 'Доступ заборонено');
            return false;
        }
        return true;
    }

    private function validateUserEligibility(int $surveyId, int $userId): bool
    {
        if (!$this->hasUserCompleted($surveyId, $userId)) {
            $this->sendAjaxResponse(false, ['Користувач не проходив це опитування'], 'Помилка');
            return false;
        }

        if ($this->hasActiveRetake($surveyId, $userId)) {
            $this->sendAjaxResponse(false, ['Користувач вже має активний дозвіл'], 'Помилка');
            return false;
        }
        return true;
    }

    private function hasUserCompleted(int $surveyId, int $userId): bool
    {
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ? AND user_id = ?",
            [$surveyId, $userId]
        );
        return ($result['count'] ?? 0) > 0;
    }

    private function hasActiveRetake(int $surveyId, int $userId): bool
    {
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ? AND user_id = ? AND used_at IS NULL",
            [$surveyId, $userId]
        );
        return ($result['count'] ?? 0) > 0;
    }

    private function processRetakeGrant(int $surveyId, int $userId): void
    {
        try {
            Database::insert(
                "INSERT INTO survey_retakes (survey_id, user_id, allowed_by) VALUES (?, ?, ?)",
                [$surveyId, $userId, Session::getUserId()]
            );
            $this->sendAjaxResponse(true, null, 'Дозвіл надано успішно');
        } catch (Exception $e) {
            error_log("Error granting retake: " . $e->getMessage());
            $this->sendAjaxResponse(false, ['Помилка при наданні дозволу'], 'Помилка');
        }
    }

    private function processBulkRetakes(int $surveyId, array $userIds): void
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0 ||
                !$this->hasUserCompleted($surveyId, $userId) ||
                $this->hasActiveRetake($surveyId, $userId)) {
                $errorCount++;
                continue;
            }

            try {
                Database::insert(
                    "INSERT INTO survey_retakes (survey_id, user_id, allowed_by) VALUES (?, ?, ?)",
                    [$surveyId, $userId, Session::getUserId()]
                );
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
    }

    private function processRetakeRevoke(int $surveyId, int $userId): void
    {
        try {
            $affected = Database::execute(
                "DELETE FROM survey_retakes WHERE survey_id = ? AND user_id = ? AND used_at IS NULL",
                [$surveyId, $userId]
            );

            if ($affected > 0) {
                $this->sendAjaxResponse(true, null, 'Дозвіл скасовано');
            } else {
                $this->sendAjaxResponse(false, ['Дозвіл не знайдено'], 'Помилка');
            }
        } catch (Exception $e) {
            error_log("Error revoking retake: " . $e->getMessage());
            $this->sendAjaxResponse(false, ['Помилка при скасуванні'], 'Помилка');
        }
    }

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

    private function renderManagementHTML(array $survey, array $users, array $stats): string
    {
        $usersHtml = $this->renderUsersTable($users);

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Управління повторними спробами</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Управління повторними спробами</h1>
                    <h2>" . htmlspecialchars($survey['title']) . "</h2>
                </div>
                
                <div class='summary-stats'>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['total_retakes']}</span>
                        <span class='stat-label'>Всього дозволів</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['used_retakes']}</span>
                        <span class='stat-label'>Використано</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['active_retakes']}</span>
                        <span class='stat-label'>Активних</span>
                    </div>
                </div>
                
                <div class='bulk-actions'>
                    <button onclick='selectAll()' class='btn btn-secondary'>Вибрати всіх</button>
                    <button onclick='grantBulkRetakes()' class='btn btn-success'>Надати дозволи вибраним</button>
                </div>
                
                <form id='bulk-form'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    <div class='table-container'>
                        <table class='table'>
                            <thead>
                                <tr>
                                    <th>Вибрати</th>
                                    <th>Користувач</th>
                                    <th>Спроб</th>
                                    <th>Результат</th>
                                    <th>Остання спроба</th>
                                    <th>Статус</th>
                                    <th>Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$usersHtml}
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <div class='navigation'>
                    <a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Назад до редагування</a>
                    <a href='/surveys/results?id={$survey['id']}' class='btn btn-primary'>Результати</a>
                </div>
            </div>
            
            {$this->renderJavaScript($survey['id'])}
        </body>
        </html>";
    }

    private function renderUsersTable(array $users): string
    {
        if (empty($users)) {
            return '<tr><td colspan="7" class="no-results">Ще немає користувачів які проходили це опитування</td></tr>';
        }

        $html = '';
        foreach ($users as $user) {
            $html .= $this->renderUserRow($user);
        }
        return $html;
    }

    private function renderUserRow(array $user): string
    {
        $percentage = $user['max_possible_score'] > 0
            ? round(($user['best_score'] / $user['max_possible_score']) * 100, 1)
            : 0;

        $hasPermission = $user['has_retake_permission'] > 0;
        $statusBadge = $hasPermission
            ? '<span>Є дозвіл</span>'
            : '<span>Немає дозволу</span>';

        $actionButton = $hasPermission
            ? "<button onclick='revokeRetake({$user['user_id']})' class='btn btn-danger btn-sm'>Скасувати</button>"
            : "<button onclick='grantRetake({$user['user_id']})' class='btn btn-success btn-sm'>Надати дозвіл</button>";

        return "
        <tr>
            <td class='table-cell-center'>
                <input type='checkbox' name='user_ids[]' value='{$user['user_id']}' class='user-checkbox'>
            </td>
            <td class='table-cell-user'>
                <div class='user-info'>
                    <div class='user-name'>" . htmlspecialchars($user['name']) . "</div>
                    <div class='user-email'>" . htmlspecialchars($user['email']) . "</div>
                </div>
            </td>
            <td class='table-cell-center'>{$user['attempts_count']}</td>
            <td class='table-cell-center'>
                {$user['best_score']}/{$user['max_possible_score']} ({$percentage}%)
            </td>
            <td class='table-cell-center'>
                " . date('d.m.Y H:i', strtotime($user['last_attempt'])) . "
            </td>
            <td class='table-cell-center'>{$statusBadge}</td>
            <td class='table-cell-center'>{$actionButton}</td>
        </tr>";
    }

    private function renderJavaScript(int $surveyId): string
    {
        return "
        <script>
            function selectAll() {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
            }
            
            function grantRetake(userId) {
                if (confirm('Надати дозвіл на повторне проходження?')) {
                    makeRequest('/surveys/retake/grant', {survey_id: {$surveyId}, user_id: userId});
                }
            }
            
            function revokeRetake(userId) {
                if (confirm('Скасувати дозвіл?')) {
                    makeRequest('/surveys/retake/revoke', {survey_id: {$surveyId}, user_id: userId});
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
                    fetch('/surveys/retake/grant-bulk', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(handleResponse)
                    .catch(handleError);
                }
            }
            
            function makeRequest(url, data) {
                const formData = new URLSearchParams();
                Object.keys(data).forEach(key => formData.append(key, data[key]));
                
                fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: formData
                })
                .then(response => response.json())
                .then(handleResponse)
                .catch(handleError);
            }
            
            function handleResponse(data) {
                alert(data.message || 'Готово');
                location.reload();
            }
            
            function handleError(error) {
                console.error('Error:', error);
                alert('Виникла помилка');
            }
        </script>";
    }
}