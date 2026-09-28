<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeApiTestCommand extends Command
{
    protected $signature = 'make:api-test
                            {name : The test class name, e.g. AppointmentCancelApiTest}
                            {--method=GET : HTTP method}
                            {--uri=/api/example : API endpoint URI}
                            {--payload=[] : JSON payload for happy-path request}';

    protected $description = 'Scaffold a production-grade ACMS API Feature Test with auth + validation scenarios';

    public function handle(): int
    {
        $class = Str::studly($this->argument('name'));

        if (! Str::endsWith($class, 'Test')) {
            $class .= 'Test';
        }

        $path = base_path("tests/Feature/Api/{$class}.php");

        if (file_exists($path)) {
            $this->error("Test already exists: {$path}");

            return self::FAILURE;
        }

        $method = strtolower($this->option('method'));
        $uri = $this->option('uri');
        $payload = $this->option('payload');

        $stub = file_get_contents(base_path('stubs/api-feature-test.stub'));
        $content = str_replace(
            ['{{ class }}', '{{ snakeMethod }}', '{{ httpMethod }}', '{{ uri }}', '{{ payload }}'],
            [$class, Str::snake($class), $method, $uri, $payload],
            $stub
        );

        file_put_contents($path, $content);

        $this->info("API Feature Test created: tests/Feature/Api/{$class}.php");
        $this->line('Next steps:');
        $this->line('  1. Replace payload with real request body');
        $this->line('  2. Add role-specific setup via AcmsTestHelpers');
        $this->line('  3. Run: php artisan test tests/Feature/Api/'.$class.'.php');

        return self::SUCCESS;
    }
}
