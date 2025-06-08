<?php

require_once __DIR__ . '/../BaseView.php';

class CategoriesView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $categories = $this->get('categories', []);

        $categoriesHtml = '';
        foreach ($categories as $category) {
            $categoriesHtml .= $this->renderCategoryRow($category);
        }

        return "
            <div class='admin-header'>
                <h1>Управління категоріями</h1>
                " . $this->component('AdminNavigation') . "
            </div>
            
            <div class='categories-actions'>
                <button onclick='showCreateModal()' class='btn btn-success'>
                    <span class='btn-icon'>➕</span> Створити категорію
                </button>
            </div>
            
            <div class='table-container'>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Категорія</th>
                            <th>Опитувань</th>
                            <th>Статус</th>
                            <th>Створено</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>{$categoriesHtml}</tbody>
                </table>
            </div>
            
            " . $this->renderModal() . "
            " . $this->renderScript();
    }

    private function renderCategoryRow(array $category): string
    {
        $statusClass = $category['is_active'] ? 'status-active' : 'status-inactive';
        $statusText = $category['is_active'] ? 'Активна' : 'Неактивна';
        $protectedClass = $category['id'] == 0 ? 'protected-category' : '';

        return "
            <tr class='{$protectedClass}'>
                <td>{$category['id']}</td>
                <td>
                    <div class='category-display'>
                        <span class='category-icon' style='color: {$category['color']}'>{$category['icon']}</span>
                        <div>
                            <strong style='color: {$category['color']}'>" . $this->escape($category['name']) . "</strong><br>
                            <small>" . $this->escape($category['description']) . "</small>
                        </div>
                    </div>
                </td>
                <td class='text-center'>{$category['surveys_count']}</td>
                <td><span class='status-badge {$statusClass}'>{$statusText}</span></td>
                <td>" . date('d.m.Y', strtotime($category['created_at'])) . "</td>
                <td class='actions'>
                    <button onclick='editCategory({$category['id']})' class='btn btn-sm btn-primary'>
                        Редагувати
                    </button>
                    " . ($category['id'] != 0 ? "
                    <form method='POST' action='/admin/toggle-category-status' style='display: inline;' 
                          onsubmit='return handleToggleStatus(this)'>
                        <input type='hidden' name='id' value='{$category['id']}'>
                        <button type='submit' class='btn btn-sm btn-secondary'>
                            " . ($category['is_active'] ? 'Деактивувати' : 'Активувати') . "
                        </button>
                    </form>
                    <form method='POST' action='/admin/delete-category' style='display: inline;' 
                          onsubmit='return handleDelete(this)'>
                        <input type='hidden' name='id' value='{$category['id']}'>
                        <button type='submit' class='btn btn-danger btn-sm'>Видалити</button>
                    </form>
                    " : "<em>Захищена</em>") . "
                </td>
            </tr>";
    }

    private function renderModal(): string
    {
        return "
            <div id='categoryModal' class='modal' style='display: none;'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h3 id='modalTitle'>Створити категорію</h3>
                        <span class='close' onclick='closeModal()'>&times;</span>
                    </div>
                    <form id='categoryForm'>
                        <input type='hidden' id='categoryId' name='id'>
                        
                        <div class='form-group'>
                            <label for='categoryName'>Назва категорії:</label>
                            <input type='text' id='categoryName' name='name' required maxlength='255'>
                        </div>
                        
                        <div class='form-group'>
                            <label for='categoryDescription'>Опис:</label>
                            <textarea id='categoryDescription' name='description' rows='3' maxlength='500'></textarea>
                        </div>
                        
                        <div class='form-row'>
                            <div class='form-group'>
                                <label for='categoryColor'>Колір:</label>
                                <input type='color' id='categoryColor' name='color' value='#3498db'>
                            </div>
                            
                            <div class='form-group'>
                                <label for='categoryIcon'>Іконка:</label>
                                <select id='categoryIcon' name='icon'>
                                    <option value='📋'>📋 Загальні</option>
                                    <option value='🎓'>🎓 Освіта</option>
                                    <option value='💼'>💼 Бізнес</option>
                                    <option value='🎮'>🎮 Розваги</option>
                                    <option value='🏥'>🏥 Здоров'я</option>
                                    <option value='💻'>💻 Технології</option>
                                    <option value='🎨'>🎨 Творчість</option>
                                    <option value='🏃'>🏃 Спорт</option>
                                    <option value='🌍'>🌍 Подорожі</option>
                                    <option value='🍕'>🍕 Їжа</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class='modal-actions'>
                            <button type='button' onclick='closeModal()' class='btn btn-secondary'>Скасувати</button>
                            <button type='submit' class='btn btn-success'>Зберегти</button>
                        </div>
                    </form>
                </div>
            </div>";
    }

    private function renderScript(): string
    {
        return "
            <style>
                .categories-actions { margin-bottom: 2rem; }
                .category-display { display: flex; align-items: center; gap: 1rem; }
                .category-icon { font-size: 1.5rem; }
                .text-center { text-align: center; }
                .protected-category { background-color: #fff9e6; }
                
                .modal {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.5); z-index: 9999;
                    display: flex; align-items: center; justify-content: center;
                }
                .modal-content {
                    background: white; border-radius: 12px; padding: 2rem;
                    max-width: 500px; width: 90%; max-height: 90%; overflow-y: auto;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                }
                .modal-header {
                    display: flex; justify-content: space-between; align-items: center;
                    margin-bottom: 1.5rem; border-bottom: 2px solid #eee; padding-bottom: 1rem;
                }
                .close {
                    font-size: 2rem; cursor: pointer; color: #999;
                    transition: color 0.3s ease;
                }
                .close:hover { color: #333; }
                .modal-actions { 
                    display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; 
                }
                .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
                
                @media (max-width: 768px) {
                    .form-row { grid-template-columns: 1fr; }
                    .modal-content { padding: 1rem; }
                }
            </style>
            
            <script>
                const categoriesData = " . json_encode($this->get('categories', [])) . ";
                
                function showCreateModal() {
                    document.getElementById('modalTitle').textContent = 'Створити категорію';
                    document.getElementById('categoryForm').reset();
                    document.getElementById('categoryId').value = '';
                    document.getElementById('categoryModal').style.display = 'flex';
                }
                
                function editCategory(id) {
                    const category = categoriesData.find(c => c.id == id);
                    if (!category) return;
                    
                    document.getElementById('modalTitle').textContent = 'Редагувати категорію';
                    document.getElementById('categoryId').value = category.id;
                    document.getElementById('categoryName').value = category.name;
                    document.getElementById('categoryDescription').value = category.description || '';
                    document.getElementById('categoryColor').value = category.color;
                    document.getElementById('categoryIcon').value = category.icon;
                    document.getElementById('categoryModal').style.display = 'flex';
                }
                
                function closeModal() {
                    document.getElementById('categoryModal').style.display = 'none';
                }
                
                document.getElementById('categoryForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const isEdit = formData.get('id');
                    const url = isEdit ? '/admin/update-category' : '/admin/create-category';
                    
                    fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            closeModal();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(data.message || 'Помилка', 'error');
                        }
                    })
                    .catch(() => this.submit());
                });
                
                function handleToggleStatus(form) {
                    return submitFormAjax(form, false);
                }
                
                function handleDelete(form) {
                    if (!confirm('Видалити категорію? Опитування будуть перенесені в \"Загальні\".')) {
                        return false;
                    }
                    return submitFormAjax(form, true);
                }
                
                function submitFormAjax(form, isDelete) {
                    const formData = new FormData(form);
                    
                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        showMessage(data.message || 'Готово', data.success ? 'success' : 'error');
                        if (data.success) setTimeout(() => location.reload(), 1000);
                    })
                    .catch(() => form.submit());
                    
                    return false;
                }
                
                function showMessage(message, type) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flash-message ' + type;
                    messageDiv.textContent = message;
                    messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 1rem; border-radius: 8px;';
                    
                    if (type === 'success') {
                        messageDiv.style.cssText += 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                    } else {
                        messageDiv.style.cssText += 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                    }
                    
                    document.body.appendChild(messageDiv);
                    setTimeout(() => messageDiv.remove(), 4000);
                }
                
                // Закриття модалки по кліку поза нею
                document.getElementById('categoryModal').addEventListener('click', function(e) {
                    if (e.target === this) closeModal();
                });
                
                // Закриття по ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') closeModal();
                });
            </script>";
    }
}