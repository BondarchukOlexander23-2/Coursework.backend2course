<?php

require_once __DIR__ . '/../BaseView.php';

class MySurveysView extends BaseView
{
    protected function content(): string
    {
        $surveys = $this->get('surveys', []);

        if (empty($surveys)) {
            $surveyItems = $this->renderNoSurveys();
        } else {
            $surveyItems = '';
            foreach ($surveys as $survey) {
                $surveyItems .= $this->renderSurveyItem($survey);
            }
        }

        return "
            <div class='header-actions'>
                <h1>Мої опитування</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='my-surveys-container'>
                {$surveyItems}
            </div>
            
            <div class='page-actions'>
                <a href='/surveys/create' class='btn btn-success'>
                    <span class='btn-icon'>➕</span> Створити нове
                </a>
                <a href='/surveys' class='btn btn-secondary'>
                    <span class='btn-icon'>📋</span> Всі опитування
                </a>
            </div>
            
            " . $this->renderStyles() . "
            " . $this->renderAnimationScript() . "";
    }

    private function renderNoSurveys(): string
    {
        return '
            <div class="no-surveys">
                <div class="no-surveys-icon">📋</div>
                <h3>У вас ще немає створених опитувань</h3>
                <p>Створіть своє перше опитування та почніть збирати відгуки!</p>
                <a href="/surveys/create" class="btn btn-success btn-large">Створити перше опитування</a>
            </div>';
    }

    private function renderSurveyItem(array $survey): string
    {
        $status = $survey['is_active'] ? 'Активне' : 'Неактивне';
        $statusClass = $survey['is_active'] ? 'status-active' : 'status-inactive';
        $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);
        $questionCount = count(Question::getBySurveyId($survey['id']));

        // Визначаємо тип опитування
        $isQuiz = Question::isQuiz($survey['id']);
        $surveyType = $isQuiz ? 'Квіз' : 'Опитування';
        $surveyTypeClass = $isQuiz ? 'quiz-badge' : 'survey-badge';

        $exportButton = $responseCount > 0 ? "
            <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-outline'>
                <span class='btn-icon'>📥</span> Експорт
            </a>" : "";

        return "
            <div class='survey-item my-survey-item'>
                <div class='survey-header'>
                    <h3>" . $this->escape($survey['title']) . "</h3>
                    <div class='survey-badges'>
                        <span class='type-badge {$surveyTypeClass}'>{$surveyType}</span>
                        <span class='status-badge {$statusClass}'>{$status}</span>
                    </div>
                </div>
                
                <p class='survey-description'>" . $this->escape($survey['description']) . "</p>
                
                <div class='survey-stats'>
                    <div class='stat-item'>
                        <span class='stat-number'>{$questionCount}</span>
                        <span class='stat-label'>Питань</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>{$responseCount}</span>
                        <span class='stat-label'>Відповідей</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-number'>" . date('d.m.Y', strtotime($survey['created_at'])) . "</span>
                        <span class='stat-label'>Створено</span>
                    </div>
                </div>
                
                <div class='survey-actions'>
                    <a href='/surveys/edit?id={$survey['id']}' class='btn btn-primary'>
                        <span class='btn-icon'>✏️</span> Редагувати
                    </a>
                    <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>
                        <span class='btn-icon'>👁️</span> Переглянути
                    </a>
                    <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>
                        <span class='btn-icon'>📊</span> Результати
                    </a>
                    {$exportButton}
                </div>
            </div>";
    }

    private function renderStyles(): string
    {
        return "
            <style>
                .no-surveys {
                    text-align: center;
                    padding: 4rem 2rem;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 15px;
                    margin: 2rem 0;
                }
                .no-surveys-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.7; }
                .my-surveys-container { display: grid; gap: 2rem; margin: 2rem 0; }
                .my-survey-item {
                    background: white;
                    border: 2px solid #e9ecef;
                    border-radius: 15px;
                    padding: 2rem;
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .my-survey-item:hover {
                    border-color: #3498db;
                    transform: translateY(-3px);
                    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
                }
                .survey-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 1rem;
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                .survey-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
                .type-badge, .status-badge {
                    padding: 0.3rem 0.8rem;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .quiz-badge { background: linear-gradient(45deg, #f39c12, #e67e22); color: white; }
                .survey-badge { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
                .status-active { background: linear-gradient(45deg, #27ae60, #229954); color: white; }
                .status-inactive { background: linear-gradient(45deg, #95a5a6, #7f8c8d); color: white; }
                .survey-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                    gap: 1rem;
                    margin-bottom: 2rem;
                    padding: 1.5rem;
                    background: #f8f9fa;
                    border-radius: 10px;
                }
                .stat-item { text-align: center; }
                .stat-number {
                    display: block;
                    font-size: 1.8rem;
                    font-weight: bold;
                    color: #3498db;
                    margin-bottom: 0.3rem;
                }
                .stat-label {
                    font-size: 0.9rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .survey-actions { display: flex; gap: 0.8rem; flex-wrap: wrap; }
                .btn-icon { margin-right: 0.5rem; }
                .btn-outline {
                    background: transparent;
                    border: 2px solid #dee2e6;
                    color: #495057;
                }
                .btn-outline:hover {
                    background: #f8f9fa;
                    border-color: #3498db;
                    color: #3498db;
                }
            </style>";
    }

    private function renderAnimationScript(): string
    {
        return "
            <script>
                // Анімація для статистики
                document.addEventListener('DOMContentLoaded', function() {
                    const statNumbers = document.querySelectorAll('.stat-number');
                    statNumbers.forEach(el => {
                        const text = el.textContent;
                        if (!isNaN(text) && text !== '') {
                            const target = parseInt(text);
                            let current = 0;
                            const increment = target / 20;
                            const timer = setInterval(() => {
                                current += increment;
                                if (current >= target) {
                                    current = target;
                                    clearInterval(timer);
                                }
                                el.textContent = Math.floor(current);
                            }, 50);
                        }
                    });
                });
            </script>";
    }
}