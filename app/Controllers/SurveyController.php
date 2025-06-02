<?php

/**
 * Контролер для роботи з опитуваннями
 */
class SurveyController
{
    public function index(): void
    {
        $surveys = Survey::getAllActive();
        $content = $this->renderSurveysList($surveys);
        echo $content;
    }

    public function create(): void
    {
        // Перевіряємо авторизацію
        Session::requireLogin();

        $content = $this->renderCreateForm();
        echo $content;
    }

    public function store(): void
    {
        Session::requireLogin();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $userId = Session::getUserId();

        // Валідація
        $errors = [];
        if (empty($title)) {
            $errors[] = 'Назва опитування є обов\'язковою';
        }
        if (strlen($title) < 3) {
            $errors[] = 'Назва повинна містити мінімум 3 символи';
        }

        if (!empty($errors)) {
            $content = $this->renderCreateForm($errors, $title, $description);
            echo $content;
            return;
        }

        try {
            $surveyId = Survey::create($title, $description, $userId);
            Session::setFlashMessage('success', 'Опитування успішно створено!');
            header("Location: /surveys/view?id={$surveyId}");
            exit;
        } catch (Exception $e) {
            $content = $this->renderCreateForm(['Помилка при створенні опитування']);
            echo $content;
        }
    }

    public function view(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);

        if ($surveyId <= 0) {
            header('Location: /surveys');
            exit;
        }

        $survey = Survey::findById($surveyId);

        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        $content = $this->renderSurveyView($survey);
        echo $content;
    }

    public function submit(): void
    {
        $surveyId = (int)($_POST['survey_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        // Тут буде логіка збереження відповідей
        Session::setFlashMessage('success', 'Дякуємо за участь в опитуванні!');
        header("Location: /surveys");
        exit;
    }

    public function results(): void
    {
        $surveyId = (int)($_GET['id'] ?? 0);

        if ($surveyId <= 0) {
            header('Location: /surveys');
            exit;
        }

        $survey = Survey::findById($surveyId);
        if (!$survey) {
            header('Location: /surveys');
            exit;
        }

        $stats = Survey::getStats($surveyId);
        $content = $this->renderResults($survey, $stats);
        echo $content;
    }

    public function my(): void
    {
        Session::requireLogin();

        $userId = Session::getUserId();
        $surveys = Survey::getByUserId($userId);
        $content = $this->renderMySurveys($surveys);
        echo $content;
    }

    private function renderSurveysList(array $surveys): string
    {
        $surveyItems = '';

        if (empty($surveys)) {
            $surveyItems = '<p>Наразі немає активних опитувань.</p>';
        } else {
            foreach ($surveys as $survey) {
                $surveyItems .= "
                    <div class='survey-item'>
                        <h3>" . htmlspecialchars($survey['title']) . "</h3>
                        <p>" . htmlspecialchars($survey['description']) . "</p>
                        <p><small>Автор: " . htmlspecialchars($survey['author_name']) . "</small></p>
                        <div class='survey-actions'>
                            <a href='/surveys/view?id={$survey['id']}' class='btn'>Пройти опитування</a>
                            <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                        </div>
                    </div>";
            }
        }

        $createButton = '';
        if (Session::isLoggedIn()) {
            $createButton = "<a href='/surveys/create' class='btn btn-success'>Створити нове опитування</a>";
        }

        return $this->renderPage("Список опитувань", "
            <div class='header-actions'>
                <h1>Доступні опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-list'>
                {$surveyItems}
            </div>
            
            <div class='page-actions'>
                {$createButton}
                <a href='/' class='btn btn-secondary'>На головну</a>
                " . (Session::isLoggedIn() ? "<a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>" : "") . "
            </div>
        ");
    }

    private function renderCreateForm(array $errors = [], string $title = '', string $description = ''): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        $title = htmlspecialchars($title);
        $description = htmlspecialchars($description);

        return $this->renderPage("Створення опитування", "
            <div class='header-actions'>
                <h1>Створити нове опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            {$errorHtml}
            
            <form method='POST' action='/surveys/store'>
                <div class='form-group'>
                    <label for='title'>Назва опитування:</label>
                    <input type='text' id='title' name='title' required value='{$title}'>
                </div>
                <div class='form-group'>
                    <label for='description'>Опис:</label>
                    <textarea id='description' name='description' rows='4'>{$description}</textarea>
                </div>
                
                
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Створити опитування</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
        ");
    }

    private function renderSurveyView(array $survey): string
    {
        return $this->renderPage("Проходження опитування", "
            <div class='header-actions'>
                <div>
                    <h1>" . htmlspecialchars($survey['title']) . "</h1>
                    <p>" . htmlspecialchars($survey['description']) . "</p>
                    <p><small>Автор: " . htmlspecialchars($survey['author_name']) . "</small></p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <form method='POST' action='/surveys/submit'>
                <input type='hidden' name='survey_id' value='{$survey['id']}'>
                
                <div class='question'>
                    <h3>1. Як ви оцінюєте наш сервіс?</h3>
                    <label><input type='radio' name='answers[1]' value='excellent'> Відмінно</label><br>
                    <label><input type='radio' name='answers[1]' value='good'> Добре</label><br>
                    <label><input type='radio' name='answers[1]' value='average'> Середньо</label><br>
                    <label><input type='radio' name='answers[1]' value='poor'> Погано</label>
                </div>
                
                <div class='question'>
                    <h3>2. Що б ви хотіли покращити?</h3>
                    <textarea name='answers[2]' rows='4' placeholder='Ваша відповідь...'></textarea>
                </div>
                
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Надіслати відповіді</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
        ");
    }

    private function renderResults(array $survey, array $stats): string
    {
        return $this->renderPage("Результати опитування", "
            <div class='header-actions'>
                <div>
                    <h1>Результати: " . htmlspecialchars($survey['title']) . "</h1>
                    <p>Всього відповідей: {$stats['total_responses']}</p>
                </div>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='results'>
                <h3>Статистика відповідей:</h3>
                <div class='result-item'>
                    <p><strong>Відмінно:</strong> 45% (18 відповідей)</p>
                    <div class='progress-bar'><div class='progress' style='width: 45%'></div></div>
                </div>
                <div class='result-item'>
                    <p><strong>Добре:</strong> 30% (12 відповідей)</p>
                    <div class='progress-bar'><div class='progress' style='width: 30%'></div></div>
                </div>
                <div class='result-item'>
                    <p><strong>Середньо:</strong> 20% (8 відповідей)</p>
                    <div class='progress-bar'><div class='progress' style='width: 20%'></div></div>
                </div>
                <div class='result-item'>
                    <p><strong>Погано:</strong> 5% (2 відповіді)</p>
                    <div class='progress-bar'><div class='progress' style='width: 5%'></div></div>
                </div>
            </div>
            
            <div class='form-actions'>
                <a href='/surveys' class='btn'>До списку опитувань</a>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-secondary'>Пройти опитування</a>
            </div>
        ");
    }

    private function renderMySurveys(array $surveys): string
    {
        $surveyItems = '';

        if (empty($surveys)) {
            $surveyItems = '<p>У вас ще немає створених опитувань.</p>';
        } else {
            foreach ($surveys as $survey) {
                $status = $survey['is_active'] ? 'Активне' : 'Неактивне';
                $surveyItems .= "
                    <div class='survey-item'>
                        <h3>" . htmlspecialchars($survey['title']) . "</h3>
                        <p>" . htmlspecialchars($survey['description']) . "</p>
                        <p><small>Статус: {$status}</small></p>
                        <div class='survey-actions'>
                            <a href='/surveys/view?id={$survey['id']}' class='btn'>Переглянути</a>
                            <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                        </div>
                    </div>";
            }
        }

        return $this->renderPage("Мої опитування", "
            <div class='header-actions'>
                <h1>Мої опитування</h1>
                " . $this->renderUserNav() . "
            </div>
            
            <div class='survey-list'>
                {$surveyItems}
            </div>
            
            <div class='page-actions'>
                <a href='/surveys/create' class='btn btn-success'>Створити нове</a>
                <a href='/surveys' class='btn btn-secondary'>Всі опитування</a>
            </div>
        ");
    }

    private function renderUserNav(): string
    {
        if (Session::isLoggedIn()) {
            $userName = Session::getUserName();
            return "
                <div class='user-nav'>
                    <span>Привіт, " . htmlspecialchars($userName) . "!</span>
                    <a href='/logout' class='btn btn-sm'>Вийти</a>
                </div>";
        } else {
            return "
                <div class='user-nav'>
                    <a href='/login' class='btn btn-sm'>Увійти</a>
                    <a href='/register' class='btn btn-sm'>Реєстрація</a>
                </div>";
        }
    }

    private function renderPage(string $title, string $content): string
    {
        $flashSuccess = Session::getFlashMessage('success');
        $flashError = Session::getFlashMessage('error');

        $flashHtml = '';
        if ($flashSuccess) {
            $flashHtml .= "<div class='flash-message success'>{$flashSuccess}</div>";
        }
        if ($flashError) {
            $flashHtml .= "<div class='flash-message error'>{$flashError}</div>";
        }

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
                {$flashHtml}
                {$content}
            </div>
        </body>
        </html>";
    }
}