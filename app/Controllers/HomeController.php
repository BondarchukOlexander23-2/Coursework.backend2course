<?php

require_once __DIR__ . '/../Views/Home/HomeView.php';

/**
 * Оновлений HomeController з використанням Views
 * Демонструє застосування принципу Single Responsibility
 */
class HomeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->safeExecute(function() {
            $title = "Платформа для онлайн-опитувань";

            $view = new HomeView(['title' => $title]);
            $content = $view->render();

            // Кешуємо головну сторінку на 1 годину
            $this->responseManager
                ->setCacheHeaders(3600)
                ->sendSuccess($content);
        });
    }
}