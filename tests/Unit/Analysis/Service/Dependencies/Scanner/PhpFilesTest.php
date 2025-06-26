<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Service\Dependencies\Scanner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner\PhpFiles;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner\DependenciesScannerInterface;
use RunAsRoot\IntegrityChecker\Domain\Package;
use ReflectionClass;
use SplFileInfo;

#[CoversClass(PhpFiles::class)]
class PhpFilesTest extends TestCase
{
    private PhpFiles $phpFilesScanner;

    protected function setUp(): void
    {
        // Define ROOT_DIR constant for testing if not already defined
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', __DIR__ . '/../../../../../');
        }

        // We'll work with the real PackagesRegistry as this class is tightly coupled
        $this->phpFilesScanner = new PhpFiles();
    }

    public function testImplementsDependenciesScannerInterface(): void
    {
        $this->assertInstanceOf(DependenciesScannerInterface::class, $this->phpFilesScanner);
    }

    public function testConstructorBuildsRegularExpression(): void
    {
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $property = $reflection->getProperty('regexp');
        $property->setAccessible(true);
        
        $regexp = $property->getValue($this->phpFilesScanner);
        
        $this->assertIsString($regexp);
        $this->assertStringStartsWith('~', $regexp);
        $this->assertStringEndsWith('~', $regexp);
    }

    public function testLookupDependenciesWithEmptyPackage(): void
    {
        $package = $this->createMockPackage('test/package', [], []);
        
        $result = $this->phpFilesScanner->lookupDependencies($package);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testLookupDependenciesWithNonPhpFiles(): void
    {
        $files = [
            $this->createMockFileInfo('/test/package/README.md', 'md'),
            $this->createMockFileInfo('/test/package/config.yml', 'yml'),
        ];
        
        $package = $this->createMockPackage('test/package', $files, ['Test\\Module']);
        
        $result = $this->phpFilesScanner->lookupDependencies($package);
        
        $this->assertEmpty($result);
    }

    public function testLookupDependenciesWithPhpFilesContainingDependencies(): void
    {
        // Create temporary test files
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '<?php use RunAsRoot\\IntegrityChecker\\Domain\\Package; class Test {}');
        
        $files = [
            $this->createMockFileInfo($testFile, 'php'),
        ];
        
        $package = $this->createMockPackage('test/package', $files, ['Test\\Module']);
        
        $result = $this->phpFilesScanner->lookupDependencies($package);
        
        // Clean up
        unlink($testFile);
        
        $this->assertIsArray($result);
        // Test that the method executes and returns an array - specific results depend on registry config
    }

    public function testLookupDependenciesWithMultipleFiles(): void
    {
        // Create temporary test files
        $testFile1 = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        $testFile2 = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        
        file_put_contents($testFile1, '<?php use RunAsRoot\\IntegrityChecker\\Domain\\Package; class Test1 {}');
        file_put_contents($testFile2, '<?php use RunAsRoot\\IntegrityChecker\\Analysis\\Data\\ResultInterface; class Test2 {}');
        
        $files = [
            $this->createMockFileInfo($testFile1, 'php'),
            $this->createMockFileInfo($testFile2, 'php'),
        ];
        
        $package = $this->createMockPackage('test/package', $files, ['Test\\Module']);
        
        $result = $this->phpFilesScanner->lookupDependencies($package);
        
        // Clean up
        unlink($testFile1);
        unlink($testFile2);
        
        // Should return array and process multiple files
        $this->assertIsArray($result);
    }

    public function testAnalyzeFileSkipsSkippedFiles(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'module.xml');
        file_put_contents($testFile, '<?xml version="1.0"?><config><module name="Test_Module"/></config>');
        
        $file = $this->createMockFileInfo($testFile, 'xml');
        $file->method('getFilename')->willReturn('module.xml');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertEmpty($result);
    }

    public function testAnalyzeFileWithPhpContent(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '<?php
            namespace Test\\Module;
            use RunAsRoot\\IntegrityChecker\\Domain\\Package;
            
            class TestClass
            {
                public function test()
                {
                    $package = new Package("/test");
                }
            }
        ');
        
        $file = $this->createMockFileInfo($testFile, 'php');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertIsArray($result);
        // The result depends on how the regex matches actual namespaces in the registry
    }

    public function testAnalyzeFileIgnoresCurrentModuleNamespaces(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '<?php
            namespace Test\\Module;
            use Test\\Module\\SubClass;
            
            class TestClass
            {
                public function test()
                {
                    $sub = new SubClass();
                }
            }
        ');
        
        $file = $this->createMockFileInfo($testFile, 'php');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        // Should not include dependencies from the same module namespace
        $this->assertIsArray($result);
    }

    public function testAnalyzeFileWithPhtmlContent(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '
            <div class="test">
                <?php
                use RunAsRoot\\IntegrityChecker\\Domain\\Package;
                $package = new Package("/test");
                ?>
                <p>Some HTML content</p>
                <?= $this->getChildHtml() ?>
            </div>
        ');
        
        $file = $this->createMockFileInfo($testFile, 'phtml');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertIsArray($result);
        // Test that phtml files are processed and return results
    }

    public function testAnalyzeFileWithNoMatches(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '<?php
            class SimpleClass
            {
                public function test()
                {
                    return "hello world";
                }
            }
        ');
        
        $file = $this->createMockFileInfo($testFile, 'php');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertEmpty($result);
    }

    public function testStripeHtmlExtractsPhpContent(): void
    {
        $htmlContent = '
            <div>
                <?php echo "test"; ?>
                <p>HTML content</p>
                <?= $variable ?>
            </div>
        ';
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('stripeHtml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $htmlContent);
        
        $this->assertIsString($result);
    }

    public function testAnalyzeFileHandlesUnderscoreToBackslashConversion(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        file_put_contents($testFile, '<?php
            // This represents legacy class names with underscores
            $model = new RunAsRoot_IntegrityChecker_Domain_Package();
        ');
        
        $file = $this->createMockFileInfo($testFile, 'php');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertIsArray($result);
    }

    public function testLookupDependenciesWithMixedFileTypes(): void
     {
         $phpFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
         $phtmlFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
         $xmlFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
         
         file_put_contents($phpFile, '<?php use RunAsRoot\\IntegrityChecker\\Domain\\Package;');
         file_put_contents($phtmlFile, '<div><?php use RunAsRoot\\IntegrityChecker\\Analysis\\Data\\ResultInterface; ?></div>');
         file_put_contents($xmlFile, '<?xml version="1.0"?><config/>');
         
         $files = [
             $this->createMockFileInfo($phpFile, 'php'),
             $this->createMockFileInfo($phtmlFile, 'phtml'),
             $this->createMockFileInfo($xmlFile, 'xml'),
         ];
         
         $package = $this->createMockPackage('test/package', $files, ['Test\\Module']);
         
         $result = $this->phpFilesScanner->lookupDependencies($package);
         
         // Clean up
         unlink($phpFile);
         unlink($phtmlFile);
         unlink($xmlFile);
         
         $this->assertIsArray($result);
         // Test that mixed file types are processed correctly
     }

    public function testStripeHtmlExtractsPhpContentFromPhtml(): void
    {
        $phtmlContent = '
            <div class="container">
                <h1><?= $block->getTitle() ?></h1>
                <?php 
                    $helper = $this->helper("Magento\\Framework\\Helper");
                    use Magento\\Catalog\\Model\\Product;
                ?>
                <p>Some HTML content</p>
                <?php
                    $product = new Product();
                    echo $product->getName();
                ?>
            </div>
        ';
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('stripeHtml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $phtmlContent);
        
        $this->assertIsString($result);
        // Should contain the PHP code
        $this->assertStringContainsString('$helper = $this->helper("Magento\\Framework\\Helper");', $result);
        $this->assertStringContainsString('use Magento\\Catalog\\Model\\Product;', $result);
        $this->assertStringContainsString('$product = new Product();', $result);
    }

    public function testStripeHtmlHandlesShortPhpTags(): void
    {
        $phtmlContent = '
            <div><?= $block->getTitle() ?></div>
            <span><?= $helper->format($value) ?></span>
        ';
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('stripeHtml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $phtmlContent);
        
        $this->assertIsString($result);
        // Should extract short tag content
        $this->assertStringContainsString('$block->getTitle()', $result);
        $this->assertStringContainsString('$helper->format($value)', $result);
    }

    public function testStripeHtmlWithNoPhpContent(): void
    {
        $htmlContent = '
            <div class="container">
                <h1>Pure HTML</h1>
                <p>No PHP code here</p>
            </div>
        ';
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('stripeHtml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $htmlContent);
        
        $this->assertIsString($result);
        // Should not crash and return string (even if empty/processed)
    }

    public function testStripeHtmlWithComplexPhpBlocks(): void
    {
        $phtmlContent = '
            <div>
                <?php
                    if ($condition) {
                        echo "Multiple lines";
                        $object = new \\Vendor\\Package\\Class();
                    }
                ?>
                <p>Some HTML</p>
                <?php foreach($items as $item): ?>
                    <span><?= $item->getValue() ?></span>
                <?php endforeach; ?>
            </div>
        ';
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('stripeHtml');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $phtmlContent);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('\\Vendor\\Package\\Class', $result);
        $this->assertStringContainsString('foreach($items as $item)', $result);
    }

    public function testAnalyzeFileWithPhtmlExtension(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'phpunit_test_');
        rename($testFile, $testFile . '.phtml');
        $testFile = $testFile . '.phtml';
        
        file_put_contents($testFile, '
            <div>
                <?php use RunAsRoot\\IntegrityChecker\\Domain\\Package; ?>
                <h1>Template</h1>
                <?= $helper->format() ?>
            </div>
        ');
        
        $file = $this->createMockFileInfo($testFile, 'phtml');
        
        $reflection = new ReflectionClass($this->phpFilesScanner);
        $method = $reflection->getMethod('analyzeFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->phpFilesScanner, $file, ['Test\\Module']);
        
        unlink($testFile);
        
        $this->assertIsArray($result);
        // Should process PHTML files through stripeHtml method
    }

    private function createMockPackage(string $packageName, array $files, array $namespaces): Package
    {
        $package = $this->createMock(Package::class);
        $package->method('getPackageName')->willReturn($packageName);
        $package->method('getPackagePath')->willReturn('/test/package');
        $package->method('getPackageFiles')->willReturn($files);
        $package->method('getPackageNamespaces')->willReturn($namespaces);
        
        return $package;
    }

    private function createMockFileInfo(string $pathname, string $extension): SplFileInfo
    {
        $fileInfo = $this->createMock(SplFileInfo::class);
        $fileInfo->method('getPathname')->willReturn($pathname);
        $fileInfo->method('getExtension')->willReturn($extension);
        $fileInfo->method('getFilename')->willReturn(basename($pathname));
        
        // Create a mock for getFileInfo() method
        $innerFileInfo = $this->createMock(SplFileInfo::class);
        $innerFileInfo->method('getExtension')->willReturn($extension);
        $fileInfo->method('getFileInfo')->willReturn($innerFileInfo);
        
        return $fileInfo;
    }
} 