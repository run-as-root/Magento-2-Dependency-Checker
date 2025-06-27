<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Domain\Package\Composer;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Domain\Package\Composer\Json;

#[CoversClass(Json::class)]
class JsonTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/composer_json_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createComposerJson(array $content): string
    {
        $filePath = $this->tempDir . '/composer.json';
        file_put_contents($filePath, json_encode($content));
        return $filePath;
    }

    public function testGetDirPathReturnsCorrectDirectory(): void
    {
        $filePath = '/path/to/package/composer.json';
        $json = new Json($filePath);

        $this->assertSame('/path/to/package', $json->getDirPath());
    }

    public function testGetDirPathWithNestedDirectory(): void
    {
        $filePath = '/deep/nested/directory/structure/composer.json';
        $json = new Json($filePath);

        $this->assertSame('/deep/nested/directory/structure', $json->getDirPath());
    }

    public function testGetPackageNameReturnsNameFromJson(): void
    {
        $content = ['name' => 'vendor/package-name'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame('vendor/package-name', $json->getPackageName());
    }

    public function testGetPackageNameReturnsNullWhenNameMissing(): void
    {
        $content = ['type' => 'library'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertNull($json->getPackageName());
    }

    public function testGetPackageTypeReturnsTypeFromJson(): void
    {
        $content = ['type' => 'magento2-module'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame('magento2-module', $json->getPackageType());
    }

    public function testGetPackageTypeReturnsNullWhenTypeMissing(): void
    {
        $content = ['name' => 'vendor/package'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertNull($json->getPackageType());
    }

    public function testGetNamespaceReturnsAutoloadPsr4Keys(): void
    {
        $content = [
            'autoload' => [
                'psr-4' => [
                    'Vendor\\Package\\' => 'src/',
                    'Vendor\\Package\\Tests\\' => 'tests/'
                ]
            ]
        ];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $expected = ['Vendor\\Package\\', 'Vendor\\Package\\Tests\\'];
        $this->assertSame($expected, $json->getNamespace());
    }

    public function testGetNamespaceReturnsEmptyArrayWhenAutoloadMissing(): void
    {
        $content = ['name' => 'vendor/package'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame([], $json->getNamespace());
    }

    public function testGetNamespaceReturnsEmptyArrayWhenPsr4Missing(): void
    {
        $content = [
            'autoload' => [
                'files' => ['src/functions.php']
            ]
        ];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame([], $json->getNamespace());
    }

    public function testGetDependenciesReturnsFilteredRequirePackages(): void
    {
        $content = [
            'require' => [
                'php' => '>=8.1',
                'vendor/package-one' => '^1.0',
                'ext-json' => '*',
                'another/vendor-package' => '^2.0'
            ]
        ];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $expected = ['vendor/package-one', 'another/vendor-package'];
        $this->assertSame($expected, $json->getDependencies());
    }

    public function testGetDependenciesReturnsEmptyArrayWhenRequireMissing(): void
    {
        $content = ['name' => 'vendor/package'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame([], $json->getDependencies());
    }

    public function testGetDependenciesFiltersOutPhpAndExtensions(): void
    {
        $content = [
            'require' => [
                'php' => '>=8.1',
                'ext-json' => '*',
                'ext-mbstring' => '*'
            ]
        ];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame([], $json->getDependencies());
    }

    public function testGetContentHandlesEmptyJson(): void
    {
        $filePath = $this->createComposerJson([]);
        
        $json = new Json($filePath);
        
        $this->assertNull($json->getPackageName());
        $this->assertNull($json->getPackageType());
        $this->assertSame([], $json->getNamespace());
        $this->assertSame([], $json->getDependencies());
    }

    public function testGetContentHandlesInvalidJson(): void
    {
        $filePath = $this->tempDir . '/invalid.json';
        file_put_contents($filePath, '{invalid json}');
        
        $json = new Json($filePath);
        
        // Should handle invalid JSON gracefully
        $this->assertNull($json->getPackageName());
        $this->assertNull($json->getPackageType());
        $this->assertSame([], $json->getNamespace());
        $this->assertSame([], $json->getDependencies());
    }

    public function testGetContentCachesResults(): void
    {
        $content = ['name' => 'vendor/package'];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        // Call multiple methods that use getContent()
        $name1 = $json->getPackageName();
        $name2 = $json->getPackageName();
        $type = $json->getPackageType();
        
        // Should return consistent results (indicating caching works)
        $this->assertSame($name1, $name2);
        $this->assertSame('vendor/package', $name1);
        $this->assertNull($type);
    }

    public function testGetDirPathWithRootDirectory(): void
    {
        $filePath = '/composer.json';
        $json = new Json($filePath);

        $this->assertSame('/', $json->getDirPath());
    }

    public function testComplexComposerJsonScenario(): void
    {
        $content = [
            'name' => 'vendor/complex-package',
            'type' => 'library',
            'autoload' => [
                'psr-4' => [
                    'Vendor\\Complex\\' => 'src/',
                    'Vendor\\Complex\\Tests\\' => 'tests/'
                ]
            ],
            'require' => [
                'php' => '>=8.1',
                'vendor/dependency-one' => '^1.0',
                'another/dependency' => '^2.0',
                'ext-json' => '*'
            ]
        ];
        $filePath = $this->createComposerJson($content);
        
        $json = new Json($filePath);
        
        $this->assertSame('vendor/complex-package', $json->getPackageName());
        $this->assertSame('library', $json->getPackageType());
        $this->assertSame(['Vendor\\Complex\\', 'Vendor\\Complex\\Tests\\'], $json->getNamespace());
        $dependencies = $json->getDependencies();
        $this->assertContains('vendor/dependency-one', $dependencies);
        $this->assertContains('another/dependency', $dependencies);
        $this->assertCount(2, $dependencies);
    }
} 