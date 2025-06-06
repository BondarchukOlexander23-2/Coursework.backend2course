<?php
/**
 * Валідатор для повторних спроб
 */
class RetakeValidator
{
    /**
     * Валідація параметрів надання дозволу
     */
    public function validateGrantRetakeParams(array $params): array
    {
        $errors = [];

        if (!isset($params['survey_id']) || !is_numeric($params['survey_id']) || $params['survey_id'] <= 0) {
            $errors[] = 'Невірний ID опитування';
        }

        if (!isset($params['user_id']) || !is_numeric($params['user_id']) || $params['user_id'] <= 0) {
            $errors[] = 'Невірний ID користувача';
        }

        return $errors;
    }

    /**
     * Валідація масового надання дозволів
     */
    public function validateBulkGrantParams(array $params): array
    {
        $errors = [];

        if (!isset($params['survey_id']) || !is_numeric($params['survey_id']) || $params['survey_id'] <= 0) {
            $errors[] = 'Невірний ID опитування';
        }

        if (!isset($params['user_ids']) || !is_array($params['user_ids']) || empty($params['user_ids'])) {
            $errors[] = 'Не вибрано користувачів';
        } else {
            foreach ($params['user_ids'] as $userId) {
                if (!is_numeric($userId) || $userId <= 0) {
                    $errors[] = 'Невірний формат ID користувачів';
                    break;
                }
            }

            if (count($params['user_ids']) > 50) {
                $errors[] = 'Занадто багато користувачів (максимум 50)';
            }
        }

        return $errors;
    }
}