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
        if ($user['role'] !== 'admin') {
            $actionsHtml = "
                <form method='POST' action='/admin/change-user-role' style='display: inline;' 
                      onsubmit='return handleFormSubmit(this)'>
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
        } else {
            $actionsHtml = "<em>Системний адмін</em>";
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
                function handleFormSubmit(form) {
                    return submitFormAjax(form, false);
                }
            </script>";
    }
}