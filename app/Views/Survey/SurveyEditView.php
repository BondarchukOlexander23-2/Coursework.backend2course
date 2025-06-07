<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyEditView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);
        $categories = $this->get('categories', []);

        $questionsHtml = '';
        if (!empty($questions)) {
            foreach ($questions as $question) {
                $questionsHtml .= $this->renderQuestionItem($question);
            }
        } else {
            $questionsHtml = '<p>Ще немає питань. Додайте перше питання нижче.</p>';
        }

        $categorySection = $this->renderCategorySection($survey, $categories);

        return "
        <div class='container'>
            <div class='header-actions'>
                <h1>Редагування: " . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='survey-edit-sections'>
                {$categorySection}
                
                <section class='existing-questions'>
                    <h2>Питання опитування</h2>
                    <div class='questions-list'>
                        {$questionsHtml}
                    </div>
                </section>
                
                " . $this->renderRetakeManagementButton($survey['id']) . "
                
                <section class='add-question'>
                    <h2>Додати нове питання</h2>
                    " . $this->renderQuestionForm($survey['id']) . "
                </section>
            </div>
            
            <div class='page-actions'>
                <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
                <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-secondary'>Експорт CSV</a>
                <a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>
            </div>
            
            " . $this->renderEditScript() . "
            </div>";
    }
    private function renderCategorySection(array $survey, array $categories): string
    {
        if (empty($categories)) {
            return '<div class="category-info">Категорії недоступні</div>';
        }

        // Поточна категорія
        $currentCategoryName = 'Без категорії';
        $currentCategoryIcon = '📁';
        $currentCategoryColor = '#6c757d';

        if (!empty($survey['category_id'])) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $survey['category_id']) {
                    $currentCategoryName = $cat['name'];
                    $currentCategoryIcon = $cat['icon'];
                    $currentCategoryColor = $cat['color'];
                    break;
                }
            }
        }

        // Випадаючий список категорій
        $categoryOptions = "<option value=''>-- Без категорії --</option>";
        foreach ($categories as $category) {
            $selected = (!empty($survey['category_id']) && $survey['category_id'] == $category['id']) ? ' selected' : '';
            $categoryOptions .= "<option value='{$category['id']}'{$selected}>{$category['icon']} " . $this->escape($category['name']) . "</option>";
        }

        return "
            <section class='category-management'>
                <h2>🏷️ Категорія опитування</h2>
                
                <div class='current-category'>
                    <p><strong>Поточна категорія:</strong></p>
                    <div class='category-display'>
                        <span class='category-badge' style='background-color: {$currentCategoryColor}'>
                            {$currentCategoryIcon} {$currentCategoryName}
                        </span>
                    </div>
                </div>
                
                <form method='POST' action='/surveys/update-category' id='categoryUpdateForm' class='category-form'>
                    <input type='hidden' name='survey_id' value='{$survey['id']}'>
                    
                    <div class='form-group'>
                        <label for='category_id'>Змінити на:</label>
                        <select id='category_id' name='category_id' class='form-control category-select'>
                            {$categoryOptions}
                        </select>
                    </div>
                    
                    <div class='form-actions'>
                        <button type='submit' class='btn btn-info'>
                            <span class='btn-icon'>🔄</span> Змінити категорію
                        </button>
                    </div>
                </form>
            </section>";
    }

    private function renderRetakeManagementButton(int $surveyId): string
    {
        $participants = Database::selectOne(
            "SELECT COUNT(DISTINCT user_id) as count FROM survey_responses WHERE survey_id = ? AND user_id IS NOT NULL",
            [$surveyId]
        );

        $participantCount = $participants['count'] ?? 0;

        if ($participantCount === 0) {
            return '';
        }

        return "
        <div class='retake-management-section'>
            <h3>Управління повторними спробами</h3>
            <p>Учасників: <strong>{$participantCount}</strong></p>
            <p>Ви можете надати дозвіл на повторне проходження опитування окремим користувачам.</p>
            <a href='/surveys/retake-management?survey_id={$surveyId}' class='btn btn-info'>
                <span class='btn-icon'>🔄</span> Управляти повторними спробами
            </a>
        </div>";
    }

    private function renderQuestionItem(array $question): string
    {
        $questionType = $this->escape($question['question_type']);
        $questionText = $this->escape($question['question_text']);
        $required = $question['is_required'] ? ' (обов\'язкове)' : '';
        $points = $question['points'] ?? 1;
        $correctAnswer = $this->escape($question['correct_answer'] ?? '');

        $optionsHtml = '';
        if (isset($question['options']) && !empty($question['options'])) {
            $optionsHtml = '<ul class="question-options">';
            foreach ($question['options'] as $option) {
                $correctMark = $option['is_correct'] ? ' ✓' : '';
                $correctClass = $option['is_correct'] ? ' class="correct-option"' : '';
                $optionsHtml .= '<li' . $correctClass . '>' . $this->escape($option['option_text']) . $correctMark . '</li>';
            }
            $optionsHtml .= '</ul>';
        }

        $correctAnswerHtml = '';
        if (!empty($correctAnswer)) {
            $correctAnswerHtml = '<p class="correct-answer">Правильна відповідь: <strong>' . $correctAnswer . '</strong></p>';
        }

        return "
            <div class='question-item'>
                <div class='question-header'>
                    <h4>{$questionText}{$required} <span class='question-points'>({$points} б.)</span></h4>
                    <span class='question-type'>" . Question::getQuestionTypes()[$questionType] . "</span>
                </div>
                {$optionsHtml}
                {$correctAnswerHtml}
                <form method='POST' action='/surveys/delete-question' style='display: inline;'>
                    <input type='hidden' name='question_id' value='{$question['id']}'>
                    <input type='hidden' name='survey_id' value='{$this->get('survey')['id']}'>
                    <button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Видалити це питання?\")'>Видалити</button>
                </form>
            </div>";
    }

    private function renderQuestionForm(int $surveyId): string
    {
        return "
                <form method='POST' action='/surveys/add-question' id='questionForm'>
                    <input type='hidden' name='survey_id' value='{$surveyId}'>
                    
                    <div class='form-group'>
                        <label for='question_text'>Текст питання:</label>
                        <textarea id='question_text' name='question_text' required rows='3'></textarea>
                    </div>
                    
                    <div class='form-row'>
                        <div class='form-group'>
                            <label for='question_type'>Тип питання:</label>
                            <select id='question_type' name='question_type' required onchange='toggleOptions()'>
                                <option value=''>Оберіть тип</option>
                                <option value='radio'>Один варіант (радіо)</option>
                                <option value='checkbox'>Декілька варіантів (чекбокс)</option>
                                <option value='text'>Короткий текст</option>
                                <option value='textarea'>Довгий текст</option>
                            </select>
                        </div>
                        
                        <div class='form-group'>
                            <label for='points'>Бали за правильну відповідь:</label>
                            <input type='number' id='points' name='points' value='1' min='0' max='100'>
                        </div>
                    </div>
                    
                    <div class='form-group'>
                        <label>
                            <input type='checkbox' name='is_required' value='1'>
                            Обов'язкове питання
                        </label>
                    </div>
                    
                    <div id='text-answer-section' style='display: none;'>
                        <div class='form-group'>
                            <label for='correct_answer'>Правильна відповідь (для квізу):</label>
                            <input type='text' id='correct_answer' name='correct_answer' placeholder='Введіть правильну відповідь'>
                            <small>Залиште порожнім, якщо це звичайне опитування</small>
                        </div>
                    </div>
                    
                    <div id='options-section' style='display: none;'>
                        <div class='form-group'>
                            <label>Варіанти відповідей:</label>
                            <div id='options-container'>
                                <div class='option-input'>
                                    <input type='text' name='options[]' placeholder='Варіант 1'>
                                    <label><input type='checkbox' name='correct_options[]' value='0'> Правильна</label>
                                </div>
                                <div class='option-input'>
                                    <input type='text' name='options[]' placeholder='Варіант 2'>
                                    <label><input type='checkbox' name='correct_options[]' value='1'> Правильна</label>
                                </div>
                            </div>
                            <button type='button' onclick='addOption()' class='btn btn-sm btn-secondary'>Додати варіант</button>
                            <small>Позначте правильні відповіді для створення квізу</small>
                        </div>
                    </div>
                    
                    <div class='form-actions'>
                        <button type='submit' class='btn btn-success'>Додати питання</button>
                    </div>
                </form>";
    }

    private function renderEditScript(): string
    {
        return "
            <style>
                .category-management {
                    background: linear-gradient(135deg, #e8f4fd 0%, #d6eaf8 100%);
                    border: 2px solid #3498db;
                    border-radius: 12px;
                    padding: 2rem;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                
                .category-management h2 {
                    color: #2980b9;
                    margin-bottom: 1.5rem;
                    font-size: 1.3rem;
                }
                
                .current-category {
                    margin-bottom: 1.5rem;
                    padding: 1rem;
                    background: rgba(255,255,255,0.7);
                    border-radius: 8px;
                }
                
                .category-display {
                    margin-top: 0.5rem;
                }
                
                .category-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    color: white;
                    padding: 0.6rem 1.2rem;
                    border-radius: 20px;
                    font-weight: 600;
                    font-size: 1rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                
                .category-form {
                    background: rgba(255,255,255,0.9);
                    padding: 1.5rem;
                    border-radius: 8px;
                    border: 1px solid rgba(52, 152, 219, 0.3);
                }
                
                .category-select {
                    font-size: 1rem;
                    padding: 0.8rem;
                    border: 2px solid #dee2e6;
                    border-radius: 8px;
                    background: white;
                    cursor: pointer;
                    transition: border-color 0.3s ease;
                }
                
                .category-select:focus {
                    outline: none;
                    border-color: #3498db;
                    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
                }
                
                .btn-icon {
                    margin-right: 0.5rem;
                    font-size: 1.1rem;
                }
            </style>
            
            <script>
            let optionIndex = 2;
            
            function toggleOptions() {
                const type = document.getElementById('question_type').value;
                const optionsSection = document.getElementById('options-section');
                const textAnswerSection = document.getElementById('text-answer-section');
                
                if (type === 'radio' || type === 'checkbox') {
                    optionsSection.style.display = 'block';
                    textAnswerSection.style.display = 'none';
                } else if (type === 'text' || type === 'textarea') {
                    optionsSection.style.display = 'none';
                    textAnswerSection.style.display = 'block';
                } else {
                    optionsSection.style.display = 'none';
                    textAnswerSection.style.display = 'none';
                }
            }
            
            function addOption() {
                const container = document.getElementById('options-container');
                const optionDiv = document.createElement('div');
                optionDiv.className = 'option-input';
                optionDiv.innerHTML = `
                    <input type='text' name='options[]' placeholder='Варіант \${optionIndex + 1}'>
                    <label><input type='checkbox' name='correct_options[]' value='\${optionIndex}'> Правильна</label>
                `;
                container.appendChild(optionDiv);
                optionIndex++;
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                const categoryForm = document.getElementById('categoryUpdateForm');
                if (categoryForm) {
                    categoryForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(this);
                        const submitBtn = this.querySelector('button[type=\"submit\"]');
                        const originalText = submitBtn.textContent;
                        
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '⏳ Оновлення...';
                        
                        fetch('/surveys/update-category', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showMessage('Категорію оновлено!', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                showMessage('Помилка: ' + (data.message || 'Щось пішло не так'), 'error');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessage('Виникла помилка при оновленні', 'error');
                            this.submit(); // Fallback до звичайної форми
                        });
                    });
                }
                
                const retakeSection = document.querySelector('.retake-management-section');
                if (retakeSection) {
                    retakeSection.style.opacity = '0';
                    retakeSection.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        retakeSection.style.transition = 'all 0.5s ease';
                        retakeSection.style.opacity = '1';
                        retakeSection.style.transform = 'translateY(0)';
                    }, 500);
                }
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
        </script>";
    }
}