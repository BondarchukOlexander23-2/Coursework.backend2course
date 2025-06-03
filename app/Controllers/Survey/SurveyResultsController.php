<?php

/**
 * Контролер для відображення результатів опитувань та квізів з HTML всередині
 */
class SurveyResultsController
{
    private SurveyValidator $validator;

    public function __construct()
    {
        $this->validator = new SurveyValidator();
    }

    /**
     * Показати результати опитування або квізу
     */
    public function results(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);
        $responseId = (int)($_GET['response'] ?? 0);

        $survey = $this->validator->validateAndGetSurvey($surveyId);
        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        $questions = Question::getBySurveyId($surveyId);
        $isQuiz = Question::isQuiz($surveyId);

        if ($isQuiz) {
            $this->showQuizResults($survey, $questions, $responseId);
        } else {
            $this->showSurveyResults($survey, $questions);
        }
    }

    /**
     * Показати результати квізу
     */
    private function showQuizResults(array $survey, array $questions, int $responseId): void
    {
        $quizStats = SurveyResponse::getQuizStats($survey['id']);
        $topResults = SurveyResponse::getTopResults($survey['id'], 10);
        $userResult = null;

        if ($responseId > 0) {
            $userResult = SurveyResponse::findById($responseId);
        }

        $content = $this->renderQuizResults($survey, $questions, $quizStats, $topResults, $userResult);
        echo $content;
    }

    /**
     * Показати результати звичайного опитування
     */
    private function showSurveyResults(array $survey, array $questions): void
    {
        $questionStats = [];
        foreach ($questions as $question) {
            $questionStats[$question['id']] = QuestionAnswer::getQuestionStats($question['id']);
        }

        $totalResponses = SurveyResponse::getCountBySurveyId($survey['id']);

        $content = $this->renderSurveyResults($survey, $questions, $questionStats, $totalResponses);
        echo $content;
    }

    // === HTML РЕНДЕРИНГ ===

    /**
     * Відобразити результати квізу
     */
    private function renderQuizResults(
        array $survey,
        array $questions,
        array $stats,
        array $topResults,
        ?array $userResult
    ): string {
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
     * Відобразити звичайні результати опитування
     */
    private function renderSurveyResults(
        array $survey,
        array $questions,
        array $questionStats,
        int $totalResponses
    ): string {
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

    // === ДОПОМІЖНІ МЕТОДИ ===

    /**
     * Визначити рівень результату
     */
    private function getResultLevel(float $percentage): string
    {
        if ($percentage >= 90) return 'Відмінно';
        if ($percentage >= 75) return 'Добре';
        if ($percentage >= 60) return 'Задовільно';
        return 'Незадовільно';
    }

    /**
     * Визначити CSS клас для рівня результату
     */
    private function getResultLevelClass(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'satisfactory';
        return 'poor';
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