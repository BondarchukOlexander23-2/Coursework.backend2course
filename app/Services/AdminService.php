<?php

/**
 * Сервіс адміністрування
 */
class AdminService
{
    /**
     * Отримати статистику для дашборду
     */
    public function getDashboardStats(): array
    {
        $totalUsers = Database::selectOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
        $totalSurveys = Database::selectOne("SELECT COUNT(*) as count FROM surveys")['count'] ?? 0;
        $activeSurveys = Database::selectOne("SELECT COUNT(*) as count FROM surveys WHERE is_active = 1")['count'] ?? 0;
        $totalResponses = Database::selectOne("SELECT COUNT(*) as count FROM survey_responses")['count'] ?? 0;
        $totalCategories = Database::selectOne("SELECT COUNT(*) as count FROM categories WHERE is_active = 1")['count'] ?? 0;

        $recentActivity = $this->getRecentActivityWithCategories();
        $categoryStats = $this->getCategoryStats();

        return [
            'total_users' => $totalUsers,
            'total_surveys' => $totalSurveys,
            'active_surveys' => $activeSurveys,
            'total_responses' => $totalResponses,
            'total_categories' => $totalCategories,
            'recent_activity' => $recentActivity,
            'category_stats' => $categoryStats
        ];
    }

    /**
     * Отримати користувачів з пагінацією та пошуком
     */
    public function getUsers(int $page = 1, string $search = ''): array
    {
        $offset = ($page - 1) * 20;
        $searchTerm = '%' . $search . '%';

        $query = "SELECT u.*, 
                         COUNT(DISTINCT s.id) as surveys_count,
                         COUNT(DISTINCT sr.id) as responses_count
                  FROM users u 
                  LEFT JOIN surveys s ON u.id = s.user_id
                  LEFT JOIN survey_responses sr ON u.id = sr.user_id
                  WHERE u.name LIKE ? OR u.email LIKE ?
                  GROUP BY u.id
                  ORDER BY u.created_at DESC
                  LIMIT 20 OFFSET ?";

        return Database::select($query, [$searchTerm, $searchTerm, $offset]);
    }

    /**
     * Отримати загальну кількість користувачів
     */
    public function getTotalUsersCount(string $search = ''): int
    {
        $searchTerm = '%' . $search . '%';

        $query = "SELECT COUNT(*) as count FROM users WHERE name LIKE ? OR email LIKE ?";
        $result = Database::selectOne($query, [$searchTerm, $searchTerm]);

        return $result['count'] ?? 0;
    }

    /**
     * Видалити користувача та всі його дані
     */
    public function deleteUser(int $userId): bool
    {
        $adminCount = Database::selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'] ?? 0;
        $userRole = Database::selectOne("SELECT role FROM users WHERE id = ?", [$userId])['role'] ?? '';

        if ($userRole === 'admin' && $adminCount <= 1) {
            throw new Exception("Неможливо видалити останнього адміністратора");
        }

        return Database::execute("DELETE FROM users WHERE id = ?", [$userId]) > 0;
    }

    /**
     * Змінити роль користувача
     */
    public function changeUserRole(int $userId, string $newRole): bool
    {
        $validRoles = ['user', 'admin'];
        if (!in_array($newRole, $validRoles)) {
            throw new Exception("Невірна роль користувача");
        }

        // Перевіряємо, що не знижуємо останнього адміна
        if ($newRole === 'user') {
            $currentRole = Database::selectOne("SELECT role FROM users WHERE id = ?", [$userId])['role'] ?? '';
            if ($currentRole === 'admin') {
                $adminCount = Database::selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'] ?? 0;
                if ($adminCount <= 1) {
                    throw new Exception("Неможливо знизити роль останнього адміністратора");
                }
            }
        }

        return Database::execute("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId]) > 0;
    }

    /**
     * Отримати опитування з пагінацією та фільтрами
     */
    public function getSurveys(int $page = 1, string $search = '', string $status = 'all'): array
    {
        $offset = ($page - 1) * 20;
        $searchTerm = '%' . $search . '%';

        $statusCondition = '';
        $params = [$searchTerm, $searchTerm];

        if ($status === 'active') {
            $statusCondition = " AND s.is_active = 1";
        } elseif ($status === 'inactive') {
            $statusCondition = " AND s.is_active = 0";
        }

        $query = "SELECT s.*, u.name as author_name,
                         COUNT(DISTINCT q.id) as question_count,
                         COUNT(DISTINCT sr.id) as response_count
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id
                  LEFT JOIN questions q ON s.id = q.survey_id
                  LEFT JOIN survey_responses sr ON s.id = sr.survey_id
                  WHERE (s.title LIKE ? OR s.description LIKE ?) {$statusCondition}
                  GROUP BY s.id
                  ORDER BY s.created_at DESC
                  LIMIT 20 OFFSET ?";

        $params[] = $offset;

        return Database::select($query, $params);
    }

    /**
     * Отримати загальну кількість опитувань
     */
    public function getTotalSurveysCount(string $search = '', string $status = 'all', int $categoryId = 0): int
    {
        $searchTerm = '%' . $search . '%';

        $statusCondition = '';
        $categoryCondition = '';
        $params = [$searchTerm, $searchTerm];

        if ($status === 'active') {
            $statusCondition = " AND is_active = 1";
        } elseif ($status === 'inactive') {
            $statusCondition = " AND is_active = 0";
        }

        if ($categoryId > 0) {
            $categoryCondition = " AND category_id = ?";
            $params[] = $categoryId;
        }

        $query = "SELECT COUNT(*) as count FROM surveys WHERE (title LIKE ? OR description LIKE ?) {$statusCondition} {$categoryCondition}";
        $result = Database::selectOne($query, $params);

        return $result['count'] ?? 0;
    }

    /**
     * Видалити опитування та всі пов'язані дані
     */
    public function deleteSurvey(int $surveyId): bool
    {
        return Database::execute("DELETE FROM surveys WHERE id = ?", [$surveyId]) > 0;
    }

    /**
     * Перемкнути статус опитування
     */
    public function toggleSurveyStatus(int $surveyId): bool
    {
        return Database::execute("UPDATE surveys SET is_active = !is_active WHERE id = ?", [$surveyId]) > 0;
    }

    /**
     * Отримати детальну статистику опитування
     */
    public function getSurveyDetailedStats(int $surveyId): array
    {
        $general = Database::selectOne(
            "SELECT 
                COUNT(DISTINCT sr.id) as total_responses,
                COUNT(DISTINCT q.id) as total_questions,
                COUNT(DISTINCT sr.user_id) as unique_users,
                MIN(sr.created_at) as first_response,
                MAX(sr.created_at) as last_response
             FROM surveys s
             LEFT JOIN questions q ON s.id = q.survey_id
             LEFT JOIN survey_responses sr ON s.id = sr.survey_id
             WHERE s.id = ?",
            [$surveyId]
        );

        $questions = Database::select(
            "SELECT q.*, 
                    COUNT(qa.id) as answers_count,
                    CASE 
                        WHEN COUNT(CASE WHEN qa.is_correct IS NOT NULL THEN 1 END) > 0 
                        THEN ROUND(AVG(CASE WHEN qa.is_correct = 1 THEN 100 ELSE 0 END), 1)
                        ELSE NULL 
                    END as avg_correctness
             FROM questions q
             LEFT JOIN question_answers qa ON q.id = qa.question_id
             WHERE q.survey_id = ?
             GROUP BY q.id
             ORDER BY q.order_number",
            [$surveyId]
        );

        // Статистика по днях (останні 30 днів)
        $daily = Database::select(
            "SELECT DATE(created_at) as date, COUNT(*) as responses 
             FROM survey_responses 
             WHERE survey_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            [$surveyId]
        );

        return [
            'general' => $general ?: [
                'total_responses' => 0,
                'total_questions' => 0,
                'unique_users' => 0,
                'first_response' => null,
                'last_response' => null
            ],
            'questions' => $questions,
            'daily' => $daily
        ];
    }

    private function getCategoryStats(): array
    {
        $query = "SELECT c.name, c.icon, c.color, COUNT(s.id) as surveys_count
              FROM categories c
              LEFT JOIN surveys s ON c.id = s.category_id AND s.is_active = 1
              WHERE c.is_active = 1
              GROUP BY c.id
              ORDER BY surveys_count DESC
              LIMIT 5";

        return Database::select($query);
    }

    /**
     * Остання активність з категоріями
     */
    private function getRecentActivityWithCategories(): array
    {
        $activities = [];

        // Останні користувачі
        $recentUsers = Database::select(
            "SELECT name, created_at FROM users ORDER BY created_at DESC LIMIT 2"
        );

        foreach ($recentUsers as $user) {
            $activities[] = [
                'icon' => '👤',
                'description' => "Новий користувач: " . htmlspecialchars($user['name']),
                'time' => $this->timeAgo($user['created_at'])
            ];
        }

        // Останні опитування з категоріями
        $recentSurveys = Database::select(
            "SELECT s.title, s.created_at, u.name as author, c.name as category_name, c.icon as category_icon
         FROM surveys s 
         JOIN users u ON s.user_id = u.id 
         LEFT JOIN categories c ON s.category_id = c.id
         ORDER BY s.created_at DESC LIMIT 3"
        );

        foreach ($recentSurveys as $survey) {
            $categoryInfo = $survey['category_name'] ? " ({$survey['category_icon']} {$survey['category_name']})" : "";
            $activities[] = [
                'icon' => '📋',
                'description' => "Нове опитування: " . htmlspecialchars($survey['title']) . " від " . htmlspecialchars($survey['author']) . $categoryInfo,
                'time' => $this->timeAgo($survey['created_at'])
            ];
        }

        // Останні категорії
        $recentCategories = Database::select(
            "SELECT name, icon, created_at FROM categories ORDER BY created_at DESC LIMIT 2"
        );

        foreach ($recentCategories as $category) {
            $activities[] = [
                'icon' => '🏷️',
                'description' => "Нова категорія: {$category['icon']} " . htmlspecialchars($category['name']),
                'time' => $this->timeAgo($category['created_at'])
            ];
        }

        // Сортуємо по часу
        usort($activities, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        return array_slice($activities, 0, 8);
    }
    /**
     * Експортувати статистику опитування
     */
    public function exportSurveyStats(int $surveyId, string $format = 'csv'): void
    {
        $survey = Survey::findById($surveyId);
        if (!$survey) {
            throw new Exception("Опитування не знайдено");
        }

        $responses = Database::select(
            "SELECT sr.id, sr.created_at, u.name as user_name, u.email,
                    sr.total_score, sr.max_score,
                    q.question_text, qa.answer_text, qo.option_text, qa.is_correct, qa.points_earned
             FROM survey_responses sr
             LEFT JOIN users u ON sr.user_id = u.id
             LEFT JOIN question_answers qa ON sr.id = qa.response_id
             LEFT JOIN questions q ON qa.question_id = q.id
             LEFT JOIN question_options qo ON qa.option_id = qo.id
             WHERE sr.survey_id = ?
             ORDER BY sr.id, q.order_number",
            [$surveyId]
        );

        $filename = "survey_{$surveyId}_stats_" . date('Y-m-d_H-i-s');

        if ($format === 'csv') {
            $this->exportToCsv($responses, $filename, $survey['title']);
        } elseif ($format === 'xlsx') {
            $this->exportToExcel($responses, $filename, $survey['title']);
        } else {
            throw new Exception("Непідтримуваний формат експорту");
        }
    }


    /**
     * Отримати опитування з категоріями
     */
    public function getSurveysWithCategories(int $page = 1, string $search = '', string $status = 'all', int $categoryId = 0): array
    {
        $offset = ($page - 1) * 20;
        $searchTerm = '%' . $search . '%';

        $statusCondition = '';
        $categoryCondition = '';
        $params = [$searchTerm, $searchTerm];

        if ($status === 'active') {
            $statusCondition = " AND s.is_active = 1";
        } elseif ($status === 'inactive') {
            $statusCondition = " AND s.is_active = 0";
        }

        if ($categoryId > 0) {
            $categoryCondition = " AND s.category_id = ?";
            $params[] = $categoryId;
        }

        $query = "SELECT s.*, u.name as author_name, c.name as category_name, 
                     c.color as category_color, c.icon as category_icon,
                     COUNT(DISTINCT q.id) as question_count,
                     COUNT(DISTINCT sr.id) as response_count
              FROM surveys s 
              JOIN users u ON s.user_id = u.id
              LEFT JOIN categories c ON s.category_id = c.id
              LEFT JOIN questions q ON s.id = q.survey_id
              LEFT JOIN survey_responses sr ON s.id = sr.survey_id
              WHERE (s.title LIKE ? OR s.description LIKE ?) {$statusCondition} {$categoryCondition}
              GROUP BY s.id
              ORDER BY s.created_at DESC
              LIMIT 20 OFFSET ?";

        $params[] = $offset;

        return Database::select($query, $params);
    }
    /**
     * Отримати останню активність
     */
    private function getRecentActivity(): array
    {
        $activities = [];

        $recentUsers = Database::select(
            "SELECT name, created_at FROM users ORDER BY created_at DESC LIMIT 3"
        );

        foreach ($recentUsers as $user) {
            $activities[] = [
                'icon' => '👤',
                'description' => "Новий користувач: " . htmlspecialchars($user['name']),
                'time' => $this->timeAgo($user['created_at'])
            ];
        }

        // Останні опитування
        $recentSurveys = Database::select(
            "SELECT s.title, s.created_at, u.name as author 
             FROM surveys s 
             JOIN users u ON s.user_id = u.id 
             ORDER BY s.created_at DESC LIMIT 3"
        );

        foreach ($recentSurveys as $survey) {
            $activities[] = [
                'icon' => '📋',
                'description' => "Нове опитування: " . htmlspecialchars($survey['title']) . " від " . htmlspecialchars($survey['author']),
                'time' => $this->timeAgo($survey['created_at'])
            ];
        }

        // Сортуємо по часу
        usort($activities, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Експорт до CSV
     */
    private function exportToCsv(array $data, string $filename, string $surveyTitle): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");

        $output = fopen('php://output', 'w');

        // BOM для правильного відображення UTF-8 в Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Заголовки
        fputcsv($output, [
            'ID відповіді',
            'Дата',
            'Користувач',
            'Email',
            'Питання',
            'Відповідь',
            'Правильно',
            'Бали',
            'Загальний рахунок'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['user_name'] ?: 'Анонім',
                $row['email'] ?: '',
                $row['question_text'],
                $row['answer_text'] ?: $row['option_text'],
                $row['is_correct'] ? 'Так' : 'Ні',
                $row['points_earned'],
                $row['total_score'] . '/' . $row['max_score']
            ]);
        }

        fclose($output);
        exit;
    }

    private function exportToExcel(array $data, string $filename, string $surveyTitle): void
    {
        $this->exportToCsv($data, $filename, $surveyTitle);
    }


    private function timeAgo(string $datetime): string
    {
        $time = time() - strtotime($datetime);

        if ($time < 60) return 'щойно';
        if ($time < 3600) return floor($time/60) . ' хв тому';
        if ($time < 86400) return floor($time/3600) . ' год тому';
        if ($time < 2592000) return floor($time/86400) . ' дн тому';

        return date('d.m.Y', strtotime($datetime));
    }
}