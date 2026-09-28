<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleApiRoutesTest extends TestCase
{
    private const MODULES_WITHOUT_API = [
        'Audit',
    ];

    public function test_every_active_module_exposes_a_discoverable_api_route_file(): void
    {
        $modules = glob(base_path('Modules/*'), GLOB_ONLYDIR) ?: [];
        $missing = [];

        foreach ($modules as $modulePath) {
            $module = basename($modulePath);

            if (in_array($module, self::MODULES_WITHOUT_API, true)) {
                continue;
            }

            $hasApiFile = file_exists($modulePath.DIRECTORY_SEPARATOR.'Routes'.DIRECTORY_SEPARATOR.'api.php')
                || file_exists($modulePath.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'api.php');

            if (! $hasApiFile) {
                $missing[] = $module;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Modules missing Routes/api.php or routes/api.php: '.implode(', ', $missing)
        );
    }

    public function test_application_registers_api_routes_for_all_modules(): void
    {
        $apiRoutes = collect(Route::getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/'))
            ->count();

        $this->assertGreaterThan(
            150,
            $apiRoutes,
            'Expected a large API surface — route registration may be broken.'
        );
    }

    public function test_module_route_paths_work_on_case_sensitive_filesystems(): void
    {
        $modules = glob(base_path('Modules/*'), GLOB_ONLYDIR) ?: [];

        foreach ($modules as $modulePath) {
            $module = basename($modulePath);

            if (in_array($module, self::MODULES_WITHOUT_API, true)) {
                continue;
            }

            $resolved = null;

            foreach (['Routes', 'routes'] as $directory) {
                $candidate = $modulePath.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'api.php';

                if (file_exists($candidate)) {
                    $resolved = $candidate;
                    break;
                }
            }

            $this->assertNotNull(
                $resolved,
                "Module {$module} must expose api.php under Routes/ or routes/."
            );

            $this->assertFileIsReadable($resolved);
        }
    }
}
