<?php
/**
 * Сервіс для управління повторними спробами
 * Відповідає принципу Single Responsibility
 */
class RetakeService
{
    /**
     * Надати дозвіл на повторне проходження
     */
    public function grantRetakePermission(int $surveyId, int $userId, int $grantedBy): array
    {
        $errors = $this->validateRetakeGrant($surveyId, $userId, $grantedBy);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $success = Survey::allowRetake($surveyId, $userId);

            if ($success) {
                // Логуємо дію
                error_log("Retake permission granted: Survey {$surveyId}, User {$userId}, By {$grantedBy}");

                return [
                    'success' => true,
                    'message' => 'Дозвіл на повторне проходження надано'
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => ['Помилка при наданні дозволу']
                ];
            }
        } catch (Exception $e) {
            error_log("Error granting retake permission: " . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Виникла помилка при обробці запиту']
            ];
        }
    }

    /**
     * Масове надання дозволів на повторне проходження
     */
    public function grantBulkRetakePermissions(int $surveyId, array $userIds, int $grantedBy): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($userIds as $userId) {
            $result = $this->grantRetakePermission($surveyId, $userId, $grantedBy);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Користувач ID {$userId}: " . implode(', ', $result['errors']);
            }
        }

        return $results;
    }

    /**
     * Валідація надання дозволу на повторне проходження
     */
    private function validateRetakeGrant(int $surveyId, int $userId, int $grantedBy): array
    {
        $errors = [];

        // Перевіряємо чи існує опитування
        $survey = Survey::findById($surveyId);
        if (!$survey) {
            $errors[] = 'Опитування не знайдено';
            return $errors;
        }

        // Перевіряємо чи користувач є автором опитування
        if (!Survey::isAuthor($surveyId, $grantedBy)) {
            $errors[] = 'Тільки автор опитування може надавати дозволи';
            return $errors;
        }

        // Перевіряємо чи існує користувач
        $user = User::findById($userId);
        if (!$user) {
            $errors[] = 'Користувача не знайдено';
            return $errors;
        }

        // Перевіряємо чи користувач вже проходив опитування
        $hasResponded = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ? AND user_id = ?",
            [$surveyId, $userId]
        );

        if (($hasResponded['count'] ?? 0) === 0) {
            $errors[] = 'Користувач ще не проходив це опитування';
            return $errors;
        }

        // Перевіряємо чи не має вже активного дозволу
        if (Survey::isRetakeAllowed($surveyId, $userId)) {
            $errors[] = 'Користувач вже має активний дозвіл на повторне проходження';
            return $errors;
        }

        return $errors;
    }

    /**
     * Отримати статистику повторних спроб для опитування
     */
    public function getRetakeStats(int $surveyId): array
    {
        $totalRetakes = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ?",
            [$surveyId]
        )['count'] ?? 0;

        $usedRetakes = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ? AND used_at IS NOT NULL",
            [$surveyId]
        )['count'] ?? 0;

        $activeRetakes = Database::selectOne(
            "SELECT COUNT(*) as count FROM survey_retakes WHERE survey_id = ? AND used_at IS NULL",
            [$surveyId]
        )['count'] ?? 0;

        return [
            'total_retakes' => $totalRetakes,
            'used_retakes' => $usedRetakes,
            'active_retakes' => $activeRetakes
        ];
    }
}