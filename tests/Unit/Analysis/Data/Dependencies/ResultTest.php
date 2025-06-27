<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Data\Dependencies;


use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    public function testConstructorSetsPackageName(): void
    {
        $packageName = 'test/package';
        $packagePath = '/path/to/package';
        $composerDefects = [];
        $moduleXmlDefects = [];
        
        $result = new Result($packageName, $packagePath, $composerDefects, $moduleXmlDefects);
        
        $this->assertSame($packageName, $result->getPackageName());
        $this->assertSame($packagePath, $result->getPackagePath());
    }

    public function testHasDefectsReturnsFalseWhenNoDefects(): void
    {
        $result = new Result('test/package', '/path/to/package', [], []);
        
        $this->assertFalse($result->hasDefects());
    }

    public function testHasDefectsReturnsTrueWhenComposerDefectsExist(): void
    {
        $composerDefects = ['missing/package'];
        $result = new Result('test/package', '/path/to/package', $composerDefects, []);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testHasDefectsReturnsTrueWhenModuleXmlDefectsExist(): void
    {
        $moduleXmlDefects = ['Module_Name'];
        $result = new Result('test/package', '/path/to/package', [], $moduleXmlDefects);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testHasDefectsReturnsTrueWhenBothDefectsExist(): void
    {
        $composerDefects = ['missing/composer-package'];
        $moduleXmlDefects = ['Module_Missing'];
        $result = new Result('test/package', '/path/to/package', $composerDefects, $moduleXmlDefects);
        
        $this->assertTrue($result->hasDefects());
    }

    public function testGetDefectsReturnsEmptyArraysWhenNoDefects(): void
    {
        $result = new Result('test/package', '/path/to/package', [], []);
        
        $expected = [
            'composer' => [],
            'module' => []
        ];
        
        $this->assertSame($expected, $result->getDefects());
    }

    public function testGetDefectsReturnsComposerDefectsOnly(): void
    {
        $composerDefects = ['vendor/package-one', 'vendor/package-two'];
        $result = new Result('test/package', '/path/to/package', $composerDefects, []);
        
        $expected = [
            'composer' => ['vendor/package-one', 'vendor/package-two'],
            'module' => []
        ];
        
        $this->assertSame($expected, $result->getDefects());
    }

    public function testGetDefectsReturnsModuleXmlDefectsOnly(): void
    {
        $moduleXmlDefects = ['Module_One', 'Module_Two'];
        $result = new Result('test/package', '/path/to/package', [], $moduleXmlDefects);
        
        $expected = [
            'composer' => [],
            'module' => ['Module_One', 'Module_Two']
        ];
        
        $this->assertSame($expected, $result->getDefects());
    }

    public function testGetDefectsReturnsBothTypesOfDefects(): void
    {
        $composerDefects = ['vendor/missing-package'];
        $moduleXmlDefects = ['Module_Missing'];
        $result = new Result('test/package', '/path/to/package', $composerDefects, $moduleXmlDefects);
        
        $expected = [
            'composer' => ['vendor/missing-package'],
            'module' => ['Module_Missing']
        ];
        
        $this->assertSame($expected, $result->getDefects());
    }

    public function testImplementsResultInterface(): void
    {
        $result = new Result('test/package', '/path/to/package', [], []);
        
        $this->assertInstanceOf(ResultInterface::class, $result);
    }

    public function testGetPackageNameWithSpecialCharacters(): void
    {
        $packageName = 'vendor/package-name_with.special-chars';
        $result = new Result($packageName, '/path/to/package', [], []);
        
        $this->assertSame($packageName, $result->getPackageName());
    }

    public function testGetPackagePathWithSpecialCharacters(): void
    {
        $packagePath = '/path/to/package-name_with.special-chars/';
        $result = new Result('test/package', $packagePath, [], []);
        
        $this->assertSame($packagePath, $result->getPackagePath());
    }

    public function testComplexDefectsScenario(): void
    {
        $composerDefects = [
            'vendor/package-one',
            'vendor/package-two',
            'another/vendor-package'
        ];
        $moduleXmlDefects = [
            'Module_One',
            'Module_Two',
            'Another_Module'
        ];
        
        $result = new Result('complex/package', '/complex/path', $composerDefects, $moduleXmlDefects);
        
        $this->assertTrue($result->hasDefects());
        $this->assertSame('complex/package', $result->getPackageName());
        $this->assertSame('/complex/path', $result->getPackagePath());
        
        $expected = [
            'composer' => $composerDefects,
            'module' => $moduleXmlDefects
        ];
        
        $this->assertSame($expected, $result->getDefects());
    }

    public function testHasDefectsWithEmptyStringDefects(): void
    {
        $composerDefects = [''];
        $moduleXmlDefects = [''];
        $result = new Result('test/package', '/path/to/package', $composerDefects, $moduleXmlDefects);
        
        // Empty arrays are falsy, but arrays with empty strings are truthy
        $this->assertTrue($result->hasDefects());
    }
} 