<?php

require_once __DIR__ . '/../BaseView.php';

/**
 * View для відображення опитування для проходження
 * Показує питання та форму для відповідей
 */
class SurveyViewView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);
        $userHasResponded = $this->get('userHasResponded', false);

        if ($userHasResponded) {
            return $this->renderAlreadyResponded($survey);
        }

        return $this->renderSurveyForm($survey, $questions);
    }

    private function renderAlreadyResponded(array $survey): string
    {
        return "
            <div class='header-actions'>
                <h1>" . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='already-responded'>
                <div class='message-icon'>✅</div>
                <h2>Ви вже проходили це опитування</h2>
                <p>Дякуємо за участь! Ви можете переглянути результати або повернутися до списку опитувань.</p>
                
                <div class='form-actions'>
                    <a href='/surveys/results?id={$survey['id']}' class='btn btn-primary'>Переглянути результати</a>
                    <a href='/surveys' class='btn btn-secondary'>До списку опитувань</a>
                </div>
            </div>
            
            " . $this->renderAlreadyRespondedStyles();
    }

    private function renderSurveyForm(array $survey, array $questions): string
    {
        $isQuiz = $this->determineIfQuiz($questions);
        $surveyType = $isQuiz ? 'квіз' : 'опитування';
        $totalQuestions = count($questions);

        $questionsHtml = '';
        $questionNumber = 1;

        foreach ($questions as $question) {
            $questionsHtml .= $this->renderQuestion($question, $questionNumber);
            $questionNumber++;
        }

        return "
            <div class='header-actions'>
                <h1>" . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='survey-info'>
                <div class='survey-meta'>
                    <div class='survey-description'>
                        <p>" . $this->escape($survey['description']) . "</p>
                    </div>
                    <div class='survey-stats'>
                        <span class='survey-type " . ($isQuiz ? 'quiz' : 'survey') . "'>" . ucfirst($surveyType) . "</span>
                        <span class='question-count'>{$totalQuestions} " . $this->getQuestionWord($totalQuestions) . "</span>
                        <span class='author'>Автор: " . $this->escape($survey['author_name']) . "</span>
                    </div>
                </div>
            </div>
            
            <form method='POST' action='/surveys/submit' id='survey-form' class='survey-form'>
                <input type='hidden' name='survey_id' value='{$survey['id']}'>
                
                <div class='progress-bar-container'>
                    <div class='progress-bar'>
                        <div class='progress' id='survey-progress'></div>
                    </div>
                    <span class='progress-text'>Питання <span id='current-question'>1</span> з {$totalQuestions}</span>
                </div>
                
                <div class='questions-container'>
                    {$questionsHtml}
                </div>
                
                <div class='form-navigation'>
                    <button type='button' id='prev-btn' class='btn btn-secondary' disabled>← Попереднє</button>
                    <button type='button' id='next-btn' class='btn btn-primary'>Наступне →</button>
                    <button type='submit' id='submit-btn' class='btn btn-success' style='display: none;'>
                        " . ($isQuiz ? 'Завершити квіз' : 'Відправити відповіді') . "
                    </button>
                </div>
            </form>
            
            " . $this->renderSurveyStyles() . "
            " . $this->renderSurveyScript($totalQuestions);
    }

    private function renderQuestion(array $question, int $questionNumber): string
    {
        $questionText = $this->escape($question['question_text']);
        $questionType = $question['question_type'];
        $isRequired = $question['is_required'];
        $questionId = $question['id'];

        $requiredLabel = $isRequired ? '<span class="required">*</span>' : '';
        $requiredAttr = $isRequired ? 'required' : '';

        $inputHtml = '';

        switch ($questionType) {
            case 'radio':
                $inputHtml = $this->renderRadioOptions($question, $questionId, $requiredAttr);
                break;

            case 'checkbox':
                $inputHtml = $this->renderCheckboxOptions($question, $questionId);
                break;

            case 'text':
                $inputHtml = "<input type='text' name='answers[{$questionId}]' class='form-input' {$requiredAttr} placeholder='Введіть вашу відповідь'>";
                break;

            case 'textarea':
                $inputHtml = "<textarea name='answers[{$questionId}]' class='form-textarea' rows='4' {$requiredAttr} placeholder='Введіть детальну відповідь'></textarea>";
                break;
        }

        $displayStyle = $questionNumber === 1 ? 'block' : 'none';

        return "
            <div class='question-slide' data-question='{$questionNumber}' style='display: {$displayStyle};'>
                <div class='question-header'>
                    <span class='question-number'>{$questionNumber}</span>
                    <h3 class='question-text'>{$questionText} {$requiredLabel}</h3>
                </div>
                <div class='question-input'>
                    {$inputHtml}
                </div>
            </div>";
    }

    private function renderRadioOptions(array $question, int $questionId, string $requiredAttr): string
    {
        $optionsHtml = '';

        if (isset($question['options']) && !empty($question['options'])) {
            foreach ($question['options'] as $index => $option) {
                $optionText = $this->escape($option['option_text']);
                $optionId = $option['id'];

                $optionsHtml .= "
                    <label class='option-label radio-option'>
                        <input type='radio' name='answers[{$questionId}]' value='{$optionId}' {$requiredAttr}>
                        <span class='option-checkmark'></span>
                        <span class='option-text'>{$optionText}</span>
                    </label>";
            }
        }

        return "<div class='options-container'>{$optionsHtml}</div>";
    }

    private function renderCheckboxOptions(array $question, int $questionId): string
    {
        $optionsHtml = '';

        if (isset($question['options']) && !empty($question['options'])) {
            foreach ($question['options'] as $index => $option) {
                $optionText = $this->escape($option['option_text']);
                $optionId = $option['id'];

                $optionsHtml .= "
                    <label class='option-label checkbox-option'>
                        <input type='checkbox' name='answers[{$questionId}][]' value='{$optionId}'>
                        <span class='option-checkmark'></span>
                        <span class='option-text'>{$optionText}</span>
                    </label>";
            }
        }

        return "<div class='options-container'>{$optionsHtml}</div>";
    }

    private function determineIfQuiz(array $questions): bool
    {
        foreach ($questions as $question) {
            if (!empty($question['correct_answer']) ||
                (isset($question['options']) && $this->hasCorrectOptions($question['options']))) {
                return true;
            }
        }
        return false;
    }

    private function hasCorrectOptions(array $options): bool
    {
        foreach ($options as $option) {
            if ($option['is_correct']) {
                return true;
            }
        }
        return false;
    }

    private function getQuestionWord(int $count): string
    {
        if ($count % 10 === 1 && $count % 100 !== 11) {
            return 'питання';
        } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
            return 'питання';
        } else {
            return 'питань';
        }
    }

    private function renderAlreadyRespondedStyles(): string
    {
        return "
            <style>
                .already-responded {
                    text-align: center;
                    max-width: 600px;
                    margin: 3rem auto;
                    padding: 3rem;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 20px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                }
                .message-icon {
                    font-size: 4rem;
                    margin-bottom: 1.5rem;
                    color: #28a745;
                }
                .already-responded h2 {
                    color: #2c3e50;
                    margin-bottom: 1rem;
                }
                .already-responded p {
                    color: #6c757d;
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 2rem;
                }
            </style>";
    }

    private function renderSurveyStyles(): string
    {
        return "
            <style>
                .survey-info {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 15px;
                    margin-bottom: 2rem;
                }
                .survey-meta {
                    max-width: 800px;
                    margin: 0 auto;
                }
                .survey-description {
                    margin-bottom: 1.5rem;
                }
                .survey-description p {
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin: 0;
                }
                .survey-stats {
                    display: flex;
                    gap: 1rem;
                    flex-wrap: wrap;
                    align-items: center;
                }
                .survey-stats span {
                    background: rgba(255, 255, 255, 0.2);
                    padding: 0.5rem 1rem;
                    border-radius: 20px;
                    font-size: 0.9rem;
                    font-weight: 600;
                    backdrop-filter: blur(10px);
                }
                .survey-type.quiz {
                    background: rgba(243, 156, 18, 0.3);
                    color: #f39c12;
                }
                .survey-type.survey {
                    background: rgba(52, 152, 219, 0.3);
                    color: #3498db;
                }
                .progress-bar-container {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 10px;
                    margin-bottom: 2rem;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e9ecef;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-bottom: 0.5rem;
                }
                .progress {
                    height: 100%;
                    background: linear-gradient(45deg, #3498db, #2980b9);
                    width: 0%;
                    transition: width 0.3s ease;
                }
                .progress-text {
                    color: #6c757d;
                    font-size: 0.9rem;
                    font-weight: 600;
                }
                .survey-form {
                    max-width: 800px;
                    margin: 0 auto;
                }
                .questions-container {
                    background: white;
                    border-radius: 15px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                    overflow: hidden;
                    margin-bottom: 2rem;
                }
                .question-slide {
                    padding: 3rem;
                    min-height: 400px;
                }
                .question-header {
                    display: flex;
                    align-items: flex-start;
                    gap: 1rem;
                    margin-bottom: 2rem;
                }
                .question-number {
                    background: #3498db;
                    color: white;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    flex-shrink: 0;
                }
                .question-text {
                    margin: 0;
                    color: #2c3e50;
                    font-size: 1.3rem;
                    line-height: 1.4;
                }
                .required {
                    color: #e74c3c;
                    margin-left: 0.25rem;
                }
                .question-input {
                    margin-left: 3rem;
                }
                .options-container {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                .option-label {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem;
                    background: #f8f9fa;
                    border: 2px solid #e9ecef;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    position: relative;
                }
                .option-label:hover {
                    background: #e3f2fd;
                    border-color: #3498db;
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
                }
                .option-label input {
                    opacity: 0;
                    position: absolute;
                    left: -9999px;
                }
                .option-checkmark {
                    width: 20px;
                    height: 20px;
                    border: 2px solid #dee2e6;
                    border-radius: 4px;
                    position: relative;
                    transition: all 0.3s ease;
                }
                .radio-option .option-checkmark {
                    border-radius: 50%;
                }
                .option-label input:checked + .option-checkmark {
                    background: #3498db;
                    border-color: #3498db;
                }
                .option-label input:checked + .option-checkmark::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                    width: 6px;
                    height: 6px;
                    background: white;
                    border-radius: 50%;
                }
                .checkbox-option .option-label input:checked + .option-checkmark::after {
                    content: '✓';
                    font-size: 12px;
                    color: white;
                    font-weight: bold;
                    width: auto;
                    height: auto;
                    background: none;
                    border-radius: 0;
                }
                .option-text {
                    flex: 1;
                    font-size: 1rem;
                    color: #495057;
                }
                .form-input, .form-textarea {
                    width: 100%;
                    padding: 1rem;
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    font-size: 1rem;
                    transition: border-color 0.3s ease;
                    background: #f8f9fa;
                }
                .form-input:focus, .form-textarea:focus {
                    outline: none;
                    border-color: #3498db;
                    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
                    background: white;
                }
                .form-textarea {
                    resize: vertical;
                    min-height: 120px;
                }
                .form-navigation {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1.5rem;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                @media (max-width: 768px) {
                    .survey-stats {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .question-slide {
                        padding: 2rem 1.5rem;
                    }
                    .question-input {
                        margin-left: 0;
                        margin-top: 1rem;
                    }
                    .form-navigation {
                        flex-direction: column;
                        gap: 1rem;
                    }
                    .form-navigation button {
                        width: 100%;
                    }
                }
            </style>";
    }

    private function renderSurveyScript(int $totalQuestions): string
    {
        return "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    let currentQuestion = 1;
                    const totalQuestions = {$totalQuestions};
                    
                    const prevBtn = document.getElementById('prev-btn');
                    const nextBtn = document.getElementById('next-btn');
                    const submitBtn = document.getElementById('submit-btn');
                    const progressBar = document.getElementById('survey-progress');
                    const currentQuestionSpan = document.getElementById('current-question');
                    
                    function updateProgress() {
                        const progress = (currentQuestion / totalQuestions) * 100;
                        progressBar.style.width = progress + '%';
                        currentQuestionSpan.textContent = currentQuestion;
                    }
                    
                    function showQuestion(questionNumber) {
                        // Приховати всі питання
                        const allQuestions = document.querySelectorAll('.question-slide');
                        allQuestions.forEach(function(question) {
                            question.style.display = 'none';
                        });
                        
                        // Показати поточне питання
                        const currentQuestionEl = document.querySelector('[data-question=\"' + questionNumber + '\"]');
                        if (currentQuestionEl) {
                            currentQuestionEl.style.display = 'block';
                        }
                        
                        // Оновити кнопки
                        prevBtn.disabled = questionNumber === 1;
                        
                        if (questionNumber === totalQuestions) {
                            nextBtn.style.display = 'none';
                            submitBtn.style.display = 'inline-block';
                        } else {
                            nextBtn.style.display = 'inline-block';
                            submitBtn.style.display = 'none';
                        }
                        
                        updateProgress();
                    }
                    
                    function validateCurrentQuestion() {
                        const currentQuestionEl = document.querySelector('[data-question=\"' + currentQuestion + '\"]');
                        const requiredInputs = currentQuestionEl.querySelectorAll('input[required], textarea[required]');
                        
                        for (let input of requiredInputs) {
                            if (input.type === 'radio') {
                                const radioGroup = currentQuestionEl.querySelectorAll('input[name=\"' + input.name + '\"]');
                                let isChecked = false;
                                radioGroup.forEach(function(radio) {
                                    if (radio.checked) isChecked = true;
                                });
                                if (!isChecked) {
                                    alert('Будь ласка, оберіть відповідь на це питання.');
                                    return false;
                                }
                            } else if (input.type === 'checkbox') {
                                const checkboxGroup = currentQuestionEl.querySelectorAll('input[name=\"' + input.name + '\"]');
                                let isChecked = false;
                                checkboxGroup.forEach(function(checkbox) {
                                    if (checkbox.checked) isChecked = true;
                                });
                                if (!isChecked) {
                                    alert('Будь ласка, оберіть принаймні один варіант.');
                                    return false;
                                }
                            } else if (!input.value.trim()) {
                                alert('Будь ласка, заповніть це поле.');
                                input.focus();
                                return false;
                            }
                        }
                        return true;
                    }
                    
                    prevBtn.addEventListener('click', function() {
                        if (currentQuestion > 1) {
                            currentQuestion--;
                            showQuestion(currentQuestion);
                        }
                    });
                    
                    nextBtn.addEventListener('click', function() {
                        if (validateCurrentQuestion() && currentQuestion < totalQuestions) {
                            currentQuestion++;
                            showQuestion(currentQuestion);
                        }
                    });
                    
                    // Валідація форми перед відправкою
                    document.getElementById('survey-form').addEventListener('submit', function(e) {
                        if (!validateCurrentQuestion()) {
                            e.preventDefault();
                            return false;
                        }
                        
                        // Показати індикатор завантаження
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Відправляється...';
                    });
                    
                    // Ініціалізація
                    showQuestion(1);
                    
                    // Автозбереження прогресу (опціонально)
                    setInterval(function() {
                        // Тут можна зберігати прогрес в localStorage
                        // але в Claude.ai це не підтримується
                    }, 30000);
                });
            </script>";
    }
}