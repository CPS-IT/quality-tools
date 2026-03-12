<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Configuration\ValidationResult
 */
final class ValidationResultTest extends TestCase
{
    public function testValidResult(): void
    {
        $result = new ValidationResult(true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
        self::assertFalse($result->hasErrors());
    }

    public function testValidResultWithEmptyErrors(): void
    {
        $result = new ValidationResult(true, []);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
        self::assertFalse($result->hasErrors());
    }

    public function testInvalidResult(): void
    {
        $errors = ['Field is required', 'Invalid format'];
        $result = new ValidationResult(false, $errors);

        self::assertFalse($result->isValid());
        self::assertSame($errors, $result->getErrors());
        self::assertTrue($result->hasErrors());
    }

    public function testInvalidResultWithoutErrors(): void
    {
        $result = new ValidationResult(false);

        self::assertFalse($result->isValid());
        self::assertSame([], $result->getErrors());
        self::assertFalse($result->hasErrors());
    }

    public function testSingleError(): void
    {
        $result = new ValidationResult(false, ['Single error message']);

        self::assertFalse($result->isValid());
        self::assertSame(['Single error message'], $result->getErrors());
        self::assertTrue($result->hasErrors());
    }

    public function testMultipleErrors(): void
    {
        $errors = [
            'project.name: Field is required',
            'tools.phpstan.level: Must be between 0 and 9',
            'paths.scan: Must be an array',
        ];

        $result = new ValidationResult(false, $errors);

        self::assertFalse($result->isValid());
        self::assertSame($errors, $result->getErrors());
        self::assertTrue($result->hasErrors());
        self::assertCount(3, $result->getErrors());
    }

    public function testHasErrorsConsistency(): void
    {
        // Test that hasErrors() is consistent with getErrors()
        $resultWithErrors = new ValidationResult(false, ['error']);
        self::assertTrue($resultWithErrors->hasErrors());
        self::assertNotEmpty($resultWithErrors->getErrors());

        $resultWithoutErrors = new ValidationResult(true, []);
        self::assertFalse($resultWithoutErrors->hasErrors());
        self::assertEmpty($resultWithoutErrors->getErrors());
    }

    /**
     * Test that ValidationResult can be used as a boolean-like value.
     */
    public function testBooleanUsage(): void
    {
        $validResult = new ValidationResult(true);
        $invalidResult = new ValidationResult(false);

        self::assertTrue($validResult->isValid(), 'Valid result should be truthy');
        self::assertFalse($invalidResult->isValid(), 'Invalid result should be falsy');
    }

    /**
     * Test immutability - ValidationResult should be immutable once created.
     */
    public function testImmutability(): void
    {
        $errors = ['original error'];
        $result = new ValidationResult(false, $errors);

        // Modify the original array
        $errors[] = 'added error';

        // Result should still have the original errors
        self::assertSame(['original error'], $result->getErrors());
        self::assertNotContains('added error', $result->getErrors());
    }
}
