<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Application\Structure;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Application\Structure\Console;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;
use RunAsRoot\IntegrityChecker\Application\Registry\DefectsState;

#[CoversClass(Console::class)]
class ConsoleTest extends TestCase
{
    private Console $console;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Define ROOT_DIR constant if not already set
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', '/test/root/dir/');
        }
        
        $this->console = new Console();
    }

    public function testImplementsConsoleInterface(): void
    {
        $this->assertInstanceOf(\RunAsRoot\IntegrityChecker\Application\ConsoleInterface::class, $this->console);
    }

    public function testPrintOutputWithNoDefectsProducesNoOutput(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(false);

        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function testPrintOutputWithDefectsDisplaysPackageNameAndStructure(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('test-package');
        $result->method('getDefects')->willReturn([
            'folder1' => 'file1.php',
            'folder2' => [
                'subfolder' => 'file2.php'
            ]
        ]);

        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();

        $this->assertStringContainsString('Package "test-package" has incorrect structure', $output);
        $this->assertStringContainsString('Missed folders/files:', $output);
        $this->assertStringContainsString('- file1.php', $output);
        $this->assertStringContainsString('- folder2', $output);
        $this->assertStringContainsString('- file2.php', $output);
    }

    public function testPrintOutputWithNestedDefectsShowsCorrectIndentation(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('nested-package');
        $result->method('getDefects')->willReturn([
            'level1' => [
                'level2' => [
                    'level3' => 'deep-file.php'
                ]
            ]
        ]);

        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();

        // Check for correct indentation levels (tabs)
        $this->assertStringContainsString("\t- level1", $output);
        $this->assertStringContainsString("\t\t- level2", $output);
        $this->assertStringContainsString("\t\t\t- deep-file.php", $output);
    }

    public function testPrintOutputWithMixedDefectsStructure(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('mixed-package');
        $result->method('getDefects')->willReturn([
            'simple-file.php',
            'folder' => [
                'nested-file.php',
                'subfolder' => 'deep-file.php'
            ]
        ]);

        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();

        $this->assertStringContainsString('- simple-file.php', $output);
        $this->assertStringContainsString('- folder', $output);
        $this->assertStringContainsString('- nested-file.php', $output);
        $this->assertStringContainsString('- deep-file.php', $output);
    }

    public function testGetStatusCodeReturnsZeroWhenNoDefects(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(false);

        $this->console->printOutput($result);
        
        $this->assertEquals(0, $this->console->getStatusCode());
    }

    public function testGetStatusCodeReturnsOneWhenDefectsExist(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('defective-package');
        $result->method('getDefects')->willReturn(['file.php']);

        ob_start();
        $this->console->printOutput($result);
        ob_get_clean();
        
        $this->assertEquals(1, $this->console->getStatusCode());
    }

    public function testGetStatusCodeAccumulatesDefectsFromMultipleResults(): void
    {
        $result1 = $this->createMock(ResultInterface::class);
        $result1->method('hasDefects')->willReturn(false);

        $result2 = $this->createMock(ResultInterface::class);
        $result2->method('hasDefects')->willReturn(true);
        $result2->method('getPackageName')->willReturn('defective-package');
        $result2->method('getDefects')->willReturn(['file.php']);

        ob_start();
        $this->console->printOutput($result1);
        $this->console->printOutput($result2);
        ob_get_clean();
        
        $this->assertEquals(1, $this->console->getStatusCode());
    }

    public function testValidateParametersFailsWithInsufficientArguments(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;

        $_SERVER['argc'] = 1;
        $_SERVER['argv'] = ['script.php'];

        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Expected first parameter as Magento 2 Root Directory', $output);

        // Restore original values
        if ($originalArgc !== null) {
            $_SERVER['argc'] = $originalArgc;
        } else {
            unset($_SERVER['argc']);
        }
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testValidateParametersFailsWithoutComposerLock(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;

        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['script.php', '/nonexistent/path'];

        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('"composer.lock" file was not found', $output);

        // Restore original values
        if ($originalArgc !== null) {
            $_SERVER['argc'] = $originalArgc;
        } else {
            unset($_SERVER['argc']);
        }
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testValidateParametersFailsWithInvalidDirectories(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;

        // Create a temporary composer.lock file
        $tempDir = sys_get_temp_dir() . '/test_magento_' . uniqid();
        mkdir($tempDir, 0777, true);
        touch($tempDir . '/composer.lock');

        $_SERVER['argc'] = 4;
        $_SERVER['argv'] = ['script.php', $tempDir, 'nonexistent1', 'nonexistent2'];

        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Can not find directory', $output);
        $this->assertStringContainsString('nonexistent1', $output);
        $this->assertStringContainsString('nonexistent2', $output);

        // Cleanup
        unlink($tempDir . '/composer.lock');
        rmdir($tempDir);

        // Restore original values
        if ($originalArgc !== null) {
            $_SERVER['argc'] = $originalArgc;
        } else {
            unset($_SERVER['argc']);
        }
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testValidateParametersSucceedsWithValidSetup(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;

        // Create a temporary composer.lock file and directories
        $tempDir = sys_get_temp_dir() . '/test_magento_' . uniqid();
        mkdir($tempDir, 0777, true);
        touch($tempDir . '/composer.lock');

        // Create test directories that ROOT_DIR + relative path should find
        $testDir1 = ROOT_DIR . 'app';
        $testDir2 = ROOT_DIR . 'src';
        
        // Since we can't easily create these directories in ROOT_DIR, we'll mock a scenario
        // where argc = 2 (just script and magento root, no additional dirs to check)
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['script.php', $tempDir];

        $result = $this->console->validateParameters();

        $this->assertTrue($result);

        // Cleanup
        unlink($tempDir . '/composer.lock');
        rmdir($tempDir);

        // Restore original values
        if ($originalArgc !== null) {
            $_SERVER['argc'] = $originalArgc;
        } else {
            unset($_SERVER['argc']);
        }
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintHelpDisplaysCorrectUsageInformation(): void
    {
        ob_start();
        $this->console->printHelp();
        $output = ob_get_clean();

        $this->assertStringContainsString('Help', $output);
        $this->assertStringContainsString('Tool to check if modules are follow to standard module structure', $output);
        $this->assertStringContainsString('php bin/structure [Magento2 root] {folder1} {folder2}', $output);
        $this->assertStringContainsString('[Magento2 root] - path to Magento 2 project root directory', $output);
        $this->assertStringContainsString('{folder1} {folder2} - list of relative folders to scan', $output);
        $this->assertStringContainsString('If not provided, scan will be run for "src" and "app"', $output);
    }

    public function testPrintHelpUsesColorFormatting(): void
    {
        ob_start();
        $this->console->printHelp();
        $output = ob_get_clean();

        // Check for ANSI color codes
        $this->assertStringContainsString("\e[32m", $output); // Green for "Help"
        $this->assertStringContainsString("\e[30m", $output); // Reset color
    }

    public function testConsoleInstanceCreatesDefectsStateInternally(): void
    {
        // Test that the console properly initializes its internal state
        $this->assertEquals(0, $this->console->getStatusCode());
        
        // After processing a result with defects, status should change
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('test');
        $result->method('getDefects')->willReturn(['file.php']);

        ob_start();
        $this->console->printOutput($result);
        ob_get_clean();

        $this->assertEquals(1, $this->console->getStatusCode());
    }

    public function testPrintOutputFormatsEmptyDefectsArray(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);
        $result->method('getPackageName')->willReturn('empty-defects-package');
        $result->method('getDefects')->willReturn([]);

        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();

        $this->assertStringContainsString('Package "empty-defects-package" has incorrect structure', $output);
        $this->assertStringContainsString('Missed folders/files:', $output);
        // Should not crash with empty defects array
    }
} 