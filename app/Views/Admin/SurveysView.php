<?php

require_once __DIR__ . '/../BaseView.php';

class SurveysView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $surveys = $this->get('surveys', []);
        $categories = $this->get('categories', []);
        $currentPage = $this->get('currentPage', 1);
        $totalPages = $this->get('totalPages', 1);
        $search = $this->get('search', '');
        $status = $this->get('status', 'all');
        $categoryFilter = $this->get('category', 0);

        $surveysHtml = '';
        foreach ($surveys as $survey) {
            $surveysHtml .= $this->renderSurveyRow($survey);
        }

        $categoryFilterHtml = $this->renderCategoryFilter($categories, $categoryFilter);

        $pagination = $this->component('Pagination', [
            'baseUrl' => '/admin/surveys',
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'params' => ['search' => $search, 'status' => $status, 'category' => $categoryFilter]
        ]);

        return "
        <div class='admin-header'>
            <h1>Управління опитуваннями</h1>
            " . $this->component('AdminNavigation') . "
        </div>
        
        <div class='admin-filters'>
            <form method='GET' action='/admin/surveys' class='filter-form'>
                <input type='text' name='search' placeholder='Пошук опитувань...' value='" . $this->escape($search) . "'>
                <select name='status'>
                    <option value='all'" . ($status === 'all' ? ' selected' : '') . ">Всі статуси</option>
                    <option value='active'" . ($status === 'active' ? ' selected' : '') . ">Активні</option>
                    <option value='inactive'" . ($status === 'inactive' ? ' selected' : '') . ">Неактивні</option>
                </select>
                {$categoryFilterHtml}
                <button type='submit' class='btn btn-primary'>Фільтрувати</button>
            </form>
        </div>
        
        <div class='table-container'>
            <table class='admin-table'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Опитування</th>
                        <th>Категорія</th>
                        <th>Автор</th>
                        <th>Статус</th>
                        <th>Питань</th>
                        <th>Відповідей</th>
                        <th>Створено</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody>{$surveysHtml}</tbody>
            </table>
        </div>
        
        {$pagination}
        
        " . $this->renderSurveyScript();
    }

    private function renderSurveyRow(array $survey): string
    {
        $statusClass = $survey['is_active'] ? 'status-active' : 'status-inactive';
        $statusText = $survey['is_active'] ? 'Активне' : 'Неактивне';

        $categoryBadge = 'Без категорії';
        if (!empty($survey['category_name'])) {
            $categoryBadge = "
            <span class='category-mini-badge' style='background-color: {$survey['category_color']}'>
                {$survey['category_icon']} " . $this->escape($survey['category_name']) . "
            </span>";
        }

        return "
        <tr>
            <td>{$survey['id']}</td>
            <td>
                <strong>" . $this->escape($survey['title']) . "</strong><br>
                <small>" . $this->escape(substr($survey['description'], 0, 100)) . "...</small>
            </td>
            <td>{$categoryBadge}</td>
            <td>" . $this->escape($survey['author_name']) . "</td>
            <td><span class='status-badge {$statusClass}'>{$statusText}</span></td>
            <td>{$survey['question_count']}</td>
            <td>{$survey['response_count']}</td>
            <td>" . date('d.m.Y', strtotime($survey['created_at'])) . "</td>
            <td class='actions'>
                <a href='/admin/edit-survey?id={$survey['id']}' class='btn btn-sm btn-info'>Редагувати</a>
                <a href='/admin/survey-stats?id={$survey['id']}' class='btn btn-sm btn-primary'>Статистика</a>
                
                <form method='POST' action='/admin/toggle-survey-status' style='display: inline;' 
                      onsubmit='return handleFormSubmit(this)'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    <button type='submit' class='btn btn-sm btn-secondary'>
                        " . ($survey['is_active'] ? 'Деактивувати' : 'Активувати') . "
                    </button>
                </form>
                
                <form method='POST' action='/admin/delete-survey' style='display: inline;' 
                      onsubmit='return handleDeleteSubmit(this)'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    <button type='submit' class='btn btn-danger btn-sm'>Видалити</button>
                </form>
            </td>
        </tr>
        
        <style>
            .category-mini-badge {
                display: inline-block;
                color: white;
                padding: 0.3rem 0.6rem;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 500;
                white-space: nowrap;
            }
        </style>";
    }


    private function renderCategoryFilter(array $categories, int $selected): string
    {
        if (empty($categories)) {
            return '';
        }

        $options = "<option value='0'" . ($selected == 0 ? ' selected' : '') . ">Всі категорії</option>";
        foreach ($categories as $category) {
            $isSelected = $selected == $category['id'] ? ' selected' : '';
            $options .= "<option value='{$category['id']}'{$isSelected}>{$category['icon']} " . $this->escape($category['name']) . "</option>";
        }

        return "<select name='category'>{$options}</select>";
    }


    private function renderSurveyScript(): string
    {
        return "
            <script>
                function handleFormSubmit(form) {
                    return submitFormAjax(form, false);
                }
                
                function handleDeleteSubmit(form) {
                    if (!confirm('Видалити це опитування? Ця дія незворотна!')) {
                        return false;
                    }
                    return submitFormAjax(form, true);
                }
                
                function submitFormAjax(form, isDelete) {
                    const formData = new FormData(form);
                    
                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(data.message || 'Виникла помилка', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Виникла помилка при виконанні операції', 'error');
                        form.submit();
                    });
                    
                    return false;
                }
                
                function showMessage(message, type) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flash-message ' + type;
                    messageDiv.style.cssText = `
                        position: fixed; top: 20px; right: 20px; z-index: 9999;
                        padding: 1rem; border-radius: 8px; max-width: 400px;
                    `;
                    messageDiv.textContent = message;
                    
                    if (type === 'success') {
                        messageDiv.style.cssText += 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                    } else {
                        messageDiv.style.cssText += 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                    }
                    
                    document.body.appendChild(messageDiv);
                    setTimeout(() => messageDiv.remove(), 3000);
                }
            </script>";
    }
}