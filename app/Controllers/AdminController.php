<?php

/**
 * Оновлений контролер адміністрування з BaseController
 */
class AdminController extends BaseController
{
    private AdminValidator $validator;
    private AdminService $adminService;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new AdminValidator();
        $this->adminService = new AdminService();
    }

    /**
     * Головна сторінка адмін-панелі
     */
    public function dashboard(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $stats = $this->adminService->getDashboardStats();
            $content = $this->renderDashboard($stats);

            // Дашборд не кешуємо - дані динамічні
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }


    /**
     * Управління опитуваннями
     */
    public function surveys(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $page = $this->getIntParam('page', 1);
            $search = $this->getStringParam('search');
            $status = $this->getStringParam('status', 'all');

            $surveys = $this->adminService->getSurveys($page, $search, $status);
            $totalSurveys = $this->adminService->getTotalSurveysCount($search, $status);
            $totalPages = ceil($totalSurveys / 20);

            $content = $this->renderSurveys($surveys, $page, $totalPages, $search, $status);

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }
    /**
     * Видалити опитування
     */
    public function deleteSurvey(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            // Правильно отримуємо POST параметр
            $surveyId = (int)($this->postParam('survey_id', 0));
            $errors = $this->validator->validateSurveyDeletion($surveyId);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилка видалення');
                } else {
                    $this->redirectWithMessage('/admin/surveys', 'error', implode('<br>', $errors));
                }
                return;
            }

            try {
                $this->adminService->deleteSurvey($surveyId);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Опитування успішно видалено');
                } else {
                    $this->redirectWithMessage('/admin/surveys', 'success', 'Опитування успішно видалено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при видаленні опитування');
            }
        });
    }

    /**
     * Перемикання статусу опитування
     */
    public function toggleSurveyStatus(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            // Правильно отримуємо POST параметр
            $surveyId = (int)($this->postParam('survey_id', 0));

            if ($surveyId <= 0) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, ['Невірний ID опитування'], 'Помилка');
                } else {
                    $this->redirectWithMessage('/admin/surveys', 'error', 'Невірний ID опитування');
                }
                return;
            }

            try {
                $this->adminService->toggleSurveyStatus($surveyId);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Статус опитування змінено');
                } else {
                    $this->redirectWithMessage('/admin/surveys', 'success', 'Статус опитування змінено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при зміні статусу');
            }
        });
    }

    protected function postIntParam(string $key, int $default = 0): int
    {
        $value = $this->postParam($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Детальна статистика опитування
     */
    public function surveyStats(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $surveyId = $this->getIntParam('id');
            $survey = Survey::findById($surveyId);

            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $stats = $this->adminService->getSurveyDetailedStats($surveyId);
            $content = $this->renderSurveyStats($survey, $stats);

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Рендер опитувань (метод який я пропустив)
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
                    
                    <!-- Форма зміни статусу -->
                    <form method='POST' action='/admin/toggle-survey-status' style='display: inline;' 
                          onsubmit='return handleFormSubmit(this)'>
                        <input type='hidden' name='survey_id' value='{$survey['id']}'>
                        <button type='submit' class='btn btn-sm btn-secondary'>
                            " . ($survey['is_active'] ? 'Деактивувати' : 'Активувати') . "
                        </button>
                    </form>
                    
                    <!-- Форма видалення -->
                    <form method='POST' action='/admin/delete-survey' style='display: inline;' 
                          onsubmit='return handleDeleteSubmit(this)'>
                        <input type='hidden' name='survey_id' value='{$survey['id']}'>
                        <button type='submit' class='btn btn-danger btn-sm'>Видалити</button>
                    </form>
                </td>
            </tr>";
        }

        $pagination = $this->renderPagination('/admin/surveys', $currentPage, $totalPages, ['search' => $search, 'status' => $status]);

        return $this->buildAdminPage("Управління опитуваннями", "
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
                <tbody>{$surveysHtml}</tbody>
            </table>
        </div>
        
        {$pagination}
        
        <script>
            function handleFormSubmit(form) {
                return submitFormAjax(form, false);
            }
            
            function handleDeleteSubmit(form) {
                if (!confirm('Видалити це опитування? Ця дія незворотна!')) {
                    return false;
                }
                return submitFormAjax(form, true);
            }
            
            function submitFormAjax(form, isDelete) {
                const formData = new FormData(form);
                
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Показуємо повідомлення
                        showMessage(data.message, 'success');
                        
                        // Перезавантажуємо сторінку через секунду
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message || 'Виникла помилка', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Виникла помилка при виконанні операції', 'error');
                    
                    // Fallback - звичайна submit форми
                    form.submit();
                });
                
                return false; // Запобігаємо звичайній submit
            }
            
            function showMessage(message, type) {
                // Створюємо елемент повідомлення
                const messageDiv = document.createElement('div');
                messageDiv.className = 'flash-message ' + type;
                messageDiv.style.position = 'fixed';
                messageDiv.style.top = '20px';
                messageDiv.style.right = '20px';
                messageDiv.style.zIndex = '9999';
                messageDiv.style.padding = '1rem';
                messageDiv.style.borderRadius = '8px';
                messageDiv.style.maxWidth = '400px';
                messageDiv.textContent = message;
                
                if (type === 'success') {
                    messageDiv.style.background = '#d4edda';
                    messageDiv.style.color = '#155724';
                    messageDiv.style.border = '1px solid #c3e6cb';
                } else {
                    messageDiv.style.background = '#f8d7da';
                    messageDiv.style.color = '#721c24';
                    messageDiv.style.border = '1px solid #f5c6cb';
                }
                
                document.body.appendChild(messageDiv);
                
                // Видаляємо через 3 секунди
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 3000);
            }
        </script>
    ");
    }

    /**
     * Рендер статистики опитування (метод який я пропустив)
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

        return $this->buildAdminPage("Статистика опитування", "
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
     * Управління користувачами
     */
    public function users(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $page = $this->getIntParam('page', 1);
            $search = $this->getStringParam('search');

            $users = $this->adminService->getUsers($page, $search);
            $totalUsers = $this->adminService->getTotalUsersCount($search);
            $totalPages = ceil($totalUsers / 20);

            $content = $this->renderUsers($users, $page, $totalPages, $search);

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Видалення користувача
     */
    public function deleteUser(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            // Правильно отримуємо POST параметр
            $userId = (int)($this->postParam('user_id', 0));
            $errors = $this->validator->validateUserDeletion($userId);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилка видалення');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', implode('<br>', $errors));
                }
                return;
            }

            try {
                $this->adminService->deleteUser($userId);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Користувача успішно видалено');
                } else {
                    $this->redirectWithMessage('/admin/users', 'success', 'Користувача успішно видалено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при видаленні користувача');
            }
        });
    }

    /**
     * Зміна ролі користувача
     */
    public function changeUserRole(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            // Правильно отримуємо POST параметри
            $userId = (int)($this->postParam('user_id', 0));
            $newRole = $this->postParam('role', '');

            $errors = $this->validator->validateRoleChange($userId, $newRole);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилка зміни ролі');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', implode('<br>', $errors));
                }
                return;
            }

            try {
                $this->adminService->changeUserRole($userId, $newRole);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Роль користувача успішно змінено');
                } else {
                    $this->redirectWithMessage('/admin/users', 'success', 'Роль користувача успішно змінено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при зміні ролі');
            }
        });
    }

    /**
     * Експорт статистики
     */
    public function exportStats(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $type = $this->getStringParam('type', 'csv');
            $surveyId = $this->getIntParam('survey_id');

            $errors = $this->validator->validateExportParams($surveyId, $type);
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            try {
                // Генеруємо дані для експорту
                $stats = $this->adminService->getSurveyDetailedStats($surveyId);
                $survey = Survey::findById($surveyId);

                if (!$survey) {
                    throw new NotFoundException('Опитування не знайдено');
                }

                $filename = "survey_{$surveyId}_stats_" . date('Y-m-d_H-i-s') . ".{$type}";
                $content = $this->generateExportContent($stats, $type);

                if ($type === 'csv') {
                    $this->downloadCsv($content, $filename);
                } else {
                    $this->downloadFile($content, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                }

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при експорті');
            }
        });
    }

    // === ПРИВАТНІ МЕТОДИ ===

    /**
     * Перевірка прав адміністратора (перевизначаємо для спеціального повідомлення)
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin()) {
            throw new ForbiddenException('Доступ заборонено. Тільки для адміністраторів.');
        }
    }

    /**
     * Рендер дашборду (скорочена версія - основна логіка залишається та сама)
     */
    private function renderDashboard(array $stats): string
    {
        return $this->buildAdminPage("Адмін-панель", "
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

    private function renderUsers(array $users, int $currentPage, int $totalPages, string $search): string
    {
        $usersHtml = '';
        foreach ($users as $user) {
            $roleClass = $user['role'] === 'admin' ? 'role-admin' : 'role-user';
            $roleText = $user['role'] === 'admin' ? 'Адмін' : 'Користувач';

            $usersHtml .= "<tr>
            <td>{$user['id']}</td>
            <td>" . htmlspecialchars($user['name']) . "</td>
            <td>" . htmlspecialchars($user['email']) . "</td>
            <td><span class='role-badge {$roleClass}'>{$roleText}</span></td>
            <td>{$user['surveys_count']}</td>
            <td>{$user['responses_count']}</td>
            <td>{$user['created_at']}</td>
            <td class='actions'>
                " . ($user['role'] !== 'admin' ? "
                <form method='POST' action='/admin/change-user-role' style='display: inline;' 
                      onsubmit='return handleFormSubmit(this)'>
                    <input type='hidden' name='user_id' value='{$user['id']}'>
                    <select name='role' onchange='this.form.submit()'>
                        <option value='user'" . ($user['role'] === 'user' ? ' selected' : '') . ">Користувач</option>
                        <option value='admin'" . ($user['role'] === 'admin' ? ' selected' : '') . ">Адмін</option>
                    </select>
                </form>
                <form method='POST' action='/admin/delete-user' style='display: inline;' 
                      onsubmit='return handleDeleteSubmit(this)'>
                    <input type='hidden' name='user_id' value='{$user['id']}'>
                    <button type='submit' class='btn btn-danger btn-sm'>Видалити</button>
                </form>" : "<em>Системний адмін</em>") . "
            </td>
        </tr>";
        }

        $pagination = $this->renderPagination('/admin/users', $currentPage, $totalPages, ['search' => $search]);

        return $this->buildAdminPage("Управління користувачами", "
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
                <tbody>{$usersHtml}</tbody>
            </table>
        </div>
        
        {$pagination}
        
        <script>
            function handleFormSubmit(form) {
                return submitFormAjax(form, false);
            }
            
            function handleDeleteSubmit(form) {
                if (!confirm('Видалити цього користувача? Ця дія незворотна!')) {
                    return false;
                }
                return submitFormAjax(form, true);
            }
            
            // Тут той самий JavaScript код як вище
        </script>
    ");
    }

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
     * Побудувати адмін сторінку
     */
    private function buildAdminPage(string $title, string $content): string
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

    private function generateExportContent(array $stats, string $type): string
    {
        // Базова реалізація для CSV
        $content = "Показник,Значення\n";
        $content .= "Загальна кількість відповідей,{$stats['general']['total_responses']}\n";
        $content .= "Загальна кількість питань,{$stats['general']['total_questions']}\n";
        $content .= "Унікальних користувачів,{$stats['general']['unique_users']}\n";

        return $content;
    }
}