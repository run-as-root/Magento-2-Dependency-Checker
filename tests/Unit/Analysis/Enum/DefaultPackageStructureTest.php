<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Enum;

use PHPUnit\Framework\TestCase;
use RunAsRoot\IntegrityChecker\Analysis\Enum\DefaultPackageStructure;

class DefaultPackageStructureTest extends TestCase
{
    public function testStructureConstantExists(): void
    {
        $this->assertTrue(defined('RunAsRoot\IntegrityChecker\Analysis\Enum\DefaultPackageStructure::STRUCTURE'));
        $this->assertIsArray(DefaultPackageStructure::STRUCTURE);
    }

    public function testStructureHasRequiredFiles(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Test required root files
        $this->assertContains('composer.json', $structure);
        $this->assertContains('README.md', $structure);
        $this->assertContains('registration.php', $structure);
    }

    public function testStructureHasRequiredDirectories(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Test required directories exist as keys
        $this->assertArrayHasKey('docs', $structure);
        $this->assertArrayHasKey('src', $structure);
        
        // Test directory values are arrays
        $this->assertIsArray($structure['docs']);
        $this->assertIsArray($structure['src']);
    }

    public function testSrcDirectoryStructure(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        $srcStructure = $structure['src'];
        
        // Test src has etc directory
        $this->assertArrayHasKey('etc', $srcStructure);
        $this->assertIsArray($srcStructure['etc']);
        
        // Test etc contains module.xml
        $this->assertContains('module.xml', $srcStructure['etc']);
    }

    public function testDocsDirectoryIsEmpty(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Test docs directory is empty array by default
        $this->assertEquals([], $structure['docs']);
    }

    public function testStructureIsWellFormed(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Test that the structure is not empty
        $this->assertNotEmpty($structure);
        
        // Test that each element is either a string (file) or array (directory)
        foreach ($structure as $key => $value) {
            if (is_string($key)) {
                // Directory (key is string, value is array)
                $this->assertIsArray($value, "Directory '$key' should have array value");
            } else {
                // File (key is numeric, value is string)
                $this->assertIsString($value, "File entry should be string");
            }
        }
    }

    public function testNestedStructureIntegrity(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Verify the complete nested structure
        $expectedEtcContents = ['module.xml'];
        $this->assertEquals($expectedEtcContents, $structure['src']['etc']);
    }

    public function testStructureCanBeUsedForValidation(): void
    {
        $structure = DefaultPackageStructure::STRUCTURE;
        
        // Test that structure can be traversed for validation purposes
        $fileCount = 0;
        $directoryCount = 0;
        
        foreach ($structure as $key => $value) {
            if (is_string($key)) {
                $directoryCount++;
            } else {
                $fileCount++;
            }
        }
        
        $this->assertGreaterThan(0, $fileCount, 'Should have some files');
        $this->assertGreaterThan(0, $directoryCount, 'Should have some directories');
    }
} 