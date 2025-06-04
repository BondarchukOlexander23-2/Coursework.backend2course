<?php

/**
 * Виправлений сервіс валідації для опитувань
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

        $title = trim($title);
        if (empty($title)) {
            $errors[] = 'Назва опитування є обов\'язковою';
        }
        if (strlen($title) < 3) {
            $errors[] = 'Назва повинна містити мінімум 3 символи';
        }
        if (strlen($title) > 255) {
            $errors[] = 'Назва занадто довга (максимум 255 символів)';
        }

        if (strlen($description) > 1000) {
            $errors[] = 'Опис занадто довгий (максимум 1000 символів)';
        }

        return $errors;
    }

    /**
     * Валідація даних питання
     */
    public function validateQuestionData(string $questionText, string $questionType, array $options, int $points): array
    {
        $errors = [];

        $questionText = trim($questionText);
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
            $validOptions = array_filter($options, function($opt) {
                return !empty(trim($opt));
            });
            if (count($validOptions) < 2) {
                $errors[] = 'Додайте принаймні 2 варіанти відповіді';
            }
        }

        return $errors;
    }

    /**
     * ВИПРАВЛЕНА валідація відповідей користувача
     */
    public function validateAnswers(array $questions, array $answers): array
    {
        $errors = [];

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $isRequired = $question['is_required'];
            $questionType = $question['question_type'];
            $questionText = $question['question_text'];

            // Перевіряємо обов'язкові питання
            if ($isRequired) {
                if (!isset($answers[$questionId])) {
                    $errors[] = "Питання '{$questionText}' є обов'язковим";
                    continue;
                }

                $answer = $answers[$questionId];

                // Перевіряємо залежно від типу питання
                switch ($questionType) {
                    case Question::TYPE_RADIO:
                        if (!is_numeric($answer) || $answer <= 0) {
                            $errors[] = "Оберіть відповідь для питання '{$questionText}'";
                        }
                        break;

                    case Question::TYPE_CHECKBOX:
                        if (!is_array($answer) || empty($answer)) {
                            $errors[] = "Оберіть хоча б один варіант для питання '{$questionText}'";
                        } else {
                            // Перевіряємо що всі варіанти є числами
                            $validAnswers = array_filter($answer, function($optionId) {
                                return is_numeric($optionId) && $optionId > 0;
                            });
                            if (empty($validAnswers)) {
                                $errors[] = "Оберіть правильні варіанти для питання '{$questionText}'";
                            }
                        }
                        break;

                    case Question::TYPE_TEXT:
                    case Question::TYPE_TEXTAREA:
                        if (!is_string($answer) || empty(trim($answer))) {
                            $errors[] = "Введіть відповідь для питання '{$questionText}'";
                        }
                        break;
                }
            } else {
                // Для необов'язкових питань перевіряємо формат, якщо відповідь надана
                if (isset($answers[$questionId]) && !empty($answers[$questionId])) {
                    $answer = $answers[$questionId];

                    switch ($questionType) {
                        case Question::TYPE_RADIO:
                            if (!is_numeric($answer) || $answer <= 0) {
                                $errors[] = "Невірний формат відповіді для питання '{$questionText}'";
                            }
                            break;

                        case Question::TYPE_CHECKBOX:
                            if (!is_array($answer)) {
                                $errors[] = "Невірний формат відповіді для питання '{$questionText}'";
                            } else {
                                $validAnswers = array_filter($answer, function($optionId) {
                                    return is_numeric($optionId) && $optionId > 0;
                                });
                                if (empty($validAnswers)) {
                                    $errors[] = "Невірний формат відповіді для питання '{$questionText}'";
                                }
                            }
                            break;

                        case Question::TYPE_TEXT:
                        case Question::TYPE_TEXTAREA:
                            if (!is_string($answer)) {
                                $errors[] = "Невірний формат відповіді для питання '{$questionText}'";
                            }
                            break;
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

    /**
     * Валідація ID питання
     */
    public function validateQuestionId(int $questionId): array
    {
        $errors = [];

        if ($questionId <= 0) {
            $errors[] = 'Невірний ID питання';
        } else {
            $question = Question::findById($questionId);
            if (!$question) {
                $errors[] = 'Питання не знайдено';
            }
        }

        return $errors;
    }

    /**
     * Валідація параметрів експорту
     */
    public function validateExportParameters(int $surveyId, string $format): array
    {
        $errors = [];

        if ($surveyId <= 0) {
            $errors[] = 'Невірний ID опитування';
        }

        $allowedFormats = ['csv', 'xlsx'];
        if (!in_array($format, $allowedFormats)) {
            $errors[] = 'Непідтримуваний формат експорту';
        }

        return $errors;
    }

    /**
     * Валідація прав доступу до опитування
     */
    public function validateSurveyAccess(int $surveyId, int $userId): array
    {
        $errors = [];

        $survey = $this->validateAndGetSurvey($surveyId);
        if (!$survey) {
            $errors[] = 'Опитування не знайдено';
            return $errors;
        }

        if (!Survey::isAuthor($surveyId, $userId)) {
            $errors[] = 'У вас немає прав для цього опитування';
        }

        return $errors;
    }

    /**
     * Валідація можливості проходження опитування
     */
    public function validateSurveyAvailability(int $surveyId, ?int $userId = null): array
    {
        $errors = [];

        $survey = $this->validateAndGetSurvey($surveyId);
        if (!$survey) {
            $errors[] = 'Опитування не знайдено';
            return $errors;
        }

        if (!$survey['is_active']) {
            $errors[] = 'Це опитування неактивне';
        }

        // Перевіряємо чи є питання
        $questions = Question::getBySurveyId($surveyId);
        if (empty($questions)) {
            $errors[] = 'Опитування не містить питань';
        }

        // Перевіряємо чи користувач вже проходив опитування
        if ($userId && SurveyResponse::hasUserResponded($surveyId, $userId)) {
            $errors[] = 'Ви вже проходили це опитування';
        }

        return $errors;
    }

    /**
     * Валідація масиву варіантів відповідей
     */
    public function validateOptions(array $options): array
    {
        $errors = [];

        if (empty($options)) {
            $errors[] = 'Додайте варіанти відповідей';
            return $errors;
        }

        $validOptions = 0;
        foreach ($options as $index => $option) {
            if (is_string($option) && !empty(trim($option))) {
                $validOptions++;
                if (strlen(trim($option)) > 255) {
                    $errors[] = "Варіант " . ($index + 1) . " занадто довгий (максимум 255 символів)";
                }
            }
        }

        if ($validOptions < 2) {
            $errors[] = 'Додайте принаймні 2 непорожні варіанти відповідей';
        }

        return $errors;
    }

    /**
     * Валідація правильних відповідей для квізу
     */
    public function validateCorrectAnswers(string $questionType, array $options, array $correctOptions): array
    {
        $errors = [];

        if (in_array($questionType, [Question::TYPE_RADIO, Question::TYPE_CHECKBOX])) {
            $validOptions = array_filter($options, function($opt) {
                return !empty(trim($opt));
            });

            foreach ($correctOptions as $correctIndex) {
                if (!is_numeric($correctIndex) || $correctIndex < 0 || $correctIndex >= count($validOptions)) {
                    $errors[] = 'Невірні індекси правильних відповідей';
                    break;
                }
            }

            // Для радіо кнопок може бути тільки одна правильна відповідь
            if ($questionType === Question::TYPE_RADIO && count($correctOptions) > 1) {
                $errors[] = 'Для питання з одним варіантом може бути тільки одна правильна відповідь';
            }
        }

        return $errors;
    }
}