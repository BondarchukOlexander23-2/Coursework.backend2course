<?php

/**
 * Простий SurveyResponseController
 * Створіть файл app/Controllers/Survey/SurveyResponseController.php
 */
class SurveyResponseController extends BaseController
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
     * Обробити відповіді на опитування
     */
    public function submit(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('survey_id');
            if (!$surveyId) {
                $surveyId = (int)$this->postParam('survey_id', 0);
            }

            $answers = $this->postParam('answers', []);

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
     * Зберегти відповідь на питання з перевіркою правильності
     */
    private function saveQuestionAnswer(int $responseId, array $question, $answer, array $result): void
    {
        $questionId = $question['id'];
        $questionType = $question['question_type'];
        $isCorrect = $result['is_correct'] ?? false;
        $pointsEarned = $result['points'] ?? 0;

        try {
            switch ($questionType) {
                case Question::TYPE_RADIO:
                    if (is_numeric($answer) && $answer > 0) {
                        QuestionAnswer::createOptionAnswer($responseId, $questionId, (int)$answer, $isCorrect, $pointsEarned);
                    }
                    break;

                case Question::TYPE_CHECKBOX:
                    if (is_array($answer) && !empty($answer)) {
                        $validAnswers = array_filter($answer, function($optionId) {
                            return is_numeric($optionId) && $optionId > 0;
                        });

                        if (!empty($validAnswers)) {
                            QuestionAnswer::createMultipleOptionAnswers($responseId, $questionId, $validAnswers, $isCorrect, $pointsEarned);
                        }
                    }
                    break;

                case Question::TYPE_TEXT:
                case Question::TYPE_TEXTAREA:
                    $answerText = trim($answer);
                    if (!empty($answerText)) {
                        QuestionAnswer::createTextAnswer($responseId, $questionId, $answerText, $isCorrect, $pointsEarned);
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("Error saving answer for question {$questionId}: " . $e->getMessage());
            throw $e;
        }
    }
}