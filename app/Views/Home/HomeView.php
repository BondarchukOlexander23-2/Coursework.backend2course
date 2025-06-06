<?php

require_once __DIR__ . '/../BaseView.php';

class HomeView extends BaseView
{
    protected string $layout = '';

    protected function content(): string
    {
        $title = $this->get('title', 'Платформа для онлайн-опитувань');

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body class='home-page'>
            " . $this->renderHeader() . "
            
            <main class='hero-section'>
                <div class='hero-content'>
                    <h1 class='hero-title'>Створюйте та проводьте <span class='highlight'>онлайн-опитування</span> легко</h1>
                    <p class='hero-subtitle'>Потужна платформа для збору відгуків, проведення досліджень та аналізу думок вашої аудиторії</p>
                    
                    <div class='hero-actions'>
                        <a href='/surveys' class='btn btn-primary btn-large'>Переглянути опитування</a>
                        <a href='/surveys/create' class='btn btn-outline btn-large'>Створити опитування</a>
                    </div>
                </div>
                
                " . $this->renderHeroVisual() . "
            </main>
            
            " . $this->renderFeaturesSection() . "
            " . $this->renderCtaSection() . "
            " . $this->renderFooter() . "
        </body>
        </html>";
    }

    private function renderHeader(): string
    {
        return "
            <header class='header'>
                <div class='container'>
                    <div class='header-content'>
                        <div class='logo'>
                            <a href='/'>
                                <span class='logo-icon'>📋</span>
                                <span class='logo-text'>Survey Platform</span>
                            </a>
                        </div>
                        
                        <nav class='nav'>
                            <a href='/surveys' class='nav-link'>Опитування</a>
                            <a href='/surveys/create' class='nav-link'>Створити</a>
                        </nav>
                        
                        <div class='header-auth'>
                            " . $this->renderAuthButtons() . "
                        </div>
                    </div>
                </div>
            </header>";
    }

    private function renderAuthButtons(): string
    {
        if (Session::isLoggedIn()) {
            $userName = Session::getUserName();

            $adminButton = "";
            if ($this->isAdmin()) {
                $adminButton = "<a href='/admin' class='btn btn-sm btn-warning'>⚙️ Адмін-панель</a>";
            }

            return "
            <div class='user-menu'>
                <span class='user-name'>Привіт, " . $this->escape($userName) . "!</span>
                <a href='/surveys/my' class='btn btn-sm btn-outline'>Мої опитування</a>
                {$adminButton}
                <a href='/logout' class='btn btn-sm btn-secondary'>Вийти</a>
            </div>";
        } else {
            return "
            <div class='auth-buttons'>
                <a href='/login' class='btn btn-sm btn-outline'>Увійти</a>
                <a href='/register' class='btn btn-sm btn-primary'>Реєстрація</a>
            </div>";
        }
    }

    private function isAdmin(): bool
    {
        $userId = Session::getUserId();
        if (!$userId) {
            return false;
        }

        try {
            $user = User::findById($userId);
            return $user && $user['role'] === 'admin';
        } catch (Exception $e) {
            error_log("Error checking admin role in HomeView: " . $e->getMessage());
            return false;
        }
    }

    private function renderHeroVisual(): string
    {
        return "
                <div class='hero-visual'>
                    <div class='floating-card card-1'>
                        <div class='card-icon'>📊</div>
                        <h4>Аналітика</h4>
                        <p>Детальна статистика результатів</p>
                    </div>
                    <div class='floating-card card-2'>
                        <div class='card-icon'>⚡</div>
                        <h4>Швидко</h4>
                        <p>Створюйте опитування за хвилини</p>
                    </div>
                    <div class='floating-card card-3'>
                        <div class='card-icon'>🎯</div>
                        <h4>Ефективно</h4>
                        <p>Отримуйте якісні відповіді</p>
                    </div>
                </div>";
    }

    private function renderFeaturesSection(): string
    {
        return "
            <section class='features-section'>
                <div class='container'>
                    <h2 class='section-title'>Чому обирають нашу платформу?</h2>
                    <div class='features-grid'>
                        <div class='feature-card'>
                            <div class='feature-icon'>🚀</div>
                            <h3>Простота використання</h3>
                            <p>Інтуїтивний інтерфейс дозволяє створювати опитування без технічних знань</p>
                        </div>
                        <div class='feature-card'>
                            <div class='feature-icon'>📈</div>
                            <h3>Аналітика в реальному часі</h3>
                            <p>Відстежуйте результати та статистику відповідей миттєво</p>
                        </div>
                        <div class='feature-card'>
                            <div class='feature-icon'>🔒</div>
                            <h3>Безпека даних</h3>
                            <p>Ваші дані захищені сучасними методами шифрування</p>
                        </div>
                        <div class='feature-card'>
                            <div class='feature-icon'>📱</div>
                            <h3>Адаптивний дизайн</h3>
                            <p>Працює на всіх пристроях - від смартфонів до комп'ютерів</p>
                        </div>
                        <div class='feature-card'>
                            <div class='feature-icon'>⭐</div>
                            <h3>Безкоштовно</h3>
                            <p>Користуйтесь всіма можливостями платформи абсолютно безкоштовно</p>
                        </div>
                        <div class='feature-card'>
                            <div class='feature-icon'>🤝</div>
                            <h3>Підтримка 24/7</h3>
                            <p>Наша команда завжди готова допомогти вам</p>
                        </div>
                    </div>
                </div>
            </section>";
    }

    private function renderCtaSection(): string
    {
        $ctaButtons = !Session::isLoggedIn() ?
            "<a href='/register' class='btn btn-success btn-large'>Зареєструватися безкоштовно</a>
             <a href='/login' class='btn btn-outline-light btn-large'>Увійти</a>" :
            "<a href='/surveys/create' class='btn btn-success btn-large'>Створити перше опитування</a>
             <a href='/surveys' class='btn btn-outline-light btn-large'>Переглянути опитування</a>";

        return "
            <section class='cta-section'>
                <div class='container'>
                    <div class='cta-content'>
                        <h2>Готові почати?</h2>
                        <p>Приєднуйтесь до тисяч користувачів які вже використовують нашу платформу</p>
                        <div class='cta-actions'>
                            {$ctaButtons}
                        </div>
                    </div>
                </div>
            </section>";
    }

    private function renderFooter(): string
    {
        $footerLinks = !Session::isLoggedIn() ?
            "<a href='/login'>Увійти</a>
             <a href='/register'>Реєстрація</a>" :
            "<a href='/surveys/my'>Мої опитування</a>
             <a href='/logout'>Вийти</a>";

        return "
            <footer class='footer'>
                <div class='container'>
                    <div class='footer-content'>
                        <div class='footer-brand'>
                            <h3>Survey Platform</h3>
                            <p>Створюйте, діліться та аналізуйте опитування легко</p>
                        </div>
                        <div class='footer-links'>
                            <div class='footer-column'>
                                <h4>Платформа</h4>
                                <a href='/surveys'>Опитування</a>
                                <a href='/surveys/create'>Створити</a>
                            </div>
                            <div class='footer-column'>
                                <h4>Акаунт</h4>
                                {$footerLinks}
                            </div>
                        </div>
                    </div>
                    <div class='footer-bottom'>
                        <p>&copy; 2024 Survey Platform. Всі права захищені.</p>
                    </div>
                </div>
            </footer>";
    }
}