<?php

/**
 * Базовий контролер для опитувань з HTML всередині
 * Відповідає принципу Single Responsibility - тільки основні CRUD операції
 */
class SurveyController
{
    private SurveyValidator $validator;
    private QuestionService $questionService;

    public function __construct()
    {
        $this->validator = new SurveyValidator();
        $this->questionService = new QuestionService();
    }

    /**
     * Показати список опитувань
     */
    public function index(): void
    {
        $surveys = Survey::getAllActive();
        $content = $this->renderSurveysList($surveys);
        echo $content;
    }

    /**
     * Показати форму створення опитування
     */
    public function create(): void
    {
        Session::requireLogin();
        $content = $this->renderCreateForm();
        echo $content;
    }

    /**
     * Зберегти нове опитування
     */
    public function store(): void
    {
        Session::requireLogin();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $userId = Session::getUserId();

        $errors = $this->validator->validateSurveyData($title, $description);

        if (!empty($errors)) {
            $content = $this->renderCreateForm($errors, $title, $description);
            echo $content;
            return;
        }

        try {
            $surveyId = Survey::create($title, $description, $userId);
            Session::setFlashMessage('success', 'Опитування успішно створено! Тепер додайте питання.');
            header("Location: /surveys/edit?id={$surveyId}");
            exit;
        } catch (Exception $e) {
            $content = $this->renderCreateForm(['Помилка при створенні опитування']);
            echo $content;
        }
    }

    /**
     * Показати форму редагування опитування з питаннями
     */
    public function edit(): void
    {
        Session::requireLogin();

        $surveyId = (int)($_GET['id'] ?? 0);
        $survey = $this->validator->validateAndGetSurvey($surveyId);

        if (!$survey) {
            Session::setFlashMessage('error', 'Опитування не знайдено');
            header('Location: /surveys/my');
            exit;
        }

        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'У вас немає прав для редагування цього опитування');
            header('Location: /surveys/my');
            exit;
        }

        $questions = Question::getBySurveyId($surveyId, true);
        $this->questionService->loadQuestionsWithOptions($questions);

        $content = $this->renderEditForm($survey, $questions);
        echo $content;
    }

    /**
     * Показати мої опитування
     */
    public function my(): void
    {
        Session::requireLogin();

        $userId = Session::getUserId();
        $surveys = Survey::getByUserId($userId);
        $content = $this->renderMySurveys($surveys);
        echo $content;
    }

    /**
     * Показати опитування для проходження
     */
    public function view(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);
        $survey = $this->validator->validateAndGetSurvey($surveyId);

        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        if (Session::isLoggedIn() && SurveyResponse::hasUserResponded($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'Ви вже проходили це опитування');
            header("Location: /surveys/results?id={$surveyId}");
            exit;
        }

        $questions = Question::getBySurveyId($surveyId);
        $this->questionService->loadQuestionsWithOptions($questions);

        $content = $this->renderSurveyView($survey, $questions);
        echo $content;
    }

    // === HTML РЕНДЕРИНГ ===

    /**
     * Відобразити список опитувань
     */
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

        return $this->renderPage("Список опитувань", "
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

    /**
     * Відобразити форму створення опитування
     */
    private function renderCreateForm(array $errors = [], string $title = '', string $description = ''): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        $title = htmlspecialchars($title);
        $description = htmlspecialchars($description);

        return $this->renderPage("Створення опитування", "
            <div class='header-actions'>
                <h1>Створити нове опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            {$errorHtml}
            
            <form method='POST' action='/surveys/store'>
                <div class='form-group'>
                    <label for='title'>Назва опитування:</label>
                    <input type='text' id='title' name='title' required value='{$title}'>
                </div>
                <div class='form-group'>
                    <label for='description'>Опис:</label>
                    <textarea id='description' name='description' rows='4'>{$description}</textarea>
                </div>
                
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Створити опитування</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
        ");
    }

    /**
     * Відобразити форму редагування з підтримкою квізів
     */
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

        $questionTypesOptions = '';
        foreach (Question::getQuestionTypes() as $type => $label) {
            $questionTypesOptions .= "<option value='{$type}'>{$label}</option>";
        }

        return $this->renderPage("Редагування опитування", "
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
                                    {$questionTypesOptions}
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

    /**
     * Відобразити опитування для проходження
     */
    private function renderSurveyView(array $survey, array $questions): string
    {
        $questionsHtml = '';

        if (empty($questions)) {
            $questionsHtml = '<p>Це опитування ще не має питань.</p>';
        } else {
            $questionNumber = 1;
            foreach ($questions as $question) {
                $required = $question['is_required'] ? ' <span class="required">*</span>' : '';
                $questionText = htmlspecialchars($question['question_text']);

                $inputHtml = '';

                switch ($question['question_type']) {
                    case Question::TYPE_RADIO:
                        if (isset($question['options'])) {
                            foreach ($question['options'] as $option) {
                                $optionText = htmlspecialchars($option['option_text']);
                                $inputHtml .= "
                                    <label class='option-label'>
                                        <input type='radio' name='answers[{$question['id']}]' value='{$option['id']}'>
                                        {$optionText}
                                    </label>";
                            }
                        }
                        break;

                    case Question::TYPE_CHECKBOX:
                        if (isset($question['options'])) {
                            foreach ($question['options'] as $option) {
                                $optionText = htmlspecialchars($option['option_text']);
                                $inputHtml .= "
                                    <label class='option-label'>
                                        <input type='checkbox' name='answers[{$question['id']}][]' value='{$option['id']}'>
                                        {$optionText}
                                    </label>";
                            }
                        }
                        break;

                    case Question::TYPE_TEXT:
                        $inputHtml = "<input type='text' name='answers[{$question['id']}]' class='form-control'>";
                        break;

                    case Question::TYPE_TEXTAREA:
                        $inputHtml = "<textarea name='answers[{$question['id']}]' rows='4' class='form-control'></textarea>";
                        break;
                }

                $questionsHtml .= "
                    <div class='question'>
                        <h3>{$questionNumber}. {$questionText}{$required}</h3>
                        <div class='question-input'>
                            {$inputHtml}
                        </div>
                    </div>";

                $questionNumber++;
            }
        }

        return $this->renderPage("Проходження опитування", "
            <div class='header-actions'>
                <div>
                    <h1>" . htmlspecialchars($survey['title']) . "</h1>
                    <p>" . htmlspecialchars($survey['description']) . "</p>
                    <p><small>Автор: " . htmlspecialchars($survey['author_name']) . "</small></p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <form method='POST' action='/surveys/submit'>
                <input type='hidden' name='survey_id' value='{$survey['id']}'>
                
                {$questionsHtml}
                
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Надіслати відповіді</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
        ");
    }

    /**
     * Відобразити мої опитування
     */
    private function renderMySurveys(array $surveys): string
    {
        $surveyItems = '';

        if (empty($surveys)) {
            $surveyItems = '<p>У вас ще немає створених опитувань.</p>';
        } else {
            foreach ($surveys as $survey) {
                $status = $survey['is_active'] ? 'Активне' : 'Неактивне';
                $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);
                $questionCount = count(Question::getBySurveyId($survey['id']));

                $surveyItems .= "
                    <div class='survey-item'>
                        <h3>" . htmlspecialchars($survey['title']) . "</h3>
                        <p>" . htmlspecialchars($survey['description']) . "</p>
                        <p><small>Статус: {$status} | Питань: {$questionCount} | Відповідей: {$responseCount}</small></p>
                        <div class='survey-actions'>
                            <a href='/surveys/edit?id={$survey['id']}' class='btn btn-primary'>Редагувати</a>
                            <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Переглянути</a>
                            <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                        </div>
                    </div>";
            }
        }

        return $this->renderPage("Мої опитування", "
            <div class='header-actions'>
                <h1>Мої опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-list'>
                {$surveyItems}
            </div>
            
            <div class='page-actions'>
                <a href='/surveys/create' class='btn btn-success'>Створити нове</a>
                <a href='/surveys' class='btn btn-secondary'>Всі опитування</a>
            </div>
        ");
    }

    // === ДОПОМІЖНІ МЕТОДИ ===

    /**
     * Відобразити навігацію користувача
     */
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

    /**
     * Відобразити базову сторінку
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
        </head>
        <body>
            <div class='container'>
                {$flashHtml}
                {$content}
            </div>
        </body>
        </html>";
    }
    /**
     * Додати питання до опитування
     */
    public function addQuestion(): void
    {
        Session::requireLogin();

        $surveyId = (int)($_POST['survey_id'] ?? 0);
        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? '';
        $isRequired = isset($_POST['is_required']);
        $points = (int)($_POST['points'] ?? 1);
        $correctAnswer = trim($_POST['correct_answer'] ?? '') ?: null;
        $options = $_POST['options'] ?? [];
        $correctOptions = $_POST['correct_options'] ?? [];

        // Валідація
        $survey = $this->validator->validateAndGetSurvey($surveyId);
        if (!$survey) {
            Session::setFlashMessage('error', 'Опитування не знайдено');
            header('Location: /surveys/my');
            exit;
        }

        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'У вас немає прав для редагування цього опитування');
            header('Location: /surveys/my');
            exit;
        }

        $errors = $this->validator->validateQuestionData($questionText, $questionType, $options, $points);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header("Location: /surveys/edit?id={$surveyId}");
            exit;
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

            Session::setFlashMessage('success', 'Питання успішно додано');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при додаванні питання: ' . $e->getMessage());
        }

        header("Location: /surveys/edit?id={$surveyId}");
        exit;
    }

    /**
     * Видалити питання
     */
    public function deleteQuestion(): void
    {
        Session::requireLogin();

        $questionId = (int)($_POST['question_id'] ?? 0);
        $surveyId = (int)($_POST['survey_id'] ?? 0);

        $question = Question::findById($questionId);
        if (!$question) {
            Session::setFlashMessage('error', 'Питання не знайдено');
            header("Location: /surveys/edit?id={$surveyId}");
            exit;
        }

        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'У вас немає прав для редагування цього опитування');
            header('Location: /surveys/my');
            exit;
        }

        try {
            $this->questionService->deleteQuestion($questionId);
            Session::setFlashMessage('success', 'Питання видалено');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при видаленні питання: ' . $e->getMessage());
        }

        header("Location: /surveys/edit?id={$surveyId}");
        exit;
    }

}