<?php

/**
 * Покращений контролер для роботи з опитуваннями
 * Відповідає принципам SOLID:
 * - Single Responsibility: відповідає тільки за обробку HTTP запитів для опитувань
 * - Open/Closed: можна розширювати новими методами без зміни існуючих
 * - Dependency Inversion: залежить від абстракцій (моделей), а не від конкретних реалізацій
 */
class SurveyController
{
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

        // Валідація основних даних
        $errors = $this->validateSurveyData($title, $description);

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
        $survey = $this->validateAndGetSurvey($surveyId);

        if (!$survey) {
            Session::setFlashMessage('error', 'Опитування не знайдено');
            header('Location: /surveys/my');
            exit;
        }

        // Перевіряємо права доступу
        if (!Survey::isAuthor($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'У вас немає прав для редагування цього опитування');
            header('Location: /surveys/my');
            exit;
        }

        $questions = Question::getBySurveyId($surveyId, true); // Включаємо правильні відповіді
        $this->loadQuestionsWithOptions($questions);

        $content = $this->renderEditForm($survey, $questions);
        echo $content;
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
        $errors = $this->validateQuestionData($questionText, $questionType, $options, $points);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header("Location: /surveys/edit?id={$surveyId}");
            exit;
        }

        try {
            $orderNumber = Question::getNextOrderNumber($surveyId);
            $questionId = Question::create(
                $surveyId,
                $questionText,
                $questionType,
                $isRequired,
                $orderNumber,
                $correctAnswer,
                $points
            );

            // Додаємо варіанти відповідей з позначенням правильних
            if (in_array($questionType, [Question::TYPE_RADIO, Question::TYPE_CHECKBOX])) {
                $optionsData = [];
                foreach ($options as $index => $optionText) {
                    if (!empty(trim($optionText))) {
                        $optionsData[] = [
                            'text' => $optionText,
                            'is_correct' => in_array($index, $correctOptions)
                        ];
                    }
                }
                QuestionOption::createMultiple($questionId, $optionsData);
            }

            Session::setFlashMessage('success', 'Питання успішно додано!');
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

        try {
            // Спочатку видаляємо варіанти відповідей
            QuestionOption::deleteByQuestionId($questionId);
            // Потім видаляємо саме питання
            Question::delete($questionId);

            Session::setFlashMessage('success', 'Питання успішно видалено!');
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при видаленні питання');
        }

        header("Location: /surveys/edit?id={$surveyId}");
        exit;
    }

    /**
     * Показати опитування для проходження
     */
    public function view(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);
        $survey = $this->validateAndGetSurvey($surveyId);

        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        // Перевіряємо чи користувач вже проходив опитування
        if (Session::isLoggedIn() && SurveyResponse::hasUserResponded($surveyId, Session::getUserId())) {
            Session::setFlashMessage('error', 'Ви вже проходили це опитування');
            header("Location: /surveys/results?id={$surveyId}");
            exit;
        }

        $questions = Question::getBySurveyId($surveyId);
        $this->loadQuestionsWithOptions($questions);

        $content = $this->renderSurveyView($survey, $questions);
        echo $content;
    }

    /**
     * Обробити відповіді на опитування
     */
    public function submit(): void
    {
        $surveyId = (int)($_POST['survey_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        $survey = $this->validateAndGetSurvey($surveyId);
        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        // Валідація відповідей
        $questions = Question::getBySurveyId($surveyId, true);
        $errors = $this->validateAnswers($questions, $answers);

        if (!empty($errors)) {
            Session::setFlashMessage('error', implode('<br>', $errors));
            header("Location: /surveys/view?id={$surveyId}");
            exit;
        }

        try {
            // Створюємо запис про проходження опитування
            $userId = Session::isLoggedIn() ? Session::getUserId() : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $responseId = SurveyResponse::create($surveyId, $userId, $ipAddress);

            // Зберігаємо відповіді та підраховуємо результат
            $totalScore = 0;
            $maxScore = Question::getMaxPointsForSurvey($surveyId);
            $isQuiz = Question::isQuiz($surveyId);

            foreach ($questions as $question) {
                $questionId = $question['id'];
                $questionType = $question['question_type'];

                if (!isset($answers[$questionId])) {
                    continue;
                }

                $answer = $answers[$questionId];
                $result = ['is_correct' => false, 'points' => 0];

                // Перевіряємо правильність відповіді, якщо це квіз
                if ($isQuiz) {
                    $result = Question::checkUserAnswer($questionId, $answer);
                    $totalScore += $result['points'];
                }

                // Зберігаємо відповідь
                $this->saveQuestionAnswer($responseId, $question, $answer, $result);
            }

            // Оновлюємо загальний результат
            if ($isQuiz) {
                SurveyResponse::updateScore($responseId, $totalScore, $maxScore);
            }

            // Встановлюємо повідомлення залежно від типу
            if ($isQuiz) {
                $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
                Session::setFlashMessage('success',
                    "Квіз завершено! Ваш результат: {$totalScore}/{$maxScore} балів ({$percentage}%)");
            } else {
                Session::setFlashMessage('success', 'Дякуємо за участь в опитуванні!');
            }

            header("Location: /surveys/results?id={$surveyId}&response={$responseId}");
            exit;
        } catch (Exception $e) {
            Session::setFlashMessage('error', 'Помилка при збереженні відповідей');
            header("Location: /surveys/view?id={$surveyId}");
            exit;
        }
    }

    /**
     * Показати результати опитування
     */
    public function results(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);
        $responseId = (int)($_GET['response'] ?? 0);

        $survey = $this->validateAndGetSurvey($surveyId);
        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        $questions = Question::getBySurveyId($surveyId);
        $isQuiz = Question::isQuiz($surveyId);

        if ($isQuiz) {
            // Для квізів показуємо детальні результати
            $quizStats = SurveyResponse::getQuizStats($surveyId);
            $topResults = SurveyResponse::getTopResults($surveyId, 10);
            $userResult = null;

            if ($responseId > 0) {
                $userResult = SurveyResponse::findById($responseId);
            }

            $content = $this->renderQuizResults($survey, $questions, $quizStats, $topResults, $userResult);
        } else {
            // Для звичайних опитувань показуємо статистику
            $questionStats = [];
            foreach ($questions as $question) {
                $questionStats[$question['id']] = QuestionAnswer::getQuestionStats($question['id']);
            }
            $totalResponses = SurveyResponse::getCountBySurveyId($surveyId);
            $content = $this->renderSurveyResults($survey, $questions, $questionStats, $totalResponses);
        }

        echo $content;
    }

    /**
     * Відобразити звичайні результати опитування
     */
    private function renderSurveyResults(array $survey, array $questions, array $questionStats, int $totalResponses): string
    {
        $resultsHtml = '';

        if ($totalResponses === 0) {
            $resultsHtml = '<p>Ще немає відповідей на це опитування.</p>';
        } else {
            $questionNumber = 1;
            foreach ($questions as $question) {
                $questionText = htmlspecialchars($question['question_text']);
                $stats = $questionStats[$question['id']] ?? [];

                $questionResultHtml = '';

                if ($question['question_type'] === Question::TYPE_RADIO || $question['question_type'] === Question::TYPE_CHECKBOX) {
                    // Статистика для варіантів відповідей
                    foreach ($stats['option_stats'] ?? [] as $optionStat) {
                        $count = $optionStat['total_selected'];
                        $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0;
                        $optionText = htmlspecialchars($optionStat['option_text']);

                        $questionResultHtml .= "
                            <div class='result-item'>
                                <p><strong>{$optionText}:</strong> {$percentage}% ({$count} відповідей)</p>
                                <div class='progress-bar'>
                                    <div class='progress' style='width: {$percentage}%'></div>
                                </div>
                            </div>";
                    }
                } else {
                    // Текстові відповіді
                    $textAnswers = $stats['text_answers'] ?? [];
                    if (!empty($textAnswers)) {
                        $questionResultHtml .= "<div class='text-answers'>";
                        foreach (array_slice($textAnswers, 0, 10) as $answer) {
                            $answerText = htmlspecialchars($answer['answer_text']);
                            $questionResultHtml .= "<p class='text-answer'>\"$answerText\"</p>";
                        }
                        if (count($textAnswers) > 10) {
                            $remaining = count($textAnswers) - 10;
                            $questionResultHtml .= "<p class='more-answers'>... та ще {$remaining} відповідей</p>";
                        }
                        $questionResultHtml .= "</div>";
                    } else {
                        $questionResultHtml .= "<p>Немає відповідей на це питання.</p>";
                    }
                }

                $resultsHtml .= "
                    <div class='question-results'>
                        <h3>{$questionNumber}. {$questionText}</h3>
                        {$questionResultHtml}
                    </div>";

                $questionNumber++;
            }
        }

        return $this->renderPage("Результати опитування", "
            <div class='header-actions'>
                <div>
                    <h1>Результати: " . htmlspecialchars($survey['title']) . "</h1>
                    <p><strong>Всього відповідей: {$totalResponses}</strong></p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='results'>
                {$resultsHtml}
            </div>
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти опитування</a>
            </div>
        ");
    }


    /**
     * Відобразити результати квізу
     */
    private function renderQuizResults(array $survey, array $questions, array $stats, array $topResults, ?array $userResult): string
    {
        $userResultHtml = '';
        if ($userResult) {
            $percentage = $userResult['percentage'];
            $level = $this->getResultLevel($percentage);
            $userResultHtml = "
                <div class='user-result highlight'>
                    <h3>Ваш результат</h3>
                    <div class='score-display'>
                        <span class='score'>{$userResult['total_score']}/{$userResult['max_score']}</span>
                        <span class='percentage'>{$percentage}%</span>
                        <span class='level {$this->getResultLevelClass($percentage)}'>{$level}</span>
                    </div>
                </div>";
        }

        $statsHtml = "
            <div class='quiz-stats'>
                <h3>Загальна статистика</h3>
                <div class='stats-grid'>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['total_attempts']}</span>
                        <span class='stat-label'>Спроб</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['avg_percentage']}%</span>
                        <span class='stat-label'>Середній результат</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['best_score']}</span>
                        <span class='stat-label'>Найкращий результат</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$stats['perfect_scores']}</span>
                        <span class='stat-label'>Ідеальних результатів</span>
                    </div>
                </div>
            </div>";

        $topResultsHtml = '';
        if (!empty($topResults)) {
            $topResultsHtml = '<div class="top-results"><h3>Топ результати</h3><ol>';
            foreach ($topResults as $result) {
                $userName = $result['user_name'] ?: 'Анонім';
                $topResultsHtml .= "<li>{$userName}: {$result['total_score']}/{$result['max_score']} ({$result['percentage']}%)</li>";
            }
            $topResultsHtml .= '</ol></div>';
        }

        return $this->renderPage("Результати квізу", "
            <div class='header-actions'>
                <div>
                    <h1>Квіз: " . htmlspecialchars($survey['title']) . "</h1>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            {$userResultHtml}
            {$statsHtml}
            {$topResultsHtml}
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти ще раз</a>
            </div>
        ");
    }

    /**
     * Допоміжні методи для роботи з результатами
     */
    private function getResultLevel(float $percentage): string
    {
        if ($percentage >= 90) return 'Відмінно';
        if ($percentage >= 75) return 'Добре';
        if ($percentage >= 60) return 'Задовільно';
        return 'Незадовільно';
    }

    private function getResultLevelClass(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'satisfactory';
        return 'poor';
    }

    /**
     * Показати детальні результати конкретної відповіді
     */
    public function responseDetails(): void
    {
        $responseId = (int)($_GET['id'] ?? 0);

        if ($responseId <= 0) {
            header('Location: /surveys');
            exit;
        }

        $response = SurveyResponse::findById($responseId);
        if (!$response) {
            header('Location: /surveys');
            exit;
        }

        $survey = Survey::findById($response['survey_id']);
        $answers = QuestionAnswer::getByResponseId($responseId);

        $content = $this->renderResponseDetails($survey, $response, $answers);
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

    // === Приватні методи для валідації ===

    /**
     * Валідація даних опитування
     */
    private function validateSurveyData(string $title, string $description): array
    {
        $errors = [];

        if (empty($title)) {
            $errors[] = 'Назва опитування є обов\'язковою';
        }
        if (strlen($title) < 3) {
            $errors[] = 'Назва повинна містити мінімум 3 символи';
        }
        if (strlen($title) > 255) {
            $errors[] = 'Назва занадто довга (максимум 255 символів)';
        }

        return $errors;
    }

    /**
     * Валідація даних питання
     */
    private function validateQuestionData(string $questionText, string $questionType, array $options, int $points): array
    {
        $errors = [];

        if (empty($questionText)) {
            $errors[] = 'Текст питання є обов\'язковим';
        }

        if (!in_array($questionType, array_keys(Question::getQuestionTypes()))) {
            $errors[] = 'Невірний тип питання';
        }

        if ($points < 0) {
            $errors[] = 'Бали не можуть бути від\'ємними';
        }

        // Для питань з варіантами відповідей перевіряємо наявність опцій
        if (in_array($questionType, [Question::TYPE_RADIO, Question::TYPE_CHECKBOX])) {
            $validOptions = array_filter($options, fn($opt) => !empty(trim($opt)));
            if (count($validOptions) < 2) {
                $errors[] = 'Додайте принаймні 2 варіанти відповіді';
            }
        }

        return $errors;
    }

    /**
     * Валідація відповідей користувача
     */
    private function validateAnswers(array $questions, array $answers): array
    {
        $errors = [];

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $isRequired = $question['is_required'];
            $questionType = $question['question_type'];

            if ($isRequired) {
                if (!isset($answers[$questionId]) || empty($answers[$questionId])) {
                    $errors[] = "Питання '{$question['question_text']}' є обов'язковим";
                    continue;
                }

                // Для текстових питань перевіряємо чи не порожній текст
                if (in_array($questionType, [Question::TYPE_TEXT, Question::TYPE_TEXTAREA])) {
                    if (empty(trim($answers[$questionId]))) {
                        $errors[] = "Питання '{$question['question_text']}' є обов'язковим";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Перевірити та отримати опитування
     */
    private function validateAndGetSurvey(int $surveyId): ?array
    {
        if ($surveyId <= 0) {
            return null;
        }

        return Survey::findById($surveyId);
    }
    /**
     * Зберегти відповідь на питання з перевіркою правильності
     */
    private function saveQuestionAnswer(int $responseId, array $question, $answer, array $result): void
    {
        $questionId = $question['id'];
        $questionType = $question['question_type'];
        $isCorrect = $result['is_correct'] ?? false;
        $pointsEarned = $result['points'] ?? 0;

        switch ($questionType) {
            case Question::TYPE_RADIO:
                if (is_numeric($answer)) {
                    QuestionAnswer::createOptionAnswer($responseId, $questionId, (int)$answer, $isCorrect, $pointsEarned);
                }
                break;

            case Question::TYPE_CHECKBOX:
                if (is_array($answer)) {
                    QuestionAnswer::createMultipleOptionAnswers($responseId, $questionId, $answer, $isCorrect, $pointsEarned);
                }
                break;

            case Question::TYPE_TEXT:
            case Question::TYPE_TEXTAREA:
                if (!empty(trim($answer))) {
                    QuestionAnswer::createTextAnswer($responseId, $questionId, $answer, $isCorrect, $pointsEarned);
                }
                break;
        }
    }
    /**
     * Завантажити варіанти відповідей для питань
     */
    private function loadQuestionsWithOptions(array &$questions): void
    {
        foreach ($questions as &$question) {
            if ($question['has_options']) {
                $question['options'] = QuestionOption::getByQuestionId($question['id']);
            }
        }
    }

    /**
     * Зберегти відповіді користувача
     */
    private function saveAnswers(int $responseId, array $questions, array $answers): void
    {
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $questionType = $question['question_type'];

            if (!isset($answers[$questionId])) {
                continue;
            }

            $answer = $answers[$questionId];

            switch ($questionType) {
                case Question::TYPE_RADIO:
                    if (is_numeric($answer)) {
                        QuestionAnswer::createOptionAnswer($responseId, $questionId, (int)$answer);
                    }
                    break;

                case Question::TYPE_CHECKBOX:
                    if (is_array($answer)) {
                        QuestionAnswer::createMultipleOptionAnswers($responseId, $questionId, $answer);
                    }
                    break;

                case Question::TYPE_TEXT:
                case Question::TYPE_TEXTAREA:
                    if (!empty(trim($answer))) {
                        QuestionAnswer::createTextAnswer($responseId, $questionId, $answer);
                    }
                    break;
            }
        }
    }

    // === Методи рендерингу ===

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
     * Відобразити результати опитування
     */
    private function renderResults(array $survey, array $questions, array $questionStats, int $totalResponses): string
    {
        $resultsHtml = '';

        if ($totalResponses === 0) {
            $resultsHtml = '<p>Ще немає відповідей на це опитування.</p>';
        } else {
            $questionNumber = 1;
            foreach ($questions as $question) {
                $questionText = htmlspecialchars($question['question_text']);
                $stats = $questionStats[$question['id']] ?? [];

                $questionResultHtml = '';

                if ($question['question_type'] === Question::TYPE_RADIO || $question['question_type'] === Question::TYPE_CHECKBOX) {
                    // Статистика для варіантів відповідей
                    foreach ($stats['option_stats'] ?? [] as $optionStat) {
                        $count = $optionStat['count'];
                        $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0;
                        $optionText = htmlspecialchars($optionStat['option_text']);

                        $questionResultHtml .= "
                            <div class='result-item'>
                                <p><strong>{$optionText}:</strong> {$percentage}% ({$count} відповідей)</p>
                                <div class='progress-bar'>
                                    <div class='progress' style='width: {$percentage}%'></div>
                                </div>
                            </div>";
                    }
                } else {
                    // Текстові відповіді
                    $textAnswers = $stats['text_answers'] ?? [];
                    if (!empty($textAnswers)) {
                        $questionResultHtml .= "<div class='text-answers'>";
                        foreach (array_slice($textAnswers, 0, 10) as $answer) { // Показуємо тільки перші 10
                            $answerText = htmlspecialchars($answer['answer_text']);
                            $questionResultHtml .= "<p class='text-answer'>\"$answerText\"</p>";
                        }
                        if (count($textAnswers) > 10) {
                            $remaining = count($textAnswers) - 10;
                            $questionResultHtml .= "<p class='more-answers'>... та ще {$remaining} відповідей</p>";
                        }
                        $questionResultHtml .= "</div>";
                    } else {
                        $questionResultHtml .= "<p>Немає відповідей на це питання.</p>";
                    }
                }

                $resultsHtml .= "
                    <div class='question-results'>
                        <h3>{$questionNumber}. {$questionText}</h3>
                        {$questionResultHtml}
                    </div>";

                $questionNumber++;
            }
        }

        return $this->renderPage("Результати опитування", "
            <div class='header-actions'>
                <div>
                    <h1>Результати: " . htmlspecialchars($survey['title']) . "</h1>
                    <p><strong>Всього відповідей: {$totalResponses}</strong></p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='results'>
                {$resultsHtml}
            </div>
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти опитування</a>
                " . (Session::isLoggedIn() && Survey::isAuthor($survey['id'], Session::getUserId()) ?
                "<a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати</a>" : "") . "
            </div>
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
}