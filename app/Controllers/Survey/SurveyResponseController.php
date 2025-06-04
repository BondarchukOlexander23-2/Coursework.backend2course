<?php

/**
 * Виправлений контролер для обробки відповідей на опитування
 * Дотримується принципів SOLID з повною обробкою всіх типів відповідей
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
     * Обробити відповіді на опитування - ВИПРАВЛЕНА ВЕРСІЯ
     */
    public function submit(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('survey_id');
            if (!$surveyId) {
                $surveyId = (int)$this->postParam('survey_id', 0);
            }

            $answers = $this->postParam('answers', []);

            // Детальне логування для діагностики
            error_log("Survey submission - Survey ID: " . $surveyId);
            error_log("Survey submission - Answers: " . print_r($answers, true));

            if ($surveyId <= 0) {
                throw new ValidationException(['Невірний ID опитування']);
            }

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            if (!$survey['is_active']) {
                throw new ForbiddenException('Це опитування неактивне');
            }

            // Перевіряємо чи користувач вже відповідав
            if (Session::isLoggedIn()) {
                $userId = Session::getUserId();
                if (SurveyResponse::hasUserResponded($surveyId, $userId)) {
                    throw new ConflictException('Ви вже проходили це опитування');
                }
            }

            $questions = Question::getBySurveyId($surveyId, true);
            $this->questionService->loadQuestionsWithOptions($questions);

            if (empty($questions)) {
                throw new ValidationException(['Опитування не містить питань']);
            }

            // Валідуємо відповіді
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

                error_log("Created response with ID: " . $responseId);

                // Зберігаємо відповіді та підраховуємо результат
                $totalScore = 0;
                $maxScore = Question::getMaxPointsForSurvey($surveyId);
                $isQuiz = Question::isQuiz($surveyId);

                foreach ($questions as $question) {
                    $questionId = $question['id'];
                    $questionType = $question['question_type'];

                    if (!isset($answers[$questionId])) {
                        continue; // Пропускаємо необов'язкові питання без відповідей
                    }

                    $answer = $answers[$questionId];
                    $result = ['is_correct' => false, 'points' => 0];

                    // Перевіряємо правильність відповіді, якщо це квіз
                    if ($isQuiz) {
                        $result = Question::checkUserAnswer($questionId, $answer);
                        $totalScore += $result['points'];
                        error_log("Question {$questionId}: " . print_r($result, true));
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
                        'is_quiz' => $isQuiz,
                        'redirect_url' => $redirectUrl
                    ], $message);
                } else {
                    $this->redirectWithMessage($redirectUrl, 'success', $message);
                }

            } catch (Exception $e) {
                error_log("Error saving survey response: " . $e->getMessage());
                throw new DatabaseException($e->getMessage(), 'Помилка при збереженні відповідей');
            }
        });
    }

    /**
     * Зберегти відповідь на питання з перевіркою правильності - ВИПРАВЛЕНА ВЕРСІЯ
     */
    private function saveQuestionAnswer(int $responseId, array $question, $answer, array $result): void
    {
        $questionId = $question['id'];
        $questionType = $question['question_type'];
        $isCorrect = $result['is_correct'] ?? false;
        $pointsEarned = $result['points'] ?? 0;

        error_log("Saving answer for question {$questionId}, type: {$questionType}, answer: " . print_r($answer, true));

        try {
            switch ($questionType) {
                case Question::TYPE_RADIO:
                    if (is_numeric($answer) && $answer > 0) {
                        QuestionAnswer::createOptionAnswer($responseId, $questionId, (int)$answer, $isCorrect, $pointsEarned);
                        error_log("Saved radio answer: option {$answer}");
                    }
                    break;

                case Question::TYPE_CHECKBOX:
                    if (is_array($answer) && !empty($answer)) {
                        $validAnswers = array_filter($answer, function($optionId) {
                            return is_numeric($optionId) && $optionId > 0;
                        });

                        if (!empty($validAnswers)) {
                            QuestionAnswer::createMultipleOptionAnswers($responseId, $questionId, $validAnswers, $isCorrect, $pointsEarned);
                            error_log("Saved checkbox answers: " . implode(',', $validAnswers));
                        }
                    }
                    break;

                case Question::TYPE_TEXT:
                case Question::TYPE_TEXTAREA:
                    $answerText = trim($answer);
                    if (!empty($answerText)) {
                        QuestionAnswer::createTextAnswer($responseId, $questionId, $answerText, $isCorrect, $pointsEarned);
                        error_log("Saved text answer: {$answerText}");
                    }
                    break;

                default:
                    error_log("Unknown question type: {$questionType}");
                    break;
            }
        } catch (Exception $e) {
            error_log("Error saving answer for question {$questionId}: " . $e->getMessage());
            throw $e;
        }
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