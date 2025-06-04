<?php

require_once __DIR__ . '/../Views/Admin/DashboardView.php';
require_once __DIR__ . '/../Views/Admin/UsersView.php';
require_once __DIR__ . '/../Views/Admin/SurveysView.php';
require_once __DIR__ . '/../Views/Admin/StatsView.php';

/**
 * Оновлений AdminController з використанням Views
 * Демонструє принцип Dependency Inversion
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

            $view = new DashboardView([
                'title' => 'Адмін-панель',
                'stats' => $stats
            ]);
            $content = $view->render();

            // Дашборд не кешуємо - дані динамічні
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
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

            $view = new UsersView([
                'title' => 'Управління користувачами',
                'users' => $users,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'search' => $search
            ]);
            $content = $view->render();

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

            $view = new SurveysView([
                'title' => 'Управління опитуваннями',
                'surveys' => $surveys,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'search' => $search,
                'status' => $status
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
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

            $view = new StatsView([
                'title' => 'Статистика опитування',
                'survey' => $survey,
                'stats' => $stats
            ]);
            $content = $view->render();

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
}