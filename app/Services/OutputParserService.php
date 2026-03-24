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

            // Validate model files contain class definition
            if (str_starts_with($file['path'], 'app/Models/') && str_ends_with($file['path'], '.php')) {
                if (!str_contains($file['content'], 'extends Model') && !str_contains($file['content'], 'extends Authenticatable')) {
                    $errors[] = "Model {$file['path']} does not extend Model or Authenticatable";
                }
                if (!str_contains($file['content'], 'namespace App\\Models')) {
                    $errors[] = "Model {$file['path']} missing namespace App\\Models";
                }
            }

            // Validate controller files
            if (str_starts_with($file['path'], 'app/Http/Controllers/') && str_ends_with($file['path'], '.php')) {
                if (!str_contains($file['content'], 'namespace App\\Http\\Controllers')) {
                    $errors[] = "Controller {$file['path']} missing namespace";
                }
            }

            // Validate form request files
            if (str_starts_with($file['path'], 'app/Http/Requests/') && str_ends_with($file['path'], '.php')) {
                if (!str_contains($file['content'], 'extends FormRequest')) {
                    $errors[] = "Form Request {$file['path']} does not extend FormRequest";
                }
            }

            // Validate seeder file
            if ($file['path'] === 'database/seeders/DatabaseSeeder.php') {
                if (!str_contains($file['content'], 'extends Seeder')) {
                    $errors[] = "DatabaseSeeder does not extend Seeder";
                }
            }

            // Check file size (50KB max)
            if (strlen($file['content']) > 50 * 1024) {
                $errors[] = "File {$file['path']} exceeds 50KB limit";
            }
        }

        return $errors;
    }

    public function summarize(array $files): array
    {
        $summary = [
            'docs' => 0,
            'migrations' => 0,
            'models' => 0,
            'controllers' => 0,
            'requests' => 0,
            'routes' => 0,
            'seeders' => 0,
            'other' => 0,
        ];

        foreach ($files as $file) {
            $path = $file['path'];
            if (str_ends_with($path, '.md')) {
                $summary['docs']++;
            } elseif (str_starts_with($path, 'database/migrations/')) {
                $summary['migrations']++;
            } elseif (str_starts_with($path, 'app/Models/')) {
                $summary['models']++;
            } elseif (str_starts_with($path, 'app/Http/Controllers/')) {
                $summary['controllers']++;
            } elseif (str_starts_with($path, 'app/Http/Requests/')) {
                $summary['requests']++;
            } elseif (str_starts_with($path, 'routes/')) {
                $summary['routes']++;
            } elseif (str_starts_with($path, 'database/seeders/')) {
                $summary['seeders']++;
            } else {
                $summary['other']++;
            }
        }

        $summary['total'] = count($files);
        return $summary;
    }
}
