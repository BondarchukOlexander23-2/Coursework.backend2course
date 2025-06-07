<?php

require_once __DIR__ . '/../../Views/Admin/SurveysView.php';
require_once __DIR__ . '/../../Views/Admin/EditSurveyView.php';
require_once __DIR__ . '/../../Views/Admin/StatsView.php';

/**
 * Контролер для управління опитуваннями в адмін-панелі
 */
class AdminSurveyController extends BaseController
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
     * Управління опитуваннями
     */
    public function surveys(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $page = $this->getIntParam('page', 1);
            $search = $this->getStringParam('search');
            $status = $this->getStringParam('status', 'all');
            $categoryId = $this->getIntParam('category');

            $surveys = $this->adminService->getSurveysWithCategories($page, $search, $status, $categoryId);
            $totalSurveys = $this->adminService->getTotalSurveysCount($search, $status, $categoryId);
            $totalPages = ceil($totalSurveys / 20);
            $categories = Category::getAll();

            $view = new SurveysView([
                'title' => 'Управління опитуваннями',
                'surveys' => $surveys,
                'categories' => $categories,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'search' => $search,
                'status' => $status,
                'category' => $categoryId
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Редагування опитування
     */
    public function editSurvey(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $surveyId = $this->getIntParam('id');

            if ($surveyId <= 0) {
                throw new NotFoundException('Невірний ID опитування');
            }

            $survey = Survey::findById($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $questions = Question::getBySurveyId($surveyId, true);
            $questionService = new QuestionService();
            $questionService->loadQuestionsWithOptions($questions);

            $view = new EditSurveyView([
                'title' => 'Редагування опитування',
                'survey' => $survey,
                'questions' => $questions
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Оновлення опитування з адмін-панелі
     */
    public function updateSurvey(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $surveyId = (int)$this->postParam('survey_id', 0);
            $title = $this->postParam('title', '');
            $description = $this->postParam('description', '');

            if ($surveyId <= 0) {
                throw new ValidationException(['Невірний ID опитування']);
            }

            $survey = Survey::findById($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $errors = [];
            if (empty(trim($title))) {
                $errors[] = 'Назва опитування є обов\'язковою';
            }
            if (strlen($title) > 255) {
                $errors[] = 'Назва занадто довга';
            }
            if (strlen($description) > 1000) {
                $errors[] = 'Опис занадто довгий';
            }

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
            }

            try {
                Survey::update($surveyId, $title, $description);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Опитування оновлено');
                } else {
                    $this->redirectWithMessage('/admin/surveys', 'success', 'Опитування успішно оновлено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при оновленні');
            }
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
     * Експорт статистики
     */
    public function exportStats(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $surveyId = $this->getIntParam('survey_id', 0);
            $format = $this->getStringParam('type', 'csv');

            $errors = $this->validator->validateExportParams($surveyId, $format);

            if (!empty($errors)) {
                $this->redirectWithMessage('/admin/surveys', 'error', implode('<br>', $errors));
                return;
            }

            try {
                $this->adminService->exportSurveyStats($surveyId, $format);
            } catch (Exception $e) {
                $this->redirectWithMessage('/admin/surveys', 'error', 'Помилка експорту: ' . $e->getMessage());
            }
        });
    }

    /**
     * Перевірка прав адміністратора
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin()) {
            throw new ForbiddenException('Доступ заборонено. Тільки для адміністраторів.');
        }
    }
}