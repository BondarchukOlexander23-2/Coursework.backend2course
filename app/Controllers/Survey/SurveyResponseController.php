<?php

/**
 * Контролер для обробки відповідей на опитування з HTML всередині
 */
class SurveyResponseController
{
    private SurveyValidator $validator;
    private QuestionService $questionService;

    public function __construct()
    {
        $this->validator = new SurveyValidator();
        $this->questionService = new QuestionService();
    }

    /**
     * Обробити відповіді на опитування
     */
    public function submit(): void
    {
        $surveyId = (int)($_POST['survey_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        $survey = $this->validator->validateAndGetSurvey($surveyId);
        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        $questions = Question::getBySurveyId($surveyId, true);
        $errors = $this->validator->validateAnswers($questions, $answers);

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

    // === HTML РЕНДЕРИНГ ===

    /**
     * Відобразити деталі відповіді
     */
    private function renderResponseDetails(array $survey, array $response, array $answers): string
    {
        $answersHtml = '';
        $questionNumber = 1;

        foreach ($answers as $answer) {
            $questionText = htmlspecialchars($answer['question_text']);
            $answerText = '';
            $correctnessClass = '';
            $correctnessIcon = '';

            if ($answer['option_text']) {
                $answerText = htmlspecialchars($answer['option_text']);
            } else {
                $answerText = htmlspecialchars($answer['answer_text']);
            }

            if (isset($answer['is_correct'])) {
                $correctnessClass = $answer['is_correct'] ? 'correct-answer' : 'incorrect-answer';
                $correctnessIcon = $answer['is_correct'] ? '✓' : '✗';
            }

            $pointsText = '';
            if (isset($answer['points_earned']) && $answer['points_earned'] > 0) {
                $pointsText = " <span class='points'>+{$answer['points_earned']} б.</span>";
            }

            $answersHtml .= "
                <div class='answer-item {$correctnessClass}'>
                    <h4>{$questionNumber}. {$questionText}</h4>
                    <p class='answer-text'>{$correctnessIcon} {$answerText}{$pointsText}</p>
                </div>";
            $questionNumber++;
        }

        $userInfo = $response['user_name'] ?: 'Анонім';
        $scoreInfo = '';

        if (isset($response['max_score']) && $response['max_score'] > 0) {
            $scoreInfo = "
                <div class='response-score'>
                    <h3>Результат: {$response['total_score']}/{$response['max_score']} ({$response['percentage']}%)</h3>
                </div>";
        }

        return $this->renderPage("Деталі відповіді", "
            <div class='header-actions'>
                <h1>Деталі відповіді на: " . htmlspecialchars($survey['title']) . "</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='response-info'>
                <p><strong>Респондент:</strong> {$userInfo}</p>
                <p><strong>Дата:</strong> {$response['created_at']}</p>
                {$scoreInfo}
            </div>
            
            <div class='response-answers'>
                {$answersHtml}
            </div>
            
            <div class='form-actions'>
                <a href='/surveys/results?id={$survey['id']}' class='btn btn-primary'>Назад до результатів</a>
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