<?php

/**
 * Валідатор для адміністративних операцій
 */
class AdminValidator
{
    /**
     * Валідація видалення користувача
     */
    public function validateUserDeletion(int $userId): array
    {
        $errors = [];

        if ($userId <= 0) {
            $errors[] = 'Невірний ID користувача';
            return $errors;
        }

        // Перевіряємо чи існує користувач
        $user = User::findById($userId);
        if (!$user) {
            $errors[] = 'Користувача не знайдено';
            return $errors;
        }

        // Перевіряємо чи не намагаємося видалити себе
        if ($userId === Session::getUserId()) {
            $errors[] = 'Не можна видалити власний акаунт';
            return $errors;
        }

        // Перевіряємо чи не видаляємо останнього адміна
        if ($user['role'] === 'admin') {
            $adminCount = Database::selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'] ?? 0;
            if ($adminCount <= 1) {
                $errors[] = 'Неможливо видалити останнього адміністратора';
                return $errors;
            }
        }

        return $errors;
    }

    /**
     * Валідація зміни ролі користувача
     */
    public function validateRoleChange(int $userId, string $newRole): array
    {
        $errors = [];

        if ($userId <= 0) {
            $errors[] = 'Невірний ID користувача';
            return $errors;
        }

        // Перевіряємо валідність ролі
        $validRoles = ['user', 'admin'];
        if (!in_array($newRole, $validRoles)) {
            $errors[] = 'Невірна роль користувача';
            return $errors;
        }

        // Перевіряємо чи існує користувач
        $user = User::findById($userId);
        if (!$user) {
            $errors[] = 'Користувача не знайдено';
            return $errors;
        }

        // Перевіряємо чи не знижуємо роль самому собі
        if ($userId === Session::getUserId() && $newRole !== 'admin') {
            $errors[] = 'Не можна знизити власну роль';
            return $errors;
        }

        // Перевіряємо чи не знижуємо останнього адміна
        if ($user['role'] === 'admin' && $newRole !== 'admin') {
            $adminCount = Database::selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'] ?? 0;
            if ($adminCount <= 1) {
                $errors[] = 'Неможливо знизити роль останнього адміністратора';
                return $errors;
            }
        }

        return $errors;
    }

    /**
     * Валідація видалення опитування
     */
    public function validateSurveyDeletion(int $surveyId): array
    {
        $errors = [];

        if ($surveyId <= 0) {
            $errors[] = 'Невірний ID опитування';
            return $errors;
        }

        // Перевіряємо чи існує опитування
        $survey = Survey::findById($surveyId);
        if (!$survey) {
            $errors[] = 'Опитування не знайдено';
            return $errors;
        }

        // Можна додати додаткові перевірки, наприклад:
        // - чи має опитування активні відповіді
        // - чи це не системне опитування тощо

        return $errors;
    }

    /**
     * Валідація параметрів експорту
     */
    public function validateExportParams(int $surveyId, string $format): array
    {
        $errors = [];

        if ($surveyId <= 0) {
            $errors[] = 'Невірний ID опитування';
            return $errors;
        }

        $validFormats = ['csv', 'xlsx'];
        if (!in_array($format, $validFormats)) {
            $errors[] = 'Непідтримуваний формат експорту';
            return $errors;
        }

        // Перевіряємо чи існує опитування
        $survey = Survey::findById($surveyId);
        if (!$survey) {
            $errors[] = 'Опитування не знайдено';
            return $errors;
        }

        return $errors;
    }

    /**
     * Валідація пагінації
     */
    public function validatePagination(int $page, int $limit = 20): array
    {
        $errors = [];

        if ($page < 1) {
            $errors[] = 'Номер сторінки має бути більше 0';
        }

        if ($limit < 1 || $limit > 100) {
            $errors[] = 'Ліміт має бути від 1 до 100';
        }

        return $errors;
    }

    /**
     * Валідація пошукового запиту
     */
    public function validateSearchQuery(string $query): array
    {
        $errors = [];

        if (strlen($query) > 255) {
            $errors[] = 'Пошуковий запит занадто довгий (максимум 255 символів)';
        }

        // Можна додати перевірки на заборонені символи, SQL ін'єкції тощо
        if (preg_match('/[<>"\']/', $query)) {
            $errors[] = 'Пошуковий запит містить заборонені символи';
        }

        return $errors;
    }

    /**
     * Валідація прав доступу до адмін-панелі
     */
    public function validateAdminAccess(): array
    {
        $errors = [];

        // Перевіряємо чи користувач залогінений
        if (!Session::isLoggedIn()) {
            $errors[] = 'Необхідно увійти в систему';
            return $errors;
        }

        // Перевіряємо роль користувача
        $userId = Session::getUserId();
        $user = User::findById($userId);

        if (!$user || $user['role'] !== 'admin') {
            $errors[] = 'Доступ заборонено. Тільки для адміністраторів.';
            return $errors;
        }

        return $errors;
    }

    /**
     * Валідація масових операцій
     */
    public function validateBulkOperation(array $ids, string $operation): array
    {
        $errors = [];

        if (empty($ids)) {
            $errors[] = 'Не вибрано жодного елемента';
            return $errors;
        }

        if (count($ids) > 100) {
            $errors[] = 'Занадто багато елементів для масової операції (максимум 100)';
            return $errors;
        }

        $validOperations = ['delete', 'activate', 'deactivate', 'change_role'];
        if (!in_array($operation, $validOperations)) {
            $errors[] = 'Невірна операція';
            return $errors;
        }

        // Перевіряємо що всі ID є числами
        foreach ($ids as $id) {
            if (!is_numeric($id) || $id <= 0) {
                $errors[] = 'Невірний ID елемента';
                break;
            }
        }

        return $errors;
    }
}