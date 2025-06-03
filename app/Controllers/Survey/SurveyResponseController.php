<?php

/**
 * Оновлений контролер для обробки відповідей на опитування з BaseController
 */
class SurveyResponseController extends BaseController
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
     * Обробити відповіді на опитування
     */
    public function submit(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('survey_id');
            $answers = $this->postParam('answers', []);

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $questions = Question::getBySurveyId($surveyId, true);
            $errors = $this->validator->validateAnswers($questions, $answers);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
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
                    $message = "Квіз завершено! Ваш результат: {$totalScore}/{$maxScore} балів ({$percentage}%)";
                } else {
                    $message = 'Дякуємо за участь в опитуванні!';
                }

                $redirectUrl = "/surveys/results?id={$surveyId}&response={$responseId}";

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, [
                        'response_id' => $responseId,
                        'total_score' => $totalScore,
                        'max_score' => $maxScore,
                        'is_quiz' => $isQuiz
                    ], $message);
                } else {
                    $this->redirectWithMessage($redirectUrl, 'success', $message);
                }

            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при збереженні відповідей');
            }
        });
    }

    /**
     * Показати детальні результати конкретної відповіді
     */
    public function responseDetails(): void
    {
        $this->safeExecute(function() {
            $responseId = $this->getIntParam('id');

            if ($responseId <= 0) {
                throw new NotFoundException('Невірний ID відповіді');
            }

            $response = SurveyResponse::findById($responseId);
            if (!$response) {
                throw new NotFoundException('Відповідь не знайдена');
            }

            $survey = Survey::findById($response['survey_id']);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $answers = QuestionAnswer::getByResponseId($responseId);

            $content = $this->renderResponseDetails($survey, $response, $answers);

            // Результати кешуємо на 1 годину
            $this->responseManager
                ->setCacheHeaders(3600)
                ->sendSuccess($content);
        });
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

        return $this->buildHtmlPage("Деталі відповіді", "
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
                <a href='/surveys' class='btn btn-secondary'>Всі опитування</a>
            </div>
            
            <style>
                .answer-item {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 1.5rem;
                    margin-bottom: 1rem;
                    border-left: 4px solid #dee2e6;
                }
                .answer-item.correct-answer {
                    background: #d4edda;
                    border-left-color: #28a745;
                }
                .answer-item.incorrect-answer {
                    background: #f8d7da;
                    border-left-color: #dc3545;
                }
                .answer-text {
                    margin: 0.5rem 0 0 0;
                    font-size: 1.1rem;
                }
                .points {
                    color: #28a745;
                    font-weight: bold;
                }
                .response-score {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 12px;
                    text-align: center;
                    margin-bottom: 2rem;
                }
                .response-score h3 {
                    margin: 0;
                    color: white;
                }
                .response-info {
                    background: #f8f9fa;
                    padding: 1.5rem;
                    border-radius: 8px;
                    margin-bottom: 2rem;
                }
            </style>
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
}