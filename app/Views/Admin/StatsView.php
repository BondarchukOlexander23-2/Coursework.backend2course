<?php

require_once __DIR__ . '/../BaseView.php';

class StatsView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $survey = $this->get('survey');
        $stats = $this->get('stats');

        $questionsStatsHtml = '';
        foreach ($stats['questions'] as $question) {
            $questionsStatsHtml .= "
                <div class='question-stats'>
                    <h4>" . $this->escape($question['question_text']) . "</h4>
                    <div class='question-metrics'>
                        <span>Відповідей: {$question['answers_count']}</span>
                        " . (isset($question['avg_correctness']) ? "<span>Правильність: {$question['avg_correctness']}%</span>" : "") . "
                    </div>
                </div>";
        }

        return "
            <div class='admin-header'>
                <h1>Статистика: " . $this->escape($survey['title']) . "</h1>
                " . $this->component('AdminNavigation') . "
            </div>
            
            <div class='survey-overview'>
                <div class='overview-grid'>
                    <div class='overview-item'>
                        <h3>Загальна інформація</h3>
                        <p><strong>Автор:</strong> " . $this->escape($survey['author_name']) . "</p>
                        <p><strong>Створено:</strong> {$survey['created_at']}</p>
                        <p><strong>Статус:</strong> " . ($survey['is_active'] ? 'Активне' : 'Неактивне') . "</p>
                    </div>
                    <div class='overview-item'>
                        <h3>Статистика</h3>
                        <p><strong>Питань:</strong> {$stats['general']['total_questions']}</p>
                        <p><strong>Відповідей:</strong> {$stats['general']['total_responses']}</p>
                        <p><strong>Унікальних користувачів:</strong> {$stats['general']['unique_users']}</p>
                    </div>
                </div>
            </div>
            
            <div class='stats-charts'>
                <h2>Статистика по питаннях</h2>
                {$questionsStatsHtml}
            </div>
            
            <div class='export-actions'>
                <h2>Експорт даних</h2>
                <a href='/admin/export-stats?survey_id={$survey['id']}&type=csv' class='btn btn-primary'>Експорт CSV</a>
                <a href='/admin/export-stats?survey_id={$survey['id']}&type=xlsx' class='btn btn-secondary'>Експорт Excel</a>
            </div>
            
            <div class='form-actions'>
                <a href='/admin/surveys' class='btn btn-secondary'>Назад до списку</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
            </div>
            
            " . $this->renderStatsScript();
    }


    private function renderStatsScript(): string
    {
        return "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const questionStats = document.querySelectorAll('.question-stats');
                    
                    questionStats.forEach(function(stat, index) {
                        stat.style.opacity = '0';
                        stat.style.transform = 'translateY(20px)';
                        
                        setTimeout(function() {
                            stat.style.transition = 'all 0.5s ease';
                            stat.style.opacity = '1';
                            stat.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                    
                    const metrics = document.querySelectorAll('.question-metrics span');
                    metrics.forEach(function(metric) {
                        metric.addEventListener('mouseenter', function() {
                            this.style.transform = 'scale(1.05)';
                            this.style.boxShadow = '0 4px 15px rgba(52, 152, 219, 0.3)';
                        });
                        
                        metric.addEventListener('mouseleave', function() {
                            this.style.transform = 'scale(1)';
                            this.style.boxShadow = 'none';
                        });
                    });
                });
            </script>";
    }
}