<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LayerBoundariesTest extends TestCase
{
    public function test_domain_layer_has_no_framework_or_infrastructure_dependencies(): void
    {
        $violations = $this->collectForbiddenImports(
            basePath: __DIR__ . '/../../../app/Domain',
            forbiddenPrefixes: ['Illuminate\\', 'App\\Infrastructure\\']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_application_layer_does_not_depend_on_infrastructure_or_facades(): void
    {
        $violations = $this->collectForbiddenImports(
            basePath: __DIR__ . '/../../../app/Application',
            forbiddenPrefixes: ['App\\Infrastructure\\', 'Illuminate\\Support\\Facades\\']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_domain_like_services_do_not_depend_on_framework_or_infrastructure(): void
    {
        $files = [
            __DIR__ . '/../../../app/Services/CharacterStatService.php',
            __DIR__ . '/../../../app/Services/CharacterStatValidator.php',
            __DIR__ . '/../../../app/Services/TeamAssigner.php',
        ];

        $violations = $this->collectForbiddenImportsInFiles(
            files: $files,
            forbiddenPrefixes: ['Illuminate\\', 'App\\Infrastructure\\']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_controllers_do_not_reference_infrastructure_models_or_repositories(): void
    {
        $violations = $this->collectForbiddenContentPatterns(
            basePath: __DIR__ . '/../../../app/Http/Controllers',
            forbiddenPatterns: [
                'App\\Infrastructure\\Eloquent\\Models\\',
                'App\\Infrastructure\\Eloquent\\Repositories\\',
            ],
            excludedPaths: ['/Auth/', '/Controllers/Controller.php']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_websocket_handlers_do_not_reference_infrastructure_models_or_repositories(): void
    {
        $violations = $this->collectForbiddenContentPatterns(
            basePath: __DIR__ . '/../../../app/Infrastructure/WebSockets/Handlers',
            forbiddenPatterns: [
                'App\\Infrastructure\\Eloquent\\Models\\',
                'App\\Infrastructure\\Eloquent\\Repositories\\',
                'Illuminate\\Support\\Facades\\DB',
            ]
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_critical_modules_avoid_direct_infrastructure_model_access(): void
    {
        $files = [
            __DIR__ . '/../../../app/Services/MovementResolver.php',
            __DIR__ . '/../../../app/Http/Controllers/StoreController.php',
        ];

        $violations = $this->collectForbiddenContentPatternsInFiles(
            files: $files,
            forbiddenPatterns: [
                'App\\Infrastructure\\Eloquent\\Models\\',
                'broadcast(',
            ]
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_api_controllers_do_not_catch_domain_exception_manually(): void
    {
        $violations = $this->collectForbiddenContentPatterns(
            basePath: __DIR__ . '/../../../app/Http/Controllers',
            forbiddenPatterns: [
                'catch (DomainException',
                'catch (\\App\\Domain\\DomainException',
            ],
            excludedPaths: ['/Auth/', '/Controllers/Controller.php']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_api_controllers_do_not_catch_broad_exception_types(): void
    {
        $violations = $this->collectForbiddenContentPatterns(
            basePath: __DIR__ . '/../../../app/Http/Controllers',
            forbiddenPatterns: [
                'catch (\\Exception',
                'catch (Exception',
            ],
            excludedPaths: ['/Auth/', '/Controllers/Controller.php']
        );

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_services_do_not_reference_eloquent_models_without_whitelist(): void
    {
        $basePath = __DIR__ . '/../../../app/Services';
        $allowedFiles = [];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            $isAllowed = false;
            foreach ($allowedFiles as $allowedFile) {
                if (str_ends_with($filePath, $allowedFile)) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                continue;
            }

            $contents = file_get_contents($filePath);
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'App\\Infrastructure\\Eloquent\\Models\\')) {
                $violations[] = sprintf(
                    '%s contains forbidden model dependency without whitelist',
                    $filePath
                );
            }
        }

        sort($violations);
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_services_do_not_use_direct_eloquent_static_calls_without_whitelist(): void
    {
        $basePath = __DIR__ . '/../../../app/Services';
        $allowedFiles = [];
        $forbiddenStaticCalls = ['::find(', '::where(', '::query(', '::updateOrCreate(', '::create('];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            $isAllowed = false;
            foreach ($allowedFiles as $allowedFile) {
                if (str_ends_with($filePath, $allowedFile)) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                continue;
            }

            $contents = file_get_contents($filePath);
            if ($contents === false) {
                continue;
            }

            foreach ($forbiddenStaticCalls as $call) {
                if (str_contains($contents, $call)) {
                    $violations[] = sprintf(
                        '%s contains forbidden direct Eloquent static call: %s',
                        $filePath,
                        $call
                    );
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    /**
     * @param string[] $forbiddenPrefixes
     * @return string[]
     */
    private function collectForbiddenImports(string $basePath, array $forbiddenPrefixes): array
    {
        $violations = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            foreach (token_get_all($contents) as $token) {
                if (!is_array($token) || $token[0] !== T_NAME_QUALIFIED) {
                    continue;
                }

                $name = $token[1];
                foreach ($forbiddenPrefixes as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $violations[] = sprintf('%s:%d uses %s', $file->getPathname(), $token[2], $name);
                    }
                }
            }
        }

        sort($violations);
        return $violations;
    }

    /**
     * @param string[] $files
     * @param string[] $forbiddenPrefixes
     * @return string[]
     */
    private function collectForbiddenImportsInFiles(array $files, array $forbiddenPrefixes): array
    {
        $violations = [];

        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            $contents = file_get_contents($filePath);
            if ($contents === false) {
                continue;
            }

            foreach (token_get_all($contents) as $token) {
                if (!is_array($token) || $token[0] !== T_NAME_QUALIFIED) {
                    continue;
                }

                $name = $token[1];
                foreach ($forbiddenPrefixes as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $violations[] = sprintf('%s:%d uses %s', $filePath, $token[2], $name);
                    }
                }
            }
        }

        sort($violations);
        return $violations;
    }

    /**
     * @param string[] $forbiddenPatterns
     * @param string[] $excludedPaths
     * @return string[]
     */
    private function collectForbiddenContentPatterns(string $basePath, array $forbiddenPatterns, array $excludedPaths = []): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        return $this->collectForbiddenContentPatternsInFiles($files, $forbiddenPatterns, $excludedPaths);
    }

    /**
     * @param string[] $files
     * @param string[] $forbiddenPatterns
     * @param string[] $excludedPaths
     * @return string[]
     */
    private function collectForbiddenContentPatternsInFiles(array $files, array $forbiddenPatterns, array $excludedPaths = []): array
    {
        $violations = [];

        foreach ($files as $filePath) {
            foreach ($excludedPaths as $excludedPath) {
                if (str_contains($filePath, $excludedPath)) {
                    continue 2;
                }
            }

            if (!is_file($filePath)) {
                continue;
            }

            $contents = file_get_contents($filePath);
            if ($contents === false) {
                continue;
            }

            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = sprintf('%s contains forbidden pattern: %s', $filePath, $pattern);
                }
            }
        }

        sort($violations);
        return $violations;
    }
}
