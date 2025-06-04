<?php

class AppLayout
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function render(string $content): string
    {
        $title = $this->data['title'] ?? 'Survey Platform';
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
            <div>
                {$flashHtml}
                {$content}
            </div>
        </body>
        </html>";
    }
}
