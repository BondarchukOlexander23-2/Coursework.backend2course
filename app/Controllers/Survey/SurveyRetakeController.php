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
     * Рендер HTML сторінки з виправленими URL
     */
    private function renderManagementHTML(array $survey, array $users, array $stats): string
    {
        $usersHtml = '';

        if (empty($users)) {
            $usersHtml = '<tr><td colspan="7">Ще немає користувачів які проходили це опитування</td></tr>';
        } else {
            foreach ($users as $user) {
                $percentage = $user['max_possible_score'] > 0
                    ? round(($user['best_score'] / $user['max_possible_score']) * 100, 1)
                    : 0;

                $hasPermission = $user['has_retake_permission'] > 0;
                $statusBadge = $hasPermission
                    ? '<span class="badge badge-success">Є дозвіл</span>'
                    : '<span class="badge badge-danger">Немає дозволу</span>';

                $actionButton = $hasPermission
                    ? "<button onclick='revokeRetake({$user['user_id']})' class='btn btn-warning btn-sm'>Скасувати</button>"
                    : "<button onclick='grantRetake({$user['user_id']})' class='btn btn-success btn-sm'>Надати дозвіл</button>";

                $usersHtml .= "
                <tr>
                    <td>
                        <input type='checkbox' name='user_ids[]' value='{$user['user_id']}' class='user-checkbox'>
                    </td>
                    <td>
                        <div class='user-info'>
                            <div class='user-name'>" . htmlspecialchars($user['name']) . "</div>
                            <div class='user-email'>" . htmlspecialchars($user['email']) . "</div>
                        </div>
                    </td>
                    <td class='text-center'>{$user['attempts_count']}</td>
                    <td class='text-center'>
                        <div class='score-info'>
                            <span class='score'>{$user['best_score']}/{$user['max_possible_score']}</span>
                            <small class='percentage'>({$percentage}%)</small>
                        </div>
                    </td>
                    <td class='text-center'>
                        <small>" . date('d.m.Y H:i', strtotime($user['last_attempt'])) . "</small>
                    </td>
                    <td class='text-center'>{$statusBadge}</td>
                    <td class='text-center actions-cell'>{$actionButton}</td>
                </tr>";
            }
        }

        return "
    <!DOCTYPE html>
    <html lang='uk'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Управління повторними спробами</title>
        <link rel='stylesheet' href='/assets/css/style.css'>
        <style>
            * {
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                color: #2d3748;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 30px 20px;
                min-height: 100vh;
            }

            .header {
                text-align: center;
                margin-bottom: 40px;
                padding: 30px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .header h1 {
                margin: 0 0 15px 0;
                color: #2d3748;
                font-size: 2.5rem;
                font-weight: 700;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .header h2 {
                margin: 0;
                color: #718096;
                font-size: 1.3rem;
                font-weight: 400;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 25px;
                margin: 40px 0;
            }

            .stat-item {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 30px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.18);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .stat-item::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            }

            .stat-item:hover {
                transform: translateY(-8px);
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            }

            .stat-number {
                font-size: 3rem;
                font-weight: 800;
                margin-bottom: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .stat-label {
                font-size: 1rem;
                color: #718096;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .bulk-actions {
                margin: 30px 0;
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: center;
                padding: 25px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 15px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 12px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
                min-height: 44px;
            }

            .btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s;
            }

            .btn:hover::before {
                left: 100%;
            }

            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            .btn-primary { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
            }
            .btn-success { 
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                color: white; 
            }
            .btn-warning { 
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
                color: white; 
            }
            .btn-secondary { 
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
                color: white; 
            }
            .btn-sm { 
                padding: 8px 16px; 
                font-size: 12px; 
                min-height: 36px;
            }

            .table-container {
                overflow-x: auto;
                margin: 20px 0;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                background: white;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                min-width: 800px;
            }

            .table th {
                background: #f8f9fa;
                padding: 15px 12px;
                text-align: left;
                font-weight: 600;
                color: #495057;
                border-bottom: 2px solid #dee2e6;
                white-space: nowrap;
            }

            .table td {
                padding: 12px;
                border-bottom: 1px solid #dee2e6;
                vertical-align: middle;
            }

            .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .user-info {
                min-width: 200px;
            }

            .user-name {
                font-weight: 500;
                color: #212529;
                margin-bottom: 2px;
            }

            .user-email {
                font-size: 0.85rem;
                color: #6c757d;
            }

            .score-info {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .score {
                font-weight: 500;
            }

            .percentage {
                color: #6c757d;
                margin-top: 2px;
            }

            .badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .badge-success {
                background: #d4edda;
                color: #155724;
            }

            .badge-danger {
                background: #f8d7da;
                color: #721c24;
            }

            .text-center {
                text-align: center;
            }

            .actions-cell {
                white-space: nowrap;
                width: 120px;
            }

            .navigation {
                margin: 30px 0;
                padding: 20px 0;
                border-top: 1px solid #dee2e6;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .user-checkbox {
                transform: scale(1.2);
                cursor: pointer;
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .container {
                    padding: 15px;
                }

                .header h1 {
                    font-size: 1.5rem;
                }

                .stats {
                    flex-direction: column;
                    gap: 15px;
                }

                .stat-item {
                    flex-direction: row;
                    justify-content: space-between;
                    align-items: center;
                    min-width: 100%;
                }

                .stat-number {
                    font-size: 1.5rem;
                    margin-bottom: 0;
                }

                .bulk-actions {
                    flex-direction: column;
                    align-items: stretch;
                }

                .btn {
                    width: 100%;
                    text-align: center;
                }

                .table th, .table td {
                    padding: 8px 6px;
                    font-size: 14px;
                }

                .user-info {
                    min-width: 150px;
                }

                .user-name, .user-email {
                    font-size: 12px;
                }
            }

            @media (max-width: 480px) {
                .stat-number {
                    font-size: 1.2rem;
                }

                .table {
                    min-width: 600px;
                }

                .table th, .table td {
                    padding: 6px 4px;
                    font-size: 12px;
                }
            }

            /* Спеціальні стилі для маленьких екранів */
            @media (max-width: 600px) {
                .table-container {
                    margin: 10px -15px;
                    border-radius: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Управління повторними спробами</h1>
                <h2>" . htmlspecialchars($survey['title']) . "</h2>
            </div>
            
            <div class='stats'>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['total_retakes']}</div>
                    <div class='stat-label'>Всього дозволів</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['used_retakes']}</div>
                    <div class='stat-label'>Використано</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['active_retakes']}</div>
                    <div class='stat-label'>Активних</div>
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
                                <th style='width: 50px;'>Вибрати</th>
                                <th>Користувач</th>
                                <th style='width: 80px;'>Спроб</th>
                                <th style='width: 120px;'>Результат</th>
                                <th style='width: 100px;'>Остання спроба</th>
                                <th style='width: 100px;'>Статус</th>
                                <th style='width: 120px;'>Дії</th>
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
        
        <script>
            function selectAll() {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
            }
            
            function grantRetake(userId) {
                if (confirm('Надати дозвіл на повторне проходження?')) {
                    fetch('/surveys/retake/grant', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'survey_id={$survey['id']}&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Готово');
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Виникла помилка');
                    });
                }
            }
            
            function revokeRetake(userId) {
                if (confirm('Скасувати дозвіл?')) {
                    fetch('/surveys/retake/revoke', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'survey_id={$survey['id']}&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Готово');
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Виникла помилка');
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
                    fetch('/surveys/retake/grant-bulk', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Готово');
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Виникла помилка');
                    });
                }
            }
        </script>
    </body>
    </html>";
    }
}