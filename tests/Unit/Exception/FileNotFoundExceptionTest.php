<?php

declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RunAsRoot\IntegrityChecker\Exception\FileNotFoundException;

#[CoversClass(FileNotFoundException::class)]
class FileNotFoundExceptionTest extends TestCase
{
    public function testConstructorWithDefaultMessage(): void
    {
        $file = 'composer.json';
        $package = '/path/to/package';
        
        $exception = new FileNotFoundException($file, $package);
        
        $expectedMessage = 'File was not found: composer.json in package /path/to/package.';
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithCustomMessage(): void
    {
        $file = 'module.xml';
        $package = '/custom/package/path';
        $customMessage = 'Custom error message';
        
        $exception = new FileNotFoundException($file, $package, $customMessage);
        
        $this->assertSame($customMessage, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithCustomCode(): void
    {
        $file = 'config.json';
        $package = '/test/package';
        $code = 404;
        
        $exception = new FileNotFoundException($file, $package, '', $code);
        
        $expectedMessage = 'File was not found: config.json in package /test/package.';
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $file = 'package.json';
        $package = '/another/package';
        $previousException = new \RuntimeException('Previous error');
        
        $exception = new FileNotFoundException($file, $package, '', 0, $previousException);
        
        $expectedMessage = 'File was not found: package.json in package /another/package.';
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters(): void
    {
        $file = 'test.xml';
        $package = '/full/package/path';
        $customMessage = 'Complete custom message';
        $code = 500;
        $previousException = new \InvalidArgumentException('Previous error');
        
        $exception = new FileNotFoundException($file, $package, $customMessage, $code, $previousException);
        
        $this->assertSame($customMessage, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInheritsFromException(): void
    {
        $exception = new FileNotFoundException('file.txt', '/path');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithEmptyStringMessage(): void
    {
        $file = 'empty.json';
        $package = '/empty/path';
        $emptyMessage = '';
        
        $exception = new FileNotFoundException($file, $package, $emptyMessage);
        
        $expectedMessage = 'File was not found: empty.json in package /empty/path.';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithSpecialCharactersInParameters(): void
    {
        $file = 'special-file_name.json';
        $package = '/path/with spaces/and-dashes_underscores';
        
        $exception = new FileNotFoundException($file, $package);
        
        $expectedMessage = 'File was not found: special-file_name.json in package /path/with spaces/and-dashes_underscores.';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testCanBeThrown(): void
    {
        $file = 'throwable.json';
        $package = '/throwable/path';
        
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File was not found: throwable.json in package /throwable/path.');
        
        throw new FileNotFoundException($file, $package);
    }

    public function testCanBeCaught(): void
    {
        $file = 'catch.json';
        $package = '/catch/path';
        $caught = false;
        
        try {
            throw new FileNotFoundException($file, $package);
        } catch (FileNotFoundException $e) {
            $caught = true;
            $this->assertSame('File was not found: catch.json in package /catch/path.', $e->getMessage());
        }
        
        $this->assertTrue($caught, 'Exception should have been caught');
    }
} 