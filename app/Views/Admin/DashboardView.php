<?php

require_once __DIR__ . '/../BaseView.php';

class DashboardView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $stats = $this->get('stats', []);

        return "
            <div class='admin-header'>
                <h1>Адміністративна панель</h1>
                " . $this->component('AdminNavigation') . "
            </div>
            
            <div class='dashboard-stats'>
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-icon'>👥</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_users']}</h3>
                            <p>Користувачів</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>📋</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_surveys']}</h3>
                            <p>Опитувань</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>✅</div>
                        <div class='stat-info'>
                            <h3>{$stats['active_surveys']}</h3>
                            <p>Активних</p>
                        </div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-icon'>📊</div>
                        <div class='stat-info'>
                            <h3>{$stats['total_responses']}</h3>
                            <p>Відповідей</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='quick-actions'>
                <h2>Швидкі дії</h2>
                <div class='actions-grid'>
                    <a href='/admin/users' class='action-card'>
                        <div class='action-icon'>👥</div>
                        <h3>Управління користувачами</h3>
                        <p>Переглянути, редагувати та видалити користувачів</p>
                    </a>
                    <a href='/admin/surveys' class='action-card'>
                        <div class='action-icon'>📋</div>
                        <h3>Управління опитуваннями</h3>
                        <p>Модерація та статистика опитувань</p>
                    </a>
                </div>
            </div>";
    }
}