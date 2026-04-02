<?php
declare(strict_types=1);

namespace App\Lib;

class View
{
    public static function render(string $view, array $data = []): void
    {
        $path = BASE_PATH . '/app/views/' . $view . '.php';
        if (!file_exists($path)) {
            http_response_code(500);
            echo 'View not found';
            exit;
        }
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/views/partials/header.php';
        require $path;
        require BASE_PATH . '/app/views/partials/footer.php';
    }
}
