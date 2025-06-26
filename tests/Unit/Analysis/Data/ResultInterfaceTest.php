<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Analysis\Data;

use PHPUnit\Framework\TestCase;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;
use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result as DependenciesResult;
use RunAsRoot\IntegrityChecker\Analysis\Data\Structure\Result as StructureResult;

class ResultInterfaceTest extends TestCase
{
    public function testDependenciesResultImplementsInterface(): void
    {
        $result = new DependenciesResult('test/package', '/path/to/package', [], []);
        
        $this->assertInstanceOf(ResultInterface::class, $result);
        $this->assertIsString($result->getPackageName());
        $this->assertIsBool($result->hasDefects());
        $this->assertIsArray($result->getDefects());
    }

    public function testStructureResultImplementsInterface(): void
    {
        $result = new StructureResult('test/package', []);
        
        $this->assertInstanceOf(ResultInterface::class, $result);
        $this->assertIsString($result->getPackageName());
        $this->assertIsBool($result->hasDefects());
        $this->assertIsArray($result->getDefects());
    }

    public function testInterfaceMethodsAreCorrectlyImplemented(): void
    {
        $depResult = new DependenciesResult('dep/package', '/path/to/dep', ['missing-dep'], ['missing-module']);
        $structResult = new StructureResult('struct/package', ['missing-file']);
        
        // Test Dependencies Result
        $this->assertEquals('dep/package', $depResult->getPackageName());
        $this->assertTrue($depResult->hasDefects());
        $this->assertCount(2, $depResult->getDefects());
        
        // Test Structure Result  
        $this->assertEquals('struct/package', $structResult->getPackageName());
        $this->assertTrue($structResult->hasDefects());
        $this->assertCount(1, $structResult->getDefects());
    }
} 