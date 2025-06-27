<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Service;

use PHPUnit\Framework\TestCase;
use RunAsRoot\IntegrityChecker\Analysis\Service\Structure;
use RunAsRoot\IntegrityChecker\Analysis\Service\AnalyzerInterface;
use RunAsRoot\IntegrityChecker\Analysis\Data\Structure\Result;
use RunAsRoot\IntegrityChecker\Domain\Package;
use ReflectionClass;
use SplFileInfo;

class StructureTest extends TestCase
{
    private Structure $structureService;
    private array $standardStructure;

    protected function setUp(): void
    {
        $this->standardStructure = [
            'composer.json',
            'registration.php',
            'etc' => [
                'module.xml',
                'di.xml'
            ],
            'Model' => [
                'Entity.php'
            ],
            'README.md'
        ];
        
        $this->structureService = new Structure($this->standardStructure);
    }

    public function testConstructorSetsStandardStructure(): void
    {
        $expectedStructure = ['test.php', 'dir' => ['file.xml']];
        $structure = new Structure($expectedStructure);
        
        $reflection = new ReflectionClass($structure);
        $property = $reflection->getProperty('standardStructure');
        $property->setAccessible(true);
        
        $this->assertEquals($expectedStructure, $property->getValue($structure));
    }

    public function testConstructorWithEmptyStructure(): void
    {
        $structure = new Structure();
        
        $reflection = new ReflectionClass($structure);
        $property = $reflection->getProperty('standardStructure');
        $property->setAccessible(true);
        
        $this->assertEquals([], $property->getValue($structure));
    }

    public function testImplementsAnalyzerInterface(): void
    {
        $this->assertInstanceOf(AnalyzerInterface::class, $this->structureService);
    }

    public function testAnalyseReturnsGenerator(): void
    {
        $packages = [$this->createMockPackage('test/package', [])];
        
        $result = $this->structureService->analyse($packages);
        
        $this->assertInstanceOf(\Generator::class, $result);
    }

    public function testAnalyseWithEmptyPackages(): void
    {
        $packages = [];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertEmpty($resultArray);
    }

    public function testAnalyseWithSinglePackage(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
            $this->createMockFileInfo('/test/package/registration.php', false),
        ];
        
        $packages = [$this->createMockPackage('test/package', $packageFiles)];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertCount(1, $resultArray);
        $this->assertInstanceOf(Result::class, $resultArray[0]);
        $this->assertEquals('test/package', $resultArray[0]->getPackageName());
    }

    public function testAnalyseWithCompleteStructure(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
            $this->createMockFileInfo('/test/package/registration.php', false),
            $this->createMockFileInfo('/test/package/etc/module.xml', false),
            $this->createMockFileInfo('/test/package/etc/di.xml', false),
            $this->createMockFileInfo('/test/package/Model/Entity.php', false),
            $this->createMockFileInfo('/test/package/README.md', false),
        ];
        
        $packages = [$this->createMockPackage('test/package', $packageFiles)];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertCount(1, $resultArray);
        $this->assertFalse($resultArray[0]->hasDefects());
        $this->assertEmpty($resultArray[0]->getDefects());
    }

    public function testAnalyseWithMissingFiles(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
            // Missing registration.php, etc directory, and Model directory
        ];
        
        $packages = [$this->createMockPackage('test/package', $packageFiles)];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertCount(1, $resultArray);
        $this->assertTrue($resultArray[0]->hasDefects());
        
        $defects = $resultArray[0]->getDefects();
        $this->assertContains('registration.php', $defects);
        $this->assertArrayHasKey('etc', $defects);
        $this->assertArrayHasKey('Model', $defects);
        $this->assertContains('README.md', $defects);
    }

    public function testAnalyseWithPartialDirectoryStructure(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
            $this->createMockFileInfo('/test/package/registration.php', false),
            $this->createMockFileInfo('/test/package/etc/module.xml', false),
            // Missing etc/di.xml and Model directory
            $this->createMockFileInfo('/test/package/README.md', false),
        ];
        
        $packages = [$this->createMockPackage('test/package', $packageFiles)];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertCount(1, $resultArray);
        $this->assertTrue($resultArray[0]->hasDefects());
        
        $defects = $resultArray[0]->getDefects();
        $this->assertArrayHasKey('etc', $defects);
        $this->assertContains('di.xml', $defects['etc']);
        $this->assertArrayHasKey('Model', $defects);
    }

    public function testAnalyseWithMultiplePackages(): void
    {
        $package1Files = [
            $this->createMockFileInfo('/package1/composer.json', false),
        ];
        
        $package2Files = [
            $this->createMockFileInfo('/package2/composer.json', false),
            $this->createMockFileInfo('/package2/registration.php', false),
        ];
        
        $packages = [
            $this->createMockPackage('package1', $package1Files),
            $this->createMockPackage('package2', $package2Files),
        ];
        
        $result = $this->structureService->analyse($packages);
        $resultArray = iterator_to_array($result);
        
        $this->assertCount(2, $resultArray);
        $this->assertEquals('package1', $resultArray[0]->getPackageName());
        $this->assertEquals('package2', $resultArray[1]->getPackageName());
    }

    public function testBuildPackageTreeWithEmptyPackage(): void
    {
        $packageFiles = [];
        $package = $this->createMockPackage('test/package', $packageFiles);
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('buildPackageTree');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $package);
        
        $this->assertEquals([], $result);
    }

    public function testBuildPackageTreeWithSingleFile(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
        ];
        $package = $this->createMockPackage('test/package', $packageFiles);
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('buildPackageTree');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $package);
        
        $expected = ['composer.json'];
        $this->assertEquals($expected, $result);
    }

    public function testBuildPackageTreeWithNestedStructure(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/composer.json', false),
            $this->createMockFileInfo('/test/package/src/Model/Entity.php', false),
            $this->createMockFileInfo('/test/package/src/etc/module.xml', false),
        ];
        $package = $this->createMockPackage('test/package', $packageFiles);
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('buildPackageTree');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $package);
        
        $expected = [
            'composer.json',
            'src' => [
                'Model' => ['Entity.php'],
                'etc' => ['module.xml']
            ]
        ];
        $this->assertEquals($expected, $result);
    }

    public function testBuildPackageTreeIgnoresDirectories(): void
    {
        $packageFiles = [
            $this->createMockFileInfo('/test/package/src', true), // Directory - should be ignored
            $this->createMockFileInfo('/test/package/src/Model/Entity.php', false),
        ];
        $package = $this->createMockPackage('test/package', $packageFiles);
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('buildPackageTree');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $package);
        
        $expected = [
            'src' => [
                'Model' => ['Entity.php']
            ]
        ];
        $this->assertEquals($expected, $result);
    }

    public function testCompareTreesWithIdenticalTrees(): void
    {
        $standardTree = ['file.php', 'dir' => ['nested.xml']];
        $packageTree = ['file.php', 'dir' => ['nested.xml']];
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('compareTrees');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $standardTree, $packageTree);
        
        $this->assertEquals([], $result);
    }

    public function testCompareTreesWithMissingFile(): void
    {
        $standardTree = ['file1.php', 'file2.php'];
        $packageTree = ['file1.php'];
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('compareTrees');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $standardTree, $packageTree);
        
        $this->assertEquals(['file2.php'], $result);
    }

    public function testCompareTreesWithMissingDirectory(): void
    {
        $standardTree = ['file.php', 'dir' => ['nested.xml']];
        $packageTree = ['file.php'];
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('compareTrees');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $standardTree, $packageTree);
        
        $expected = ['dir' => ['nested.xml']];
        $this->assertEquals($expected, $result);
    }

    public function testCompareTreesWithPartiallyMissingNestedStructure(): void
    {
        $standardTree = [
            'file.php',
            'dir' => [
                'file1.xml',
                'file2.xml',
                'subdir' => ['nested.php']
            ]
        ];
        
        $packageTree = [
            'file.php',
            'dir' => [
                'file1.xml'
                // Missing file2.xml and subdir
            ]
        ];
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('compareTrees');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $standardTree, $packageTree);
        
        $expected = [
            'dir' => [
                'file2.xml',
                'subdir' => ['nested.php']
            ]
        ];
        $this->assertEquals($expected, $result);
    }

    public function testCompareTreesWithExtraFilesInPackage(): void
    {
        $standardTree = ['file.php'];
        $packageTree = ['file.php', 'extra.php']; // Extra file should be ignored
        
        $reflection = new ReflectionClass($this->structureService);
        $method = $reflection->getMethod('compareTrees');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->structureService, $standardTree, $packageTree);
        
        $this->assertEquals([], $result);
    }

    private function createMockPackage(string $packageName, array $files): Package
    {
        $package = $this->createMock(Package::class);
        $package->method('getPackageName')->willReturn($packageName);
        $package->method('getPackagePath')->willReturn('/test/package');
        $package->method('getPackageFiles')->willReturn($files);
        
        return $package;
    }

    private function createMockFileInfo(string $pathname, bool $isDir): SplFileInfo
    {
        $fileInfo = $this->createMock(SplFileInfo::class);
        $fileInfo->method('getPathname')->willReturn($pathname);
        $fileInfo->method('isDir')->willReturn($isDir);
        
        return $fileInfo;
    }
} 