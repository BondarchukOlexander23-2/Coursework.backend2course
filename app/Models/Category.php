<?php

/**
 * Модель категорій
 */
class Category
{
    private int $id;
    private string $name;
    private string $description;
    private string $color;
    private string $icon;
    private bool $isActive;

    public function __construct(
        string $name,
        string $description = '',
        string $color = '#3498db',
        string $icon = '📋',
        bool $isActive = true,
        int $id = 0
    ) {
        $this->validateData($name, $color);

        $this->id = $id;
        $this->name = trim($name);
        $this->description = trim($description);
        $this->color = $color;
        $this->icon = $icon;
        $this->isActive = $isActive;
    }

    private function validateData(string $name, string $color): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException("Category name cannot be empty");
        }

        if (strlen(trim($name)) > 255) {
            throw new InvalidArgumentException("Category name is too long");
        }

        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            throw new InvalidArgumentException("Invalid color format");
        }
    }

    /**
     * Створити категорію (тільки адміни)
     */
    public static function create(string $name, string $description = '', string $color = '#3498db', string $icon = '📋'): int
    {
        $category = new self($name, $description, $color, $icon);

        $query = "INSERT INTO categories (name, description, color, icon) VALUES (?, ?, ?, ?)";
        return Database::insert($query, [
            $category->name,
            $category->description,
            $category->color,
            $category->icon
        ]);
    }

    /**
     * Отримати всі активні категорії
     */
    public static function getAllActive(): array
    {
        $query = "SELECT c.*, COUNT(s.id) as surveys_count 
                  FROM categories c 
                  LEFT JOIN surveys s ON c.id = s.category_id AND s.is_active = 1
                  WHERE c.is_active = 1 
                  GROUP BY c.id 
                  ORDER BY c.name ASC";
        return Database::select($query);
    }

    /**
     * Отримати всі категорії (для адмінів)
     */
    public static function getAll(): array
    {
        $query = "SELECT c.*, COUNT(s.id) as surveys_count 
                  FROM categories c 
                  LEFT JOIN surveys s ON c.id = s.category_id
                  GROUP BY c.id 
                  ORDER BY c.name ASC";
        return Database::select($query);
    }

    /**
     * Знайти категорію за ID
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT * FROM categories WHERE id = ?";
        return Database::selectOne($query, [$id]);
    }

    /**
     * Оновити категорію
     */
    public static function update(int $id, string $name, string $description = '', string $color = '#3498db', string $icon = '📋'): bool
    {
        $category = new self($name, $description, $color, $icon, true, $id);

        $query = "UPDATE categories SET name = ?, description = ?, color = ?, icon = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return Database::execute($query, [
                $category->name,
                $category->description,
                $category->color,
                $category->icon,
                $id
            ]) > 0;
    }

    /**
     * Змінити статус категорії
     */
    public static function toggleStatus(int $id): bool
    {
        return Database::execute("UPDATE categories SET is_active = !is_active WHERE id = ?", [$id]) > 0;
    }

    /**
     * Видалити категорію (перенести опитування в загальну)
     */
    public static function delete(int $id): bool
    {
        // Переносимо опитування в загальну категорію (ID = 1)
        Database::execute("UPDATE surveys SET category_id = 1 WHERE category_id = ?", [$id]);
        return Database::execute("DELETE FROM categories WHERE id = ? AND id != 1", [$id]) > 0;
    }

    /**
     * Getters
     */
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getColor(): string { return $this->color; }
    public function getIcon(): string { return $this->icon; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->isActive
        ];
    }
}