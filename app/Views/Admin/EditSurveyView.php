<?php

require_once __DIR__ . '/../BaseView.php';

/**
 * View для редагування опитування з адмін-панелі
 * Дотримується принципу Single Responsibility - відповідає тільки за відображення сторінки редагування
 */
class EditSurveyView extends BaseView
{
    protected string $layout = 'admin';

    protected function content(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);

        $questionsHtml = $this->renderQuestionsSection($questions);

        return "
            <div class='edit-survey-page'>
                <div class='survey-header'>
                    <h1>Редагування опитування</h1>
                    <p><strong>ID:</strong> {$survey['id']} | <strong>Автор:</strong> " . $this->escape($survey['author_name']) . "</p>
                </div>

                " . $this->renderEditForm($survey) . "
                " . $questionsHtml . "
                " . $this->renderNavigationActions($survey['id']) . "
            </div>

            " . $this->renderStyles() . "
            " . $this->renderScript() . "";
    }

    /**
     * Рендер форми редагування основної інформації
     */
    private function renderEditForm(array $survey): string
    {
        return "
            <div class='edit-form'>
                <h2>Основна інформація</h2>
                <form method='POST' action='/admin/update-survey' id='survey-form'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    
                    <div class='form-group'>
                        <label for='title'>Назва опитування:</label>
                        <input type='text' id='title' name='title' class='form-control' 
                               value='" . $this->escape($survey['title']) . "' required maxlength='255'>
                    </div>
                    
                    <div class='form-group'>
                        <label for='description'>Опис:</label>
                        <textarea id='description' name='description' class='form-control' 
                                  rows='4' maxlength='1000'>" . $this->escape($survey['description']) . "</textarea>
                    </div>
                    
                    <div class='actions'>
                        <button type='submit' class='btn btn-success'>Зберегти зміни</button>
                        <a href='/admin/surveys' class='btn btn-secondary'>Скасувати</a>
                    </div>
                </form>
            </div>";
    }

    /**
     * Рендер секції питань
     */
    private function renderQuestionsSection(array $questions): string
    {
        $questionsHtml = '';

        if (!empty($questions)) {
            foreach ($questions as $question) {
                $questionsHtml .= $this->renderQuestionItem($question);
            }
        } else {
            $questionsHtml = '<p class="no-questions">Немає питань в цьому опитуванні</p>';
        }

        return "
            <div class='questions-section'>
                <h2>Питання опитування (" . count($questions) . ")</h2>
                {$questionsHtml}
                
                <div class='actions'>
                    <a href='/surveys/edit?id={$this->get('survey')['id']}' class='btn btn-primary'>
                        Повне редагування (додавати/видаляти питання)
                    </a>
                </div>
            </div>";
    }

    /**
     * Рендер окремого питання
     */
    private function renderQuestionItem(array $question): string
    {
        $questionText = $this->escape($question['question_text']);
        $questionType = $this->escape($question['question_type']);
        $required = $question['is_required'] ? ' (обов\'язкове)' : '';
        $points = $question['points'] ?? 1;
        $correctAnswer = $this->escape($question['correct_answer'] ?? '');

        $typeNames = [
            'radio' => 'Один варіант',
            'checkbox' => 'Декілька варіантів',
            'text' => 'Короткий текст',
            'textarea' => 'Довгий текст'
        ];

        $typeName = $typeNames[$questionType] ?? $questionType;

        $optionsHtml = $this->renderQuestionOptions($question);
        $correctAnswerHtml = $this->renderCorrectAnswer($correctAnswer);

        return "
            <div class='question-item'>
                <div class='question-header'>
                    <h4>{$questionText}{$required} <small>({$points} б.)</small></h4>
                    <span class='question-type'>{$typeName}</span>
                </div>
                {$optionsHtml}
                {$correctAnswerHtml}
            </div>";
    }

    /**
     * Рендер варіантів відповідей для питання
     */
    private function renderQuestionOptions(array $question): string
    {
        if (!isset($question['options']) || empty($question['options'])) {
            return '';
        }

        $optionsHtml = '<ul class="question-options">';
        foreach ($question['options'] as $option) {
            $correctMark = $option['is_correct'] ? ' ✓' : '';
            $correctClass = $option['is_correct'] ? ' class="correct-option"' : '';
            $optionsHtml .= '<li' . $correctClass . '>' . $this->escape($option['option_text']) . $correctMark . '</li>';
        }
        $optionsHtml .= '</ul>';

        return $optionsHtml;
    }

    /**
     * Рендер правильної відповіді для текстових питань
     */
    private function renderCorrectAnswer(string $correctAnswer): string
    {
        if (empty($correctAnswer)) {
            return '';
        }

        return '<p><strong>Правильна відповідь:</strong> ' . $correctAnswer . '</p>';
    }

    /**
     * Рендер навігаційних дій
     */
    private function renderNavigationActions(int $surveyId): string
    {
        return "
            <div class='actions'>
                <a href='/admin/surveys' class='btn btn-secondary'>Назад до списку</a>
                <a href='/surveys/view?id={$surveyId}' class='btn btn-primary'>Переглянути опитування</a>
                <a href='/admin/survey-stats?id={$surveyId}' class='btn btn-primary'>Статистика</a>
            </div>";
    }

    /**
     * CSS стилі для сторінки
     */
    private function renderStyles(): string
    {
        return "
            <style>
                .edit-survey-page { 
                    max-width: 1200px; 
                    margin: 0 auto; 
                    padding: 20px; 
                }
                
                .survey-header { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin-bottom: 30px; 
                }
                
                .edit-form { 
                    background: white; 
                    padding: 30px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
                    margin-bottom: 30px; 
                }
                
                .questions-section { 
                    background: white; 
                    padding: 30px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
                }
                
                .question-item { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    margin: 15px 0; 
                    border-radius: 8px; 
                    border-left: 4px solid #007cba; 
                }
                
                .question-header { 
                    display: flex; 
                    align-items: center; 
                    margin-bottom: 10px; 
                    justify-content: space-between;
                    flex-wrap: wrap;
                }
                
                .question-options { 
                    margin: 10px 0; 
                    padding-left: 20px; 
                }
                
                .question-options li { 
                    margin: 5px 0; 
                }
                
                .correct-option { 
                    font-weight: bold; 
                    color: #28a745; 
                }
                
                .form-group { 
                    margin-bottom: 20px; 
                }
                
                .form-group label { 
                    display: block; 
                    margin-bottom: 5px; 
                    font-weight: bold; 
                }
                
                .form-control { 
                    width: 100%; 
                    padding: 10px; 
                    border: 1px solid #ddd; 
                    border-radius: 4px; 
                    font-size: 14px;
                }
                
                .form-control:focus {
                    outline: none;
                    border-color: #007cba;
                    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
                }
                
                .btn { 
                    padding: 10px 20px; 
                    border: none; 
                    border-radius: 4px; 
                    cursor: pointer; 
                    text-decoration: none; 
                    display: inline-block; 
                    margin: 5px; 
                    font-size: 14px;
                    transition: all 0.3s ease;
                }
                
                .btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                
                .btn-primary { 
                    background: #007cba; 
                    color: white; 
                }
                
                .btn-success { 
                    background: #28a745; 
                    color: white; 
                }
                
                .btn-secondary { 
                    background: #6c757d; 
                    color: white; 
                }
                
                .btn-danger { 
                    background: #dc3545; 
                    color: white; 
                }
                
                .actions { 
                    margin-top: 20px; 
                    text-align: center;
                }
                
                .no-questions { 
                    text-align: center; 
                    color: #6c757d; 
                    font-style: italic; 
                    padding: 40px; 
                }

                .question-type {
                    background: #e9ecef;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    color: #495057;
                }

                @media (max-width: 768px) {
                    .edit-survey-page {
                        padding: 10px;
                    }
                    
                    .edit-form,
                    .questions-section {
                        padding: 20px;
                    }
                    
                    .question-header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }
                }
            </style>";
    }

    /**
     * JavaScript для інтерактивності
     */
    private function renderScript(): string
    {
        return "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('survey-form');
                    
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const formData = new FormData(this);
                            const submitBtn = this.querySelector('button[type=\"submit\"]');
                            const originalText = submitBtn.textContent;
                            
                            // Показати стан завантаження
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Збереження...';
                            
                            fetch('/admin/update-survey', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showMessage('Опитування оновлено!', 'success');
                                    setTimeout(() => {
                                        window.location.href = '/admin/surveys';
                                    }, 1500);
                                } else {
                                    showMessage('Помилка: ' + (data.message || 'Щось пішло не так'), 'error');
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = originalText;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showMessage('Виникла помилка при збереженні', 'error');
                                // Fallback - звичайна відправка форми
                                this.submit();
                            });
                        });
                    }
                    
                    // Анімація появи питань
                    const questionItems = document.querySelectorAll('.question-item');
                    questionItems.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            item.style.transition = 'all 0.5s ease';
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                });
                
                function showMessage(message, type) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flash-message ' + type;
                    messageDiv.style.cssText = `
                        position: fixed; 
                        top: 20px; 
                        right: 20px; 
                        z-index: 9999;
                        padding: 1rem; 
                        border-radius: 8px; 
                        max-width: 400px;
                        font-size: 14px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                        animation: slideInRight 0.3s ease-out;
                    `;
                    messageDiv.textContent = message;
                    
                    if (type === 'success') {
                        messageDiv.style.cssText += 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                    } else {
                        messageDiv.style.cssText += 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                    }
                    
                    document.body.appendChild(messageDiv);
                    setTimeout(() => messageDiv.remove(), 4000);
                }
                
                // CSS анімація
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes slideInRight {
                        from {
                            opacity: 0;
                            transform: translateX(100%);
                        }
                        to {
                            opacity: 1;
                            transform: translateX(0);
                        }
                    }
                `;
                document.head.appendChild(style);
            </script>";
    }
}