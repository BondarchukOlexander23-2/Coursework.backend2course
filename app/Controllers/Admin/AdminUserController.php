<?php

require_once __DIR__ . '/../../Views/Admin/UsersView.php';

/**
 * Контролер для управління користувачами в адмін-панелі
 */
class AdminUserController extends BaseController
{
    private AdminValidator $validator;
    private AdminService $adminService;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new AdminValidator();
        $this->adminService = new AdminService();
    }

    /**
     * Управління користувачами
     */
    public function users(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $page = $this->getIntParam('page', 1);
            $search = $this->getStringParam('search');

            $users = $this->adminService->getUsers($page, $search);
            $totalUsers = $this->adminService->getTotalUsersCount($search);
            $totalPages = ceil($totalUsers / 20);

            $view = new UsersView([
                'title' => 'Управління користувачами',
                'users' => $users,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'search' => $search
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Видалити користувача
     */
    public function deleteUser(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $userId = (int)$this->postParam('user_id', 0);

            $errors = $this->validator->validateUserDeletion($userId);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилка видалення');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', implode('<br>', $errors));
                }
                return;
            }

            try {
                $this->adminService->deleteUser($userId);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Користувача видалено');
                } else {
                    $this->redirectWithMessage('/admin/users', 'success', 'Користувача успішно видалено');
                }
            } catch (Exception $e) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, [$e->getMessage()], 'Помилка видалення');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', $e->getMessage());
                }
            }
        });
    }

    /**
     * Змінити роль користувача
     */
    public function changeUserRole(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $userId = (int)$this->postParam('user_id', 0);
            $newRole = $this->postParam('role', '');

            $errors = $this->validator->validateRoleChange($userId, $newRole);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилка зміни ролі');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', implode('<br>', $errors));
                }
                return;
            }

            try {
                $this->adminService->changeUserRole($userId, $newRole);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, null, 'Роль користувача змінено');
                } else {
                    $this->redirectWithMessage('/admin/users', 'success', 'Роль користувача успішно змінено');
                }
            } catch (Exception $e) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, [$e->getMessage()], 'Помилка зміни ролі');
                } else {
                    $this->redirectWithMessage('/admin/users', 'error', $e->getMessage());
                }
            }
        });
    }

    /**
     * Перевірка прав адміністратора
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin()) {
            throw new ForbiddenException('Доступ заборонено. Тільки для адміністраторів.');
        }
    }
}