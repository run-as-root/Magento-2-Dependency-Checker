<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Domain\Package;
use RunAsRoot\IntegrityChecker\Domain\Package\Composer\Json;
use RunAsRoot\IntegrityChecker\Domain\Package\Config\ModuleXml;
use RunAsRoot\IntegrityChecker\Exception\FileNotFoundException;
use ReflectionClass;
use SplFileInfo;

#[CoversClass(Package::class)]
class PackageTest extends TestCase
{
    private string $testPackagePath;
    private Package $package;

    protected function setUp(): void
    {
        $this->testPackagePath = '/test/package/path';
        $this->package = new Package($this->testPackagePath);
    }

    public function testConstructorSetsPackagePath(): void
    {
        $expectedPath = '/some/test/path';
        $package = new Package($expectedPath);
        
        $this->assertEquals($expectedPath, $package->getPackagePath());
    }

    public function testGetPackagePathReturnsSetPath(): void
    {
        $this->assertEquals($this->testPackagePath, $this->package->getPackagePath());
    }

    public function testGetPackageTypeReturnsComposerJsonType(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getPackageType')
                 ->willReturn('magento2-module');

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getPackageType();
        
        $this->assertEquals('magento2-module', $result);
    }

    public function testGetPackageTypeReturnsUnknownWhenComposerJsonNotFound(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getPackageType')
                 ->willThrowException(new FileNotFoundException('composer.json', $this->testPackagePath));

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getPackageType();
        
        $this->assertEquals('unknown', $result);
    }

    public function testGetComposerDependenciesReturnsDependencies(): void
    {
        $expectedDependencies = ['symfony/console' => '^5.0', 'psr/log' => '^1.0'];
        
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getDependencies')
                 ->willReturn($expectedDependencies);

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getComposerDependencies();
        
        $this->assertEquals($expectedDependencies, $result);
    }

    public function testGetComposerDependenciesThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);
        
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getDependencies')
                 ->willThrowException(new FileNotFoundException('composer.json', $this->testPackagePath));

        $this->setPrivateProperty('composerJson', $mockJson);

        $this->package->getComposerDependencies();
    }

    public function testGetModuleXmlDependenciesReturnsDependencies(): void
    {
        $expectedDependencies = ['Magento_Framework', 'Magento_Backend'];
        
        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->once())
                      ->method('getDependencies')
                      ->willReturn($expectedDependencies);

        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $result = $this->package->getModuleXmlDependencies();
        
        $this->assertEquals($expectedDependencies, $result);
    }

    public function testGetModuleXmlDependenciesThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);
        
        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->once())
                      ->method('getDependencies')
                      ->willThrowException(new FileNotFoundException('module.xml', $this->testPackagePath));

        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $this->package->getModuleXmlDependencies();
    }

    public function testGetPackageFilesReturnsFileArray(): void
    {
        // Mock file info objects
        $mockFile1 = $this->createMock(SplFileInfo::class);
        $mockFile2 = $this->createMock(SplFileInfo::class);
        $expectedFiles = [$mockFile1, $mockFile2];

        $this->setPrivateProperty('packageFiles', $expectedFiles);

        $result = $this->package->getPackageFiles();
        
        $this->assertEquals($expectedFiles, $result);
    }

    public function testGetPackageNamespacesReturnsComposerJsonNamespaces(): void
    {
        $composerNamespaces = ['MyVendor\\MyModule\\', 'MyVendor\\Common\\'];
        $expectedNamespaces = ['MyVendor\\MyModule', 'MyVendor\\Common'];
        
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getNamespace')
                 ->willReturn($composerNamespaces);

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getPackageNamespaces();
        
        $this->assertEquals($expectedNamespaces, $result);
    }

    public function testGetPackageNamespacesReturnsModuleXmlNamespaceWhenComposerEmpty(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getNamespace')
                 ->willReturn([]);

        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->exactly(2))
                      ->method('getModuleName')
                      ->willReturn('Vendor_Module');

        $this->setPrivateProperty('composerJson', $mockJson);
        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $result = $this->package->getPackageNamespaces();
        
        $this->assertEquals(['Vendor\\Module'], $result);
    }

    public function testGetPackageNamespacesReturnsEmptyWhenBothSourcesFail(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getNamespace')
                 ->willThrowException(new FileNotFoundException('composer.json', $this->testPackagePath));

        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->once())
                      ->method('getModuleName')
                      ->willThrowException(new FileNotFoundException('module.xml', $this->testPackagePath));

        $this->setPrivateProperty('composerJson', $mockJson);
        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $result = $this->package->getPackageNamespaces();
        
        $this->assertEquals([], $result);
    }

    public function testGetPackageNameReturnsComposerPackageName(): void
    {
        $expectedName = 'vendor/package-name';
        
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getPackageName')
                 ->willReturn($expectedName);

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getPackageName();
        
        $this->assertEquals($expectedName, $result);
    }

    public function testGetPackageNameReturnsPathWhenComposerJsonNotFound(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getPackageName')
                 ->willThrowException(new FileNotFoundException('composer.json', $this->testPackagePath));

        $this->setPrivateProperty('composerJson', $mockJson);

        $result = $this->package->getPackageName();
        
        $this->assertEquals($this->testPackagePath, $result);
    }

    public function testGetPackageNamespacesHandlesNullModuleName(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getNamespace')
                 ->willReturn([]);

        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->once())
                      ->method('getModuleName')
                      ->willReturn(null);

        $this->setPrivateProperty('composerJson', $mockJson);
        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $result = $this->package->getPackageNamespaces();
        
        $this->assertEquals([], $result);
    }

    public function testGetPackageFilesListCreatesRecursiveIterator(): void
    {
        // Test that getPackageFilesList properly caches files
        $tempDir = sys_get_temp_dir() . '/phpunit_package_test_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/subdir');
        file_put_contents($tempDir . '/file1.php', '<?php class Test1 {}');
        file_put_contents($tempDir . '/subdir/file2.php', '<?php class Test2 {}');
        
        $package = new Package($tempDir);
        
        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getPackageFilesList');
        $method->setAccessible(true);
        
        $files1 = $method->invoke($package);
        $files2 = $method->invoke($package); // Should use cached version
        
        $this->assertIsArray($files1);
        $this->assertIsArray($files2);
        $this->assertSame($files1, $files2); // Should be same cached array
        
        // Verify we have the expected files
        $filenames = array_map(fn($file) => $file->getFilename(), $files1);
        $this->assertContains('file1.php', $filenames);
        $this->assertContains('file2.php', $filenames);
        
        // Clean up
        unlink($tempDir . '/file1.php');
        unlink($tempDir . '/subdir/file2.php');
        rmdir($tempDir . '/subdir');
        rmdir($tempDir);
    }

    public function testGetComposerJsonSearchesPackageFiles(): void
    {
        // Create temporary directory with composer.json
        $tempDir = sys_get_temp_dir() . '/phpunit_package_test_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/composer.json', '{"name": "test/package", "type": "library"}');
        
        $package = new Package($tempDir);
        
        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getComposerJson');
        $method->setAccessible(true);
        
        $result = $method->invoke($package);
        
        $this->assertInstanceOf(Json::class, $result);
        
        // Test caching
        $result2 = $method->invoke($package);
        $this->assertSame($result, $result2);
        
        // Clean up
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function testGetComposerJsonThrowsExceptionWhenNotFound(): void
    {
        // Create empty temporary directory
        $tempDir = sys_get_temp_dir() . '/phpunit_package_test_' . uniqid();
        mkdir($tempDir);
        
        $package = new Package($tempDir);
        
        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getComposerJson');
        $method->setAccessible(true);
        
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('composer.json');
        
        $method->invoke($package);
        
        // Clean up
        rmdir($tempDir);
    }

    public function testGetModuleXmlSearchesPackageFiles(): void
    {
        // Create temporary directory with module.xml
        $tempDir = sys_get_temp_dir() . '/phpunit_package_test_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/module.xml', '<?xml version="1.0"?><config><module name="Test_Module"/></config>');
        
        $package = new Package($tempDir);
        
        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getModuleXml');
        $method->setAccessible(true);
        
        $result = $method->invoke($package);
        
        $this->assertInstanceOf(ModuleXml::class, $result);
        
        // Test caching
        $result2 = $method->invoke($package);
        $this->assertSame($result, $result2);
        
        // Clean up
        unlink($tempDir . '/module.xml');
        rmdir($tempDir);
    }

    public function testGetModuleXmlThrowsExceptionWhenNotFound(): void
    {
        // Create empty temporary directory
        $tempDir = sys_get_temp_dir() . '/phpunit_package_test_' . uniqid();
        mkdir($tempDir);
        
        $package = new Package($tempDir);
        
        $reflection = new ReflectionClass($package);
        $method = $reflection->getMethod('getModuleXml');
        $method->setAccessible(true);
        
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('module.xml');
        
        $method->invoke($package);
        
        // Clean up
        rmdir($tempDir);
    }

    public function testResolveNamespacesFromComposerJsonPrivateMethod(): void
    {
        $mockJson = $this->createMock(Json::class);
        $mockJson->expects($this->once())
                 ->method('getNamespace')
                 ->willReturn(['Test\\Module\\', 'Test\\Common\\']);

        $this->setPrivateProperty('composerJson', $mockJson);

        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('resolveNamespacesFromComposerJson');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->package);
        
        $this->assertEquals(['Test\\Module', 'Test\\Common'], $result);
    }

    public function testResolveNamespaceFromModuleXmlPrivateMethod(): void
    {
        $mockModuleXml = $this->createMock(ModuleXml::class);
        $mockModuleXml->expects($this->once())
                      ->method('getModuleName')
                      ->willReturn('Vendor_Module');

        $this->setPrivateProperty('moduleXml', $mockModuleXml);

        $reflection = new ReflectionClass($this->package);
        $method = $reflection->getMethod('resolveNamespaceFromModuleXml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->package);
        
        $this->assertEquals('Vendor\\Module', $result);
    }

    /**
     * Helper method to set private properties for testing
     */
    private function setPrivateProperty(string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($this->package);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($this->package, $value);
    }
} 