<?php

/**
 * Контролер управління категоріями
 */
class AdminCategoriesController extends BaseController
{
    private AdminValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new AdminValidator();
    }

    /**
     * Список категорій
     */
    public function categories(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $categories = Category::getAll();

            if (!class_exists('CategoriesView')) {
                require_once __DIR__ . '/../../Views/Admin/CategoriesView.php';
            }

            $view = new CategoriesView([
                'title' => 'Управління категоріями',
                'categories' => $categories
            ]);
            $content = $view->render();

            $this->responseManager
                ->setNoCacheHeaders()
                ->sendSuccess($content);
        });
    }

    /**
     * Створити категорію
     */
    public function createCategory(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $name = $this->postParam('name', '');
            $description = $this->postParam('description', '');
            $color = $this->postParam('color', '#3498db');
            $icon = $this->postParam('icon', '📋');

            $errors = $this->validateCategoryData($name, $color);

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
            }

            try {
                $categoryId = Category::create($name, $description, $color, $icon);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(true, ['id' => $categoryId], 'Категорію створено');
                } else {
                    $this->redirectWithMessage('/admin/categories', 'success', 'Категорію створено');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при створенні категорії');
            }
        });
    }

    /**
     * Оновити категорію
     */
    public function updateCategory(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $id = (int)$this->postParam('id', 0);
            $name = $this->postParam('name', '');
            $description = $this->postParam('description', '');
            $color = $this->postParam('color', '#3498db');
            $icon = $this->postParam('icon', '📋');

            $errors = $this->validateCategoryData($name, $color);
            if ($id <= 0) $errors[] = 'Невірний ID категорії';

            if (!empty($errors)) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, $errors, 'Помилки валідації');
                } else {
                    throw new ValidationException($errors);
                }
                return;
            }

            try {
                $updated = Category::update($id, $name, $description, $color, $icon);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse($updated, null, $updated ? 'Категорію оновлено' : 'Помилка оновлення');
                } else {
                    $this->redirectWithMessage('/admin/categories', $updated ? 'success' : 'error',
                        $updated ? 'Категорію оновлено' : 'Помилка оновлення');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при оновленні категорії');
            }
        });
    }

    /**
     * Змінити статус категорії
     */
    public function toggleCategoryStatus(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $id = (int)$this->postParam('id', 0);

            if ($id <= 0) {
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, ['Невірний ID категорії'], 'Помилка');
                } else {
                    $this->redirectWithMessage('/admin/categories', 'error', 'Невірний ID категорії');
                }
                return;
            }

            try {
                $toggled = Category::toggleStatus($id);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse($toggled, null, $toggled ? 'Статус змінено' : 'Помилка');
                } else {
                    $this->redirectWithMessage('/admin/categories', $toggled ? 'success' : 'error',
                        $toggled ? 'Статус змінено' : 'Помилка');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при зміні статусу');
            }
        });
    }

    /**
     * Видалити категорію
     */
    public function deleteCategory(): void
    {
        $this->safeExecute(function() {
            $this->requireAdmin();

            $id = (int)$this->postParam('id', 0);

            if ($id <= 1) { // Не можна видалити загальну категорію
                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse(false, ['Неможливо видалити цю категорію'], 'Помилка');
                } else {
                    $this->redirectWithMessage('/admin/categories', 'error', 'Неможливо видалити цю категорію');
                }
                return;
            }

            try {
                $deleted = Category::delete($id);

                if ($this->isAjaxRequest()) {
                    $this->sendAjaxResponse($deleted, null, $deleted ? 'Категорію видалено' : 'Помилка видалення');
                } else {
                    $this->redirectWithMessage('/admin/categories', $deleted ? 'success' : 'error',
                        $deleted ? 'Категорію видалено' : 'Помилка видалення');
                }
            } catch (Exception $e) {
                throw new DatabaseException($e->getMessage(), 'Помилка при видаленні категорії');
            }
        });
    }

    private function validateCategoryData(string $name, string $color): array
    {
        $errors = [];

        $name = trim($name);
        if (empty($name)) {
            $errors[] = 'Назва категорії є обов\'язковою';
        }
        if (strlen($name) > 255) {
            $errors[] = 'Назва занадто довга';
        }

        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            $errors[] = 'Невірний формат кольору';
        }

        return $errors;
    }
}