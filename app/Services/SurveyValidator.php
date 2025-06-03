<?php

/**
 * Сервіс валідації для опитувань
 * Відповідає принципу Single Responsibility - тільки валідація даних
 */
class SurveyValidator
{
    /**
     * Валідація даних опитування
     */
    public function validateSurveyData(string $title, string $description): array
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
    public function validateQuestionData(string $questionText, string $questionType, array $options, int $points): array
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
    public function validateAnswers(array $questions, array $answers): array
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
    public function validateAndGetSurvey(int $surveyId): ?array
    {
        if ($surveyId <= 0) {
            return null;
        }

        return Survey::findById($surveyId);
    }
}