<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Application\Dependencies;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Application\Dependencies\Console;
use RunAsRoot\IntegrityChecker\Application\ConsoleInterface;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;
use RunAsRoot\IntegrityChecker\Application\Registry\DefectsState;
use ReflectionClass;
use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result;
use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result as DependencyResult;
use RunAsRoot\IntegrityChecker\Analysis\Data\Structure\Result as StructureResult;

#[CoversClass(Console::class)]
class ConsoleTest extends TestCase
{
    private Console $console;

    protected function setUp(): void
    {
        // Define ROOT_DIR constant for testing if not already defined
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', __DIR__ . '/../../../../');
        }

        // Reset $_SERVER['argv'] to default state for each test
        $_SERVER['argv'] = ['dependencies', '/path/to/magento'];

        $this->console = new Console();
    }

    public function testImplementsConsoleInterface(): void
    {
        $this->assertInstanceOf(ConsoleInterface::class, $this->console);
    }

    public function testConstructorCreatesDefectsState(): void
    {
        $reflection = new ReflectionClass($this->console);
        $property = $reflection->getProperty('defectsState');
        $property->setAccessible(true);
        
        $defectsState = $property->getValue($this->console);
        
        $this->assertInstanceOf(DefectsState::class, $defectsState);
    }

    public function testPrintOutputWithNoDefects(): void
    {
        $result = $this->createMockResult('test/package', false, []);
        
        // Capture output
        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();
        
        // Should not output anything for packages without defects
        $this->assertEmpty($output);
    }

    public function testPrintOutputWithComposerDefects(): void
    {
        $defects = [
            'composer' => ['vendor/package1', 'vendor/package2']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        // Capture output
        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('Missed dependencies in composer.json', $output);
        $this->assertStringContainsString('"vendor/package1": "*"', $output);
        $this->assertStringContainsString('"vendor/package2": "*"', $output);
    }

    public function testPrintOutputWithModuleXmlDefects(): void
    {
        $defects = [
            'module' => ['Vendor\\Package1', 'Vendor\\Package2']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        // Capture output
        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('Missed dependencies in etc/module.xml', $output);
        $this->assertStringContainsString('- Vendor_Package1', $output);
        $this->assertStringContainsString('- Vendor_Package2', $output);
    }

    public function testPrintOutputWithBothTypes(): void
    {
        $defects = [
            'composer' => ['vendor/package1'],
            'module' => ['Vendor\\Package1']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        // Capture output
        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('Missed dependencies in composer.json', $output);
        $this->assertStringContainsString('Missed dependencies in etc/module.xml', $output);
        $this->assertStringContainsString('"vendor/package1": "*"', $output);
        $this->assertStringContainsString('- Vendor_Package1', $output);
    }

    public function testPrintModuleXmlMissedDependenciesConvertsBackslashesToUnderscores(): void
    {
        $defects = [
            'module' => ['Vendor\\Module\\SubModule']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $this->console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('- Vendor_Module_SubModule', $output);
        $this->assertStringNotContainsString('Vendor\\Module\\SubModule', $output);
    }

    public function testGetStatusCodeWithNoDefects(): void
    {
        $result = $this->createMockResult('test/package', false, []);
        $this->console->printOutput($result);
        
        $statusCode = $this->console->getStatusCode();
        
        $this->assertEquals(0, $statusCode);
    }

     public function testGetStatusCodeWithDefects(): void
     {
         $defects = ['composer' => ['vendor/package1']];
         $result = $this->createMockResult('test/package', true, $defects);
         
         // Capture output to avoid risky test warning
         ob_start();
         $this->console->printOutput($result);
         ob_get_clean();
         
         $statusCode = $this->console->getStatusCode();
         
         $this->assertEquals(1, $statusCode);
     }

    public function testValidateParametersWithNoArguments(): void
    {
        // Backup original $_SERVER values
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argc'] = 1;
        $_SERVER['argv'] = ['script.php'];
        
        // Capture output
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

    public function testValidateParametersWithNonExistentComposerLock(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['script.php', '/non/existent/path'];
        
        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('"composer.lock" file was not found', $output);
        
        // Restore original values
        if ($originalArgc !== null) $_SERVER['argc'] = $originalArgc;
        if ($originalArgv !== null) $_SERVER['argv'] = $originalArgv;
    }

    public function testValidateParametersWithNonExistentDirectory(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;
        
        // Create a temporary composer.lock file
        $tempDir = sys_get_temp_dir() . '/phpunit_test_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/composer.lock', '{}');
        
        $_SERVER['argc'] = 3;
        $_SERVER['argv'] = ['script.php', $tempDir, 'non/existent/dir'];
        
        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Can not find directory', $output);
        
        // Clean up
        unlink($tempDir . '/composer.lock');
        rmdir($tempDir);
        
        // Restore original values
        if ($originalArgc !== null) $_SERVER['argc'] = $originalArgc;
        if ($originalArgv !== null) $_SERVER['argv'] = $originalArgv;
    }

    public function testValidateParametersWithValidInput(): void
    {
        $originalArgc = $_SERVER['argc'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;
        
        // Create a temporary composer.lock file and directory
        $tempDir = sys_get_temp_dir() . '/phpunit_test_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/composer.lock', '{}');
        
        $testSubDir = 'src';
        mkdir(ROOT_DIR . $testSubDir, 0777, true);
        
        $_SERVER['argc'] = 3;
        $_SERVER['argv'] = ['script.php', $tempDir, $testSubDir];
        
        ob_start();
        $result = $this->console->validateParameters();
        $output = ob_get_clean();
        
        $this->assertTrue($result);
        $this->assertEmpty($output);
        
        // Clean up
        unlink($tempDir . '/composer.lock');
        rmdir($tempDir);
        
        // Restore original values
        if ($originalArgc !== null) $_SERVER['argc'] = $originalArgc;
        if ($originalArgv !== null) $_SERVER['argv'] = $originalArgv;
    }

    public function testPrintHelp(): void
    {
        ob_start();
        $this->console->printHelp();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Help', $output);
        $this->assertStringContainsString('Tool to check integrity', $output);
        $this->assertStringContainsString('php bin/dependencies', $output);
        $this->assertStringContainsString('[Magento2 root]', $output);
        $this->assertStringContainsString('{folder1} {folder2}', $output);
    }

    public function testPrintOutputRegistersResultWithDefectsState(): void
    {
        $result = $this->createMockResult('test/package', false, []);
        
        // Before calling printOutput, status should be 0 (assuming clean state)
        $initialStatus = $this->console->getStatusCode();
        
        $this->console->printOutput($result);
        
        // The result should be registered with DefectsState
        // Since we created a result with no defects, status should still be 0
        $finalStatus = $this->console->getStatusCode();
        
        $this->assertEquals(0, $initialStatus);
        $this->assertEquals(0, $finalStatus);
    }

    public function testDetectAiPromptsModeWithFlag(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--ai-prompts'];
        
        $console = new Console();
        
        $this->assertTrue($console->isAiPromptsMode());
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testDetectAiPromptsModeWithoutFlag(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento'];
        
        $console = new Console();
        
        $this->assertFalse($console->isAiPromptsMode());
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintOutputInAiModeCollectsResults(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--ai-prompts'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['vendor/package1'],
            'module' => ['Vendor\\Package1']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        // Capture output - should include regular output plus AI explanation
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('AI Prompt', $output);
        $this->assertStringContainsString('----------', $output);
        $this->assertStringContainsString('1 missing composer', $output);
        $this->assertStringContainsString('dependencies: vendor/package1', $output);
        $this->assertStringContainsString('1 missing module.xml', $output);
        $this->assertStringContainsString('dependencies:', $output);
        $this->assertStringContainsString('Vendor_Package1', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintOutputInAiModeWithComposerDefectsOnly(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--ai-prompts'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['vendor/package1', 'vendor/package2']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('AI Prompt', $output);
        $this->assertStringContainsString('----------', $output);
        $this->assertStringContainsString('2 undeclared', $output);
        $this->assertStringContainsString('composer', $output);
        $this->assertStringContainsString('packages: vendor/package1, vendor/package2', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintOutputInAiModeWithModuleDefectsOnly(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--ai-prompts'];
        
        $console = new Console();
        
        $defects = [
            'module' => ['Vendor\\Package1', 'Vendor\\Package2']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('AI Prompt', $output);
        $this->assertStringContainsString('----------', $output);
        $this->assertStringContainsString('2 undeclared', $output);
        $this->assertStringContainsString('Magento modules:', $output);
        $this->assertStringContainsString('Vendor_Package1, Vendor_Package2', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintOutputNotInAiModeShowsNormalOutput(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['vendor/package1']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringNotContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testPrintHelpIncludesAiPromptsFlag(): void
    {
        ob_start();
        $this->console->printHelp();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('--ai-prompts', $output);
        $this->assertStringContainsString('AI-friendly explanations', $output);
    }

    public function testPrintOutputNotInAiModeShouldNotShowAiAnalysis(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['vendor/package1']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Package test/package has defects(s)', $output);
        $this->assertStringNotContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testV2ModeDoesNotAutomaticallyEnableAiPrompts(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--v2'];
        
        $console = new Console();
        
        $this->assertTrue($console->isV2Mode());
        $this->assertFalse($console->isAiPromptsMode());
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testV2ModeShowsCopyPasteReadyComposerFormat(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--v2'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['vendor/package1', 'vendor/package2']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        // Should NOT contain legacy format since v2 auto-enables no-legacy
        $this->assertStringNotContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('Missed dependencies in src/path/to/test/package/composer.json', $output);
        $this->assertStringContainsString('"vendor/package1": "*"', $output);
        $this->assertStringContainsString('"vendor/package2": "*"', $output);
        $this->assertStringNotContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testV2ModeShowsCopyPasteReadyModuleXmlFormat(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--v2'];
        
        $console = new Console();
        
        $defects = [
            'module' => ['Vendor\\Package1', 'Magento\\Checkout']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        // Should NOT contain legacy format since v2 auto-enables no-legacy
        $this->assertStringNotContainsString('Package test/package has defects(s)', $output);
        $this->assertStringContainsString('Missed dependencies in src/path/to/test/package/etc/module.xml', $output);
        $this->assertStringContainsString('<module name="Vendor_Package1"/>', $output);
        $this->assertStringContainsString('<module name="Magento_Checkout"/>', $output);
        $this->assertStringNotContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testV2ModeWithBothComposerAndModuleDependencies(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--v2'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['magento/module-checkout'],
            'module' => ['Magento\\Checkout']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        // Should NOT contain legacy format since v2 auto-enables no-legacy
        $this->assertStringNotContainsString('Package test/package has defects(s)', $output);
        
        // Check composer format
        $this->assertStringContainsString('Missed dependencies in src/path/to/test/package/composer.json', $output);
        $this->assertStringContainsString('"magento/module-checkout": "*"', $output);
        
        // Check module.xml format  
        $this->assertStringContainsString('Missed dependencies in src/path/to/test/package/etc/module.xml', $output);
        $this->assertStringContainsString('<module name="Magento_Checkout"/>', $output);
        
        // Check AI prompt is NOT included by default
        $this->assertStringNotContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testV2ModeWithAiPromptsWhenBothFlagsAreUsed(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        
        $_SERVER['argv'] = ['script.php', '/path/to/magento', '--v2', '--ai-prompts'];
        
        $console = new Console();
        
        $defects = [
            'composer' => ['magento/module-checkout'],
            'module' => ['Magento\\Checkout']
        ];
        
        $result = $this->createMockResult('test/package', true, $defects);
        
        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();
        
        $this->assertTrue($console->isV2Mode());
        $this->assertTrue($console->isAiPromptsMode());
        $this->assertTrue($console->isNoLegacy()); // v2 auto-enables no-legacy
        
        // Should NOT contain legacy format since v2 auto-enables no-legacy
        $this->assertStringNotContainsString('Package test/package has defects(s)', $output);
        
        // Check v2 format
        $this->assertStringContainsString('Missed dependencies in src/path/to/test/package/composer.json', $output);
        $this->assertStringContainsString('"magento/module-checkout": "*"', $output);
        
        // Check AI prompt is included when both flags are used
        $this->assertStringContainsString('AI Prompt', $output);
        
        // Restore original value
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    /**
     * Test that v2 mode automatically enables no-legacy.
     */
    public function testV2ModeAutoEnablesNoLegacy(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--v2'];
        
        $console = new Console();
        $this->assertTrue($console->isV2Mode());
        $this->assertTrue($console->isNoLegacy()); // Auto-enabled by --v2
        $this->assertFalse($console->isAiPromptsMode());
    }

    /**
     * Test that no-legacy mode is detected correctly.
     */
    public function testIsNoLegacyDetection(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--no-legacy'];
        
        $console = new Console();
        $this->assertTrue($console->isNoLegacy());
        $this->assertFalse($console->isAiPromptsMode());
        $this->assertFalse($console->isV2Mode());
    }

    /**
     * Test that no-legacy mode combined with v2 is detected correctly.
     */
    public function testNoLegacyWithV2Detection(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--v2', '--no-legacy'];
        
        $console = new Console();
        $this->assertTrue($console->isNoLegacy());
        $this->assertTrue($console->isV2Mode());
        $this->assertFalse($console->isAiPromptsMode());
    }

    /**
     * Test that no-legacy mode combined with ai-prompts is detected correctly.
     */
    public function testNoLegacyWithAiPromptsDetection(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--ai-prompts', '--no-legacy'];
        
        $console = new Console();
        $this->assertTrue($console->isNoLegacy());
        $this->assertTrue($console->isAiPromptsMode());
        $this->assertFalse($console->isV2Mode());
    }

    /**
     * Test that all three modes can be enabled together.
     */
    public function testAllModesEnabledTogether(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--ai-prompts', '--v2', '--no-legacy'];
        
        $console = new Console();
        $this->assertTrue($console->isNoLegacy());
        $this->assertTrue($console->isAiPromptsMode());
        $this->assertTrue($console->isV2Mode());
    }

    /**
     * Test that no-legacy mode omits traditional output.
     */
    public function testNoLegacyOmitsTraditionalOutput(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--no-legacy', '--ai-prompts'];
        
        $console = new Console();
        
        $result = new Result(
            'test-package',
            '/path/to/package',
            ['missing-package'],
            ['Missing_Module']
        );

        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();

        // Should not contain traditional format messages
        $this->assertStringNotContainsString('Package test-package has defects(s).', $output);
        $this->assertStringNotContainsString('missed dependencies in composer.json', $output);
        $this->assertStringNotContainsString('missed dependencies in etc/module.xml', $output);
        
        // Should contain AI output since we used --ai-prompts
        $this->assertStringContainsString('AI Prompt', $output);
    }

    /**
     * Test that no-legacy with v2 shows only v2 format.
     */
    public function testNoLegacyWithV2ShowsOnlyV2Format(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--v2', '--no-legacy'];
        
        $console = new Console();
        
        $result = new Result(
            'test-package',
            '/path/to/package',
            ['missing-package'],
            ['Missing_Module']
        );

        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();

        // Should not contain traditional format
        $this->assertStringNotContainsString('Package test-package has defects(s).', $output);
        
        // Should contain v2 format
        $this->assertStringContainsString('Missed dependencies in path/to/package/composer.json', $output);
        $this->assertStringContainsString('"missing-package": "*"', $output);
        $this->assertStringContainsString('Missed dependencies in path/to/package/etc/module.xml', $output);
        $this->assertStringContainsString('<module name="Missing_Module"/>', $output);
    }

    /**
     * Test that no-legacy with ai-prompts shows only AI explanations.
     */
    public function testNoLegacyWithAiPromptsShowsOnlyAiFormat(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--ai-prompts', '--no-legacy'];
        
        $console = new Console();
        
        $result = new Result(
            'test-package',
            '/path/to/package',
            ['missing-package'],
            ['Missing_Module']
        );

        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();

        // Should not contain traditional format
        $this->assertStringNotContainsString('Package test-package has defects(s).', $output);
        
        // Should contain AI explanation
        $this->assertStringContainsString('AI Prompt', $output);
        $this->assertStringContainsString('----------', $output);
        $this->assertStringContainsString('test-package', $output);
    }

    /**
     * Test that all modes work together with no-legacy.
     */
    public function testAllModesWithNoLegacy(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--ai-prompts', '--v2', '--no-legacy'];
        
        $console = new Console();
        
        $result = new Result(
            'test-package',
            '/path/to/package',
            ['missing-package'],
            ['Missing_Module']
        );

        ob_start();
        $console->printOutput($result);
        $output = ob_get_clean();

        // Should not contain traditional format
        $this->assertStringNotContainsString('Package test-package has defects(s).', $output);
        
        // Should contain v2 format
        $this->assertStringContainsString('Missed dependencies in path/to/package/composer.json', $output);
        $this->assertStringContainsString('"missing-package": "*"', $output);
        
        // Should contain AI explanation
        $this->assertStringContainsString('AI Prompt', $output);
    }

    /**
     * Test that --no-legacy without other flags shows error and help.
     */
    public function testNoLegacyWithoutOtherFlagsShowsErrorAndHelp(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--no-legacy'];
        
        $console = new Console();

        ob_start();
        $result = $console->validateNoLegacyUsage();
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Error: --no-legacy flag requires either --v2 or --ai-prompts', $output);
        $this->assertStringContainsString('Help', $output);
    }

    /**
     * Test that --no-legacy with --v2 passes validation.
     */
    public function testNoLegacyWithV2PassesValidation(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--no-legacy', '--v2'];
        
        $console = new Console();

        ob_start();
        $result = $console->validateNoLegacyUsage();
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertEmpty($output);
    }

    /**
     * Test that --no-legacy with --ai-prompts passes validation.
     */
    public function testNoLegacyWithAiPromptsPassesValidation(): void
    {
        $_SERVER['argv'] = ['dependencies', '/path/to/magento', '--no-legacy', '--ai-prompts'];
        
        $console = new Console();

        ob_start();
        $result = $console->validateNoLegacyUsage();
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertEmpty($output);
    }

    private function createMockResult(string $packageName, bool $hasDefects, array $defects): ResultInterface
    {
        // Create an anonymous class that implements ResultInterface and adds getPackagePath
        $result = new class($packageName, $hasDefects, $defects) implements ResultInterface {
            private string $packageName;
            private bool $hasDefects;
            private array $defects;
            
            public function __construct(string $packageName, bool $hasDefects, array $defects) {
                $this->packageName = $packageName;
                $this->hasDefects = $hasDefects;
                $this->defects = $defects;
            }
            
            public function getPackageName(): string {
                return $this->packageName;
            }
            
            public function hasDefects(): bool {
                return $this->hasDefects;
            }
            
            public function getDefects(): array {
                return $this->defects;
            }
            
            public function getPackagePath(): string {
                return 'src/path/to/' . $this->packageName;
            }
        };
        
        return $result;
    }
} 