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
           <div class='my-surveys-container'>
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
                </div>
                </div>";
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