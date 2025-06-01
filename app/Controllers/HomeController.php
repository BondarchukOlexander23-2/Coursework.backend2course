<?php

/**
 * Контролер для головної сторінки
 */
class HomeController
{
    public function index(): void
    {
        $title = "Платформа для онлайн-опитувань";
        $content = $this->renderHomePage($title);

        echo $content;
    }

    private function renderHomePage(string $title): string
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
                <h1>{$title}</h1>
                <p>Ласкаво просимо на нашу платформу для створення та проведення онлайн-опитувань!</p>
                <p>Тут ви можете:</p>
                <ul>
                    <li>Створювати власні опитування</li>
                    <li>Брати участь в опитуваннях інших користувачів</li>
                    <li>Переглядати результати та статистику</li>
                </ul>
                
                <div>
                    <a href='/surveys' class='btn'>Переглянути опитування</a>
                    <a href='/surveys/create' class='btn'>Створити опитування</a>
                </div>
            </div>
        </body>
        </html>";
    }
}