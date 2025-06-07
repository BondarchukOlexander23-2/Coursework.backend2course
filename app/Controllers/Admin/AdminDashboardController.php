<?php

require_once __DIR__ . '/../../Views/Admin/DashboardView.php';

/**
 * Контролер для дашборду адмін-панелі
 */
class AdminDashboardController extends BaseController
{
    private AdminService $adminService;

    public function __construct()
    {
        parent::__construct();
        $this->adminService = new AdminService();
    }

    /**
     * Головна сторінка адмін-панелі
     */
    public function dashboard(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $stats = $this->adminService->getDashboardStats();

            $view = new DashboardView([
                'title' => 'Адмін-панель',
                'stats' => $stats
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
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

    /**
     * Відправити відповідь на AJAX запит
     */
    protected function sendAjaxResponse(bool $success, $data = null, string $message = ''): void
    {
        $response = [
            'success' => $success,
            'message' => $message
        ];

        if (is_array($data)) {
            $response['errors'] = $data;
        } elseif ($data !== null) {
            $response['data'] = $data;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Перевірка чи це AJAX запит
     */
    protected function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Редирект з повідомленням
     */
    protected function redirectWithMessage(string $url, string $type, string $message): void
    {
        Session::setFlashMessage($type, $message);
        header("Location: $url");
        exit;
    }
}