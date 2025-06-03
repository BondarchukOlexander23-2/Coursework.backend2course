<?php

/**
 * Контролер адміністрування
 * Відповідає принципу Single Responsibility - тільки адміністративні функції
 */
class AdminController
{
    private AdminValidator $validator;
    private AdminService $adminService;

    public function __construct()
    {
        $this->validator = new AdminValidator();
        $this->adminService = new AdminService();
    }

    /**
     * Головна сторінка адмін-панелі
     */
    public function dashboard(): void
    {
        $this->requireAdmin();

        $stats = $this->adminService->getDashboardStats();
        $content = $this->renderDashboard($stats);
        echo $content;
    }

    /**
     * Управління користувачами
     */
    public function users(): void
    {
        $this->requireAdmin();

        $page = (int)($_GET['page'] ?? 1);
        $search = trim($_GET['search'] ?? '');

        $users = $this->adminService->getUsers($page, $search);
        $totalUsers = $this->adminService->getTotalUsersCount($search);
        $totalPages = ceil($totalUsers / 20);

        $content = $this->renderUsers($users, $page, $totalPages, $search);
        echo $content;
    }

    /**
     * Видалення користувача
     */
    public function deleteUser(): void
    {
        $this->requireAdmin();

        $userId = (int)($_POST['user_id'] ?? 0);
        $errors = $this->validator->validateUserDeletion($userId);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header('Location: /admin/users');
            exit;
        }

        try {
            $this->adminService->deleteUser($userId);
            Session::setFlashMessage('success', 'Користувача успішно видалено');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при видаленні користувача: ' . $e->getMessage());
        }

        header('Location: /admin/users');
        exit;
    }

    /**
     * Зміна ролі користувача
     */
    public function changeUserRole(): void
    {
        $this->requireAdmin();

        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';

        $errors = $this->validator->validateRoleChange($userId, $newRole);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header('Location: /admin/users');
            exit;
        }

        try {
            $this->adminService->changeUserRole($userId, $newRole);
            Session::setFlashMessage('success', 'Роль користувача успішно змінено');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при зміні ролі: ' . $e->getMessage());
        }

        header('Location: /admin/users');
        exit;
    }

    /**
     * Управління опитуваннями
     */
    public function surveys(): void
    {
        $this->requireAdmin();

        $page = (int)($_GET['page'] ?? 1);
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';

        $surveys = $this->adminService->getSurveys($page, $search, $status);
        $totalSurveys = $this->adminService->getTotalSurveysCount($search, $status);
        $totalPages = ceil($totalSurveys / 20);

        $content = $this->renderSurveys($surveys, $page, $totalPages, $search, $status);
        echo $content;
    }

    /**
     * Видалення опитування
     */
    public function deleteSurvey(): void
    {
        $this->requireAdmin();

        $surveyId = (int)($_POST['survey_id'] ?? 0);
        $errors = $this->validator->validateSurveyDeletion($surveyId);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header('Location: /admin/surveys');
            exit;
        }

        try {
            $this->adminService->deleteSurvey($surveyId);
            Session::setFlashMessage('success', 'Опитування успішно видалено');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при видаленні опитування: ' . $e->getMessage());
        }

        header('Location: /admin/surveys');
        exit;
    }

    /**
     * Перемикання статусу опитування
     */
    public function toggleSurveyStatus(): void
    {
        $this->requireAdmin();

        $surveyId = (int)($_POST['survey_id'] ?? 0);

        try {
            $this->adminService->toggleSurveyStatus($surveyId);
            Session::setFlashMessage('success', 'Статус опитування змінено');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при зміні статусу: ' . $e->getMessage());
        }

        header('Location: /admin/surveys');
        exit;
    }

    /**
     * Детальна статистика опитування
     */
    public function surveyStats(): void
    {
        $this->requireAdmin();

        $surveyId = (int)($_GET['id'] ?? 0);
        $survey = Survey::findById($surveyId);

        if (!$survey) {
            Session::setFlashMessage('error', 'Опитування не знайдено');
            header('Location: /admin/surveys');
            exit;
        }

        $stats = $this->adminService->getSurveyDetailedStats($surveyId);
        $content = $this->renderSurveyStats($survey, $stats);
        echo $content;
    }

    /**
     * Експорт статистики
     */
    public function exportStats(): void
    {
        $this->requireAdmin();

        $type = $_GET['type'] ?? 'csv';
        $surveyId = (int)($_GET['survey_id'] ?? 0);

        try {
            $this->adminService->exportSurveyStats($surveyId, $type);
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при експорті: ' . $e->getMessage());
            header('Location: /admin/surveys');
            exit;
        }
    }

    // === ПРИВАТНІ МЕТОДИ ===

    /**
     * Перевірка прав адміністратора
     */
    private function requireAdmin(): void
    {
        Session::requireLogin();

        if (!$this->isAdmin()) {
            Session::setFlashMessage('error', 'Доступ заборонено. Тільки для адміністраторів.');
            header('Location: /');
            exit;
        }
    }

    /**
     * Перевірити чи є користувач адміном
     */
    private function isAdmin(): bool
    {
        $userId = Session::getUserId();
        if (!$userId) {
            return false;
        }

        $user = User::findById($userId);
        return $user && $user['role'] === 'admin';
    }

    // === HTML РЕНДЕРИНГ ===

    /**
     * Рендер дашборду
     */
    private function renderDashboard(array $stats): string
    {
        return $this->renderPage("Адмін-панель", "
            <div class='admin-header'>
                <h1>Адміністративна панель</h1>
                " . $this->renderAdminNav() . "
            </div>
            
            <div class='dashboard-stats'>
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-icon'>👥</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_users']}</h3>
                            <p>Користувачів</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>📋</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_surveys']}</h3>
                            <p>Опитувань</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>✅</div>
                        <div class='stat-info'>
                            <h3>{$stats['active_surveys']}</h3>
                            <p>Активних</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>📊</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_responses']}</h3>
                            <p>Відповідей</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='recent-activity'>
                <h2>Остання активність</h2>
                <div class='activity-list'>
                    " . $this->renderRecentActivity($stats['recent_activity']) . "
                </div>
            </div>
            
            <div class='quick-actions'>
                <h2>Швидкі дії</h2>
                <div class='actions-grid'>
                    <a href='/admin/users' class='action-card'>
                        <div class='action-icon'>👥</div>
                        <h3>Управління користувачами</h3>
                        <p>Переглянути, редагувати та видалити користувачів</p>
                    </a>
                    <a href='/admin/surveys' class='action-card'>
                        <div class='action-icon'>📋</div>
                        <h3>Управління опитуваннями</h3>
                        <p>Модерація та статистика опитувань</p>
                    </a>
                </div>
            </div>
        ");
    }

    /**
     * Рендер користувачів
     */
    private function renderUsers(array $users, int $currentPage, int $totalPages, string $search): string
    {
        $usersHtml = '';
        foreach ($users as $user) {
            $roleClass = $user['role'] === 'admin' ? 'role-admin' : 'role-user';
            $roleText = $user['role'] === 'admin' ? 'Адмін' : 'Користувач';

            $usersHtml .= "
                <tr>
                    <td>{$user['id']}</td>
                    <td>" . htmlspecialchars($user['name']) . "</td>
                    <td>" . htmlspecialchars($user['email']) . "</td>
                    <td><span class='role-badge {$roleClass}'>{$roleText}</span></td>
                    <td>{$user['surveys_count']}</td>
                    <td>{$user['responses_count']}</td>
                    <td>{$user['created_at']}</td>
                    <td class='actions'>
                        " . ($user['role'] !== 'admin' ? "
                        <form method='POST' action='/admin/change-user-role' style='display: inline;'>
                            <input type='hidden' name='user_id' value='{$user['id']}'>
                            <select name='role' onchange='this.form.submit()'>
                                <option value='user'" . ($user['role'] === 'user' ? ' selected' : '') . ">Користувач</option>
                                <option value='admin'" . ($user['role'] === 'admin' ? ' selected' : '') . ">Адмін</option>
                            </select>
                        </form>
                        <form method='POST' action='/admin/delete-user' style='display: inline;'>
                            <input type='hidden' name='user_id' value='{$user['id']}'>
                            <button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Видалити користувача?\")'>Видалити</button>
                        </form>" : "<em>Системний адмін</em>") . "
                    </td>
                </tr>";
        }

        $pagination = $this->renderPagination('/admin/users', $currentPage, $totalPages, ['search' => $search]);

        return $this->renderPage("Управління користувачами", "
            <div class='admin-header'>
                <h1>Управління користувачами</h1>
                " . $this->renderAdminNav() . "
            </div>
            
            <div class='admin-filters'>
                <form method='GET' action='/admin/users' class='search-form'>
                    <input type='text' name='search' placeholder='Пошук користувачів...' value='" . htmlspecialchars($search) . "'>
                    <button type='submit' class='btn btn-primary'>Знайти</button>
                </form>
            </div>
            
            <div class='table-container'>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ім'я</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Опитувань</th>
                            <th>Відповідей</th>
                            <th>Реєстрація</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$usersHtml}
                    </tbody>
                </table>
            </div>
            
            {$pagination}
        ");
    }

    /**
     * Рендер опитувань
     */
    private function renderSurveys(array $surveys, int $currentPage, int $totalPages, string $search, string $status): string
    {
        $surveysHtml = '';
        foreach ($surveys as $survey) {
            $statusClass = $survey['is_active'] ? 'status-active' : 'status-inactive';
            $statusText = $survey['is_active'] ? 'Активне' : 'Неактивне';

            $surveysHtml .= "
                <tr>
                    <td>{$survey['id']}</td>
                    <td>
                        <strong>" . htmlspecialchars($survey['title']) . "</strong><br>
                        <small>" . htmlspecialchars(substr($survey['description'], 0, 100)) . "...</small>
                    </td>
                    <td>" . htmlspecialchars($survey['author_name']) . "</td>
                    <td><span class='status-badge {$statusClass}'>{$statusText}</span></td>
                    <td>{$survey['question_count']}</td>
                    <td>{$survey['response_count']}</td>
                    <td>{$survey['created_at']}</td>
                    <td class='actions'>
                        <a href='/admin/survey-stats?id={$survey['id']}' class='btn btn-sm btn-primary'>Статистика</a>
                        <form method='POST' action='/admin/toggle-survey-status' style='display: inline;'>
                            <input type='hidden' name='survey_id' value='{$survey['id']}'>
                            <button type='submit' class='btn btn-sm btn-secondary'>" . ($survey['is_active'] ? 'Деактивувати' : 'Активувати') . "</button>
                        </form>
                        <form method='POST' action='/admin/delete-survey' style='display: inline;'>
                            <input type='hidden' name='survey_id' value='{$survey['id']}'>
                            <button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Видалити опитування?\")'>Видалити</button>
                        </form>
                    </td>
                </tr>";
        }

        $pagination = $this->renderPagination('/admin/surveys', $currentPage, $totalPages, ['search' => $search, 'status' => $status]);

        return $this->renderPage("Управління опитуваннями", "
            <div class='admin-header'>
                <h1>Управління опитуваннями</h1>
                " . $this->renderAdminNav() . "
            </div>
            
            <div class='admin-filters'>
                <form method='GET' action='/admin/surveys' class='filter-form'>
                    <input type='text' name='search' placeholder='Пошук опитувань...' value='" . htmlspecialchars($search) . "'>
                    <select name='status'>
                        <option value='all'" . ($status === 'all' ? ' selected' : '') . ">Всі статуси</option>
                        <option value='active'" . ($status === 'active' ? ' selected' : '') . ">Активні</option>
                        <option value='inactive'" . ($status === 'inactive' ? ' selected' : '') . ">Неактивні</option>
                    </select>
                    <button type='submit' class='btn btn-primary'>Фільтрувати</button>
                </form>
            </div>
            
            <div class='table-container'>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Опитування</th>
                            <th>Автор</th>
                            <th>Статус</th>
                            <th>Питань</th>
                            <th>Відповідей</th>
                            <th>Створено</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$surveysHtml}
                    </tbody>
                </table>
            </div>
            
            {$pagination}
        ");
    }

    /**
     * Рендер статистики опитування
     */
    private function renderSurveyStats(array $survey, array $stats): string
    {
        $questionsStatsHtml = '';
        foreach ($stats['questions'] as $question) {
            $questionsStatsHtml .= "
                <div class='question-stats'>
                    <h4>" . htmlspecialchars($question['question_text']) . "</h4>
                    <div class='question-metrics'>
                        <span>Відповідей: {$question['answers_count']}</span>
                        " . (isset($question['avg_correctness']) ? "<span>Правильність: {$question['avg_correctness']}%</span>" : "") . "
                    </div>
                </div>";
        }

        return $this->renderPage("Статистика опитування", "
            <div class='admin-header'>
                <h1>Статистика: " . htmlspecialchars($survey['title']) . "</h1>
                " . $this->renderAdminNav() . "
            </div>
            
            <div class='survey-overview'>
                <div class='overview-grid'>
                    <div class='overview-item'>
                        <h3>Загальна інформація</h3>
                        <p><strong>Автор:</strong> " . htmlspecialchars($survey['author_name']) . "</p>
                        <p><strong>Створено:</strong> {$survey['created_at']}</p>
                        <p><strong>Статус:</strong> " . ($survey['is_active'] ? 'Активне' : 'Неактивне') . "</p>
                    </div>
                    <div class='overview-item'>
                        <h3>Статистика</h3>
                        <p><strong>Питань:</strong> {$stats['general']['total_questions']}</p>
                        <p><strong>Відповідей:</strong> {$stats['general']['total_responses']}</p>
                        <p><strong>Унікальних користувачів:</strong> {$stats['general']['unique_users']}</p>
                    </div>
                </div>
            </div>
            
            <div class='stats-charts'>
                <h2>Статистика по питаннях</h2>
                {$questionsStatsHtml}
            </div>
            
            <div class='export-actions'>
                <h2>Експорт даних</h2>
                <a href='/admin/export-stats?survey_id={$survey['id']}&type=csv' class='btn btn-primary'>Експорт CSV</a>
                <a href='/admin/export-stats?survey_id={$survey['id']}&type=xlsx' class='btn btn-secondary'>Експорт Excel</a>
            </div>
            
            <div class='form-actions'>
                <a href='/admin/surveys' class='btn btn-secondary'>Назад до списку</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
            </div>
        ");
    }

    /**
     * Рендер навігації адміна
     */
    private function renderAdminNav(): string
    {
        return "
            <nav class='admin-nav'>
                <a href='/admin' class='admin-nav-link'>Дашборд</a>
                <a href='/admin/users' class='admin-nav-link'>Користувачі</a>
                <a href='/admin/surveys' class='admin-nav-link'>Опитування</a>
                <a href='/surveys' class='admin-nav-link'>До сайту</a>
                <a href='/logout' class='admin-nav-link'>Вийти</a>
            </nav>";
    }

    /**
     * Рендер пагінації
     */
    private function renderPagination(string $baseUrl, int $currentPage, int $totalPages, array $params = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $paginationHtml = '<div class="pagination">';

        // Попередня сторінка
        if ($currentPage > 1) {
            $prevParams = array_merge($params, ['page' => $currentPage - 1]);
            $prevUrl = $baseUrl . '?' . http_build_query($prevParams);
            $paginationHtml .= "<a href='{$prevUrl}' class='page-link'>← Попередня</a>";
        }

        // Номери сторінок
        for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
            $pageParams = array_merge($params, ['page' => $i]);
            $pageUrl = $baseUrl . '?' . http_build_query($pageParams);
            $activeClass = $i === $currentPage ? ' active' : '';
            $paginationHtml .= "<a href='{$pageUrl}' class='page-link{$activeClass}'>{$i}</a>";
        }

        // Наступна сторінка
        if ($currentPage < $totalPages) {
            $nextParams = array_merge($params, ['page' => $currentPage + 1]);
            $nextUrl = $baseUrl . '?' . http_build_query($nextParams);
            $paginationHtml .= "<a href='{$nextUrl}' class='page-link'>Наступна →</a>";
        }

        $paginationHtml .= '</div>';
        return $paginationHtml;
    }

    /**
     * Рендер останньої активності
     */
    private function renderRecentActivity(array $activities): string
    {
        if (empty($activities)) {
            return '<p>Немає останньої активності</p>';
        }

        $html = '';
        foreach ($activities as $activity) {
            $html .= "
                <div class='activity-item'>
                    <div class='activity-icon'>{$activity['icon']}</div>
                    <div class='activity-content'>
                        <p>{$activity['description']}</p>
                        <small>{$activity['time']}</small>
                    </div>
                </div>";
        }

        return $html;
    }

    /**
     * Базовий рендер сторінки
     */
    private function renderPage(string $title, string $content): string
    {
        $flashSuccess = Session::getFlashMessage('success');
        $flashError = Session::getFlashMessage('error');

        $flashHtml = '';
        if ($flashSuccess) {
            $flashHtml .= "<div class='flash-message success'>{$flashSuccess}</div>";
        }
        if ($flashError) {
            $flashHtml .= "<div class='flash-message error'>{$flashError}</div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
            <link rel='stylesheet' href='/assets/css/admin.css'>
        </head>
        <body class='admin-body'>
            <div class='admin-container'>
                {$flashHtml}
                {$content}
            </div>
        </body>
        </html>";
    }
}