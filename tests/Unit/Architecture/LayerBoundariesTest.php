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
}

