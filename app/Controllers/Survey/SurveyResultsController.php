<?php

/**
 * Оновлений контролер для відображення результатів опитувань та квізів з BaseController
 */
class SurveyResultsController extends BaseController
{
    private SurveyValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new SurveyValidator();
    }

    /**
     * Показати результати опитування або квізу
     */
    public function results(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('id');
            $responseId = $this->getIntParam('response');

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $questions = Question::getBySurveyId($surveyId);
            $isQuiz = Question::isQuiz($surveyId);

            if ($isQuiz) {
                $this->showQuizResults($survey, $questions, $responseId);
            } else {
                $this->showSurveyResults($survey, $questions);
            }
        });
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

        // Результати квізу кешуємо на 15 хвилин
        $this->responseManager
            ->setCacheHeaders(900)
            ->sendSuccess($content);
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

        // Результати опитування кешуємо на 30 хвилин
        $this->responseManager
            ->setCacheHeaders(1800)
            ->sendSuccess($content);
    }

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

        return $this->buildHtmlPage("Результати квізу", "
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
                " . (Session::isLoggedIn() && Survey::isAuthor($survey['id'], Session::getUserId()) ?
                "<a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати</a>" : "") . "
            </div>
            
            <script>
                // Анімація для результатів
                document.addEventListener('DOMContentLoaded', function() {
                    const statNumbers = document.querySelectorAll('.stat-number');
                    statNumbers.forEach(el => {
                        const target = parseInt(el.textContent);
                        let current = 0;
                        const increment = target / 50;
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= target) {
                                current = target;
                                clearInterval(timer);
                            }
                            el.textContent = Math.floor(current);
                        }, 30);
                    });
                });
            </script>
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
            $resultsHtml = '<div class="no-results">
                <h3>Ще немає відповідей</h3>
                <p>Це опитування ще не має відповідей. Поділіться посиланням щоб отримати перші результати!</p>
                <div class="share-buttons">
                    <button onclick="copyToClipboard()" class="btn btn-primary">Копіювати посилання</button>
                    <a href="/surveys/view?id=' . $survey['id'] . '" class="btn btn-secondary">Пройти самому</a>
                </div>
            </div>';
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
                            $correctnessClass = '';
                            if (isset($answer['is_correct'])) {
                                $correctnessClass = $answer['is_correct'] ? ' correct-text' : ' incorrect-text';
                            }
                            $questionResultHtml .= "<p class='text-answer{$correctnessClass}'>\"$answerText\"</p>";
                        }
                        if (count($textAnswers) > 10) {
                            $remaining = count($textAnswers) - 10;
                            $questionResultHtml .= "<p class='more-answers'>... та ще {$remaining} відповідей</p>";
                        }
                        $questionResultHtml .= "</div>";
                    } else {
                        $questionResultHtml .= "<p class='no-answers'>Немає відповідей на це питання.</p>";
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

        return $this->buildHtmlPage("Результати опитування", "
            <div class='header-actions'>
                <div>
                    <h1>Результати: " . htmlspecialchars($survey['title']) . "</h1>
                    <p><strong>Всього відповідей: {$totalResponses}</strong></p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-summary'>
                <div class='summary-stats'>
                    <div class='summary-item'>
                        <span class='summary-number'>{$totalResponses}</span>
                        <span class='summary-label'>Відповідей</span>
                    </div>
                    <div class='summary-item'>
                        <span class='summary-number'>" . count($questions) . "</span>
                        <span class='summary-label'>Питань</span>
                    </div>
                    <div class='summary-item'>
                        <span class='summary-number'>" . date('d.m.Y', strtotime($survey['created_at'])) . "</span>
                        <span class='summary-label'>Створено</span>
                    </div>
                </div>
            </div>
            
            <div class='results'>
                {$resultsHtml}
            </div>
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти опитування</a>
                " . (Session::isLoggedIn() && Survey::isAuthor($survey['id'], Session::getUserId()) ?
                "<a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати</a>
                     <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-outline'>Експорт CSV</a>" : "") . "
            </div>
            
            <style>
                .no-results {
                    text-align: center;
                    padding: 3rem;
                    background: #f8f9fa;
                    border-radius: 12px;
                    margin: 2rem 0;
                }
                .share-buttons {
                    margin-top: 1.5rem;
                }
                .survey-summary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 12px;
                    margin-bottom: 2rem;
                }
                .summary-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 2rem;
                    text-align: center;
                }
                .summary-number {
                    display: block;
                    font-size: 2.5rem;
                    font-weight: bold;
                    color: #f1c40f;
                }
                .summary-label {
                    font-size: 1rem;
                    opacity: 0.9;
                }
                .text-answer {
                    background: #f8f9fa;
                    padding: 0.8rem;
                    border-radius: 6px;
                    margin: 0.5rem 0;
                    border-left: 4px solid #dee2e6;
                }
                .text-answer.correct-text {
                    background: #d4edda;
                    border-left-color: #28a745;
                }
                .text-answer.incorrect-text {
                    background: #f8d7da;
                    border-left-color: #dc3545;
                }
                .no-answers {
                    color: #6c757d;
                    font-style: italic;
                    text-align: center;
                    padding: 2rem;
                }
                .more-answers {
                    color: #6c757d;
                    font-style: italic;
                    text-align: center;
                    margin-top: 1rem;
                }
            </style>
            
            <script>
                function copyToClipboard() {
                    const url = window.location.origin + '/surveys/view?id={$survey['id']}';
                    navigator.clipboard.writeText(url).then(function() {
                        alert('Посилання скопійовано!');
                    });
                }
                
                // Анімація прогрес-барів
                document.addEventListener('DOMContentLoaded', function() {
                    const progressBars = document.querySelectorAll('.progress');
                    progressBars.forEach(bar => {
                        const width = bar.style.width;
                        bar.style.width = '0%';
                        setTimeout(() => {
                            bar.style.width = width;
                        }, 300);
                    });
                });
            </script>
        ");
    }

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
}