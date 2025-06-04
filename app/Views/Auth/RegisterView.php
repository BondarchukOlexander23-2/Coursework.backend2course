<?php

require_once __DIR__ . '/../BaseView.php';

class RegisterView extends BaseView
{
    protected function content(): string
    {
        $errors = $this->get('errors', []);
        $name = $this->get('name', '');
        $email = $this->get('email', '');

        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', array_map([$this, 'escape'], $errors));
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        return "
            <div  class='container'>
            <h1>Реєстрація нового користувача</h1>
            {$errorHtml}
            <form method='POST' action='/register'>
                <div class='form-group'>
                    <label for='name'>Ім'я:</label>
                    <input type='text' id='name' name='name' required value='" . $this->escape($name) . "'>
                </div>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required value='" . $this->escape($email) . "'>
                </div>
                <div class='form-group'>
                    <label for='password'>Пароль:</label>
                    <input type='password' id='password' name='password' required>
                </div>
                <div class='form-group'>
                    <label for='confirm_password'>Підтвердження пароля:</label>
                    <input type='password' id='confirm_password' name='confirm_password' required>
                </div>
               <div class='form-actions'>
        <div class='top-button'>
                <button type='submit' class='btn btn-success'>Зареєструватися</button>
            </div>
            <div class='bottom-buttons'>
                <a href='/login' class='btn btn-secondary'>Вже є акаунт?</a>
                <a href='/' class='btn btn-secondary'>На головну</a>
            </div>
        </div>

            </form>
            </div>";
    }
}