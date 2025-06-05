<?php

require_once __DIR__ . '/../Views/Admin/DashboardView.php';
require_once __DIR__ . '/../Views/Admin/UsersView.php';
require_once __DIR__ . '/../Views/Admin/SurveysView.php';
require_once __DIR__ . '/../Views/Admin/StatsView.php';

/**
 * Оновлений AdminController з можливістю редагування опитувань
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
     * НОВИЙ МЕТОД: Редагування опитування з адмін-панелі
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

            // Завантажуємо питання з варіантами
            $questions = Question::getBySurveyId($surveyId, true);
            $questionService = new QuestionService();
            $questionService->loadQuestionsWithOptions($questions);

            // Створюємо простий HTML для редагування
            $content = $this->renderEditSurveyPage($survey, $questions);

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Оновити опитування з адмін-панелі
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

            // Валідація
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
     * Рендер сторінки редагування опитування
     */
    private function renderEditSurveyPage(array $survey, array $questions): string
    {
        $questionsHtml = '';
        if (!empty($questions)) {
            foreach ($questions as $question) {
                $questionsHtml .= $this->renderQuestionItem($question);
            }
        } else {
            $questionsHtml = '<p class="no-questions">Немає питань в цьому опитуванні</p>';
        }

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Редагування опитування - Адмін</title>
            <link rel='stylesheet' href='/assets/css/admin.css'>
            <style>
                .edit-survey-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
                .survey-header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
                .edit-form { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
                .questions-section { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .question-item { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #007cba; }
                .question-header { display: flex; align-items: center; margin-bottom: 10px; }
                .question-options { margin: 10px 0; padding-left: 20px; }
                .question-options li { margin: 5px 0; }
                .correct-option { font-weight: bold; color: #28a745; }
                .form-group { margin-bottom: 20px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
                .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
                .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
                .btn-primary { background: #007cba; color: white; }
                .btn-success { background: #28a745; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
                .btn-danger { background: #dc3545; color: white; }
                .actions { margin-top: 20px; }
                .no-questions { text-align: center; color: #6c757d; font-style: italic; padding: 40px; }
            </style>
        </head>
        <body>
            <div class='edit-survey-page'>
                <div class='survey-header'>
                    <h1>Редагування опитування</h1>
                    <p><strong>ID:</strong> {$survey['id']} | <strong>Автор:</strong> " . htmlspecialchars($survey['author_name']) . "</p>
                </div>

                <div class='edit-form'>
                    <h2>Основна інформація</h2>
                    <form method='POST' action='/admin/update-survey' id='survey-form'>
                        <input type='hidden' name='survey_id' value='{$survey['id']}'>
                        
                        <div class='form-group'>
                            <label for='title'>Назва опитування:</label>
                            <input type='text' id='title' name='title' class='form-control' 
                                   value='" . htmlspecialchars($survey['title']) . "' required maxlength='255'>
                        </div>
                        
                        <div class='form-group'>
                            <label for='description'>Опис:</label>
                            <textarea id='description' name='description' class='form-control' 
                                      rows='4' maxlength='1000'>" . htmlspecialchars($survey['description']) . "</textarea>
                        </div>
                        
                        <div class='actions'>
                            <button type='submit' class='btn btn-success'>Зберегти зміни</button>
                            <a href='/admin/surveys' class='btn btn-secondary'>Скасувати</a>
                        </div>
                    </form>
                </div>

                <div class='questions-section'>
                    <h2>Питання опитування ({" . count($questions) . "})</h2>
                    {$questionsHtml}
                    
                    <div class='actions'>
                        <a href='/surveys/edit?id={$survey['id']}' class='btn btn-primary'>
                            Повне редагування (додавати/видаляти питання)
                        </a>
                    </div>
                </div>

                <div class='actions'>
                    <a href='/admin/surveys' class='btn btn-secondary'>Назад до списку</a>
                    <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
                    <a href='/admin/survey-stats?id={$survey['id']}' class='btn btn-primary'>Статистика</a>
                </div>
            </div>

            <script>
                document.getElementById('survey-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('/admin/update-survey', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Опитування оновлено!');
                            window.location.href = '/admin/surveys';
                        } else {
                            alert('Помилка: ' + (data.message || 'Щось пішло не так'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.submit(); // Fallback
                    });
                });
            </script>
        </body>
        </html>";
    }

    /**
     * Рендер елемента питання
     */
    private function renderQuestionItem(array $question): string
    {
        $questionText = htmlspecialchars($question['question_text']);
        $questionType = htmlspecialchars($question['question_type']);
        $required = $question['is_required'] ? ' (обов\'язкове)' : '';
        $points = $question['points'] ?? 1;
        $correctAnswer = htmlspecialchars($question['correct_answer'] ?? '');

        $typeNames = [
            'radio' => 'Один варіант',
            'checkbox' => 'Декілька варіантів',
            'text' => 'Короткий текст',
            'textarea' => 'Довгий текст'
        ];

        $typeName = $typeNames[$questionType] ?? $questionType;

        $optionsHtml = '';
        if (isset($question['options']) && !empty($question['options'])) {
            $optionsHtml = '<ul class="question-options">';
            foreach ($question['options'] as $option) {
                $correctMark = $option['is_correct'] ? ' ✓' : '';
                $correctClass = $option['is_correct'] ? ' class="correct-option"' : '';
                $optionsHtml .= '<li' . $correctClass . '>' . htmlspecialchars($option['option_text']) . $correctMark . '</li>';
            }
            $optionsHtml .= '</ul>';
        }

        $correctAnswerHtml = '';
        if (!empty($correctAnswer)) {
            $correctAnswerHtml = '<p><strong>Правильна відповідь:</strong> ' . $correctAnswer . '</p>';
        }

        return "
            <div class='question-item'>
                <div class='question-header'>
                    <h4>{$questionText}{$required} <small>({$points} б.)</small></h4>
                    <span class='question-type'>{$typeName}</span>
                </div>
                {$optionsHtml}
                {$correctAnswerHtml}
            </div>";
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