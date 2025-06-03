<?php
/**
 * Сервіс для роботи з питаннями
 * Відповідає принципу Single Responsibility - логіка обробки питань
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

        return $questionId;
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

        // Копіюємо варіанти відповідей
        if ($question['has_options']) {
            $options = QuestionOption::getByQuestionId($questionId);
            QuestionOption::createMultiple($newQuestionId, $options);
        }

        return $newQuestionId;
    }
}