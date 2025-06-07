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
                    <div class='stat-icon'>🏷️</div>
                    <div class='stat-info'>
                        <h3>{$stats['total_categories']}</h3>
                        <p>Категорій</p>
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
        
        " . $this->renderCategoryStats($stats['category_stats'] ?? []) . "
        
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
                <a href='/admin/categories' class='action-card'>
                    <div class='action-icon'>🏷️</div>
                    <h3>Управління категоріями</h3>
                    <p>Створення та редагування категорій</p>
                </a>
            </div>
        </div>";
    }
    private function renderCategoryStats(array $categoryStats): string
    {
        if (empty($categoryStats)) {
            return '';
        }

        $statsHtml = '';
        foreach ($categoryStats as $stat) {
            $statsHtml .= "
            <div class='category-stat-item'>
                <div class='category-info'>
                    <span class='category-icon' style='color: {$stat['color']}'>{$stat['icon']}</span>
                    <span class='category-name'>" . $this->escape($stat['name']) . "</span>
                </div>
                <div class='category-count'>{$stat['surveys_count']}</div>
            </div>";
        }

        return "
        <div class='category-stats'>
            <h2>📈 Популярні категорії</h2>
            <div class='category-stats-grid'>
                {$statsHtml}
            </div>
        </div>
        
        <style>
            .category-stats {
                background: #f8f9fa;
                padding: 2rem;
                border-radius: 12px;
                margin-bottom: 2rem;
            }
            .category-stats h2 {
                color: #2c3e50;
                margin-bottom: 1.5rem;
                font-size: 1.8rem;
            }
            .category-stats-grid {
                display: grid;
                gap: 1rem;
            }
            .category-stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem;
                background: white;
                border-radius: 8px;
                border-left: 4px solid #3498db;
                transition: all 0.3s ease;
            }
            .category-stat-item:hover {
                transform: translateX(5px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            .category-info {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            .category-icon {
                font-size: 1.5rem;
            }
            .category-name {
                font-weight: 600;
                color: #2c3e50;
            }
            .category-count {
                background: #3498db;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-weight: bold;
                min-width: 40px;
                text-align: center;
            }
        </style>";
    }
}