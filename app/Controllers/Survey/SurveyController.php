<?php

/**
 * Простий робочий SurveyController з View компонентами
 * Замініть ваш існуючий файл app/Controllers/Survey/SurveyController.php
 */
class SurveyController extends BaseController
{
    private $validator;
    private $questionService;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new SurveyValidator();
        $this->questionService = new QuestionService();
    }

    /**
     * Показати список активних опитувань
     */
    public function index(): void
    {
        $this->safeExecute(function() {
            $surveys = Survey::getAllActive();

            // Завантажуємо View якщо потрібно
            if (!class_exists('SurveyListView')) {
                require_once __DIR__ . '/../../Views/Survey/SurveyListView.php';
            }

            $view = new SurveyListView([
                'title' => 'Доступні опитування',
                'surveys' => $surveys
            ]);

            $content = $view->render();

            $this->responseManager
                ->setCacheHeaders(1800)
                ->sendSuccess($content);
        });
    }

    /**
     * Показати форму створення опитування
     */
    public function create(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            if (!class_exists('SurveyCreateView')) {
                require_once __DIR__ . '/../../Views/Survey/SurveyCreateView.php';
            }

            $view = new SurveyCreateView([
                'title' => 'Створити нове опитування'
            ]);

            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Зберегти нове опитування
     */
    public function store(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $title = $this->postParam('title', '');
            $description = $this->postParam('description', '');

            $errors = $this->validator->validateSurveyData($title, $description);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    if (!class_exists('SurveyCreateView')) {
                        require_once __DIR__ . '/../../Views/Survey/SurveyCreateView.php';
                    }

                    $view = new SurveyCreateView([
                        'title' => 'Створення опитування',
                        'errors' => $errors,
                        'title' => $title,
                        'description' => $description
                    ]);

                    $content = $view->render();

                    $this->responseManager
                        ->setNoCacheHeaders()
                        ->sendClientError(422, $content);
                }
                return;
            }

            try {
                $surveyId = Survey::create($title, $description, Session::getUserId());
                $successMessage = 'Опитування успішно створено! Тепер додайте питання.';
                $redirectUrl = "/surveys/edit?id={$surveyId}";

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, ['survey_id' => $surveyId], $successMessage);
                } else {
                    $this->redirectWithMessage($redirectUrl, 'success', $successMessage);
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при створенні опитування');
            }
        });
    }

    /**
     * Показати опитування для проходження
     */
    public function view(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('id');
            $survey = $this->validator->validateAndGetSurvey($surveyId);

            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            // Перевіряємо чи користувач вже відповідав
            if (Session::isLoggedIn() && SurveyResponse::hasUserResponded($surveyId, Session::getUserId())) {
                $this->redirectWithMessage(
                    "/surveys/results?id={$surveyId}",
                    'info',
                    'Ви вже проходили це опитування'
                );
                return;
            }

            $questions = Question::getBySurveyId($surveyId);
            $this->questionService->loadQuestionsWithOptions($questions);

            if (!class_exists('SurveyViewView')) {
                require_once __DIR__ . '/../../Views/Survey/SurveyViewView.php';
            }

            $view = new SurveyViewView([
                'title' => 'Проходження опитування',
                'survey' => $survey,
                'questions' => $questions
            ]);

            $content = $view->render();

            $this->responseManager
                ->setCacheHeaders(3600)
                ->sendSuccess($content);
        });
    }

    /**
     * Показати форму редагування опитування
     */
    public function edit(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('id');
            $survey = $this->validator->validateAndGetSurvey($surveyId);

            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            $questions = Question::getBySurveyId($surveyId, true);
            $this->questionService->loadQuestionsWithOptions($questions);

            if (!class_exists('SurveyEditView')) {
                require_once __DIR__ . '/../../Views/Survey/SurveyEditView.php';
            }

            $view = new SurveyEditView([
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
     * Показати мої опитування
     */
    public function my(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $userId = Session::getUserId();
            $surveys = Survey::getByUserId($userId);

            if (!class_exists('MySurveysView')) {
                require_once __DIR__ . '/../../Views/Survey/MySurveysView.php';
            }

            $view = new MySurveysView([
                'title' => 'Мої опитування',
                'surveys' => $surveys
            ]);

            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Додати питання до опитування
     */
    public function addQuestion(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('survey_id');
            $survey = $this->validator->validateAndGetSurvey($surveyId);

            if (!$survey || !Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            $questionData = [
                'text' => $this->postParam('question_text', ''),
                'type' => $this->postParam('question_type', ''),
                'required' => (bool)$this->postParam('is_required'),
                'points' => $this->getIntParam('points', 1),
                'correct_answer' => $this->postParam('correct_answer', '') ?: null,
                'options' => $this->postParam('options', []),
                'correct_options' => $this->postParam('correct_options', [])
            ];

            $errors = $this->validator->validateQuestionData(
                $questionData['text'],
                $questionData['type'],
                $questionData['options'],
                $questionData['points']
            );

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
            }

            try {
                $this->questionService->createQuestionWithOptions(
                    $surveyId,
                    $questionData['text'],
                    $questionData['type'],
                    $questionData['required'],
                    $questionData['correct_answer'],
                    $questionData['points'],
                    $questionData['options'],
                    $questionData['correct_options']
                );

                $successMessage = 'Питання успішно додано';
                $redirectUrl = "/surveys/edit?id={$surveyId}";

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, $successMessage);
                } else {
                    $this->redirectWithMessage($redirectUrl, 'success', $successMessage);
                }

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при додаванні питання');
            }
        });
    }

    /**
     * Видалити питання
     */
    public function deleteQuestion(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $questionId = $this->getIntParam('question_id');
            $surveyId = $this->getIntParam('survey_id');

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey || !Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            $errors = $this->validator->validateQuestionDeletion($questionId, $surveyId);
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            try {
                $this->questionService->deleteQuestion($questionId);
                $this->redirectWithMessage(
                    "/surveys/edit?id={$surveyId}",
                    'success',
                    'Питання успішно видалено'
                );

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при видаленні питання');
            }
        });
    }

    /**
     * Експорт результатів опитування
     */
    public function exportResults(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('id');
            $format = $this->getStringParam('format', 'csv');

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey || !Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для експорту цього опитування');
            }

            $errors = $this->validator->validateExportParams($surveyId, $format);
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            try {
                $exportData = Database::select(
                    "SELECT sr.id, sr.created_at, u.name as user_name, u.email,
                            q.question_text, qa.answer_text, qo.option_text, qa.is_correct, qa.points_earned
                     FROM survey_responses sr
                     LEFT JOIN users u ON sr.user_id = u.id
                     LEFT JOIN question_answers qa ON sr.id = qa.response_id
                     LEFT JOIN questions q ON qa.question_id = q.id
                     LEFT JOIN question_options qo ON qa.option_id = qo.id
                     WHERE sr.survey_id = ?
                     ORDER BY sr.id, q.order_number",
                    [$surveyId]
                );

                $filename = "survey_{$surveyId}_results_" . date('Y-m-d_H-i-s') . ".{$format}";

                if ($format === 'csv') {
                    $output = "ID відповіді,Дата,Користувач,Email,Питання,Відповідь,Правильно,Бали\n";

                    foreach ($exportData as $row) {
                        $output .= sprintf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                            $row['id'],
                            $row['created_at'],
                            $row['user_name'] ?: 'Анонім',
                            $row['email'] ?: '',
                            $row['question_text'],
                            $row['answer_text'] ?: $row['option_text'],
                            $row['is_correct'] ? 'Так' : 'Ні',
                            $row['points_earned']
                        );
                    }

                    $this->downloadCsv($output, $filename);
                } else {
                    throw new ValidationException(['Непідтримуваний формат експорту']);
                }

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при експорті');
            }
        });
    }
}