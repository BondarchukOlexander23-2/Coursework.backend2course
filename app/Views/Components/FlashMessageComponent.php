<?php

class FlashMessageComponent extends BaseView
{
    protected function content(): string
    {
        $type = $this->get('type', 'info');
        $message = $this->get('message', '');

        if (empty($message)) {
            return '';
        }

        return "<div class='flash-message {$type}'>" . $this->escape($message) . "</div>";
    }
}