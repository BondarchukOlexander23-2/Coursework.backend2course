<?php

require_once __DIR__ . '/../BaseView.php';

class SurveyEditView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);

        $questionsHtml = '';
        if (!empty($questions)) {
            foreach ($questions as $question) {
                $questionsHtml .= $this->renderQuestionItem($question);
            }
        } else {
            $questionsHtml = '<p>Ще немає питань. Додайте перше питання нижче.</p>';
        }

        return "
            <div class='container'>
                <div class='header-actions'>
                    <h1>Редагування: " . $this->escape($survey['title']) . "</h1>
                    " . $this->component('Navigation') . "
                </div>
                
                <div class='survey-edit-sections'>
                    <section class='existing-questions'>
                        <h2>Питання опитування</h2>
                        <div class='questions-list'>
                            {$questionsHtml}
                        </div>
                    </section>
                    
                    <section class='add-question'>
                        <h2>Додати нове питання</h2>
                        " . $this->renderQuestionForm($survey['id']) . "
                    </section>
                </div>
                
                <div class='page-actions'>
                    <a href='/surveys/view?id={$survey['id']}' class='btn btn-primary'>Переглянути опитування</a>
                    <a href='/surveys/export-results?id={$survey['id']}&format=csv' class='btn btn-secondary'>Експорт CSV</a>
                    <a href='/surveys/my' class='btn btn-secondary'>Мої опитування</a>
                </div>
                
                " . $this->renderEditScript() . "
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
            <div class='container'>
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
                </div>
            </div>";
    }

    private function renderQuestionForm(int $surveyId): string
    {
        return "
            <div class='container'>
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
                </form>
            </div>";
    }

    private function renderEditScript(): string
    {
        return "
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
            </script>";
    }
}