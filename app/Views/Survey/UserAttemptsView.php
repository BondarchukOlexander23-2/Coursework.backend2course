<?php

require_once __DIR__ . '/../BaseView.php';

/**
 * View для відображення історії спроб користувача
 */
class UserAttemptsView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $user = $this->get('user');
        $attempts = $this->get('attempts', []);

        return "
            <div class='header-actions'>
                <h1>Історія спроб користувача</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='user-attempts-header'>
                <div class='user-info'>
                    <h2>" . $this->escape($user['name']) . "</h2>
                    <p>" . $this->escape($user['email']) . "</p>
                </div>
                <div class='survey-info'>
                    <h3>" . $this->escape($survey['title']) . "</h3>
                    <p>Зареєстрований: " . date('d.m.Y', strtotime($user['created_at'])) . "</p>
                </div>
            </div>
            
            " . $this->renderAttemptsSection($attempts, $survey) . "
            
            <div class='form-actions'>
                <a href='/surveys/retake-management?survey_id={$survey['id']}' class='btn btn-primary'>
                    Назад до управління
                </a>
                <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>
                    Загальні результати
                </a>
            </div>
            
            " . $this->renderAttemptsScript() . "";
    }

    private function renderAttemptsSection(array $attempts, array $survey): string
    {
        if (empty($attempts)) {
            return "
                <div class='no-attempts'>
                    <div class='no-attempts-icon'>📝</div>
                    <h3>Немає спроб</h3>
                    <p>Цей користувач ще не проходив дане опитування.</p>
                </div>";
        }

        $attemptsHtml = '';
        foreach ($attempts as $attempt) {
            $attemptsHtml .= $this->renderAttemptCard($attempt, $survey);
        }

        $totalAttempts = count($attempts);
        $bestAttempt = $this->getBestAttempt($attempts);
        $averageScore = $this->getAverageScore($attempts);

        return "
            <div class='attempts-summary'>
                <h3>Сводка ({$totalAttempts} " . $this->getAttemptsWord($totalAttempts) . ")</h3>
                <div class='summary-stats'>
                    <div class='summary-stat'>
                        <span class='stat-value'>{$totalAttempts}</span>
                        <span class='stat-label'>Спроб</span>
                    </div>
                    <div class='summary-stat'>
                        <span class='stat-value'>{$bestAttempt['percentage']}%</span>
                        <span class='stat-label'>Найкращий результат</span>
                    </div>
                    <div class='summary-stat'>
                        <span class='stat-value'>{$averageScore}%</span>
                        <span class='stat-label'>Середній результат</span>
                    </div>
                </div>
            </div>
            
            <div class='attempts-timeline'>
                <h3>Хронологія спроб</h3>
                <div class='timeline'>
                    {$attemptsHtml}
                </div>
            </div>";
    }

    private function renderAttemptCard(array $attempt, array $survey): string
    {
        $attemptNumber = $attempt['attempt_number'];
        $score = $attempt['total_score'];
        $maxScore = $attempt['max_score'];
        $percentage = $attempt['percentage'];
        $date = date('d.m.Y H:i', strtotime($attempt['created_at']));

        $levelClass = $this->getScoreLevelClass($percentage);
        $levelText = $this->getScoreLevelText($percentage);

        $progressBarClass = $this->getProgressBarClass($percentage);

        return "
            <div class='attempt-card {$levelClass}'>
                <div class='attempt-header'>
                    <div class='attempt-number'>
                        <span class='attempt-badge'>Спроба {$attemptNumber}</span>
                        <span class='attempt-date'>{$date}</span>
                    </div>
                    <div class='attempt-score'>
                        <span class='score-main'>{$score}/{$maxScore}</span>
                        <span class='score-percentage'>({$percentage}%)</span>
                    </div>
                </div>
                
                <div class='attempt-progress'>
                    <div class='progress-bar'>
                        <div class='progress {$progressBarClass}' style='width: {$percentage}%'></div>
                    </div>
                    <span class='level-text'>{$levelText}</span>
                </div>
                
                <div class='attempt-actions'>
                    <a href='/surveys/response-details?response_id={$attempt['id']}' 
                       class='btn btn-sm btn-outline'>
                        Детальний перегляд
                    </a>
                </div>
            </div>";
    }

    private function getBestAttempt(array $attempts): array
    {
        $best = $attempts[0];
        foreach ($attempts as $attempt) {
            if ($attempt['percentage'] > $best['percentage']) {
                $best = $attempt;
            }
        }
        return $best;
    }

    private function getAverageScore(array $attempts): float
    {
        if (empty($attempts)) return 0;

        $total = 0;
        foreach ($attempts as $attempt) {
            $total += $attempt['percentage'];
        }

        return round($total / count($attempts), 1);
    }

    private function getAttemptsWord(int $count): string
    {
        if ($count % 10 === 1 && $count % 100 !== 11) {
            return 'спроба';
        } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
            return 'спроби';
        } else {
            return 'спроб';
        }
    }

    private function getScoreLevelClass(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'satisfactory';
        return 'poor';
    }

    private function getScoreLevelText(float $percentage): string
    {
        if ($percentage >= 90) return 'Відмінно';
        if ($percentage >= 75) return 'Добре';
        if ($percentage >= 60) return 'Задовільно';
        return 'Незадовільно';
    }

    private function getProgressBarClass(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'satisfactory';
        return 'poor';
    }

    private function renderAttemptsScript(): string
    {
        return "
            <style>
                .user-attempts-header {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 2rem;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 12px;
                    margin-bottom: 2rem;
                }
                
                .user-info h2, .survey-info h3 {
                    margin-bottom: 0.5rem;
                    color: white;
                }
                
                .user-info p, .survey-info p {
                    opacity: 0.9;
                    margin: 0;
                }
                
                .attempts-summary,
                .attempts-timeline {
                    background: white;
                    border-radius: 12px;
                    padding: 2rem;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                
                .summary-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 1.5rem;
                    margin-top: 1.5rem;
                }
                
                .summary-stat {
                    text-align: center;
                    padding: 1.5rem;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 2px solid transparent;
                    transition: all 0.3s ease;
                }
                
                .summary-stat:hover {
                    border-color: #3498db;
                    transform: translateY(-2px);
                }
                
                .stat-value {
                    display: block;
                    font-size: 2rem;
                    font-weight: bold;
                    color: #3498db;
                    margin-bottom: 0.5rem;
                }
                
                .stat-label {
                    color: #6c757d;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .timeline {
                    position: relative;
                    padding-left: 2rem;
                }
                
                .timeline::before {
                    content: '';
                    position: absolute;
                    left: 0.75rem;
                    top: 0;
                    bottom: 0;
                    width: 2px;
                    background: #dee2e6;
                }
                
                .attempt-card {
                    position: relative;
                    background: #f8f9fa;
                    border-radius: 12px;
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                    border-left: 4px solid #dee2e6;
                    transition: all 0.3s ease;
                }
                
                .attempt-card::before {
                    content: '';
                    position: absolute;
                    left: -2.25rem;
                    top: 1.5rem;
                    width: 12px;
                    height: 12px;
                    background: white;
                    border: 3px solid #dee2e6;
                    border-radius: 50%;
                }
                
                .attempt-card:hover {
                    transform: translateX(5px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                }
                
                .attempt-card.excellent {
                    border-left-color: #28a745;
                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                }
                
                .attempt-card.excellent::before {
                    border-color: #28a745;
                    background: #28a745;
                }
                
                .attempt-card.good {
                    border-left-color: #17a2b8;
                    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
                }
                
                .attempt-card.good::before {
                    border-color: #17a2b8;
                    background: #17a2b8;
                }
                
                .attempt-card.satisfactory {
                    border-left-color: #ffc107;
                    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                }
                
                .attempt-card.satisfactory::before {
                    border-color: #ffc107;
                    background: #ffc107;
                }
                
                .attempt-card.poor {
                    border-left-color: #dc3545;
                    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                }
                
                .attempt-card.poor::before {
                    border-color: #dc3545;
                    background: #dc3545;
                }
                
                .attempt-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                
                .attempt-number {
                    display: flex;
                    flex-direction: column;
                    gap: 0.3rem;
                }
                
                .attempt-badge {
                    background: #3498db;
                    color: white;
                    padding: 0.3rem 0.8rem;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .attempt-date {
                    color: #6c757d;
                    font-size: 0.9rem;
                }
                
                .attempt-score {
                    text-align: right;
                }
                
                .score-main {
                    font-size: 1.5rem;
                    font-weight: bold;
                    color: #2c3e50;
                    display: block;
                    margin-bottom: 0.3rem;
                }
                
                .score-percentage {
                    font-size: 1.1rem;
                    color: #6c757d;
                }
                
                .attempt-progress {
                    margin-bottom: 1rem;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: rgba(255, 255, 255, 0.8);
                    border-radius: 10px;
                    overflow: hidden;
                    margin-bottom: 0.5rem;
                    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
                }
                
                .progress {
                    height: 100%;
                    transition: width 1s ease;
                    position: relative;
                }
                
                .progress.excellent {
                    background: linear-gradient(90deg, #28a745, #20c997);
                }
                
                .progress.good {
                    background: linear-gradient(90deg, #17a2b8, #20c997);
                }
                
                .progress.satisfactory {
                    background: linear-gradient(90deg, #ffc107, #fd7e14);
                }
                
                .progress.poor {
                    background: linear-gradient(90deg, #dc3545, #e74c3c);
                }
                
                .progress::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: linear-gradient(
                        90deg,
                        rgba(255, 255, 255, 0.1) 0%,
                        rgba(255, 255, 255, 0.3) 50%,
                        rgba(255, 255, 255, 0.1) 100%
                    );
                    animation: shimmer 2s infinite;
                }
                
                @keyframes shimmer {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
                
                .level-text {
                    font-weight: 600;
                    color: #495057;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .attempt-actions {
                    text-align: center;
                }
                
                .no-attempts {
                    text-align: center;
                    padding: 4rem 2rem;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 15px;
                    margin: 2rem 0;
                }
                
                .no-attempts-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    opacity: 0.7;
                }
                
                .no-attempts h3 {
                    color: #2c3e50;
                    margin-bottom: 1rem;
                }
                
                .no-attempts p {
                    color: #6c757d;
                    font-size: 1.1rem;
                    margin: 0;
                }
                
                @media (max-width: 768px) {
                    .user-attempts-header {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                    }
                    
                    .summary-stats {
                        grid-template-columns: 1fr;
                    }
                    
                    .timeline {
                        padding-left: 1rem;
                    }
                    
                    .timeline::before {
                        left: 0.25rem;
                    }
                    
                    .attempt-card::before {
                        left: -1.75rem;
                    }
                    
                    .attempt-header {
                        flex-direction: column;
                        align-items: stretch;
                        text-align: center;
                    }
                    
                    .attempt-score {
                        text-align: center;
                    }
                }
            </style>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Анімація прогрес-барів
                    const progressBars = document.querySelectorAll('.progress');
                    
                    const observerOptions = {
                        threshold: 0.5,
                        rootMargin: '0px 0px -50px 0px'
                    };
                    
                    const observer = new IntersectionObserver(function(entries) {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const progress = entry.target;
                                const width = progress.style.width;
                                progress.style.width = '0%';
                                
                                setTimeout(() => {
                                    progress.style.width = width;
                                }, 200);
                                
                                observer.unobserve(progress);
                            }
                        });
                    }, observerOptions);
                    
                    progressBars.forEach(bar => {
                        observer.observe(bar);
                    });
                    
                    // Анімація появи карток
                    const attemptCards = document.querySelectorAll('.attempt-card');
                    attemptCards.forEach((card, index) => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            card.style.transition = 'all 0.5s ease';
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, index * 100 + 300);
                    });
                    
                    // Анімація статистики
                    const statValues = document.querySelectorAll('.stat-value');
                    statValues.forEach(el => {
                        const finalValue = el.textContent;
                        if (!isNaN(parseInt(finalValue))) {
                            const target = parseInt(finalValue);
                            let current = 0;
                            const increment = target / 30;
                            
                            const timer = setInterval(() => {
                                current += increment;
                                if (current >= target) {
                                    current = target;
                                    clearInterval(timer);
                                    el.textContent = finalValue; // Повертаємо оригінальний текст з %
                                } else {
                                    el.textContent = Math.floor(current);
                                }
                            }, 50);
                        }
                    });
                });
            </script>";
    }
}