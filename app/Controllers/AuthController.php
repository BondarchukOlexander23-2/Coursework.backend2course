<?php

require_once __DIR__ . '/../Views/Auth/LoginView.php';
require_once __DIR__ . '/../Views/Auth/RegisterView.php';

class AuthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function showLogin(): void
    {
        $this->safeExecute(function() {
            $view = new LoginView([
                'title' => 'Вхід до системи'
            ]);
            $content = $view->render();

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
                $view = new LoginView([
                    'title' => 'Вхід до системи',
                    'error' => 'Заповніть всі поля',
                    'email' => $email
                ]);
                $content = $view->render();

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
                    $view = new LoginView([
                        'title' => 'Вхід до системи',
                        'error' => 'Невірний email або пароль',
                        'email' => $email
                    ]);
                    $content = $view->render();

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
            $view = new RegisterView([
                'title' => 'Реєстрація'
            ]);
            $content = $view->render();

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
                    // перевірка чи не існує вже користувач з таким email
                    if (User::emailExists($email)) {
                        $errors[] = "Користувач з таким email вже існує";
                    } else {
                        $userId = User::create($name, $email, $password);

                        // Автичний логін користувача після реєстрації
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

            if (!empty($errors)) {
                $view = new RegisterView([
                    'title' => 'Реєстрація',
                    'errors' => $errors,
                    'name' => $name,
                    'email' => $email
                ]);
                $content = $view->render();

                $this->responseManager
                    ->setNoCacheHeaders()
                    ->sendClientError(ResponseManager::STATUS_UNPROCESSABLE_ENTITY, $content);
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
}
