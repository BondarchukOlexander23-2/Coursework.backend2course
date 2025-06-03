<?php

/**
 * Оновлений контролер для авторизації та реєстрації користувачів
 * Тепер успадковує від BaseController та використовує ResponseManager
 */
class AuthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function showLogin(): void
    {
        $this->safeExecute(function() {
            $content = $this->renderLoginForm();

            // Вимикаємо кешування для форм
            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    public function login(): void
    {
        $this->safeExecute(function() {
            $email = $this->postParam('email', '');
            $password = $this->postParam('password', '');

            if (empty($email) || empty($password)) {
                $content = $this->renderLoginForm('Заповніть всі поля');
                $this->responseManager
                    ->setNoCacheHeaders()
                    ->sendClientError(ResponseManager::STATUS_BAD_REQUEST, $content);
                return;
            }

            try {
                $user = User::authenticate($email, $password);

                if ($user) {
                    session_start();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];

                    $this->redirect('/surveys');
                } else {
                    $content = $this->renderLoginForm('Невірний email або пароль');
                    $this->responseManager
                        ->setNoCacheHeaders()
                        ->sendClientError(ResponseManager::STATUS_UNAUTHORIZED, $content);
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при авторизації');
            }
        });
    }

    public function showRegister(): void
    {
        $this->safeExecute(function() {
            $content = $this->renderRegisterForm();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    public function register(): void
    {
        $this->safeExecute(function() {
            $name = $this->postParam('name', '');
            $email = $this->postParam('email', '');
            $password = $this->postParam('password', '');
            $confirmPassword = $this->postParam('confirm_password', '');

            $errors = $this->validateRegistration($name, $email, $password, $confirmPassword);

            if (empty($errors)) {
                try {
                    // Перевіряємо чи не існує вже користувач з таким email
                    if (User::emailExists($email)) {
                        $errors[] = "Користувач з таким email вже існує";
                    } else {
                        $userId = User::create($name, $email, $password);

                        // Автоматично логінимо користувача після реєстрації
                        session_start();
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_role'] = 'user';

                        $this->redirect('/surveys');
                        return;
                    }
                } catch (Exception $e) {
                    throw new DatabaseException($e->getMessage(), 'Помилка при реєстрації');
                }
            }

            // Якщо є помилки - показуємо форму з помилками
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }
        });
    }

    public function logout(): void
    {
        $this->safeExecute(function() {
            session_start();
            session_destroy();
            $this->redirect('/');
        });
    }

    private function validateRegistration(string $name, string $email, string $password, string $confirmPassword): array
    {
        $errors = [];

        if (empty($name)) {
            $errors[] = "Ім'я є обов'язковим полем";
        } elseif (strlen($name) < 2) {
            $errors[] = "Ім'я повинно містити мінімум 2 символи";
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
        $email = htmlspecialchars($this->postParam('email', ''));

        return $this->buildHtmlPage("Вхід", "
            <h1>Вхід до системи</h1>
            {$errorHtml}
            <form method='POST' action='/login'>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required value='{$email}'>
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
        ");
    }

    private function renderRegisterForm(array $errors = []): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', array_map('htmlspecialchars', $errors));
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        // Зберігаємо введені дані при помилці
        $name = htmlspecialchars($this->postParam('name', ''));
        $email = htmlspecialchars($this->postParam('email', ''));

        return $this->buildHtmlPage("Реєстрація", "
            <h1>Реєстрація нового користувача</h1>
            {$errorHtml}
            <form method='POST' action='/register'>
                <div class='form-group'>
                    <label for='name'>Ім'я:</label>
                    <input type='text' id='name' name='name' required value='{$name}'>
                </div>
                <div class='form-group'>
                    <label for='email'>Email:</label>
                    <input type='email' id='email' name='email' required value='{$email}'>
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
}