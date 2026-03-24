<?php

use App\Services\OutputParserService;

beforeEach(function () {
    $this->parser = new OutputParserService();
});

it('parses valid XML output into file array', function () {
    $content = '<file path="CLAUDE.md"># Project Context</file><file path="PROJECT.md">## Overview</file>';
    $files = $this->parser->parse($content);

    expect($files)->toHaveCount(2);
    expect($files[0]['path'])->toBe('CLAUDE.md');
    expect($files[0]['content'])->toBe('# Project Context');
    expect($files[1]['path'])->toBe('PROJECT.md');
});

it('handles PHP code with angle brackets in content', function () {
    $content = '<file path="database/migrations/create_users.php"><?php
use Illuminate\Database\Schema\Blueprint;
Schema::create(\'users\', function (Blueprint $table) {
    $table->id();
    if ($table->hasColumn(\'name\')) { /* noop */ }
});
</file>';

    $files = $this->parser->parse($content);

    expect($files)->toHaveCount(1);
    expect($files[0]['content'])->toContain('Schema::create');
    expect($files[0]['content'])->toContain('$table->id()');
});

it('returns empty array for malformed input', function () {
    expect($this->parser->parse('no xml tags here'))->toBe([]);
    expect($this->parser->parse(''))->toBe([]);
});

it('trims whitespace from content', function () {
    $content = '<file path="test.md">
    content with leading whitespace
    </file>';

    $files = $this->parser->parse($content);
    expect($files[0]['content'])->toBe('content with leading whitespace');
});

it('validates required files are present', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->filter(fn($e) => str_contains($e, 'todo.md')))->not->toBeEmpty();
});

it('validates migration files contain Schema::create', function () {
    $files = [
        ['path' => 'database/migrations/2026_create_users.php', 'content' => '<?php echo "bad";'],
    ];

    $errors = $this->parser->validate($files);
    expect(collect($errors)->filter(fn($e) => str_contains($e, 'Schema::create')))->not->toBeEmpty();
});

it('passes validation with all required files', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toBe([]);
});

it('rejects files over 50KB', function () {
    $files = [
        ['path' => 'huge.md', 'content' => str_repeat('x', 51 * 1024)],
    ];

    $errors = $this->parser->validate($files);
    expect(collect($errors)->filter(fn($e) => str_contains($e, '50KB')))->not->toBeEmpty();
});

it('validates model files have correct namespace', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
        ['path' => 'app/Models/Task.php', 'content' => '<?php class Task extends Model {}'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toContain('Model app/Models/Task.php missing namespace App\\Models');
});

it('validates controller files have correct namespace', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
        ['path' => 'app/Http/Controllers/TaskController.php', 'content' => '<?php class TaskController {}'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toContain('Controller app/Http/Controllers/TaskController.php missing namespace');
});

it('validates form request files extend FormRequest', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
        ['path' => 'app/Http/Requests/StoreTaskRequest.php', 'content' => '<?php class StoreTaskRequest {}'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toContain('Form Request app/Http/Requests/StoreTaskRequest.php does not extend FormRequest');
});

it('validates DatabaseSeeder extends Seeder', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
        ['path' => 'database/seeders/DatabaseSeeder.php', 'content' => '<?php class DatabaseSeeder {}'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toContain('DatabaseSeeder does not extend Seeder');
});

it('passes validation with all required files plus new file types', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
        ['path' => 'database/migrations/2026_01_01_000001_create_tasks_table.php', 'content' => '<?php Schema::create("tasks", function ($table) { $table->id(); });'],
        ['path' => 'app/Models/Task.php', 'content' => '<?php namespace App\\Models; class Task extends Model {}'],
        ['path' => 'app/Http/Controllers/TaskController.php', 'content' => '<?php namespace App\\Http\\Controllers; class TaskController {}'],
        ['path' => 'app/Http/Requests/StoreTaskRequest.php', 'content' => '<?php namespace App\\Http\\Requests; class StoreTaskRequest extends FormRequest {}'],
        ['path' => 'routes/api.php', 'content' => '<?php Route::get("/tasks", fn() => "ok");'],
        ['path' => 'database/seeders/DatabaseSeeder.php', 'content' => '<?php namespace Database\\Seeders; class DatabaseSeeder extends Seeder {}'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toBe([]);
});

it('summarize counts files by category', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Test'],
        ['path' => 'PROJECT.md', 'content' => '# Test'],
        ['path' => 'database/migrations/2026_01_01_000001_create_tasks_table.php', 'content' => 'Schema::create'],
        ['path' => 'app/Models/Task.php', 'content' => 'class Task extends Model'],
        ['path' => 'app/Http/Controllers/TaskController.php', 'content' => 'class TaskController'],
        ['path' => 'app/Http/Requests/StoreTaskRequest.php', 'content' => 'extends FormRequest'],
        ['path' => 'routes/api.php', 'content' => 'Route::'],
        ['path' => 'database/seeders/DatabaseSeeder.php', 'content' => 'extends Seeder'],
    ];

    $summary = $this->parser->summarize($files);
    expect($summary['docs'])->toBe(2);
    expect($summary['migrations'])->toBe(1);
    expect($summary['models'])->toBe(1);
    expect($summary['controllers'])->toBe(1);
    expect($summary['requests'])->toBe(1);
    expect($summary['routes'])->toBe(1);
    expect($summary['seeders'])->toBe(1);
    expect($summary['total'])->toBe(8);
});

it('summarize returns zero counts for empty file list', function () {
    $summary = $this->parser->summarize([]);
    expect($summary['total'])->toBe(0);
    expect($summary['docs'])->toBe(0);
    expect($summary['models'])->toBe(0);
});

it('parses the full sample fixture', function () {
    $fixturePath = base_path('tests/fixtures/sample-generation-output.xml');
    if (!file_exists($fixturePath)) {
        $this->markTestSkipped('Fixture file not found');
    }

    $content = file_get_contents($fixturePath);
    $files = $this->parser->parse($content);

    expect($files)->not->toBeEmpty();
    expect(count($files))->toBeGreaterThanOrEqual(7);

    // Validate the fixture passes validation
    $errors = $this->parser->validate($files);
    expect($errors)->toBe([]);
});
