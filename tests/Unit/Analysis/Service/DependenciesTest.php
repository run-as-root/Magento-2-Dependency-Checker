<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies;
use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner\DependenciesScannerInterface;
use RunAsRoot\IntegrityChecker\Domain\PackagesRegistry;
use RunAsRoot\IntegrityChecker\Domain\Package;
use RunAsRoot\IntegrityChecker\Exception\FileNotFoundException;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(Dependencies::class)]
class DependenciesTest extends TestCase
{
    private Dependencies $dependencies;
    private PackagesRegistry $mockPackagesRegistry;

    protected function setUp(): void
    {
        // Mock PackagesRegistry singleton
        $this->mockPackagesRegistry = $this->createMock(PackagesRegistry::class);
        $this->setPackagesRegistrySingleton($this->mockPackagesRegistry);
        
        $this->dependencies = new Dependencies();
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $this->resetPackagesRegistrySingleton();
    }

    public function testAnalyseReturnsgeneratorWithResults(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageName')->willReturn('test/package');
        $mockPackage->method('getPackageType')->willReturn('library');
        $mockPackage->method('getComposerDependencies')->willReturn(['vendor/dep1']);
        
        $packages = [$mockPackage];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')->willReturn('vendor/dep1');
        $this->mockPackagesRegistry->method('getPackageType')->willReturn('library');

        $results = $this->dependencies->analyse($packages);
        
        $this->assertInstanceOf(\Generator::class, $results);
        
        $resultArray = iterator_to_array($results);
        $this->assertCount(1, $resultArray);
        $this->assertInstanceOf(Result::class, $resultArray[0]);
        $this->assertEquals('test/package', $resultArray[0]->getPackageName());
    }

    public function testAnalyseWithMultiplePackages(): void
    {
        $mockPackage1 = $this->createMock(Package::class);
        $mockPackage1->method('getPackageName')->willReturn('test/package1');
        $mockPackage1->method('getPackageType')->willReturn('library');
        $mockPackage1->method('getComposerDependencies')->willReturn([]);
        
        $mockPackage2 = $this->createMock(Package::class);
        $mockPackage2->method('getPackageName')->willReturn('test/package2');
        $mockPackage2->method('getPackageType')->willReturn('library');
        $mockPackage2->method('getComposerDependencies')->willReturn([]);
        
        $packages = [$mockPackage1, $mockPackage2];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')->willReturn(null);
        $this->mockPackagesRegistry->method('getPackageType')->willReturn('library');

        $results = $this->dependencies->analyse($packages);
        $resultArray = iterator_to_array($results);
        
        $this->assertCount(2, $resultArray);
        $this->assertEquals('test/package1', $resultArray[0]->getPackageName());
        $this->assertEquals('test/package2', $resultArray[1]->getPackageName());
    }

    public function testAnalyseWithEmptyPackages(): void
    {
        $packages = [];
        
        $results = $this->dependencies->analyse($packages);
        $resultArray = iterator_to_array($results);
        
        $this->assertEmpty($resultArray);
    }

    public function testAnalyseWithMagentoModule(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageName')->willReturn('vendor/magento-module');
        $mockPackage->method('getPackageType')->willReturn('magento2-module');
        $mockPackage->method('getComposerDependencies')->willReturn(['magento/framework']);
        $mockPackage->method('getModuleXmlDependencies')->willReturn(['Magento_Framework']);
        
        $packages = [$mockPackage];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')
            ->willReturnMap([
                ['Magento\\Framework', 'magento/framework'],
                ['Other\\Namespace', 'other/package']
            ]);
        
        $this->mockPackagesRegistry->method('getPackageType')
            ->willReturnMap([
                ['magento/framework', 'magento2-module'],
                ['other/package', 'library']
            ]);

        $results = $this->dependencies->analyse($packages);
        $resultArray = iterator_to_array($results);
        
        $this->assertCount(1, $resultArray);
        $this->assertInstanceOf(Result::class, $resultArray[0]);
        $this->assertEquals('vendor/magento-module', $resultArray[0]->getPackageName());
    }

    public function testAnalyseHandlesFileNotFoundException(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageName')->willReturn('test/package');
        $mockPackage->method('getPackageType')->willReturn('magento2-module');
        $mockPackage->method('getComposerDependencies')
            ->willThrowException(new FileNotFoundException('composer.json', '/path'));
        $mockPackage->method('getModuleXmlDependencies')
            ->willThrowException(new FileNotFoundException('module.xml', '/path'));
        
        $packages = [$mockPackage];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')->willReturn(null);
        $this->mockPackagesRegistry->method('getPackageType')->willReturn('library');

        $results = $this->dependencies->analyse($packages);
        $resultArray = iterator_to_array($results);
        
        $this->assertCount(1, $resultArray);
        $this->assertInstanceOf(Result::class, $resultArray[0]);
        $this->assertEquals('test/package', $resultArray[0]->getPackageName());
    }

    public function testAnalyseUsesCustomDependencyScanner(): void
    {
        $mockScanner = $this->createMock(DependenciesScannerInterface::class);
        $mockScanner->expects($this->once())
            ->method('lookupDependencies')
            ->willReturn(['Custom\\Namespace']);
        
        // Inject custom scanner
        $this->setDependenciesScanner([$mockScanner]);
        
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageName')->willReturn('test/package');
        $mockPackage->method('getPackageType')->willReturn('library');
        $mockPackage->method('getComposerDependencies')->willReturn([]);
        
        $packages = [$mockPackage];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')->willReturn('custom/package');
        $this->mockPackagesRegistry->method('getPackageType')->willReturn('library');

        $results = $this->dependencies->analyse($packages);
        $resultArray = iterator_to_array($results);
        
        $this->assertCount(1, $resultArray);
    }

    public function testCompareDependenciesReturnsResult(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageName')->willReturn('test/package');
        $mockPackage->method('getPackageType')->willReturn('library');
        $mockPackage->method('getComposerDependencies')->willReturn([]);
        
        $dependencies = ['Test\\Namespace'];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')->willReturn('test/dep');
        $this->mockPackagesRegistry->method('getPackageType')->willReturn('library');

        $result = $this->callPrivateMethod('compareDependencies', [$mockPackage, $dependencies]);
        
        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals('test/package', $result->getPackageName());
    }

    public function testCompareModuleXmlDependenciesWithNonMagentoModule(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageType')->willReturn('library');
        
        $dependencies = ['Any\\Namespace'];
        
        $result = $this->callPrivateMethod('compareModuleXmlDependencies', [$mockPackage, $dependencies]);
        
        $this->assertEquals([], $result);
    }

    public function testCompareModuleXmlDependenciesWithMagentoModule(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getPackageType')->willReturn('magento2-module');
        $mockPackage->method('getModuleXmlDependencies')->willReturn(['Magento_Framework']);
        
        $dependencies = ['Magento\\Framework', 'Magento\\Backend'];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')
            ->willReturnMap([
                ['Magento\\Framework', 'magento/framework'],
                ['Magento\\Backend', 'magento/backend']
            ]);
        
        $this->mockPackagesRegistry->method('getPackageType')
            ->willReturn('magento2-module');

        $result = $this->callPrivateMethod('compareModuleXmlDependencies', [$mockPackage, $dependencies]);
        
        // Should return missing dependencies (Backend is used but not declared)
        $this->assertContains('Magento\\Backend', $result);
        $this->assertNotContains('Magento\\Framework', $result);
    }

    public function testCompareComposerDependencies(): void
    {
        $mockPackage = $this->createMock(Package::class);
        $mockPackage->method('getComposerDependencies')->willReturn(['vendor/declared']);
        
        $dependencies = ['Vendor\\Used', 'Vendor\\AlsoUsed'];
        
        $this->mockPackagesRegistry->method('getPackageNameByNamespace')
            ->willReturnMap([
                ['Vendor\\Used', 'vendor/used'],
                ['Vendor\\AlsoUsed', 'vendor/also-used']
            ]);

        $result = $this->callPrivateMethod('compareComposerDependencies', [$mockPackage, $dependencies]);
        
        // Should return packages that are used but not declared
        $this->assertContains('vendor/used', $result);
        $this->assertContains('vendor/also-used', $result);
    }

    /**
     * Helper method to call private methods for testing
     */
    private function callPrivateMethod(string $methodName, array $args)
    {
        $reflection = new ReflectionClass($this->dependencies);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->dependencies, $args);
    }

    /**
     * Helper method to set PackagesRegistry singleton for testing
     */
    private function setPackagesRegistrySingleton(PackagesRegistry $registry): void
    {
        $reflection = new ReflectionClass(PackagesRegistry::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, $registry);
    }

    /**
     * Helper method to reset PackagesRegistry singleton
     */
    private function resetPackagesRegistrySingleton(): void
    {
        $reflection = new ReflectionClass(PackagesRegistry::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    /**
     * Helper method to set custom dependency scanners
     */
    private function setDependenciesScanner(array $scanners): void
    {
        $reflection = new ReflectionClass($this->dependencies);
        $property = $reflection->getProperty('dependenciesScanner');
        $property->setAccessible(true);
        $property->setValue($this->dependencies, $scanners);
    }
} 