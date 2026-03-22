<?php

use App\Services\TemplateService;

// TemplateService reads from disk, so we need the seeder to have run.
// Move this to Feature tests since it needs the Laravel app context.
// Or just seed manually using the artisan facade.

beforeEach(function () {
    // Run seeder via artisan (available because Pest.php extends TestCase for Feature)
    // For Unit tests, we just instantiate directly — templates were seeded by `php artisan db:seed`
    // and exist on disk permanently in storage/app/templates/
    $this->service = new TemplateService();
});

it('lists all templates', function () {
    $templates = $this->service->listTemplates();

    // Templates are files on disk, should be 25 from seeder
    expect($templates)->toBeArray();
    expect(count($templates))->toBeGreaterThanOrEqual(1);
});

it('puts available templates first', function () {
    $templates = $this->service->listTemplates();

    if (count($templates) > 1) {
        expect($templates[0]['available'])->toBeTrue();
    }
});

it('loads defaults for booking platform', function () {
    $defaults = $this->service->getDefaults('booking-platform');

    expect($defaults)->not->toBeNull();
    expect($defaults)->toHaveKey('step_describe');
    expect($defaults)->toHaveKey('step_models');
    expect($defaults['step_models']['models'])->toHaveCount(8);
});

it('returns null for unknown template slug', function () {
    expect($this->service->getDefaults('nonexistent'))->toBeNull();
});

it('returns template metadata', function () {
    $template = $this->service->getTemplate('booking-platform');

    expect($template)->not->toBeNull();
    expect($template['name'])->toBe('Booking & Reservation Platform');
    expect($template['available'])->toBeTrue();
});

it('returns null for unknown template metadata', function () {
    expect($this->service->getTemplate('nonexistent'))->toBeNull();
});
