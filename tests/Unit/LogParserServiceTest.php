<?php

declare(strict_types=1);

use Clinically\Companion\Services\LogParserService;

beforeEach(function () {
    $this->parser = new LogParserService;
});

it('parses standard Laravel log entries', function () {
    $content = <<<'LOG'
[2026-03-30 12:00:00] local.ERROR: Something went wrong {"exception":"RuntimeException"}
[2026-03-30 12:01:00] local.INFO: User logged in {"user_id":1}
LOG;

    $entries = $this->parser->parse($content);

    expect($entries)->toHaveCount(2);
    expect($entries[0]['level'])->toBe('error');
    expect($entries[0]['message'])->toBe('Something went wrong {"exception":"RuntimeException"}');
    expect($entries[1]['level'])->toBe('info');
});

it('filters by log level', function () {
    $content = <<<'LOG'
[2026-03-30 12:00:00] local.ERROR: Error message
[2026-03-30 12:01:00] local.INFO: Info message
[2026-03-30 12:02:00] local.ERROR: Another error
LOG;

    $entries = $this->parser->parse($content, 'error');

    expect($entries)->toHaveCount(2);
    expect($entries[0]['level'])->toBe('error');
    expect($entries[1]['level'])->toBe('error');
});

it('filters by search term', function () {
    $content = <<<'LOG'
[2026-03-30 12:00:00] local.ERROR: Database connection failed
[2026-03-30 12:01:00] local.ERROR: Authentication error
LOG;

    $entries = $this->parser->parse($content, search: 'database');

    expect($entries)->toHaveCount(1);
    expect($entries[0]['message'])->toContain('Database');
});

it('separates stack traces from context', function () {
    $content = <<<'LOG'
[2026-03-30 12:00:00] local.ERROR: Something failed
#0 /app/Http/Controller.php(25): doThing()
#1 /vendor/laravel/framework/src/Router.php(100): dispatch()
LOG;

    $entries = $this->parser->parse($content);

    expect($entries)->toHaveCount(1);
    expect($entries[0]['stack_trace'])->toContain('#0');
});
