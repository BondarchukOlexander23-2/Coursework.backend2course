<?php

/**
 * Контролер для роботи з опитуваннями
 */
class SurveyController
{
    public function index(): void
    {
        $content = $this->renderSurveysList();
        echo $content;
    }

    public function create(): void
    {
        $content = $this->renderCreateForm();
        echo $content;
    }

    public function store(): void
    {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';

        // В майбутньому тут буде збереження в базу даних
        $content = $this->renderSuccessMessage($title, $description);
        echo $content;
    }

    /**
     * Відображає конкретне опитування для проходження
     */
    public function view(): void
    {
        $surveyId = $_GET['id'] ?? 1;
        $content = $this->renderSurveyView($surveyId);
        echo $content;
    }

    public function submit(): void
    {
        $answers = $_POST['answers'] ?? [];
        $content = $this->renderSubmitSuccess($answers);
        echo $content;
    }
    public function results(): void
    {
        $surveyId = $_GET['id'] ?? 1;
        $content = $this->renderResults($surveyId);
        echo $content;
    }
    private function renderSurveysList(): string
    {
        return $this->renderPage("Список опитувань", "
            <h1>Доступні опитування</h1>
            <div class='survey-list'>
                <div class='survey-item'>
                    <h3>Опитування про задоволення сервісом</h3>
                    <p>Допоможіть нам покращити наш сервіс</p>
                    <a href='/surveys/view?id=1' class='btn'>Пройти опитування</a>
                    <a href='/surveys/results?id=1' class='btn btn-secondary'>Результати</a>
                </div>
                <div class='survey-item'>
                    <h3>Дослідження ринку IT</h3>
                    <p>Ваша думка про сучасні IT технології</p>
                    <a href='/surveys/view?id=2' class='btn'>Пройти опитування</a>
                    <a href='/surveys/results?id=2' class='btn btn-secondary'>Результати</a>
                </div>
            </div>
            <div style='margin-top: 20px;'>
                <a href='/surveys/create' class='btn btn-success'>Створити нове опитування</a>
                <a href='/' class='btn btn-secondary'>На головну</a>
            </div>
        ");
    }

    private function renderCreateForm(): string
    {
        return $this->renderPage("Створення опитування", "
            <h1>Створити нове опитування</h1>
            <form method='POST' action='/surveys/store'>
                <div class='form-group'>
                    <label for='title'>Назва опитування:</label>
                    <input type='text' id='title' name='title' required>
                </div>
                <div class='form-group'>
                    <label for='description'>Опис:</label>
                    <textarea id='description' name='description' rows='4'></textarea>
                </div>
                <div class='form-actions'>
                    <button type='submit' class='btn btn-success'>Створити опитування</button>
                    <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                </div>
            </form>
        ");
    }
    private function renderSuccessMessage(string $title, string $description): string
    {
        $safeTitle = htmlspecialchars($title);
        $safeDescription = htmlspecialchars($description);

        return $this->renderPage("Опитування створено", "
            <h1>Опитування успішно створено!</h1>
            <div class='success-message'>
                <p><strong>Назва:</strong> {$safeTitle}</p>
                <p><strong>Опис:</strong> {$safeDescription}</p>
            </div>
            <div class='form-actions'>
                <a href='/surveys' class='btn'>Повернутися до списку</a>
                <a href='/surveys/create' class='btn btn-success'>Створити ще одне</a>
            </div>
        ");
    }
    private function renderSurveyView(int $surveyId): string
    {
        return $this->renderPage("Проходження опитування", "
            <h1>Опитування #{$surveyId}</h1>
            <form method='POST' action='/surveys/submit'>
                <input type='hidden' name='survey_id' value='{$surveyId}'>
                
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
    private function renderSubmitSuccess(array $answers): string
    {
        return $this->renderPage("Дякуємо за участь!", "
            <h1>Дякуємо за участь в опитуванні!</h1>
            <p>Ваші відповіді успішно збережено.</p>
            <div class='form-actions'>
                <a href='/surveys' class='btn'>Інші опитування</a>
                <a href='/' class='btn btn-secondary'>На головну</a>
            </div>
        ");
    }
    private function renderResults(int $surveyId): string
    {
        return $this->renderPage("Результати опитування", "
            <h1>Результати опитування #{$surveyId}</h1>
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
                <a href='/surveys/view?id={$surveyId}' class='btn btn-secondary'>Пройти опитування</a>
            </div>
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