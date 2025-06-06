<?php

require_once __DIR__ . '/../BaseView.php';

class UsersView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $users = $this->get('users', []);
        $currentPage = $this->get('currentPage', 1);
        $totalPages = $this->get('totalPages', 1);
        $search = $this->get('search', '');

        $usersHtml = '';
        foreach ($users as $user) {
            $usersHtml .= $this->renderUserRow($user);
        }

        $pagination = $this->component('Pagination', [
            'baseUrl' => '/admin/users',
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'params' => ['search' => $search]
        ]);

        return "
            <div class='admin-header'>
                <h1>Управління користувачами</h1>
                " . $this->component('AdminNavigation') . "
            </div>
            
            <div class='admin-filters'>
                <form method='GET' action='/admin/users' class='search-form'>
                    <input type='text' name='search' placeholder='Пошук користувачів...' value='" . $this->escape($search) . "'>
                    <button type='submit' class='btn btn-primary'>Знайти</button>
                </form>
            </div>
            
            <div class='table-container'>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ім'я</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Опитувань</th>
                            <th>Відповідей</th>
                            <th>Реєстрація</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>{$usersHtml}</tbody>
                </table>
            </div>
            
            {$pagination}
            
            " . $this->renderUserScript() . "";
    }

    private function renderUserRow(array $user): string
    {
        $roleClass = $user['role'] === 'admin' ? 'role-admin' : 'role-user';
        $roleText = $user['role'] === 'admin' ? 'Адмін' : 'Користувач';

        $actionsHtml = '';

        $currentUserId = Session::getUserId();
        $isCurrentUser = ($user['id'] == $currentUserId);

        if ($isCurrentUser) {
            $actionsHtml = "<em>Поточний користувач</em>";
        } else {
            $actionsHtml = "
                <form method='POST' action='/admin/change-user-role' style='display: inline;' 
                      onsubmit='return handleRoleChange(this)'>
                    <input type='hidden' name='user_id' value='{$user['id']}'>
                    <select name='role' onchange='this.form.submit()'>
                        <option value='user'" . ($user['role'] === 'user' ? ' selected' : '') . ">Користувач</option>
                        <option value='admin'" . ($user['role'] === 'admin' ? ' selected' : '') . ">Адмін</option>
                    </select>
                </form>
                
                <form method='POST' action='/admin/delete-user' style='display: inline;' 
                      onsubmit='return handleDeleteSubmit(this)'>
                    <input type='hidden' name='user_id' value='{$user['id']}'>
                    <button type='submit' class='btn btn-danger btn-sm'>Видалити</button>
                </form>";
        }

        return "<tr>
            <td>{$user['id']}</td>
            <td>" . $this->escape($user['name']) . "</td>
            <td>" . $this->escape($user['email']) . "</td>
            <td><span class='role-badge {$roleClass}'>{$roleText}</span></td>
            <td>{$user['surveys_count']}</td>
            <td>{$user['responses_count']}</td>
            <td>{$user['created_at']}</td>
            <td class='actions'>{$actionsHtml}</td>
        </tr>";
    }

    private function renderUserScript(): string
    {
        return "
            <script>
                function handleRoleChange(form) {
                    return submitFormAjax(form, false, 'Змінити роль користувача?');
                }
                
                function handleDeleteSubmit(form) {
                    if (!confirm('Видалити цього користувача? Ця дія незворотна!')) {
                        return false;
                    }
                    return submitFormAjax(form, true);
                }
                
                function submitFormAjax(form, isDelete, confirmMessage = null) {
                    if (confirmMessage && !confirm(confirmMessage)) {
                        return false;
                    }
                    
                    const formData = new FormData(form);
                    const button = form.querySelector('button[type=\"submit\"]');
                    const select = form.querySelector('select');
                    
                    // Блокуємо кнопки/селекти на час запиту
                    if (button) button.disabled = true;
                    if (select) select.disabled = true;
                    
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
                            // Розблоковуємо елементи при помилці
                            if (button) button.disabled = false;
                            if (select) select.disabled = false;
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
                    // Видаляємо попередні повідомлення
                    const existingMessages = document.querySelectorAll('.flash-message');
                    existingMessages.forEach(msg => msg.remove());
                    
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flash-message ' + type;
                    messageDiv.style.cssText = `
                        position: fixed; 
                        top: 20px; 
                        right: 20px; 
                        z-index: 9999;
                        padding: 1rem; 
                        border-radius: 8px; 
                        max-width: 400px;
                        font-size: 14px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                        animation: slideInRight 0.3s ease-out;
                    `;
                    messageDiv.textContent = message;
                    
                    if (type === 'success') {
                        messageDiv.style.cssText += 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                    } else {
                        messageDiv.style.cssText += 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                    }
                    
                    document.body.appendChild(messageDiv);
                    setTimeout(() => messageDiv.remove(), 4000);
                }
                
                if (!document.querySelector('#flash-animation-styles')) {
                    const style = document.createElement('style');
                    style.id = 'flash-animation-styles';
                    style.textContent = `
                        @keyframes slideInRight {
                            from {
                                opacity: 0;
                                transform: translateX(100%);
                            }
                            to {
                                opacity: 1;
                                transform: translateX(0);
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
            </script>";
    }
}