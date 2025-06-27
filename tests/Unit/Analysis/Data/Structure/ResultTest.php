<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Data\Structure;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Analysis\Data\Structure\Result;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    public function testConstructorSetsPackageName(): void
    {
        $packageName = 'test/package';
        $defects = [];
        
        $result = new Result($packageName, $defects);
        
        $this->assertSame($packageName, $result->getPackageName());
    }

    public function testHasDefectsReturnsFalseWhenNoDefects(): void
    {
        $result = new Result('test/package', []);
        
        $this->assertFalse($result->hasDefects());
    }

    public function testHasDefectsReturnsTrueWhenDefectsExist(): void
    {
        $defects = ['Some defect message'];
        $result = new Result('test/package', $defects);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testGetDefectsReturnsEmptyArrayWhenNoDefects(): void
    {
        $result = new Result('test/package', []);
        
        $this->assertSame([], $result->getDefects());
    }

    public function testGetDefectsReturnsProvidedDefects(): void
    {
        $defects = ['Defect 1', 'Defect 2'];
        $result = new Result('test/package', $defects);
        
        $this->assertSame($defects, $result->getDefects());
    }

    public function testImplementsResultInterface(): void
    {
        $result = new Result('test/package', []);
        
        $this->assertInstanceOf(ResultInterface::class, $result);
    }

    public function testGetPackageNameWithSpecialCharacters(): void
    {
        $packageName = 'vendor/package-name_with.special-chars';
        $result = new Result($packageName, []);
        
        $this->assertSame($packageName, $result->getPackageName());
    }

    public function testComplexDefectsScenario(): void
    {
        $defects = [
            'Structure violation in file A',
            'Missing dependency in file B',
            'Circular dependency detected'
        ];
        
        $result = new Result('complex/package', $defects);
        
        $this->assertTrue($result->hasDefects());
        $this->assertSame('complex/package', $result->getPackageName());
        $this->assertSame($defects, $result->getDefects());
    }

    public function testHasDefectsWithEmptyStringDefect(): void
    {
        // Test with empty string as defect (edge case)
        $defects = [''];
        $result = new Result('test/package', $defects);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testGetDefectsWithMixedDefectTypes(): void
    {
        $defects = [
            'Critical: Missing required dependency',
            'Warning: Deprecated function usage',
            'Info: Unused import statement'
        ];
        
        $result = new Result('mixed/package', $defects);
        
        $this->assertSame($defects, $result->getDefects());
    }

    public function testHasDefectsWithMultipleDefects(): void
    {
        $defects = [
            'First defect',
            'Second defect',
            'Third defect'
        ];
        
        $result = new Result('multi/package', $defects);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testGetDefectsPreservesOrder(): void
    {
        $defects = [
            'Z defect',
            'A defect', 
            'M defect'
        ];
        
        $result = new Result('ordered/package', $defects);
        
        // Should preserve the original order
        $this->assertSame($defects, $result->getDefects());
    }
} 