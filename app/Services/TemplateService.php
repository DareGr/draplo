<?php

namespace App\Services;

class TemplateService
{
    public function listTemplates(): array
    {
        $templatesPath = storage_path('app/templates');

        if (!is_dir($templatesPath)) {
            return [];
        }

        $templates = [];
        $dirs = array_filter(glob($templatesPath . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $metaFile = $dir . '/template.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) {
                    $templates[] = $meta;
                }
            }
        }

        // Available templates first
        usort($templates, fn($a, $b) => ($b['available'] ?? false) <=> ($a['available'] ?? false));

        return $templates;
    }

    public function getDefaults(string $slug): ?array
    {
        $defaultsFile = storage_path("app/templates/{$slug}/wizard-defaults.json");

        if (!file_exists($defaultsFile)) {
            return null;
        }

        return json_decode(file_get_contents($defaultsFile), true);
    }

    public function getTemplate(string $slug): ?array
    {
        $metaFile = storage_path("app/templates/{$slug}/template.json");

        if (!file_exists($metaFile)) {
            return null;
        }

        return json_decode(file_get_contents($metaFile), true);
    }
}
