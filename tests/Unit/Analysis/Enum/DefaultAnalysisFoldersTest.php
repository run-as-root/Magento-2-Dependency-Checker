<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Enum;

use PHPUnit\Framework\TestCase;
use RunAsRoot\IntegrityChecker\Analysis\Enum\DefaultAnalysisFolders;

class DefaultAnalysisFoldersTest extends TestCase
{
    public function testAppConstantExists(): void
    {
        $this->assertTrue(defined('RunAsRoot\IntegrityChecker\Analysis\Enum\DefaultAnalysisFolders::APP'));
        $this->assertEquals('app', DefaultAnalysisFolders::APP);
    }

    public function testSrcConstantExists(): void
    {
        $this->assertTrue(defined('RunAsRoot\IntegrityChecker\Analysis\Enum\DefaultAnalysisFolders::SRC'));
        $this->assertEquals('src', DefaultAnalysisFolders::SRC);
    }

    public function testConstantsAreStrings(): void
    {
        $this->assertIsString(DefaultAnalysisFolders::APP);
        $this->assertIsString(DefaultAnalysisFolders::SRC);
    }

    public function testConstantsAreNotEmpty(): void
    {
        $this->assertNotEmpty(DefaultAnalysisFolders::APP);
        $this->assertNotEmpty(DefaultAnalysisFolders::SRC);
    }

    public function testConstantsAreValidDirectoryNames(): void
    {
        // Test that constants contain valid directory name characters
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', DefaultAnalysisFolders::APP);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', DefaultAnalysisFolders::SRC);
    }

    public function testConstantsAreUnique(): void
    {
        $this->assertNotEquals(DefaultAnalysisFolders::APP, DefaultAnalysisFolders::SRC);
    }
} 