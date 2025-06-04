<?php

abstract class BaseView
{
    protected array $data = [];
    protected string $layout = 'app';

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Встановити дані для представлення
     */
    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Встановити макет
     */
    public function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Отримати значення з даних
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Перевірити наявність ключа в даних
     */
    protected function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Безпечне відображення HTML
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Рендерити представлення
     */
    public function render(): string
    {
        $content = $this->content();

        if ($this->layout) {
            return $this->renderWithLayout($content);
        }

        return $content;
    }

    /**
     * Абстрактний метод для контенту представлення
     */
    abstract protected function content(): string;

    /**
     * Рендерити з макетом
     */
    protected function renderWithLayout(string $content): string
    {
        $layoutClass = ucfirst($this->layout) . 'Layout';
        $layoutPath = __DIR__ . "/Layouts/{$layoutClass}.php";

        if (file_exists($layoutPath)) {
            require_once $layoutPath;

            if (class_exists($layoutClass)) {
                $layout = new $layoutClass($this->data);
                return $layout->render($content);
            } else {
                error_log("Клас макету не знайдено: {$layoutClass}");
            }
        } else {
            error_log("Файл макету не знайдено: {$layoutPath}");
        }

        return $content;
    }

    /**
     * Рендерити компонент
     */
    protected function component(string $componentName, array $data = []): string
    {
        $componentClass = $componentName . 'Component';
        $componentPath = __DIR__ . "/Components/{$componentClass}.php";

        if (file_exists($componentPath)) {
            require_once $componentPath;

            if (class_exists($componentClass)) {
                $component = new $componentClass(array_merge($this->data, $data));
                return $component->render();
            } else {
                error_log("Клас компонента не знайдено: {$componentClass}");
            }
        } else {
            error_log("Файл компонента не знайдено: {$componentPath}");
        }

        return '';
    }

    /**
     * Включити часткове представлення
     */
    protected function partial(string $partialName, array $data = []): string
    {
        $partialPath = __DIR__ . "/Partials/{$partialName}.php";

        if (file_exists($partialPath)) {
            extract(array_merge($this->data, $data));
            ob_start();
            include $partialPath;
            return ob_get_clean();
        } else {
            error_log("Файл часткового шаблону не знайдено: {$partialPath}");
        }

        return '';
    }
}
