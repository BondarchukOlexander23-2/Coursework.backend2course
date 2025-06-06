<?php

require_once __DIR__ . '/../BaseView.php';

/**
 * View для відображення опитування для проходження
 */
class SurveyViewView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $questions = $this->get('questions', []);
        $userHasResponded = $this->get('userHasResponded', false);

        // Перевіряємо дозвіл на повторне проходження
        $retakeInfo = null;
        if (Session::isLoggedIn()) {
            $retakeInfo = Survey::getRetakeInfo($survey['id'], Session::getUserId());
        }

        if ($userHasResponded && (!$retakeInfo || !$retakeInfo['allowed'])) {
            return $this->renderAlreadyResponded($survey);
        }

        return $this->renderSurveyForm($survey, $questions, $retakeInfo);
    }

    private function renderAlreadyResponded(array $survey): string
    {
        return "
            <div class='header-actions'>
                <h1>" . $this->escape($survey['title']) . "</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='no-surveys'>
                <div class='no-surveys-icon'>✅</div>
                <h3>Ви вже проходили це опитування</h3>
                <p>Дякуємо за участь! Ви можете переглянути результати або повернутися до списку опитувань.</p>
                
                <div class='form-actions'>
                    <a href='/surveys/results?id={$survey['id']}' class='btn btn-primary btn-large'>Переглянути результати</a>
                    <a href='/surveys' class='btn btn-secondary btn-large'>До списку опитувань</a>
                </div>
            </div>";
    }

    private function renderSurveyForm(array $survey, array $questions, ?array $retakeInfo = null): string
    {
        $isQuiz = $this->determineIfQuiz($questions);
        $surveyType = $isQuiz ? 'квіз' : 'опитування';
        $totalQuestions = count($questions);

        // Повідомлення про повторне проходження
        $retakeNotice = '';
        if ($retakeInfo && $retakeInfo['allowed']) {
            $retakeNotice = "
            <div class='retake-info-section'>
                <div class='btn-icon'>🔄</div>
                <h3>Дозвіл на повторне проходження</h3>
                <p>Вам надано дозвіл пройти це опитування ще раз.</p>
                <div class='retake-actions'>
                    <small>Дозвіл надано: {$retakeInfo['allowed_by']} " . date('d.m.Y H:i', strtotime($retakeInfo['allowed_at'])) . "</small>
                </div>
            </div>";
        }

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
        
        {$retakeNotice}
        
        <div class='survey-summary'>
            <div class='summary-stats'>
                <div class='summary-item'>
                    <span class='summary-number'>" . ucfirst($surveyType) . "</span>
                    <span class='summary-label'>Тип</span>
                </div>
                <div class='summary-item'>
                    <span class='summary-number'>{$totalQuestions}</span>
                    <span class='summary-label'>" . $this->getQuestionWord($totalQuestions) . "</span>
                </div>
                <div class='summary-item'>
                    <span class='summary-number'>" . $this->escape($survey['author_name']) . "</span>
                    <span class='summary-label'>Автор</span>
                </div>
            </div>
            <div style='margin-top: 1.5rem; text-align: center;'>
                <p style='color: rgba(255,255,255,0.9); font-size: 1.1rem;'>" . $this->escape($survey['description']) . "</p>
            </div>
        </div>
        
        <form method='POST' action='/surveys/submit' id='survey-form' class='survey-form'>
            <input type='hidden' name='survey_id' value='{$survey['id']}'>
            
            <div class='question-results' style='background: #f8f9fa; border: 2px solid #3498db; margin-bottom: 2rem;'>
                <div class='progress-bar'>
                    <div class='progress' id='survey-progress' style='width: 0%;'></div>
                </div>
                <p style='text-align: center; margin-top: 1rem; color: #2c3e50; font-weight: 600;'>
                    Питання <span id='current-question'>1</span> з {$totalQuestions}
                </p>
            </div>
            
            <div class='questions-container'>
                {$questionsHtml}
            </div>
            
            <div class='form-actions'>
                <button type='button' id='prev-btn' class='btn btn-secondary' disabled>← Попереднє</button>
                <button type='button' id='next-btn' class='btn btn-primary'>Наступне →</button>
                <button type='submit' id='submit-btn' class='btn btn-success btn-large' style='display: none;'>
                    " . ($isQuiz ? '🎯 Завершити квіз' : '📝 Відправити відповіді') . "
                </button>
            </div>
        </form>
        
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
                $inputHtml = "<input type='text' name='answers[{$questionId}]' class='form-control' {$requiredAttr} placeholder='Введіть вашу відповідь'>";
                break;

            case 'textarea':
                $inputHtml = "<textarea name='answers[{$questionId}]' class='form-control' rows='4' {$requiredAttr} placeholder='Введіть детальну відповідь'></textarea>";
                break;
        }

        $displayStyle = $questionNumber === 1 ? 'block' : 'none';

        return "
            <div class='question' data-question='{$questionNumber}' style='display: {$displayStyle};'>
                <h3>{$questionNumber}. {$questionText} {$requiredLabel}</h3>
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
                    <label class='option-label'>
                        <input type='radio' name='answers[{$questionId}]' value='{$optionId}' {$requiredAttr}>
                        <span class='option-text'>{$optionText}</span>
                    </label>";
            }
        }

        return $optionsHtml;
    }

    private function renderCheckboxOptions(array $question, int $questionId): string
    {
        $optionsHtml = '';

        if (isset($question['options']) && !empty($question['options'])) {
            foreach ($question['options'] as $index => $option) {
                $optionText = $this->escape($option['option_text']);
                $optionId = $option['id'];

                $optionsHtml .= "
                    <label class='option-label'>
                        <input type='checkbox' name='answers[{$questionId}][]' value='{$optionId}'>
                        <span class='option-text'>{$optionText}</span>
                    </label>";
            }
        }

        return $optionsHtml;
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
                        const allQuestions = document.querySelectorAll('.question');
                        allQuestions.forEach(function(question) {
                            question.style.display = 'none';
                        });
                        
                        // Показати поточне питання з анімацією
                        const currentQuestionEl = document.querySelector('[data-question=\"' + questionNumber + '\"]');
                        if (currentQuestionEl) {
                            currentQuestionEl.style.display = 'block';
                            currentQuestionEl.style.opacity = '0';
                            currentQuestionEl.style.transform = 'translateY(20px)';
                            
                            setTimeout(() => {
                                currentQuestionEl.style.transition = 'all 0.3s ease';
                                currentQuestionEl.style.opacity = '1';
                                currentQuestionEl.style.transform = 'translateY(0)';
                            }, 50);
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
                                    showMessage('Будь ласка, оберіть відповідь на це питання.', 'error');
                                    return false;
                                }
                            } else if (input.type === 'checkbox') {
                                const checkboxGroup = currentQuestionEl.querySelectorAll('input[name=\"' + input.name + '\"]');
                                let isChecked = false;
                                checkboxGroup.forEach(function(checkbox) {
                                    if (checkbox.checked) isChecked = true;
                                });
                                if (!isChecked) {
                                    showMessage('Будь ласка, оберіть принаймні один варіант.', 'error');
                                    return false;
                                }
                            } else if (!input.value.trim()) {
                                showMessage('Будь ласка, заповніть це поле.', 'error');
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
                        submitBtn.innerHTML = '⏳ Відправляється...';
                        submitBtn.style.background = '#95a5a6';
                    });
                    
                    // Функція для показу повідомлень
                    function showMessage(message, type) {
                        // Видаляємо попередні повідомлення
                        const existingMessages = document.querySelectorAll('.flash-message');
                        existingMessages.forEach(msg => msg.remove());
                        
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'flash-message ' + type;
                        messageDiv.textContent = message;
                        
                        // Вставляємо повідомлення на початок форми
                        const form = document.getElementById('survey-form');
                        form.insertBefore(messageDiv, form.firstChild);
                        
                        // Прокручуємо до повідомлення
                        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Видаляємо через 4 секунди
                        setTimeout(() => messageDiv.remove(), 4000);
                    }
                    
                    // Ініціалізація
                    showQuestion(1);
                    
                    // Клавіатурна навігація
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'ArrowLeft' && !prevBtn.disabled) {
                            prevBtn.click();
                        } else if (e.key === 'ArrowRight' && nextBtn.style.display !== 'none') {
                            nextBtn.click();
                        } else if (e.key === 'Enter' && submitBtn.style.display !== 'none') {
                            e.preventDefault();
                            submitBtn.click();
                        }
                    });
                });
            </script>";
    }
}