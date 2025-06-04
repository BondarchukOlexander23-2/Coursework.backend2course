<?php

require_once __DIR__ . '/../BaseView.php';

class LoginView extends BaseView
{
    protected function content(): string
    {
        $error = $this->get('error', '');
        $email = $this->get('email', '');

        $errorHtml = $error ? "<div class='error-message'>{$error}</div>" : '';

        return "
            <div class='container'>
            <h1>Вхід до системи</h1>
            {$errorHtml}
            <form method='POST' action='/login'>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required value='" . $this->escape($email) . "'>
                </div>
                <div class='form-group'>
                    <label for='password'>Пароль:</label>
                    <input type='password' id='password' name='password' required>
                </div>
                <div class='form-actions'>
                    <div class='top-button'>
                        <button type='submit' class='btn btn-success'>Увійти</button>
                    </div>
                    <div class='bottom-buttons'>
                        <a href='/register' class='btn btn-secondary'>Реєстрація</a>
                        <a href='/' class='btn btn-secondary'>На головну</a>
                    </div>
                </div>
            </form>
            </div>";
    }
}