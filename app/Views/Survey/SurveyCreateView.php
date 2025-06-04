<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyCreateView extends BaseView
{
    protected function content(): string
    {
        $errors = $this->get('errors', []);
        $title = $this->get('title', '');
        $description = $this->get('description', '');

        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='error-message'><ul><li>{$errorList}</li></ul></div>";
        }

        return "
            <div class='container'> 
                <div class='header-actions'>
                    <h1>Створити нове опитування</h1>
                    " . $this->component('Navigation') . "
                </div>
                
                {$errorHtml}
                
                <form method='POST' action='/surveys/store' id='create-survey-form'>
                    <div class='form-group'>
                        <label for='title'>Назва опитування:</label>
                        <input type='text' id='title' name='title' required value='" . $this->escape($title) . "' maxlength='255'>
                    </div>
                    <div class='form-group'>
                        <label for='description'>Опис:</label>
                        <textarea id='description' name='description' rows='4' maxlength='1000'>" . $this->escape($description) . "</textarea>
                    </div>
                    
                   <div class='form-actions'>
                        <div class='top-button'>
                            <button type='submit' class='btn btn-success'>Створити опитування</button>
                        </div>
                        <div class='bottom-buttons'>
                            <a href='/surveys' class='btn btn-secondary'>Скасувати</a>
                        </div>
                    </div>
                </form>
            </div>
                <script>
                    // AJAX обробка форми для демонстрації
                    document.getElementById('create-survey-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(this);
                        
                        fetch('/surveys/store', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = '/surveys/edit?id=' + data.data.survey_id;
                            } else {
                                alert('Помилка: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.submit(); // Fallback до звичайної submit
                        });
                    });
                </script>";
    }
}