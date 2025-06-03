<?php

/**
 * Покращена модель питання з підтримкою квізів та правильних відповідей
 * Відповідає принципу Single Responsibility
 */
class Question
{
    private int $id;
    private int $surveyId;
    private string $questionText;
    private string $questionType;
    private bool $isRequired;
    private int $orderNumber;
    private ?string $correctAnswer; // Для текстових питань
    private int $points; // Бали за правильну відповідь

    // Типи питань
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';

    public function __construct(
        int $surveyId,
        string $questionText,
        string $questionType,
        bool $isRequired = false,
        int $orderNumber = 1,
        ?string $correctAnswer = null,
        int $points = 1,
        int $id = 0
    ) {
        $this->validateQuestionType($questionType);
        $this->validatePoints($points);

        $this->id = $id;
        $this->surveyId = $surveyId;
        $this->questionText = $questionText;
        $this->questionType = $questionType;
        $this->isRequired = $isRequired;
        $this->orderNumber = $orderNumber;
        $this->correctAnswer = $correctAnswer;
        $this->points = $points;
    }

    /**
     * Валідація типу питання
     */
    private function validateQuestionType(string $type): void
    {
        $validTypes = [self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_TEXT, self::TYPE_TEXTAREA];
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException("Invalid question type: {$type}");
        }
    }

    /**
     * Валідація балів
     */
    private function validatePoints(int $points): void
    {
        if ($points < 0) {
            throw new InvalidArgumentException("Points cannot be negative");
        }
    }

    /**
     * Створити нове питання
     */
    public static function create(
        int $surveyId,
        string $questionText,
        string $questionType,
        bool $isRequired = false,
        int $orderNumber = 1,
        ?string $correctAnswer = null,
        int $points = 1
    ): int {
        $question = new self($surveyId, $questionText, $questionType, $isRequired, $orderNumber, $correctAnswer, $points);

        $query = "INSERT INTO questions (survey_id, question_text, question_type, is_required, order_number, correct_answer, points) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        return Database::insert($query, [
            $question->surveyId,
            $question->questionText,
            $question->questionType,
            $question->isRequired ? 1 : 0,
            $question->orderNumber,
            $question->correctAnswer,
            $question->points
        ]);
    }

    /**
     * Отримати всі питання опитування з правильними відповідями
     */
    public static function getBySurveyId(int $surveyId, bool $includeCorrectAnswers = false): array
    {
        $query = "SELECT * FROM questions WHERE survey_id = ? ORDER BY order_number ASC";
        $rows = Database::select($query, [$surveyId]);

        $questions = [];
        foreach ($rows as $row) {
            $questionData = [
                'id' => $row['id'],
                'survey_id' => $row['survey_id'],
                'question_text' => $row['question_text'],
                'question_type' => $row['question_type'],
                'is_required' => (bool)$row['is_required'],
                'order_number' => $row['order_number'],
                'points' => $row['points'] ?? 1,
                'has_options' => in_array($row['question_type'], [self::TYPE_RADIO, self::TYPE_CHECKBOX])
            ];

            // Додаємо правильні відповіді тільки якщо запитано
            if ($includeCorrectAnswers) {
                $questionData['correct_answer'] = $row['correct_answer'];
            }

            $questions[] = $questionData;
        }

        return $questions;
    }

    /**
     * Знайти питання за ID
     */
    public static function findById(int $id, bool $includeCorrectAnswer = false): ?array
    {
        $query = "SELECT * FROM questions WHERE id = ?";
        $row = Database::selectOne($query, [$id]);

        if (!$row) {
            return null;
        }

        $questionData = [
            'id' => $row['id'],
            'survey_id' => $row['survey_id'],
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'is_required' => (bool)$row['is_required'],
            'order_number' => $row['order_number'],
            'points' => $row['points'] ?? 1,
            'has_options' => in_array($row['question_type'], [self::TYPE_RADIO, self::TYPE_CHECKBOX])
        ];

        if ($includeCorrectAnswer) {
            $questionData['correct_answer'] = $row['correct_answer'];
        }

        return $questionData;
    }

    /**
     * Оновити питання
     */
    public static function update(
        int $id,
        string $questionText,
        string $questionType,
        bool $isRequired,
        int $orderNumber,
        ?string $correctAnswer = null,
        int $points = 1
    ): bool {
        // Валідуємо через конструктор
        $question = new self(0, $questionText, $questionType, $isRequired, $orderNumber, $correctAnswer, $points, $id);

        $query = "UPDATE questions 
                  SET question_text = ?, question_type = ?, is_required = ?, order_number = ?, correct_answer = ?, points = ?
                  WHERE id = ?";

        return Database::execute($query, [
                $questionText,
                $questionType,
                $isRequired ? 1 : 0,
                $orderNumber,
                $correctAnswer,
                $points,
                $id
            ]) > 0;
    }

    /**
     * Встановити правильну відповідь для текстового питання
     */
    public static function setCorrectAnswer(int $id, ?string $correctAnswer): bool
    {
        $query = "UPDATE questions SET correct_answer = ? WHERE id = ?";
        return Database::execute($query, [$correctAnswer, $id]) > 0;
    }

    /**
     * Встановити бали за питання
     */
    public static function setPoints(int $id, int $points): bool
    {
        if ($points < 0) {
            throw new InvalidArgumentException("Points cannot be negative");
        }

        $query = "UPDATE questions SET points = ? WHERE id = ?";
        return Database::execute($query, [$points, $id]) > 0;
    }

    /**
     * Видалити питання
     */
    public static function delete(int $id): bool
    {
        $query = "DELETE FROM questions WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Видалити всі питання опитування
     */
    public static function deleteBySurveyId(int $surveyId): bool
    {
        $query = "DELETE FROM questions WHERE survey_id = ?";
        return Database::execute($query, [$surveyId]) > 0;
    }

    /**
     * Отримати наступний номер порядку для опитування
     */
    public static function getNextOrderNumber(int $surveyId): int
    {
        $query = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order FROM questions WHERE survey_id = ?";
        $result = Database::selectOne($query, [$surveyId]);
        return $result['next_order'] ?? 1;
    }

    /**
     * Перевірити чи має питання варіанти відповідей
     */
    public function hasOptions(): bool
    {
        return in_array($this->questionType, [self::TYPE_RADIO, self::TYPE_CHECKBOX]);
    }

    /**
     * Перевірити відповідь користувача
     */
    public static function checkUserAnswer(int $questionId, $userAnswer): array
    {
        $question = self::findById($questionId, true);
        if (!$question) {
            return ['is_correct' => false, 'points' => 0, 'message' => 'Питання не знайдено'];
        }

        $isCorrect = false;
        $points = 0;
        $message = '';

        switch ($question['question_type']) {
            case self::TYPE_RADIO:
                // Перевіряємо один варіант відповіді
                $correctOptions = QuestionOption::getCorrectByQuestionId($questionId);
                if (!empty($correctOptions)) {
                    $correctIds = array_column($correctOptions, 'id');
                    $isCorrect = in_array((int)$userAnswer, $correctIds);
                }
                break;

            case self::TYPE_CHECKBOX:
                // Перевіряємо множинні варіанти
                $correctOptions = QuestionOption::getCorrectByQuestionId($questionId);
                if (!empty($correctOptions)) {
                    $correctIds = array_column($correctOptions, 'id');
                    $userAnswerIds = is_array($userAnswer) ? array_map('intval', $userAnswer) : [];

                    // Правильно, якщо вибрані саме ті варіанти, що правильні
                    sort($correctIds);
                    sort($userAnswerIds);
                    $isCorrect = $correctIds === $userAnswerIds;
                }
                break;

            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                // Перевіряємо текстову відповідь
                if (!empty($question['correct_answer'])) {
                    $correctAnswer = trim(strtolower($question['correct_answer']));
                    $userAnswerText = trim(strtolower($userAnswer));
                    $isCorrect = $correctAnswer === $userAnswerText;
                }
                break;
        }

        if ($isCorrect) {
            $points = $question['points'] ?? 1;
            $message = 'Правильно!';
        } else {
            $message = 'Неправильно';
        }

        return [
            'is_correct' => $isCorrect,
            'points' => $points,
            'message' => $message
        ];
    }

    /**
     * Отримати максимальну кількість балів за опитування
     */
    public static function getMaxPointsForSurvey(int $surveyId): int
    {
        $query = "SELECT SUM(points) as max_points FROM questions WHERE survey_id = ?";
        $result = Database::selectOne($query, [$surveyId]);
        return $result['max_points'] ?? 0;
    }

    /**
     * Перевірити чи опитування є квізом (має правильні відповіді)
     */
    public static function isQuiz(int $surveyId): bool
    {
        // Перевіряємо чи є правильні відповіді в текстових питаннях
        $textQuery = "SELECT COUNT(*) as count FROM questions WHERE survey_id = ? AND correct_answer IS NOT NULL";
        $textResult = Database::selectOne($textQuery, [$surveyId]);

        if (($textResult['count'] ?? 0) > 0) {
            return true;
        }

        // Перевіряємо чи є правильні відповіді в питаннях з варіантами
        $optionQuery = "SELECT COUNT(DISTINCT q.id) as count 
                       FROM questions q 
                       JOIN question_options qo ON q.id = qo.question_id 
                       WHERE q.survey_id = ? AND qo.is_correct = 1";
        $optionResult = Database::selectOne($optionQuery, [$surveyId]);

        return ($optionResult['count'] ?? 0) > 0;
    }

    /**
     * Отримати всі допустимі типи питань
     */
    public static function getQuestionTypes(): array
    {
        return [
            self::TYPE_RADIO => 'Один варіант (радіо)',
            self::TYPE_CHECKBOX => 'Декілька варіантів (чекбокс)',
            self::TYPE_TEXT => 'Короткий текст',
            self::TYPE_TEXTAREA => 'Довгий текст'
        ];
    }

    /**
     * Getters
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getSurveyId(): int
    {
        return $this->surveyId;
    }

    public function getQuestionText(): string
    {
        return $this->questionText;
    }

    public function getQuestionType(): string
    {
        return $this->questionType;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function getOrderNumber(): int
    {
        return $this->orderNumber;
    }

    public function getCorrectAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    /**
     * Конвертувати об'єкт в масив
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'survey_id' => $this->surveyId,
            'question_text' => $this->questionText,
            'question_type' => $this->questionType,
            'is_required' => $this->isRequired,
            'order_number' => $this->orderNumber,
            'correct_answer' => $this->correctAnswer,
            'points' => $this->points,
            'has_options' => $this->hasOptions()
        ];
    }
}