<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyListView extends BaseView
{
    protected function content(): string
    {
        $surveys = $this->get('surveys', []);
        $categories = $this->get('categories', []);
        $selectedCategory = $this->get('category', '');

        // Категорії фільтр
        $categoryFilter = $this->renderCategoryFilter($categories, $selectedCategory);

        $surveyItems = '';
        if (empty($surveys)) {
            $surveyItems = '<div class="no-surveys"><div class="no-surveys-icon">📋</div><h3>Немає опитувань в цій категорії</h3></div>';
        } else {
            foreach ($surveys as $survey) {
                $surveyItems .= $this->renderSurveyItem($survey);
            }
        }

        $createButton = '';
        if (Session::isLoggedIn()) {
            $createButton = "<a href='/surveys/create' class='btn btn-success'>Створити нове опитування</a>";
        }

        return "
            <div class='container'>
                <div class='header-actions'>
                    <h1>Доступні опитування</h1>
                    " . $this->component('Navigation') . "
                </div>
                
                {$categoryFilter}
                
                <div class='survey-list'>
                    {$surveyItems}
                </div>
                
                <div class='page-actions'>
                    {$createButton}
                    <a href='/' class='btn btn-secondary'>На головну</a>
                    " . (Session::isLoggedIn() ? "<a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>" : "") . "
                </div>
            </div>";
    }
    private function renderCategoryFilter(array $categories, string $selected): string
    {
        if (empty($categories)) return '';

        $categoriesHtml = "<a href='/surveys' class='category-filter-item" . (empty($selected) ? ' active' : '') . "'>
            <span class='category-icon'>📁</span>
            <span class='category-name'>Всі категорії</span>
        </a>";

        foreach ($categories as $category) {
            $isActive = $selected == $category['id'] ? ' active' : '';
            $categoriesHtml .= "
                <a href='/surveys?category={$category['id']}' class='category-filter-item{$isActive}'>
                    <span class='category-icon' style='color: {$category['color']}'>{$category['icon']}</span>
                    <span class='category-name'>" . $this->escape($category['name']) . "</span>
                    <span class='category-count'>({$category['surveys_count']})</span>
                </a>";
        }

        return "
            <div class='category-filter'>
                <h3>Категорії</h3>
                <div class='category-filter-list'>
                    {$categoriesHtml}
                </div>
            </div>
            
            <style>
                .category-filter {
                    background: #f8f9fa;
                    padding: 1.5rem;
                    border-radius: 12px;
                    margin-bottom: 2rem;
                }
                .category-filter h3 {
                    margin-bottom: 1rem;
                    color: #2c3e50;
                }
                .category-filter-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.8rem;
                }
                .category-filter-item {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.8rem 1.2rem;
                    background: white;
                    border: 2px solid #dee2e6;
                    border-radius: 25px;
                    text-decoration: none;
                    color: #495057;
                    transition: all 0.3s ease;
                    font-weight: 500;
                }
                .category-filter-item:hover {
                    border-color: #3498db;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
                    color: #3498db;
                }
                .category-filter-item.active {
                    background: #3498db;
                    border-color: #3498db;
                    color: white;
                }
                .category-icon {
                    font-size: 1.2rem;
                }
                .category-count {
                    background: rgba(0,0,0,0.1);
                    padding: 0.2rem 0.5rem;
                    border-radius: 12px;
                    font-size: 0.8rem;
                }
                .category-filter-item.active .category-count {
                    background: rgba(255,255,255,0.2);
                }
                @media (max-width: 768px) {
                    .category-filter-list {
                        flex-direction: column;
                    }
                    .category-filter-item {
                        justify-content: space-between;
                    }
                }
            </style>";
    }

    private function renderSurveyItem(array $survey): string
    {
        $responseCount = SurveyResponse::getCountBySurveyId($survey['id']);

        $categoryBadge = '';
        if (!empty($survey['category_name'])) {
            $categoryBadge = "
                <div class='survey-category'>
                    <span class='category-badge' style='background-color: {$survey['category_color']}'>
                        {$survey['category_icon']} " . $this->escape($survey['category_name']) . "
                    </span>
                </div>";
        }

        return "
            <div class='survey-item'>
                {$categoryBadge}
                <h3>" . $this->escape($survey['title']) . "</h3>
                <p>" . $this->escape($survey['description']) . "</p>
                <p><small>Автор: " . $this->escape($survey['author_name']) . " | Відповідей: {$responseCount}</small></p>
                <div class='survey-actions'>
                    <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Пройти опитування</a>
                    <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                </div>
            </div>";
    }
}