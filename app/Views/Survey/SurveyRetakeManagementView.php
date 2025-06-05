<?php

require_once __DIR__ . '/../BaseView.php';

/**
 * View для управління повторними спробами опитування
 */
class SurveyRetakeManagementView extends BaseView
{
    protected function content(): string
    {
        $survey = $this->get('survey');
        $users = $this->get('users', []);
        $retakeHistory = $this->get('retakeHistory', []);
        $stats = $this->get('stats', []);

        return "
            <div class='header-actions'>
                <h1>Управління повторними спробами</h1>
                " . $this->component('Navigation') . "
            </div>
            
            <div class='survey-info-card'>
                <h2>" . $this->escape($survey['title']) . "</h2>
                <p>" . $this->escape($survey['description']) . "</p>
                <div class='survey-meta'>
                    <span>Статус: " . ($survey['is_active'] ? 'Активне' : 'Неактивне') . "</span>
                    <span>Створено: " . date('d.m.Y', strtotime($survey['created_at'])) . "</span>
                </div>
            </div>
            
            " . $this->renderStatsSection($stats) . "
            " . $this->renderUsersSection($users, $survey['id']) . "
            " . $this->renderHistorySection($retakeHistory) . "
            
            <div class='form-actions'>
                <a href='/surveys/edit?id={$survey['id']}' class='btn btn-secondary'>Редагувати опитування</a>
                <a href='/surveys/results?id={$survey['id']}' class='btn btn-secondary'>Результати</a>
                <a href='/surveys/my' class='btn btn-primary'>Мої опитування</a>
            </div>
            
            " . $this->renderScript() . "";
    }

    private function renderStatsSection(array $stats): string
    {
        return "
            <div class='retake-stats'>
                <h3>Статистика повторних спроб</h3>
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-number'>{$stats['total_retakes']}</div>
                        <div class='stat-label'>Всього дозволів</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>{$stats['used_retakes']}</div>
                        <div class='stat-label'>Використано</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>{$stats['active_retakes']}</div>
                        <div class='stat-label'>Активних</div>
                    </div>
                </div>
            </div>";
    }

    private function renderUsersSection(array $users, int $surveyId): string
    {
        if (empty($users)) {
            return "
                <div class='no-users'>
                    <h3>Користувачі, які проходили опитування</h3>
                    <p>Поки що ніхто не проходив це опитування.</p>
                </div>";
        }

        $usersHtml = '';
        foreach ($users as $user) {
            $usersHtml .= $this->renderUserRow($user, $surveyId);
        }

        return "
            <div class='users-section'>
                <div class='section-header'>
                    <h3>Користувачі, які проходили опитування ({" . count($users) . "})</h3>
                    <div class='bulk-actions'>
                        <button onclick='selectAllUsers()' class='btn btn-sm btn-secondary'>Вибрати всіх</button>
                        <button onclick='grantBulkRetakes()' class='btn btn-sm btn-success'>Надати дозволи вибраним</button>
                    </div>
                </div>
                
                <form id='bulk-retake-form' method='POST' action='/surveys/retake/grant-bulk'>
                    <input type='hidden' name='survey_id' value='{$surveyId}'>
                    <div class='users-table'>
                        <div class='table-header'>
                            <div class='checkbox-col'>
                                <input type='checkbox' id='select-all' onchange='toggleAllUsers(this)'>
                            </div>
                            <div class='user-col'>Користувач</div>
                            <div class='attempts-col'>Спроби</div>
                            <div class='score-col'>Найкращий результат</div>
                            <div class='date-col'>Остання спроба</div>
                            <div class='status-col'>Статус</div>
                            <div class='actions-col'>Дії</div>
                        </div>
                        <div class='table-body'>
                            {$usersHtml}
                        </div>
                    </div>
                </form>
            </div>";
    }

    private function renderUserRow(array $user, int $surveyId): string
    {
        $percentage = $user['max_possible_score'] > 0
            ? round(($user['best_score'] / $user['max_possible_score']) * 100, 1)
            : 0;

        $statusBadge = '';
        $actionButton = '';

        if ($user['has_retake_permission']) {
            $statusBadge = "<span class='status-badge active'>Є дозвіл</span>";
            $actionButton = "
                <button onclick='revokeRetake({$user['user_id']})' class='btn btn-sm btn-warning'>
                    Скасувати дозвіл
                </button>";
        } else {
            $statusBadge = "<span class='status-badge inactive'>Немає дозволу</span>";
            $actionButton = "
                <button onclick='grantRetake({$user['user_id']})' class='btn btn-sm btn-success'>
                    Надати дозвіл
                </button>";
        }

        return "
            <div class='user-row'>
                <div class='checkbox-col'>
                    <input type='checkbox' name='user_ids[]' value='{$user['user_id']}' class='user-checkbox'>
                </div>
                <div class='user-col'>
                    <div class='user-info'>
                        <strong>" . $this->escape($user['name']) . "</strong>
                        <small>" . $this->escape($user['email']) . "</small>
                    </div>
                </div>
                <div class='attempts-col'>
                    <span class='attempts-count'>{$user['attempts_count']}</span>
                    <a href='/surveys/retake/user-attempts?survey_id={$surveyId}&user_id={$user['user_id']}' 
                       class='view-attempts-link'>переглянути</a>
                </div>
                <div class='score-col'>
                    <div class='score-display'>
                        <span class='score'>{$user['best_score']}/{$user['max_possible_score']}</span>
                        <span class='percentage'>({$percentage}%)</span>
                    </div>
                </div>
                <div class='date-col'>
                    " . date('d.m.Y H:i', strtotime($user['last_attempt'])) . "
                </div>
                <div class='status-col'>
                    {$statusBadge}
                </div>
                <div class='actions-col'>
                    {$actionButton}
                </div>
            </div>";
    }

    private function renderHistorySection(array $retakeHistory): string
    {
        if (empty($retakeHistory)) {
            return "
                <div class='history-section'>
                    <h3>Історія дозволів</h3>
                    <p class='no-history'>Поки що не було надано жодного дозволу на повторне проходження.</p>
                </div>";
        }

        $historyHtml = '';
        foreach ($retakeHistory as $record) {
            $historyHtml .= $this->renderHistoryRow($record);
        }

        return "
            <div class='history-section'>
                <h3>Історія дозволів ({" . count($retakeHistory) . "})</h3>
                <div class='history-list'>
                    {$historyHtml}
                </div>
            </div>";
    }

    private function renderHistoryRow(array $record): string
    {
        $usedStatus = $record['used_at']
            ? "<span class='used-badge'>Використано " . date('d.m.Y H:i', strtotime($record['used_at'])) . "</span>"
            : "<span class='pending-badge'>Очікує використання</span>";

        return "
            <div class='history-item'>
                <div class='history-user'>
                    <strong>" . $this->escape($record['user_name']) . "</strong>
                    <small>" . $this->escape($record['user_email']) . "</small>
                </div>
                <div class='history-details'>
                    <div class='granted-info'>
                        Надано: " . $this->escape($record['allowed_by_name']) . " 
                        <span class='date'>" . date('d.m.Y H:i', strtotime($record['allowed_at'])) . "</span>
                    </div>
                    <div class='status-info'>
                        {$usedStatus}
                    </div>
                </div>
            </div>";
    }

    private function renderScript(): string
    {
        return "
            <style>
                .survey-info-card {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 12px;
                    margin-bottom: 2rem;
                }
                
                .survey-meta {
                    display: flex;
                    gap: 2rem;
                    margin-top: 1rem;
                    font-size: 0.9rem;
                    opacity: 0.9;
                }
                
                .retake-stats {
                    background: white;
                    border-radius: 12px;
                    padding: 2rem;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1.5rem;
                    margin-top: 1rem;
                }
                
                .stat-card {
                    background: #f8f9fa;
                    padding: 1.5rem;
                    border-radius: 8px;
                    text-align: center;
                    border: 2px solid transparent;
                    transition: all 0.3s ease;
                }
                
                .stat-card:hover {
                    border-color: #3498db;
                    transform: translateY(-2px);
                }
                
                .stat-number {
                    font-size: 2.5rem;
                    font-weight: bold;
                    color: #3498db;
                    display: block;
                    margin-bottom: 0.5rem;
                }
                
                .stat-label {
                    color: #6c757d;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .users-section {
                    background: white;
                    border-radius: 12px;
                    padding: 2rem;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                
                .section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.5rem;
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                
                .bulk-actions {
                    display: flex;
                    gap: 0.5rem;
                }
                
                .users-table {
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                .table-header,
                .user-row {
                    display: grid;
                    grid-template-columns: 50px 2fr 1fr 1fr 1fr 1fr 1fr;
                    gap: 1rem;
                    align-items: center;
                    padding: 1rem;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .table-header {
                    background: #f8f9fa;
                    font-weight: 600;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .user-row:hover {
                    background: #f8f9fa;
                }
                
                .user-row:last-child {
                    border-bottom: none;
                }
                
                .user-info strong {
                    display: block;
                    color: #2c3e50;
                }
                
                .user-info small {
                    color: #6c757d;
                    font-size: 0.8rem;
                }
                
                .attempts-count {
                    font-weight: bold;
                    color: #3498db;
                    font-size: 1.2rem;
                    display: block;
                    margin-bottom: 0.3rem;
                }
                
                .view-attempts-link {
                    font-size: 0.8rem;
                    color: #6c757d;
                    text-decoration: none;
                }
                
                .view-attempts-link:hover {
                    color: #3498db;
                    text-decoration: underline;
                }
                
                .score-display {
                    text-align: center;
                }
                
                .score {
                    font-weight: bold;
                    color: #2c3e50;
                    display: block;
                    margin-bottom: 0.3rem;
                }
                
                .percentage {
                    font-size: 0.8rem;
                    color: #6c757d;
                }
                
                .status-badge {
                    padding: 0.3rem 0.8rem;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .status-badge.active {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                
                .status-badge.inactive {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                
                .history-section {
                    background: white;
                    border-radius: 12px;
                    padding: 2rem;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                
                .history-list {
                    display: grid;
                    gap: 1rem;
                }
                
                .history-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #dee2e6;
                }
                
                .history-user strong {
                    display: block;
                    color: #2c3e50;
                }
                
                .history-user small {
                    color: #6c757d;
                    font-size: 0.8rem;
                }
                
                .history-details {
                    text-align: right;
                }
                
                .granted-info {
                    margin-bottom: 0.5rem;
                    font-size: 0.9rem;
                    color: #6c757d;
                }
                
                .date {
                    font-weight: 500;
                    color: #495057;
                }
                
                .used-badge {
                    background: #d4edda;
                    color: #155724;
                    padding: 0.2rem 0.6rem;
                    border-radius: 12px;
                    font-size: 0.8rem;
                    font-weight: 500;
                }
                
                .pending-badge {
                    background: #fff3cd;
                    color: #856404;
                    padding: 0.2rem 0.6rem;
                    border-radius: 12px;
                    font-size: 0.8rem;
                    font-weight: 500;
                }
                
                .no-users,
                .no-history {
                    text-align: center;
                    padding: 3rem;
                    color: #6c757d;
                    font-style: italic;
                }
                
                @media (max-width: 768px) {
                    .table-header,
                    .user-row {
                        grid-template-columns: 1fr;
                        gap: 0.5rem;
                        text-align: left;
                    }
                    
                    .section-header {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    
                    .bulk-actions {
                        justify-content: center;
                    }
                    
                    .history-item {
                        flex-direction: column;
                        gap: 1rem;
                        align-items: stretch;
                    }
                    
                    .history-details {
                        text-align: left;
                    }
                }
            </style>
            
            <script>
                function toggleAllUsers(checkbox) {
                    const userCheckboxes = document.querySelectorAll('.user-checkbox');
                    userCheckboxes.forEach(cb => cb.checked = checkbox.checked);
                }
                
                function selectAllUsers() {
                    const selectAllCheckbox = document.getElementById('select-all');
                    selectAllCheckbox.checked = true;
                    toggleAllUsers(selectAllCheckbox);
                }
                
                function grantRetake(userId) {
                    if (!confirm('Надати дозвіл на повторне проходження цьому користувачу?')) {
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('survey_id', '{$this->get('survey')['id']}');
                    formData.append('user_id', userId);
                    
                    fetch('/surveys/retake/grant', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(data.message || 'Виникла помилка', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Виникла помилка при виконанні операції', 'error');
                    });
                }
                
                function revokeRetake(userId) {
                    if (!confirm('Скасувати дозвіл на повторне проходження?')) {
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('survey_id', '{$this->get('survey')['id']}');
                    formData.append('user_id', userId);
                    
                    fetch('/surveys/retake/revoke', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(data.message || 'Виникла помилка', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Виnikла помилка при виконанні операції', 'error');
                    });
                }
                
                function grantBulkRetakes() {
                    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
                    
                    if (checkedBoxes.length === 0) {
                        alert('Оберіть користувачів для надання дозволів');
                        return;
                    }
                    
                    if (!confirm(`Надати дозвіл на повторне проходження \${checkedBoxes.length} користувачам?`)) {
                        return;
                    }
                    
                    const form = document.getElementById('bulk-retake-form');
                    const formData = new FormData(form);
                    
                    fetch('/surveys/retake/grant-bulk', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        showMessage(data.message, data.success ? 'success' : 'error');
                        setTimeout(() => location.reload(), 1500);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Виникла помилка при виконанні операції', 'error');
                    });
                }
                
                function showMessage(message, type) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flash-message ' + type;
                    messageDiv.style.cssText = `
                        position: fixed; top: 20px; right: 20px; z-index: 9999;
                        padding: 1rem; border-radius: 8px; max-width: 400px;
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
                
                // Анімація для повідомлень
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