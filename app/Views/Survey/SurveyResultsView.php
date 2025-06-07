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

        $userResultHtml = $this->renderUserQuizResult($userResult);
        $statsHtml = $this->renderQuizStatistics($stats);
        $topResultsHtml = $this->renderTopResults($topResults);
        $editLink = $this->renderEditLink($survey);

        return "
            <div class='header-actions'>
                <h1>🎯 Квіз: " . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            {$userResultHtml}
            {$statsHtml}
            {$topResultsHtml}
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary btn-large'>📋 До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>🔄 Пройти ще раз</a>
                {$editLink}
            </div>";
    }
    private function renderSurveyResults(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);
        $questionStats = $this->get('questionStats', []);
        $totalResponses = $this->get('totalResponses', 0);

        $resultsHtml = $this->renderQuestionResults($questions, $questionStats, $totalResponses);
        $editLinks = $this->renderEditLink($survey, true);

        return "
            <div class='header-actions'>
                <h1>📊 Результати: " . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            " . $this->renderSurveySummary($survey, $questions, $totalResponses) . "
            
            <div class='results'>
                {$resultsHtml}
            </div>
            
            <div class='form-actions'>
                <a href='/surveys' class='btn btn-primary btn-large'>📋 До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>📝 Пройти опитування</a>
                {$editLinks}
            </div>
            
            " . $this->renderResultsAnimationScript();
    }

    private function renderUserQuizResult(?array $userResult): string
    {
        if (!$userResult) {
            return '';
        }

        $percentage = $userResult['percentage'];
        $level = $this->getResultLevel($percentage);
        $levelClass = $this->getResultLevelClass($percentage);

        return "
            <div class='user-result survey-summary'>
                <h2 style='color: white; margin-bottom: 1.5rem; text-align: center;'>🏆 Ваш результат</h2>
                <div class='score-display'>
                    <div style='text-align: center;'>
                        <span class='score'>{$userResult['total_score']}/{$userResult['max_score']}</span>
                        <p style='color: rgba(255,255,255,0.9); margin: 0.5rem 0;'>балів</p>
                    </div>
                    <div style='text-align: center;'>
                        <span class='percentage'>{$percentage}%</span>
                        <p style='color: rgba(255,255,255,0.9); margin: 0.5rem 0;'>правильність</p>
                    </div>
                    <div style='text-align: center;'>
                        <span class='level {$levelClass}'>{$level}</span>
                        <p style='color: rgba(255,255,255,0.9); margin: 0.5rem 0;'>оцінка</p>
                    </div>
                </div>
            </div>";
    }

    private function renderQuizStatistics(array $stats): string
    {
        return "
            <div class='quiz-stats'>
                <h2>📈 Загальна статистика</h2>
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
    }

    private function renderTopResults(array $topResults): string
    {
        if (empty($topResults)) {
            return '';
        }

        $topResultsHtml = '';
        foreach ($topResults as $result) {
            $userName = $result['user_name'] ?: 'Анонім';
            $topResultsHtml .= "<li>" . $this->escape($userName) . ": <strong>{$result['total_score']}/{$result['max_score']}</strong> ({$result['percentage']}%)</li>";
        }

        return "
            <div class='top-results'>
                <h2>🏅 Топ результати</h2>
                <ol>
                    {$topResultsHtml}
                </ol>
            </div>";
    }

    private function renderSurveySummary(array $survey, array $questions, int $totalResponses): string
    {
        return "
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
                <div style='margin-top: 1.5rem; text-align: center;'>
                    <p style='color: rgba(255,255,255,0.9); font-size: 1.1rem;'>" . $this->escape($survey['description']) . "</p>
                </div>
            </div>";
    }

    private function renderQuestionResults(array $questions, array $questionStats, int $totalResponses): string
    {
        if ($totalResponses === 0) {
            return $this->renderNoResults();
        }

        $resultsHtml = '';
        $questionNumber = 1;

        foreach ($questions as $question) {
            $resultsHtml .= $this->renderSingleQuestionResult(
                $question,
                $questionStats[$question['id']] ?? [],
                $totalResponses,
                $questionNumber
            );
            $questionNumber++;
        }

        return $resultsHtml;
    }

    private function renderSingleQuestionResult(array $question, array $stats, int $totalResponses, int $questionNumber): string
    {
        $questionText = $this->escape($question['question_text']);
        $questionResultHtml = '';

        if ($this->isChoiceQuestion($question['question_type'])) {
            $questionResultHtml = $this->renderChoiceQuestionStats($stats, $totalResponses);
        } else {
            $questionResultHtml = $this->renderTextQuestionStats($stats);
        }

        return "
            <div class='question-results'>
                <h3>{$questionNumber}. {$questionText}</h3>
                {$questionResultHtml}
            </div>";
    }

    private function renderChoiceQuestionStats(array $stats, int $totalResponses): string
    {
        $resultHtml = '';

        foreach ($stats['option_stats'] ?? [] as $optionStat) {
            $count = $optionStat['total_selected'];
            $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0;
            $optionText = $this->escape($optionStat['option_text']);

            $resultHtml .= "
                <div class='result-item'>
                    <p><strong>{$optionText}:</strong> {$percentage}% ({$count} відповідей)</p>
                    <div class='progress-bar'>
                        <div class='progress' style='width: {$percentage}%'></div>
                    </div>
                </div>";
        }

        return $resultHtml;
    }

    private function renderTextQuestionStats(array $stats): string
    {
        $textAnswers = $stats['text_answers'] ?? [];

        if (empty($textAnswers)) {
            return "<div class='no-answers'>Немає відповідей на це питання.</div>";
        }

        $answersHtml = "<div class='text-answers'>";

        foreach (array_slice($textAnswers, 0, 10) as $answer) {
            $answerText = $this->escape($answer['answer_text']);
            $correctnessClass = $this->getAnswerCorrectnessClass($answer);

            $answersHtml .= "<div class='text-answer{$correctnessClass}'>\"$answerText\"</div>";
        }

        if (count($textAnswers) > 10) {
            $remaining = count($textAnswers) - 10;
            $answersHtml .= "<div class='more-answers'>... та ще {$remaining} відповідей</div>";
        }

        $answersHtml .= "</div>";

        return $answersHtml;
    }

    private function renderNoResults(): string
    {
        $survey = $this->get('survey');
        $surveyId = $survey['id'];

        return "
            <div class='no-results'>
                <div class='no-surveys-icon'>📝</div>
                <h3>Ще немає відповідей</h3>
                <p>Це опитування ще не має відповідей. Поділіться посиланням щоб отримати перші результати!</p>
                <div class='share-buttons'>
                    <button onclick='copyToClipboard()' class='btn btn-primary btn-large'>📋 Копіювати посилання</button>
                    <a href='/surveys/view?id={$surveyId}' class='btn btn-secondary btn-large'>📝 Пройти самому</a>
                </div>
                
                <script>
                    function copyToClipboard() {
                        const url = window.location.origin + '/surveys/view?id={$surveyId}';
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(url).then(function() {
                                showCopyMessage('Посилання скопійовано!', 'success');
                            });
                        } else {
                            // Fallback для старих браузерів
                            const textArea = document.createElement('textarea');
                            textArea.value = url;
                            document.body.appendChild(textArea);
                            textArea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            showCopyMessage('Посилання скопійовано!', 'success');
                        }
                    }
                    
                    function showCopyMessage(message, type) {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'flash-message ' + type;
                        messageDiv.textContent = message;
                        messageDiv.style.position = 'fixed';
                        messageDiv.style.top = '20px';
                        messageDiv.style.right = '20px';
                        messageDiv.style.zIndex = '9999';
                        messageDiv.style.animation = 'slideIn 0.3s ease-out';
                        
                        document.body.appendChild(messageDiv);
                        setTimeout(() => messageDiv.remove(), 3000);
                    }
                </script>
            </div>";
    }


    private function renderEditLink(array $survey, bool $includeExport = false): string
    {
        if (!Session::isLoggedIn() || !Survey::isAuthor($survey['id'], Session::getUserId())) {
            return '';
        }

        $editLinks = "<a href='/surveys/edit?id={$survey['id']}' class='btn btn-outline'>✏️ Редагувати</a>";

        if ($includeExport) {
            $editLinks .= " <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-outline'>📥 Експорт CSV</a>";
        }

        return $editLinks;
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

    private function isChoiceQuestion(string $questionType): bool
    {
        return in_array($questionType, ['radio', 'checkbox']);
    }

    private function getAnswerCorrectnessClass(array $answer): string
    {
        if (!isset($answer['is_correct'])) {
            return '';
        }
        return $answer['is_correct'] ? ' correct-text' : ' incorrect-text';
    }


    private function renderResultsAnimationScript(): string
    {
        return "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const progressBars = document.querySelectorAll('.progress');
                    
                    const observerOptions = {
                        threshold: 0.3,
                        rootMargin: '0px 0px -50px 0px'
                    };
                    
                    const observer = new IntersectionObserver(function(entries) {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const progress = entry.target;
                                const width = progress.style.width;
                                progress.style.width = '0%';
                                
                                setTimeout(() => {
                                    progress.style.transition = 'width 1.5s ease-out';
                                    progress.style.width = width;
                                }, 200);
                                
                                observer.unobserve(progress);
                            }
                        });
                    }, observerOptions);
                    
                    progressBars.forEach(bar => {
                        observer.observe(bar);
                    });
                    
                    const statNumbers = document.querySelectorAll('.stat-number, .summary-number');
                    statNumbers.forEach(el => {
                        const finalText = el.textContent;
                        const finalNumber = parseInt(finalText);
                        
                        if (!isNaN(finalNumber) && finalNumber > 0) {
                            let current = 0;
                            const increment = finalNumber / 50;
                            const duration = Math.min(2000, Math.max(500, finalNumber * 10));
                            const stepTime = duration / 50;
                            
                            el.textContent = '0';
                            
                            const timer = setInterval(() => {
                                current += increment;
                                if (current >= finalNumber) {
                                    current = finalNumber;
                                    clearInterval(timer);
                                    el.textContent = finalText; // Повертаємо оригінальний текст
                                } else {
                                    el.textContent = Math.floor(current);
                                }
                            }, stepTime);
                        }
                    });
                    
                    const questionResults = document.querySelectorAll('.question-results');
                    questionResults.forEach((result, index) => {
                        result.style.opacity = '0';
                        result.style.transform = 'translateY(30px)';
                        
                        setTimeout(() => {
                            result.style.transition = 'all 0.6s ease-out';
                            result.style.opacity = '1';
                            result.style.transform = 'translateY(0)';
                        }, index * 150 + 300);
                    });
                    
                    const topResultItems = document.querySelectorAll('.top-results li');
                    topResultItems.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(-20px)';
                        
                        setTimeout(() => {
                            item.style.transition = 'all 0.5s ease-out';
                            item.style.opacity = '1';
                            item.style.transform = 'translateX(0)';
                        }, index * 100 + 500);
                    });
                    
                    const textAnswers = document.querySelectorAll('.text-answer');
                    textAnswers.forEach((answer, index) => {
                        if (index < 5) { // Анімуємо тільки перші 5
                            answer.style.opacity = '0';
                            answer.style.transform = 'scale(0.95)';
                            
                            setTimeout(() => {
                                answer.style.transition = 'all 0.4s ease-out';
                                answer.style.opacity = '1';
                                answer.style.transform = 'scale(1)';
                            }, index * 80 + 600);
                        }
                    });
                });
                
                const style = document.createElement('style');
                style.textContent = \`
                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateX(100%);
                        }
                        to {
                            opacity: 1;
                            transform: translateX(0);
                        }
                    }
                    
                    .user-result.highlight {
                        animation: pulse 2s ease-in-out;
                    }
                    
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); }
                        50% { transform: scale(1.02); }
                    }
                \`;
                document.head.appendChild(style);
            </script>";
    }
}