<?php

namespace App\Services;

class OutputParserService
{
    private const REQUIRED_FILES = [
        'CLAUDE.md',
        'PROJECT.md',
        'todo.md',
        '.claude-reference/architecture.md',
        '.claude-reference/constants.md',
        '.claude-reference/patterns.md',
        '.claude-reference/decisions.md',
    ];

    public function parse(string $content): array
    {
        $files = [];
        $pattern = '/<file\s+path="([^"]+)">([\s\S]*?)<\/file>/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $files[] = [
                'path' => trim($match[1]),
                'content' => trim($match[2]),
            ];
        }

        return $files;
    }

    public function validate(array $files): array
    {
        $errors = [];
        $paths = array_column($files, 'path');

        // Check required files
        foreach (self::REQUIRED_FILES as $required) {
            if (!in_array($required, $paths)) {
                $errors[] = "Missing required file: {$required}";
            }
        }

        // Check migration files contain Schema::create
        foreach ($files as $file) {
            if (str_starts_with($file['path'], 'database/migrations/') && str_ends_with($file['path'], '.php')) {
                if (!str_contains($file['content'], 'Schema::create') && !str_contains($file['content'], 'Schema::table')) {
                    $errors[] = "Migration {$file['path']} does not contain Schema::create or Schema::table";
                }
            }

            // Check routes file
            if ($file['path'] === 'routes/api.php' && !str_contains($file['content'], 'Route::')) {
                $errors[] = "routes/api.php does not contain Route::";
            }

            // Check file size (50KB max)
            if (strlen($file['content']) > 50 * 1024) {
                $errors[] = "File {$file['path']} exceeds 50KB limit";
            }
        }

        return $errors;
    }
}
