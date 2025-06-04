<?php

class NavigationComponent extends BaseView
{
    protected function content(): string
    {
        if (Session::isLoggedIn()) {
            $userName = Session::getUserName();

            // Кнопка адміна тільки для адмінів
            $adminButton = "";
            if (Session::isAdmin()) {
                $adminButton = "<a href='/admin' class='btn btn-sm' style='background: #f39c12; color: white;'>⚙️ Адмін</a>";
            }

            return "
                <div class='user-nav'>
                    <span>Привіт, " . $this->escape($userName) . "!</span>
                    <a href='/surveys/my' class='btn btn-sm'>Мої опитування</a>
                    {$adminButton}
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
}