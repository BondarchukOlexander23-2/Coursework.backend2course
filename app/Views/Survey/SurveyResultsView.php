<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyResultsView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $isQuiz = $this->get('isQuiz', false);

        if ($isQuiz) {
            return $this->renderQuizResults();
        } else {
            return $this->renderSurveyResults();
        }
    }

    private function renderQuizResults(): string
    {
        $survey = $this->get('survey');
        $stats = $this->get('stats');
        $topResults = $this->get('topResults', []);
        $userResult = $this->get('userResult');

        $userResultHtml = '';
        if ($userResult) {
            $percentage = $userResult['percentage'];
            $level = $this->getResultLevel($percentage);
            $levelClass = $this->getResultLevelClass($percentage);

            $userResultHtml = "
                <div class='user-result highlight'>
                    <h3>Ваш результат</h3>
                    <div class='score-display'>
                        <span class='score'>{$userResult['total_score']}/{$userResult['max_score']}</span>
                        <span class='percentage'>{$percentage}%</span>
                        <span class='level {$levelClass}'>{$level}</span>
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
                $topResultsHtml .= "<li>" . $this->escape($userName) . ": {$result['total_score']}/{$result['max_score']} ({$result['percentage']}%)</li>";
            }
            $topResultsHtml .= '</ol></div>';
        }

        $editLink = '';
        if (Session::isLoggedIn() && Survey::isAuthor($survey['id'], Session::getUserId())) {
            $editLink = "<a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати</a>";
        }

        return "
            <div class='header-actions'>
                <div>
                    <h1>Квіз: " . $this->escape($survey['title']) . "</h1>
                </div>
                " . $this->component('Navigation') . "
            </div>
            
            {$userResultHtml}
            {$statsHtml}
            {$topResultsHtml}
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти ще раз</a>
                {$editLink}
            </div>
            
            " . $this->renderQuizAnimationScript() . "";
    }

    private function renderSurveyResults(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);
        $questionStats = $this->get('questionStats', []);
        $totalResponses = $this->get('totalResponses', 0);

        $resultsHtml = '';

        if ($totalResponses === 0) {
            $resultsHtml = $this->renderNoResults($survey['id']);
        } else {
            $questionNumber = 1;
            foreach ($questions as $question) {
                $resultsHtml .= $this->renderQuestionResult($question, $questionStats[$question['id']] ?? [], $totalResponses, $questionNumber);
                $questionNumber++;
            }
        }

        $editLinks = '';
        if (Session::isLoggedIn() && Survey::isAuthor($survey['id'], Session::getUserId())) {
            $editLinks = "
                <a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати</a>
                <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-outline'>Експорт CSV</a>";
        }

        return "
            <div class='header-actions'>
                <div>
                    <h1>Результати: " . $this->escape($survey['title']) . "</h1>
                    <p><strong>Всього відповідей: {$totalResponses}</strong></p>
                </div>
                " . $this->component('Navigation') . "
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
                {$editLinks}
            </div>
            
            " . $this->renderSurveyStyles() . "
            " . $this->renderSurveyAnimationScript() . "";
    }

    private function renderNoResults(int $surveyId): string
    {
        return "
            <div class='no-results'>
                <h3>Ще немає відповідей</h3>
                <p>Це опитування ще не має відповідей. Поділіться посиланням щоб отримати перші результати!</p>
                <div class='share-buttons'>
                    <button onclick='copyToClipboard()' class='btn btn-primary'>Копіювати посилання</button>
                    <a href='/surveys/view?id={$surveyId}' class='btn btn-secondary'>Пройти самому</a>
                </div>
                
                <script>
                    function copyToClipboard() {
                        const url = window.location.origin + '/surveys/view?id={$surveyId}';
                        navigator.clipboard.writeText(url).then(function() {
                            alert('Посилання скопійовано!');
                        });
                    }
                </script>
            </div>";
    }

    private function renderQuestionResult(array $question, array $stats, int $totalResponses, int $questionNumber): string
    {
        $questionText = $this->escape($question['question_text']);
        $questionResultHtml = '';

        if ($question['question_type'] === Question::TYPE_RADIO || $question['question_type'] === Question::TYPE_CHECKBOX) {
            // Статистика для варіантів відповідей
            foreach ($stats['option_stats'] ?? [] as $optionStat) {
                $count = $optionStat['total_selected'];
                $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0;
                $optionText = $this->escape($optionStat['option_text']);

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
                    $answerText = $this->escape($answer['answer_text']);
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

        return "
            <div class='question-results'>
                <h3>{$questionNumber}. {$questionText}</h3>
                {$questionResultHtml}
            </div>";
    }

    private function getResultLevel(float $percentage): string
    {
        if ($percentage >= 90) return 'Відмінно';
        if ($percentage >= 75) return 'Добре';
        if ($percentage >= 60) return 'Задовільно';
        return 'Незадовільно';
    }

    private function getResultLevelClass(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'satisfactory';
        return 'poor';
    }

    private function renderQuizAnimationScript(): string
    {
        return "
            <script>
                // Анімація для результатів квізу
                document.addEventListener('DOMContentLoaded', function() {
                    const statNumbers = document.querySelectorAll('.stat-number');
                    statNumbers.forEach(el => {
                        const target = parseInt(el.textContent);
                        if (!isNaN(target)) {
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
                        }
                    });
                });
            </script>";
    }

    private function renderSurveyStyles(): string
    {
        return "
            <style>
                .no-results {
                    text-align: center;
                    padding: 3rem;
                    background: #f8f9fa;
                    border-radius: 12px;
                    margin: 2rem 0;
                }
                .share-buttons { margin-top: 1.5rem; }
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
                .summary-label { font-size: 1rem; opacity: 0.9; }
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
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #e9ecef;
                    border-radius: 10px;
                    overflow: hidden;
                    margin-top: 0.5rem;
                }
                .progress {
                    height: 100%;
                    background: linear-gradient(45deg, #3498db, #2980b9);
                    transition: width 0.8s ease;
                }
            </style>";
    }

    private function renderSurveyAnimationScript(): string
    {
        return "
            <script>
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
            </script>";
    }
}