<?php

/**
 * Простий робочий SurveyController з View компонентами
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

            if (!$survey['is_active']) {
                $this->redirectWithMessage(
                    "/surveys",
                    'error',
                    'Це опитування неактивне'
                );
                return;
            }

            $questions = Question::getBySurveyId($surveyId);
            if (empty($questions)) {
                $this->redirectWithMessage(
                    "/surveys",
                    'error',
                    'Опитування не містить питань'
                );
                return;
            }

            $this->questionService->loadQuestionsWithOptions($questions);

            // Перевіряємо чи користувач вже відповідав (з урахуванням дозволів)
            if (Session::isLoggedIn()) {
                $userId = Session::getUserId();

                // Перевіряємо чи є дозвіл на повторне проходження
                $hasRetakePermission = Database::selectOne(
                    "SELECT COUNT(*) as count FROM survey_retakes 
                 WHERE survey_id = ? AND user_id = ? AND used_at IS NULL",
                    [$surveyId, $userId]
                );

                // Якщо немає дозволу на повторне проходження, перевіряємо чи вже проходив
                if (($hasRetakePermission['count'] ?? 0) === 0) {
                    if (SurveyResponse::hasUserResponded($surveyId, $userId)) {
                        $this->redirectWithMessage(
                            "/surveys/results?id={$surveyId}",
                            'info',
                            'Ви вже проходили це опитування'
                        );
                        return;
                    }
                } else {
                    // Якщо є дозвіл, показуємо повідомлення
                    Session::setFlashMessage('info', 'Ви можете пройти це опитування ще раз');
                }
            }

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

            // ВИПРАВЛЕННЯ: Використовуємо postParam() замість getIntParam()
            $surveyId = (int)$this->postParam('survey_id', 0);

            // Додаткове логування для діагностики
            error_log("DEBUG: Survey ID from POST: " . $surveyId);
            error_log("DEBUG: All POST data: " . json_encode($_POST));

            $survey = $this->validator->validateAndGetSurvey($surveyId);

            if (!$survey || !Survey::isAuthor($surveyId, Session::getUserId())) {
                error_log("DEBUG: Survey found: " . ($survey ? 'yes' : 'no'));
                error_log("DEBUG: User ID: " . Session::getUserId());
                error_log("DEBUG: Survey author ID: " . ($survey['user_id'] ?? 'N/A'));

                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            $questionData = [
                'text' => $this->postParam('question_text', ''),
                'type' => $this->postParam('question_type', ''),
                'required' => (bool)$this->postParam('is_required'),
                'points' => $this->getIntParam('points', 1), // Це OK, бо points може бути в GET або POST
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

            // ВИПРАВЛЕННЯ: Використовуємо postParam() для POST запитів
            $questionId = (int)$this->postParam('question_id', 0);
            $surveyId = (int)$this->postParam('survey_id', 0);

            // Додаткове логування для діагностики
            error_log("DEBUG deleteQuestion: Question ID from POST: " . $questionId);
            error_log("DEBUG deleteQuestion: Survey ID from POST: " . $surveyId);
            error_log("DEBUG deleteQuestion: All POST data: " . json_encode($_POST));

            if ($questionId <= 0) {
                error_log("DEBUG deleteQuestion: Invalid question ID: " . $questionId);
                throw new ValidationException(['Невірний ID питання']);
            }

            if ($surveyId <= 0) {
                error_log("DEBUG deleteQuestion: Invalid survey ID: " . $surveyId);
                throw new ValidationException(['Невірний ID опитування']);
            }

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                error_log("DEBUG deleteQuestion: Survey not found: " . $surveyId);
                throw new NotFoundException('Опитування не знайдено');
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                error_log("DEBUG deleteQuestion: User " . Session::getUserId() . " is not author of survey " . $surveyId);
                error_log("DEBUG deleteQuestion: Survey author ID: " . ($survey['user_id'] ?? 'N/A'));
                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            // Перевіряємо чи існує питання і чи воно належить цьому опитуванню
            $question = Question::findById($questionId);
            if (!$question) {
                error_log("DEBUG deleteQuestion: Question not found: " . $questionId);
                throw new NotFoundException('Питання не знайдено');
            }

            if ($question['survey_id'] != $surveyId) {
                error_log("DEBUG deleteQuestion: Question {$questionId} does not belong to survey {$surveyId}");
                throw new ForbiddenException('Це питання не належить даному опитуванню');
            }

            try {
                $this->questionService->deleteQuestion($questionId);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Питання успішно видалено');
                } else {
                    $this->redirectWithMessage(
                        "/surveys/edit?id={$surveyId}",
                        'success',
                        'Питання успішно видалено'
                    );
                }

            } catch (Exception $e) {
                error_log("DEBUG deleteQuestion: Exception during deletion: " . $e->getMessage());
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