<?php

/**
 * Контролер для авторизації та реєстрації користувачів
 */
class AuthController
{
    public function showLogin(): void
    {
        $content = $this->renderLoginForm();
        echo $content;
    }

    public function login(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($this->validateLogin($email, $password)) {
            session_start();
            $_SESSION['user_id'] = 1;
            $_SESSION['user_email'] = $email;
            header('Location: /surveys');
            exit;
        } else {
            $content = $this->renderLoginForm('Невірний email або пароль');
            echo $content;
        }
    }

    public function showRegister(): void
    {
        $content = $this->renderRegisterForm();
        echo $content;
    }

    public function register(): void
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = $this->validateRegistration($name, $email, $password, $confirmPassword);

        if (empty($errors)) {
            session_start();
            $_SESSION['user_id'] = 2;
            $_SESSION['user_email'] = $email;
            header('Location: /surveys');
            exit;
        } else {
            $content = $this->renderRegisterForm($errors);
            echo $content;
        }
    }

    private function validateLogin(string $email, string $password): bool
    {
        return $email === 'test@example.com' && $password === 'password';
    }

    private function validateRegistration(string $name, string $email, string $password, string $confirmPassword): array
    {
        $errors = [];

        if (empty($name)) {
            $errors[] = "Ім'я є обов'язковим полем";
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Введіть правильний email";
        }

        if (strlen($password) < 6) {
            $errors[] = "Пароль повинен містити мінімум 6 символів";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Паролі не співпадають";
        }

        return $errors;
    }

    private function renderLoginForm(string $error = ''): string
    {
        $errorHtml = $error ? "<div class='error-message'>{$error}</div>" : '';

        return $this->renderPage("Вхід", "
            <h1>Вхід до системи</h1>
            {$errorHtml}
            <form method='POST' action='/login'>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required>
                </div>
                <div class='form-group'>
                    <label for='password'>Пароль:</label>
                    <input type='password' id='password' name='password' required>
                </div>
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Увійти</button>
                    <a href='/register' class='btn btn-secondary'>Реєстрація</a>
                    <a href='/' class='btn btn-secondary'>На головну</a>
                </div>
            </form>
            <div class='demo-info'>
                <p><strong>Тестові дані:</strong></p>
                <p>Email: test@example.com</p>
                <p>Пароль: password</p>
            </div>
        ");
    }

    private function renderRegisterForm(array $errors = []): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        return $this->renderPage("Реєстрація", "
            <h1>Реєстрація нового користувача</h1>
            {$errorHtml}
            <form method='POST' action='/register'>
                <div class='form-group'>
                    <label for='name'>Ім'я:</label>
                    <input type='text' id='name' name='name' required>
                </div>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required>
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
                    <button type='submit' class='btn btn-success'>Зареєструватися</button>
                    <a href='/login' class='btn btn-secondary'>Вже є акаунт?</a>
                    <a href='/' class='btn btn-secondary'>На головну</a>
                </div>
            </form>
        ");
    }

    private function renderPage(string $title, string $content): string
    {
        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body>
            <div class='container'>
                {$content}
            </div>
        </body>
        </html>";
    }
}