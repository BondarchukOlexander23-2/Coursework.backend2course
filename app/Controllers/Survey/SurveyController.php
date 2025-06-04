<?php

/**
 * Рефакторений SurveyController згідно з принципами SOLID
 *
 * SOLID принципи:
 * - SRP: Контролер відповідає тільки за обробку HTTP запитів та координацію
 * - OCP: Легко розширюється новими методами без зміни існуючих
 * - LSP: Може заміщати BaseController
 * - ISP: Використовує спеціалізовані інтерфейси
 * - DIP: Залежить від абстракцій, а не від конкретних класів
 */
class SurveyController extends BaseController
{
    private SurveyValidator $validator;
    private QuestionService $questionService;
    private SurveyViewFactory $viewFactory;

    public function __construct(
        SurveyValidator $validator = null,
        QuestionService $questionService = null,
        SurveyViewFactory $viewFactory = null
    ) {
        parent::__construct();

        // Dependency Injection з fallback до створення екземплярів
        $this->validator = $validator ?? new SurveyValidator();
        $this->questionService = $questionService ?? new QuestionService();
        $this->viewFactory = $viewFactory ?? new SurveyViewFactory();
    }

    /**
     * Показати список активних опитувань
     */
    public function index(): void
    {
        $this->safeExecute(function() {
            $surveys = Survey::getAllActive();

            $view = $this->viewFactory->createListView([
                'title' => 'Доступні опитування',
                'surveys' => $surveys
            ]);

            $content = $view->render();

            // Кешуємо список на 30 хвилин
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

            $view = $this->viewFactory->createCreateView([
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

            $data = $this->extractSurveyData();
            $errors = $this->validator->validateSurveyData($data['title'], $data['description']);

            if (!empty($errors)) {
                $this->handleValidationErrors($errors, $data);
                return;
            }

            try {
                $surveyId = Survey::create($data['title'], $data['description'], Session::getUserId());
                $this->handleSuccessfulCreation($surveyId);

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при створенні опитування');
            }
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
            $survey = $this->validateSurveyAccess($surveyId);

            $questions = Question::getBySurveyId($surveyId, true);
            $this->questionService->loadQuestionsWithOptions($questions);

            $view = $this->viewFactory->createEditView([
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

            $this->checkUserResponseStatus($surveyId);

            $questions = Question::getBySurveyId($surveyId);
            $this->questionService->loadQuestionsWithOptions($questions);

            $view = $this->viewFactory->createViewView([
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
     * Показати мої опитування
     */
    public function my(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $userId = Session::getUserId();
            $surveys = Survey::getByUserId($userId);

            $view = $this->viewFactory->createMyView([
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
            $this->validateSurveyAccess($surveyId);

            $questionData = $this->extractQuestionData();
            $errors = $this->validator->validateQuestionData(
                $questionData['text'],
                $questionData['type'],
                $questionData['options'],
                $questionData['points']
            );

            if (!empty($errors)) {
                $this->handleQuestionValidationErrors($errors, $surveyId);
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

                $this->handleSuccessfulQuestionCreation($surveyId);

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

            $this->validateSurveyAccess($surveyId);

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

            $survey = $this->validateSurveyAccess($surveyId);

            $errors = $this->validator->validateExportParams($surveyId, $format);
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            try {
                $exportData = $this->generateExportData($surveyId);
                $filename = "survey_{$surveyId}_results_" . date('Y-m-d_H-i-s') . ".{$format}";

                if ($format === 'csv') {
                    $csvContent = $this->generateCsvContent($exportData);
                    $this->downloadCsv($csvContent, $filename);
                } else {
                    throw new ValidationException(['Непідтримуваний формат експорту']);
                }

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при експорті');
            }
        });
    }

    // === ПРИВАТНІ МЕТОДИ (Business Logic) ===

    /**
     * Витягти дані опитування з запиту
     */
    private function extractSurveyData(): array
    {
        return [
            'title' => $this->postParam('title', ''),
            'description' => $this->postParam('description', '')
        ];
    }

    /**
     * Витягти дані питання з запиту
     */
    private function extractQuestionData(): array
    {
        return [
            'text' => $this->postParam('question_text', ''),
            'type' => $this->postParam('question_type', ''),
            'required' => (bool)$this->postParam('is_required'),
            'points' => $this->getIntParam('points', 1),
            'correct_answer' => $this->postParam('correct_answer', '') ?: null,
            'options' => $this->postParam('options', []),
            'correct_options' => $this->postParam('correct_options', [])
        ];
    }

    /**
     * Валідувати доступ до опитування
     */
    private function validateSurveyAccess(int $surveyId): array
    {
        $survey = $this->validator->validateAndGetSurvey($surveyId);

        if (!$survey) {
            throw new NotFoundException('Опитування не знайдено');
        }

        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            throw new ForbiddenException('У вас немає прав для редагування цього опитування');
        }

        return $survey;
    }

    /**
     * Перевірити статус відповіді користувача
     */
    private function checkUserResponseStatus(int $surveyId): void
    {
        if (Session::isLoggedIn() && SurveyResponse::hasUserResponded($surveyId, Session::getUserId())) {
            $this->redirectWithMessage(
                "/surveys/results?id={$surveyId}",
                'info',
                'Ви вже проходили це опитування'
            );
        }
    }

    /**
     * Обробити помилки валідації опитування
     */
    private function handleValidationErrors(array $errors, array $data): void
    {
        if ($this->isAjaxRequest()) {
            $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
        } else {
            $view = $this->viewFactory->createCreateView([
                'title' => 'Створення опитування',
                'errors' => $errors,
                'title' => $data['title'],
                'description' => $data['description']
            ]);

            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendClientError(ResponseManager::STATUS_UNPROCESSABLE_ENTITY, $content);
        }
    }

    /**
     * Обробити успішне створення опитування
     */
    private function handleSuccessfulCreation(int $surveyId): void
    {
        $successMessage = 'Опитування успішно створено! Тепер додайте питання.';
        $redirectUrl = "/surveys/edit?id={$surveyId}";

        if ($this->isAjaxRequest()) {
            $this->sendAjaxResponse(true, ['survey_id' => $surveyId], $successMessage);
        } else {
            $this->redirectWithMessage($redirectUrl, 'success', $successMessage);
        }
    }

    /**
     * Обробити помилки валідації питання
     */
    private function handleQuestionValidationErrors(array $errors, int $surveyId): void
    {
        if ($this->isAjaxRequest()) {
            $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
        } else {
            throw new ValidationException($errors);
        }
    }

    /**
     * Обробити успішне створення питання
     */
    private function handleSuccessfulQuestionCreation(int $surveyId): void
    {
        $successMessage = 'Питання успішно додано';
        $redirectUrl = "/surveys/edit?id={$surveyId}";

        if ($this->isAjaxRequest()) {
            $this->sendAjaxResponse(true, null, $successMessage);
        } else {
            $this->redirectWithMessage($redirectUrl, 'success', $successMessage);
        }
    }

    /**
     * Генерація даних для експорту
     */
    private function generateExportData(int $surveyId): array
    {
        return Database::select(
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
    }

    /**
     * Генерація CSV контенту
     */
    private function generateCsvContent(array $data): string
    {
        $output = "ID відповіді,Дата,Користувач,Email,Питання,Відповідь,Правильно,Бали\n";

        foreach ($data as $row) {
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

        return $output;
    }
}

/**
 * Фабрика для створення Survey Views
 * Демонструє Factory Pattern та Dependency Inversion
 */
class SurveyViewFactory
{
    /**
     * Створити View для списку опитувань
     */
    public function createListView(array $data): ViewInterface
    {
        require_once __DIR__ . '/../Views/Survey/SurveyListView.php';
        return new SurveyListView($data);
    }

    /**
     * Створити View для створення опитування
     */
    public function createCreateView(array $data): ViewInterface
    {
        require_once __DIR__ . '/../Views/Survey/SurveyCreateView.php';
        return new SurveyCreateView($data);
    }

    /**
     * Створити View для редагування опитування
     */
    public function createEditView(array $data): ViewInterface
    {
        require_once __DIR__ . '/../Views/Survey/SurveyEditView.php';
        return new SurveyEditView($data);
    }

    /**
     * Створити View для проходження опитування
     */
    public function createViewView(array $data): ViewInterface
    {
        require_once __DIR__ . '/../Views/Survey/SurveyViewView.php';
        return new SurveyViewView($data);
    }

    /**
     * Створити View для моїх опитувань
     */
    public function createMyView(array $data): ViewInterface
    {
        require_once __DIR__ . '/../Views/Survey/MySurveysView.php';
        return new MySurveysView($data);
    }
}

/**
 * Інтерфейс для Survey Views
 */
interface ViewInterface
{
    public function setData(array $data): self;
    public function render(): string;
}