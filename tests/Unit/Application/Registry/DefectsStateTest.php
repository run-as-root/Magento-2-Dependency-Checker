<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Application\Registry;

use PHPUnit\Framework\TestCase;
use RunAsRoot\IntegrityChecker\Application\Registry\DefectsState;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

class DefectsStateTest extends TestCase
{
    private DefectsState $defectsState;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defectsState = new DefectsState();
    }

    public function testInitialStateHasNoDefects(): void
    {
        $this->assertFalse($this->defectsState->hasDefects());
    }

    public function testRegisterResultWithNoDefectsKeepsStateClean(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(false);

        $this->defectsState->registerResult($result);

        $this->assertFalse($this->defectsState->hasDefects());
    }

    public function testRegisterResultWithDefectsSetsStateToTrue(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(true);

        $this->defectsState->registerResult($result);

        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testMultipleResultsWithNoDefectsKeepsStateClean(): void
    {
        $result1 = $this->createMock(ResultInterface::class);
        $result1->method('hasDefects')->willReturn(false);

        $result2 = $this->createMock(ResultInterface::class);
        $result2->method('hasDefects')->willReturn(false);

        $result3 = $this->createMock(ResultInterface::class);
        $result3->method('hasDefects')->willReturn(false);

        $this->defectsState->registerResult($result1);
        $this->defectsState->registerResult($result2);
        $this->defectsState->registerResult($result3);

        $this->assertFalse($this->defectsState->hasDefects());
    }

    public function testStateBecomesDefectiveOnFirstDefectiveResult(): void
    {
        $cleanResult = $this->createMock(ResultInterface::class);
        $cleanResult->method('hasDefects')->willReturn(false);

        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);

        $this->defectsState->registerResult($cleanResult);
        $this->assertFalse($this->defectsState->hasDefects());

        $this->defectsState->registerResult($defectiveResult);
        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testStateRemainsDefectiveOnceSet(): void
    {
        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);

        $cleanResult = $this->createMock(ResultInterface::class);
        $cleanResult->method('hasDefects')->willReturn(false);

        // Set state to defective
        $this->defectsState->registerResult($defectiveResult);
        $this->assertTrue($this->defectsState->hasDefects());

        // Adding clean results should not change defective state
        $this->defectsState->registerResult($cleanResult);
        $this->assertTrue($this->defectsState->hasDefects());

        $this->defectsState->registerResult($cleanResult);
        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testMixedResultsWithDefectsAtEnd(): void
    {
        $cleanResult1 = $this->createMock(ResultInterface::class);
        $cleanResult1->method('hasDefects')->willReturn(false);

        $cleanResult2 = $this->createMock(ResultInterface::class);
        $cleanResult2->method('hasDefects')->willReturn(false);

        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);

        $this->defectsState->registerResult($cleanResult1);
        $this->defectsState->registerResult($cleanResult2);
        $this->assertFalse($this->defectsState->hasDefects());

        $this->defectsState->registerResult($defectiveResult);
        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testMixedResultsWithDefectsAtBeginning(): void
    {
        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);

        $cleanResult1 = $this->createMock(ResultInterface::class);
        $cleanResult1->method('hasDefects')->willReturn(false);

        $cleanResult2 = $this->createMock(ResultInterface::class);
        $cleanResult2->method('hasDefects')->willReturn(false);

        $this->defectsState->registerResult($defectiveResult);
        $this->assertTrue($this->defectsState->hasDefects());

        $this->defectsState->registerResult($cleanResult1);
        $this->defectsState->registerResult($cleanResult2);
        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testMultipleDefectiveResults(): void
    {
        $defectiveResult1 = $this->createMock(ResultInterface::class);
        $defectiveResult1->method('hasDefects')->willReturn(true);

        $defectiveResult2 = $this->createMock(ResultInterface::class);
        $defectiveResult2->method('hasDefects')->willReturn(true);

        $this->defectsState->registerResult($defectiveResult1);
        $this->assertTrue($this->defectsState->hasDefects());

        $this->defectsState->registerResult($defectiveResult2);
        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testRegisterResultIsCalledCorrectly(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->expects($this->once())
               ->method('hasDefects')
               ->willReturn(true);

        $this->defectsState->registerResult($result);

        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testStateAccumulationWithLargeNumberOfResults(): void
    {
        // Register 50 clean results
        for ($i = 0; $i < 50; $i++) {
            $cleanResult = $this->createMock(ResultInterface::class);
            $cleanResult->method('hasDefects')->willReturn(false);
            $this->defectsState->registerResult($cleanResult);
        }
        
        $this->assertFalse($this->defectsState->hasDefects());

        // Add one defective result
        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);
        $this->defectsState->registerResult($defectiveResult);

        $this->assertTrue($this->defectsState->hasDefects());

        // Add more clean results
        for ($i = 0; $i < 50; $i++) {
            $cleanResult = $this->createMock(ResultInterface::class);
            $cleanResult->method('hasDefects')->willReturn(false);
            $this->defectsState->registerResult($cleanResult);
        }

        $this->assertTrue($this->defectsState->hasDefects());
    }

    public function testHasDefectsReturnTypeIsBoolean(): void
    {
        $result = $this->defectsState->hasDefects();
        $this->assertIsBool($result);
        $this->assertFalse($result);

        $defectiveResult = $this->createMock(ResultInterface::class);
        $defectiveResult->method('hasDefects')->willReturn(true);
        $this->defectsState->registerResult($defectiveResult);

        $result = $this->defectsState->hasDefects();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRegisterResultDoesNotReturnValue(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('hasDefects')->willReturn(false);

        $returnValue = $this->defectsState->registerResult($result);

        $this->assertNull($returnValue);
    }
} 