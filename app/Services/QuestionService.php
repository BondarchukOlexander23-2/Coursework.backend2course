<?php
/**
 * Сервіс для роботи з питаннями
 */
class QuestionService
{
    /**
     * Завантажити варіанти відповідей для питань
     */
    public function loadQuestionsWithOptions(array &$questions): void
    {
        foreach ($questions as &$question) {
            if ($question['has_options']) {
                $question['options'] = QuestionOption::getByQuestionId($question['id']);
            }
        }
    }

    /**
     * Створити питання з варіантами відповідей
     */
    public function createQuestionWithOptions(
        int $surveyId,
        string $questionText,
        string $questionType,
        bool $isRequired,
        ?string $correctAnswer,
        int $points,
        array $options,
        array $correctOptions
    ): int {
        try {
            error_log("DEBUG QuestionService: Creating question - surveyId: $surveyId, type: $questionType");

            $orderNumber = Question::getNextOrderNumber($surveyId);
            error_log("DEBUG QuestionService: Next order number: $orderNumber");

            $questionId = Question::create(
                $surveyId,
                $questionText,
                $questionType,
                $isRequired,
                $orderNumber,
                $correctAnswer,
                $points
            );
            error_log("DEBUG QuestionService: Question created with ID: $questionId");

            // Додаємо варіанти відповідей тільки для типів radio та checkbox
            if (in_array($questionType, [Question::TYPE_RADIO, Question::TYPE_CHECKBOX])) {
                error_log("DEBUG QuestionService: Processing options for question type: $questionType");
                error_log("DEBUG QuestionService: Options array: " . json_encode($options));
                error_log("DEBUG QuestionService: Correct options array: " . json_encode($correctOptions));

                if (!empty($options) && is_array($options)) {
                    $optionsData = [];
                    foreach ($options as $index => $optionText) {
                        if (!empty(trim($optionText))) {
                            $isCorrect = in_array($index, $correctOptions) || in_array((string)$index, $correctOptions);
                            $optionsData[] = [
                                'text' => trim($optionText),
                                'is_correct' => $isCorrect
                            ];
                            error_log("DEBUG QuestionService: Option $index: '$optionText', correct: " . ($isCorrect ? 'yes' : 'no'));
                        }
                    }

                    if (!empty($optionsData)) {
                        error_log("DEBUG QuestionService: Creating " . count($optionsData) . " options");
                        QuestionOption::createMultiple($questionId, $optionsData);
                        error_log("DEBUG QuestionService: Options created successfully");
                    } else {
                        error_log("DEBUG QuestionService: No valid options to create");
                    }
                } else {
                    error_log("DEBUG QuestionService: Options array is empty or not array");
                }
            } else {
                error_log("DEBUG QuestionService: Skipping options for text question type: $questionType");
            }

            return $questionId;

        } catch (Exception $e) {
            error_log("DEBUG QuestionService: Exception in createQuestionWithOptions: " . $e->getMessage());
            error_log("DEBUG QuestionService: Exception trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Оновити питання з варіантами відповідей
     */
    public function updateQuestionWithOptions(
        int $questionId,
        string $questionText,
        string $questionType,
        bool $isRequired,
        ?string $correctAnswer,
        int $points,
        array $options,
        array $correctOptions
    ): bool {
        // Оновлюємо основні дані питання
        $question = Question::findById($questionId);
        if (!$question) {
            throw new Exception("Question not found");
        }

        $updated = Question::update(
            $questionId,
            $questionText,
            $questionType,
            $isRequired,
            $question['order_number'],
            $correctAnswer,
            $points
        );

        // Оновлюємо варіанти відповідей якщо потрібно
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
            QuestionOption::replaceForQuestion($questionId, $optionsData);
        } else {
            // Видаляємо варіанти для текстових питань
            QuestionOption::deleteByQuestionId($questionId);
        }

        return $updated;
    }

    /**
     * Видалити питання разом з варіантами
     */
    public function deleteQuestion(int $questionId): void
    {
        QuestionOption::deleteByQuestionId($questionId);
        Question::delete($questionId);
    }

    /**
     * Змінити порядок питань
     */
    public function reorderQuestions(array $questionIds): void
    {
        $orderNumber = 1;
        foreach ($questionIds as $questionId) {
            if (is_numeric($questionId)) {
                Database::execute(
                    "UPDATE questions SET order_number = ? WHERE id = ?",
                    [$orderNumber, (int)$questionId]
                );
                $orderNumber++;
            }
        }
    }

    /**
     * Дублювати питання
     */
    public function duplicateQuestion(int $questionId, int $surveyId): int
    {
        $question = Question::findById($questionId, true);
        if (!$question) {
            throw new Exception("Question not found");
        }

        $newOrderNumber = Question::getNextOrderNumber($surveyId);
        $newQuestionId = Question::create(
            $surveyId,
            $question['question_text'] . ' (копія)',
            $question['question_type'],
            $question['is_required'],
            $newOrderNumber,
            $question['correct_answer'],
            $question['points']
        );

        // Копіюємо варіанти відповідей, якщо є
        if ($question['has_options']) {
            $options = QuestionOption::getByQuestionId($questionId);
            QuestionOption::createMultiple($newQuestionId, $options);
        }

        return $newQuestionId;
    }
}