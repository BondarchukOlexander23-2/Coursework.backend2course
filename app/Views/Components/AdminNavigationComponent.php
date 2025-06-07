<?php

// === ОНОВЛЕННЯ AdminNavigationComponent.php ===

require_once __DIR__ . '/../BaseView.php';

class AdminNavigationComponent extends BaseView
{
    protected function content(): string
    {
        return "
            <nav class='admin-nav'>
                <a href='/admin' class='admin-nav-link'>📊 Дашборд</a>
                <a href='/admin/users' class='admin-nav-link'>👥 Користувачі</a>
                <a href='/admin/surveys' class='admin-nav-link'>📋 Опитування</a>
                <a href='/admin/categories' class='admin-nav-link'>🏷️ Категорії</a>
                <a href='/surveys' class='admin-nav-link'>🌐 До сайту</a>
                <a href='/logout' class='admin-nav-link'>🚪 Вийти</a>
            </nav>";
    }
}