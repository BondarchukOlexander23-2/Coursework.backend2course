<?php

/**
 * Оновлений SurveyController з використанням ResponseManager та BaseController
 * Демонструє використання буферизації залежно від статус-кодів
 */
class SurveyController extends BaseController
{
    private SurveyValidator $validator;
    private QuestionService $questionService;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new SurveyValidator();
        $this->questionService = new QuestionService();
    }

    /**
     * Показати список опитувань з кешуванням
     */
    public function index(): void
    {
        $this->safeExecute(function() {
            $surveys = Survey::getAllActive();
            $content = $this->renderSurveysList($surveys);

            // Кешуємо список опитувань на 30 хвилин
            $this->responseManager
                ->setCacheHeaders(1800) // 30 хвилин
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

            $content = $this->renderCreateForm();

            // Для форм вимикаємо кешування
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Зберегти нове опитування з валідацією
     */
    public function store(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $title = $this->postParam('title', '');
            $description = $this->postParam('description', '');
            $userId = Session::getUserId();

            // Валідуємо дані
            $errors = $this->validator->validateSurveyData($title, $description);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
            }

            try {
                $surveyId = Survey::create($title, $description, $userId);

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

            $content = $this->renderEditForm($survey, $questions);

            // Редагування не кешуємо
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

            // Перевіряємо чи користувач вже проходив опитування
            if (Session::isLoggedIn() && SurveyResponse::hasUserResponded($surveyId, Session::getUserId())) {
                $this->redirectWithMessage(
                    "/surveys/results?id={$surveyId}",
                    'error',
                    'Ви вже проходили це опитування'
                );
                return;
            }

            $questions = Question::getBySurveyId($surveyId);
            $this->questionService->loadQuestionsWithOptions($questions);

            $content = $this->renderSurveyView($survey, $questions);

            // Кешуємо опитування на 1 годину
            $this->responseManager
                ->setCacheHeaders(3600)
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
            $questionText = $this->postParam('question_text', '');
            $questionType = $this->postParam('question_type', '');
            $isRequired = (bool)$this->postParam('is_required');
            $points = $this->getIntParam('points', 1);
            $correctAnswer = $this->postParam('correct_answer', '') ?: null;
            $options = $this->postParam('options', []);
            $correctOptions = $this->postParam('correct_options', []);

            // Валідація
            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для редагування цього опитування');
            }

            $errors = $this->validator->validateQuestionData($questionText, $questionType, $options, $points);

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
                    $questionText,
                    $questionType,
                    $isRequired,
                    $correctAnswer,
                    $points,
                    $options,
                    $correctOptions
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
     * Експорт результатів опитування
     */
    public function exportResults(): void
    {
        $this->safeExecute(function() {
            $this->requireAuth();

            $surveyId = $this->getIntParam('id');
            $format = $this->getStringParam('format', 'csv');

            $survey = Survey::findById($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            if (!Survey::isAuthor($surveyId, Session::getUserId())) {
                throw new ForbiddenException('У вас немає прав для експорту цього опитування');
            }

            // Генеруємо дані для експорту
            $exportData = $this->generateExportData($surveyId);
            $filename = "survey_{$surveyId}_results_" . date('Y-m-d_H-i-s') . ".{$format}";

            if ($format === 'csv') {
                $csvContent = $this->generateCsvContent($exportData);
                $this->downloadCsv($csvContent, $filename);
            } else {
                throw new ValidationException(['Непідтримуваний формат експорту']);
            }
        });
    }

    /**
     * Обробка помилок з правильними статус кодами
     */
    public function handleError(): void
    {
        $errorType = $this->getStringParam('type', 'general');

        switch ($errorType) {
            case 'not_found':
                throw new NotFoundException('Сторінка не знайдена');

            case 'forbidden':
                throw new ForbiddenException('Доступ заборонено');

            case 'validation':
                throw new ValidationException(['Приклад помилки валідації']);

            case 'server':
                throw new Exception('Приклад серверної помилки');

            default:
                throw new BusinessLogicException('Невідомий тип помилки');
        }
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
            $content = $this->renderMySurveys($surveys);

            // Особисті опитування не кешуємо - динамічні дані
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Відобразити мої опитування
     */
    private function renderMySurveys(array $surveys): string
    {
        $surveyItems = '';

        if (empty($surveys)) {
            $surveyItems = '
            <div class="no-surveys">
                <div class="no-surveys-icon">📋</div>
                <h3>У вас ще немає створених опитувань</h3>
                <p>Створіть своє перше опитування та почніть збирати відгуки!</p>
                <a href="/surveys/create" class="btn btn-success btn-large">Створити перше опитування</a>
            </div>';
        } else {
            foreach ($surveys as $survey) {
                $status = $survey['is_active'] ? 'Активне' : 'Неактивне';
                $statusClass = $survey['is_active'] ? 'status-active' : 'status-inactive';
                $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);
                $questionCount = count(Question::getBySurveyId($survey['id']));

                // Визначаємо тип опитування
                $isQuiz = Question::isQuiz($survey['id']);
                $surveyType = $isQuiz ? 'Квіз' : 'Опитування';
                $surveyTypeClass = $isQuiz ? 'quiz-badge' : 'survey-badge';

                $surveyItems .= "
                <div class='survey-item my-survey-item'>
                    <div class='survey-header'>
                        <h3>" . htmlspecialchars($survey['title']) . "</h3>
                        <div class='survey-badges'>
                            <span class='type-badge {$surveyTypeClass}'>{$surveyType}</span>
                            <span class='status-badge {$statusClass}'>{$status}</span>
                        </div>
                    </div>
                    
                    <p class='survey-description'>" . htmlspecialchars($survey['description']) . "</p>
                    
                    <div class='survey-stats'>
                        <div class='stat-item'>
                            <span class='stat-number'>{$questionCount}</span>
                            <span class='stat-label'>Питань</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-number'>{$responseCount}</span>
                            <span class='stat-label'>Відповідей</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-number'>" . date('d.m.Y', strtotime($survey['created_at'])) . "</span>
                            <span class='stat-label'>Створено</span>
                        </div>
                    </div>
                    
                    <div class='survey-actions'>
                        <a href='/surveys/edit?id={$survey['id']}' class='btn btn-primary'>
                            <span class='btn-icon'>✏️</span> Редагувати
                        </a>
                        <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>
                            <span class='btn-icon'>👁️</span> Переглянути
                        </a>
                        <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>
                            <span class='btn-icon'>📊</span> Результати
                        </a>
                        " . ($responseCount > 0 ? "
                        <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-outline'>
                            <span class='btn-icon'>📥</span> Експорт
                        </a>" : "") . "
                    </div>
                </div>";
            }
        }
        return $this->buildPageContent("Мої опитування", "
        <div class='header-actions'>
            <h1>Мої опитування</h1>
            " . $this->renderUserNav() . "
        </div>
        
        <div class='my-surveys-container'>
            {$surveyItems}
        </div>
        
        <div class='page-actions'>
            <a href='/surveys/create' class='btn btn-success'>
                <span class='btn-icon'>➕</span> Створити нове
            </a>
            <a href='/surveys' class='btn btn-secondary'>
                <span class='btn-icon'>📋</span> Всі опитування
            </a>
        </div>
        
        <style>
            .no-surveys {
                text-align: center;
                padding: 4rem 2rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 15px;
                margin: 2rem 0;
            }
            .no-surveys-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
                opacity: 0.7;
            }
            .no-surveys h3 {
                color: #495057;
                margin-bottom: 1rem;
            }
            .no-surveys p {
                color: #6c757d;
                margin-bottom: 2rem;
                font-size: 1.1rem;
            }
            .my-surveys-container {
                display: grid;
                gap: 2rem;
                margin: 2rem 0;
            }
            .my-survey-item {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 15px;
                padding: 2rem;
                transition: all 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .my-survey-item:hover {
                border-color: #3498db;
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
            }
            .survey-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 1rem;
                flex-wrap: wrap;
                gap: 1rem;
            }
            .survey-header h3 {
                margin: 0;
                color: #2c3e50;
                flex: 1;
                min-width: 200px;
            }
            .survey-badges {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            .type-badge, .status-badge {
                padding: 0.3rem 0.8rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .quiz-badge {
                background: linear-gradient(45deg, #f39c12, #e67e22);
                color: white;
            }
            .survey-badge {
                background: linear-gradient(45deg, #3498db, #2980b9);
                color: white;
            }
            .status-active {
                background: linear-gradient(45deg, #27ae60, #229954);
                color: white;
            }
            .status-inactive {
                background: linear-gradient(45deg, #95a5a6, #7f8c8d);
                color: white;
            }
            .survey-description {
                color: #7f8c8d;
                margin-bottom: 1.5rem;
                line-height: 1.6;
            }
            .survey-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 1rem;
                margin-bottom: 2rem;
                padding: 1.5rem;
                background: #f8f9fa;
                border-radius: 10px;
            }
            .stat-item {
                text-align: center;
            }
            .stat-number {
                display: block;
                font-size: 1.8rem;
                font-weight: bold;
                color: #3498db;
                margin-bottom: 0.3rem;
            }
            .stat-label {
                font-size: 0.9rem;
                color: #6c757d;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .survey-actions {
                display: flex;
                gap: 0.8rem;
                flex-wrap: wrap;
            }
            .btn-icon {
                margin-right: 0.5rem;
            }
            .btn-outline {
                background: transparent;
                border: 2px solid #dee2e6;
                color: #495057;
            }
            .btn-outline:hover {
                background: #f8f9fa;
                border-color: #3498db;
                color: #3498db;
            }
            @media (max-width: 768px) {
                .survey-header {
                    flex-direction: column;
                    align-items: stretch;
                }
                .survey-badges {
                    justify-content: flex-start;
                }
                .survey-actions {
                    flex-direction: column;
                }
                .survey-actions .btn {
                    text-align: center;
                }
            }
        </style>
        
        <script>
            // Анімація для статистики
            document.addEventListener('DOMContentLoaded', function() {
                const statNumbers = document.querySelectorAll('.stat-number');
                statNumbers.forEach(el => {
                    const text = el.textContent;
                    if (!isNaN(text) && text !== '') {
                        const target = parseInt(text);
                        let current = 0;
                        const increment = target / 20;
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= target) {
                                current = target;
                                clearInterval(timer);
                            }
                            el.textContent = Math.floor(current);
                        }, 50);
                    }
                });
            });
        </script>
    ");
    }

    private function renderSurveysList(array $surveys): string
    {
        $surveyItems = '';

        if (empty($surveys)) {
            $surveyItems = '<p>Наразі немає активних опитувань.</p>';
        } else {
            foreach ($surveys as $survey) {
                $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);
                $surveyItems .= "
                    <div class='survey-item'>
                        <h3>" . htmlspecialchars($survey['title']) . "</h3>
                        <p>" . htmlspecialchars($survey['description']) . "</p>
                        <p><small>Автор: " . htmlspecialchars($survey['author_name']) . " | Відповідей: {$responseCount}</small></p>
                        <div class='survey-actions'>
                            <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Пройти опитування</a>
                            <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                        </div>
                    </div>";
            }
        }

        $createButton = '';
        if (Session::isLoggedIn()) {
            $createButton = "<a href='/surveys/create' class='btn btn-success'>Створити нове опитування</a>";
        }

        return $this->buildPageContent("Список опитувань", "
            <div class='header-actions'>
                <h1>Доступні опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-list'>
                {$surveyItems}
            </div>
            
            <div class='page-actions'>
                {$createButton}
                <a href='/' class='btn btn-secondary'>На головну</a>
                " . (Session::isLoggedIn() ? "<a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>" : "") . "
            </div>
        ");
    }

    private function renderCreateForm(array $errors = [], string $title = '', string $description = ''): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        $title = htmlspecialchars($title);
        $description = htmlspecialchars($description);

        return $this->buildPageContent("Створення опитування", "
            <div class='header-actions'>
                <h1>Створити нове опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            {$errorHtml}
            
            <form method='POST' action='/surveys/store' id='create-survey-form'>
                <div class='form-group'>
                    <label for='title'>Назва опитування:</label>
                    <input type='text' id='title' name='title' required value='{$title}' maxlength='255'>
                </div>
                <div class='form-group'>
                    <label for='description'>Опис:</label>
                    <textarea id='description' name='description' rows='4' maxlength='1000'>{$description}</textarea>
                </div>
                
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Створити опитування</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
            
            <script>
                // AJAX обробка форми для демонстрації
                document.getElementById('create-survey-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('/surveys/store', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/surveys/edit?id=' + data.data.survey_id;
                        } else {
                            alert('Помилка: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.submit(); // Fallback до звичайної submit
                    });
                });
            </script>
        ");
    }

    private function renderEditForm(array $survey, array $questions): string
    {
        $questionsHtml = '';

        if (!empty($questions)) {
            foreach ($questions as $question) {
                $questionType = htmlspecialchars($question['question_type']);
                $questionText = htmlspecialchars($question['question_text']);
                $required = $question['is_required'] ? ' (обов\'язкове)' : '';
                $points = $question['points'] ?? 1;
                $correctAnswer = htmlspecialchars($question['correct_answer'] ?? '');

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
                    $correctAnswerHtml = '<p class="correct-answer">Правильна відповідь: <strong>' . $correctAnswer . '</strong></p>';
                }

                $questionsHtml .= "
                    <div class='question-item'>
                        <div class='question-header'>
                            <h4>{$questionText}{$required} <span class='question-points'>({$points} б.)</span></h4>
                            <span class='question-type'>" . Question::getQuestionTypes()[$questionType] . "</span>
                        </div>
                        {$optionsHtml}
                        {$correctAnswerHtml}
                        <form method='POST' action='/surveys/delete-question' style='display: inline;'>
                            <input type='hidden' name='question_id' value='{$question['id']}'>
                            <input type='hidden' name='survey_id' value='{$survey['id']}'>
                            <button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Видалити це питання?\")'>Видалити</button>
                        </form>
                    </div>";
            }
        } else {
            $questionsHtml = '<p>Ще немає питань. Додайте перше питання нижче.</p>';
        }

        return $this->buildPageContent("Редагування опитування", "
            <div class='header-actions'>
                <h1>Редагування: " . htmlspecialchars($survey['title']) . "</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-edit-sections'>
                <section class='existing-questions'>
                    <h2>Питання опитування</h2>
                    <div class='questions-list'>
                        {$questionsHtml}
                    </div>
                </section>
                
                <section class='add-question'>
                    <h2>Додати нове питання</h2>
                    <form method='POST' action='/surveys/add-question' id='questionForm'>
                        <input type='hidden' name='survey_id' value='{$survey['id']}'>
                        
                        <div class='form-group'>
                            <label for='question_text'>Текст питання:</label>
                            <textarea id='question_text' name='question_text' required rows='3'></textarea>
                        </div>
                        
                        <div class='form-row'>
                            <div class='form-group'>
                                <label for='question_type'>Тип питання:</label>
                                <select id='question_type' name='question_type' required onchange='toggleOptions()'>
                                    <option value=''>Оберіть тип</option>
                                    <option value='radio'>Один варіант (радіо)</option>
                                    <option value='checkbox'>Декілька варіантів (чекбокс)</option>
                                    <option value='text'>Короткий текст</option>
                                    <option value='textarea'>Довгий текст</option>
                                </select>
                            </div>
                            
                            <div class='form-group'>
                                <label for='points'>Бали за правильну відповідь:</label>
                                <input type='number' id='points' name='points' value='1' min='0' max='100'>
                            </div>
                        </div>
                        
                        <div class='form-group'>
                            <label>
                                <input type='checkbox' name='is_required' value='1'>
                                Обов'язкове питання
                            </label>
                        </div>
                        
                        <div id='text-answer-section' style='display: none;'>
                            <div class='form-group'>
                                <label for='correct_answer'>Правильна відповідь (для квізу):</label>
                                <input type='text' id='correct_answer' name='correct_answer' placeholder='Введіть правильну відповідь'>
                                <small>Залиште порожнім, якщо це звичайне опитування</small>
                            </div>
                        </div>
                        
                        <div id='options-section' style='display: none;'>
                            <div class='form-group'>
                                <label>Варіанти відповідей:</label>
                                <div id='options-container'>
                                    <div class='option-input'>
                                        <input type='text' name='options[]' placeholder='Варіант 1'>
                                        <label><input type='checkbox' name='correct_options[]' value='0'> Правильна</label>
                                    </div>
                                    <div class='option-input'>
                                        <input type='text' name='options[]' placeholder='Варіант 2'>
                                        <label><input type='checkbox' name='correct_options[]' value='1'> Правильна</label>
                                    </div>
                                </div>
                                <button type='button' onclick='addOption()' class='btn btn-sm btn-secondary'>Додати варіант</button>
                                <small>Позначте правильні відповіді для створення квізу</small>
                            </div>
                        </div>
                        
                        <div class='form-actions'>
                            <button type='submit' class='btn btn-success'>Додати питання</button>
                        </div>
                    </form>
                </section>
            </div>
            
            <div class='page-actions'>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
                <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-secondary'>Експорт CSV</a>
                <a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>
            </div>
            
            <script>
                let optionIndex = 2;
                
                function toggleOptions() {
                    const type = document.getElementById('question_type').value;
                    const optionsSection = document.getElementById('options-section');
                    const textAnswerSection = document.getElementById('text-answer-section');
                    
                    if (type === 'radio' || type === 'checkbox') {
                        optionsSection.style.display = 'block';
                        textAnswerSection.style.display = 'none';
                    } else if (type === 'text' || type === 'textarea') {
                        optionsSection.style.display = 'none';
                        textAnswerSection.style.display = 'block';
                    } else {
                        optionsSection.style.display = 'none';
                        textAnswerSection.style.display = 'none';
                    }
                }
                
                function addOption() {
                    const container = document.getElementById('options-container');
                    const optionDiv = document.createElement('div');
                    optionDiv.className = 'option-input';
                    optionDiv.innerHTML = `
                        <input type='text' name='options[]' placeholder='Варіант \${optionIndex + 1}'>
                        <label><input type='checkbox' name='correct_options[]' value='\${optionIndex}'> Правильна</label>
                    `;
                    container.appendChild(optionDiv);
                    optionIndex++;
                }
            </script>
        ");
    }

    private function renderSurveyView(array $survey, array $questions): string
    {
        // Код рендерингу опитування для проходження
        // ... (аналогічно до оригінального коду)
        return $this->buildPageContent("Проходження опитування", "<!-- Survey view content -->");
    }

    private function renderUserNav(): string
    {
        if (Session::isLoggedIn()) {
            $userName = Session::getUserName();
            return "
                <div class='user-nav'>
                    <span>Привіт, " . htmlspecialchars($userName) . "!</span>
                    <a href='/logout' class='btn btn-sm'>Вийти</a>
                </div>";
        } else {
            return "
                <div class='user-nav'>
                    <a href='/login' class='btn btn-sm'>Увійти</a>
                    <a href='/register' class='btn btn-sm'>Реєстрація</a>
                </div>";
        }
    }

    private function buildPageContent(string $title, string $content): string
    {
        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body>
            <div class='container'>
                {$content}
            </div>
        </body>
        </html>";
    }

    private function generateExportData(int $surveyId): array
    {
        // Генерація даних для експорту
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