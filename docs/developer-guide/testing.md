# Testing Infrastructure and Best Practices

This document outlines the testing infrastructure, mocking strategies, and best practices for writing reliable tests in the Quality Tools package.

## Overview

The test suite uses enhanced isolation and virtual filesystem testing to ensure reliable, fast, and isolated test execution. Key improvements include:

- **Virtual Filesystem**: Using vfsStream for filesystem operation testing
- **Enhanced Test Isolation**: Automatic cleanup verification and environment management
- **Mock Factory**: Centralized mocking utilities for common scenarios
- **Test Base Classes**: Structured inheritance for different test types

## Test Base Classes

### BaseTestCase

The foundation test class providing:
- Virtual filesystem setup with vfsStream
- Environment variable isolation
- Mock registry management with automatic cleanup
- Comprehensive cleanup verification
- Memory and resource leak detection

```php
use Cpsit\QualityTools\Tests\Unit\BaseTestCase;

class MyTest extends BaseTestCase
{
    public function testSomething(): void
    {
        // Virtual filesystem is automatically available
        $virtualFile = $this->createVirtualFile('test.txt', 'content');

        // Environment isolation
        $result = $this->withEnvironment(['VAR' => 'value'], function() {
            return $_ENV['VAR'];
        });

        // Automatic cleanup verification on tearDown
    }
}
```

### FilesystemTestCase

Specialized for filesystem-related tests:
- Configuration loader utilities
- Standard project structure creation
- Configuration hierarchy testing

```php
use Cpsit\QualityTools\Tests\Unit\FilesystemTestCase;

class ConfigTest extends FilesystemTestCase
{
    public function testConfiguration(): void
    {
        // Automatic project structure with config files
        $projectRoot = $this->createConfigurationStructure([
            '.quality-tools.yaml' => 'quality-tools: { project: { name: "test" } }'
        ]);

        $loader = $this->createConfigurationLoader();
        $config = $loader->load($projectRoot);

        self::assertSame('test', $config->getProjectName());
    }
}
```

## Mock Factory

The `MockFactory` provides pre-configured mocks for common dependencies:

```php
use Cpsit\QualityTools\Tests\Unit\MockFactory;

class MyTest extends BaseTestCase
{
    private MockFactory $mockFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockFactory = new MockFactory($this);
    }

    public function testWithMocks(): void
    {
        // Pre-configured filesystem service mock
        $filesystemMock = $this->mockFactory->createFilesystemServiceMock([
            '/path/file.txt' => 'content'
        ]);

        // Security service with secure defaults
        $securityMock = $this->mockFactory->createSecurityServiceMock();

        // Process executor with command results
        $processMock = $this->mockFactory->createProcessExecutorMock([
            'phpstan' => ['exitCode' => 0]
        ]);
    }
}
```

## Virtual Filesystem Best Practices

### Prefer Virtual Filesystem Over Real Files

**Good:**
```php
public function testFileReading(): void
{
    $content = 'test content';
    $filePath = $this->createVirtualFile('test.txt', $content);

    $result = $this->service->readFile($filePath);

    self::assertSame($content, $result);
}
```

**Avoid:**
```php
public function testFileReading(): void
{
    // Real temporary file - cleanup issues, slower, less reliable
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'test content');

    try {
        $result = $this->service->readFile($tempFile);
        self::assertSame('test content', $result);
    } finally {
        unlink($tempFile);
    }
}
```

### Use Structured Test Data

```php
public function testConfigurationHierarchy(): void
{
    // Use provided helper methods for common scenarios
    $hierarchy = $this->createConfigurationHierarchy();

    $config = $this->withEnvironment(
        ['HOME' => $hierarchy['homeDir']],
        fn() => $this->loader->load($hierarchy['projectRoot'])
    );

    // Test merged configuration behavior
}
```

## Environment Variable Management

### Always Use Environment Isolation

**Good:**
```php
public function testEnvironmentVariables(): void
{
    $result = $this->withEnvironment(['TEST_VAR' => 'value'], function() {
        return $this->service->getEnvironmentValue('TEST_VAR');
    });

    self::assertSame('value', $result);
    // TEST_VAR is automatically cleaned up
}
```

**Avoid:**
```php
public function testEnvironmentVariables(): void
{
    $_ENV['TEST_VAR'] = 'value'; // Leaks to other tests!

    $result = $this->service->getEnvironmentValue('TEST_VAR');

    self::assertSame('value', $result);
    // Manual cleanup required and often forgotten
    unset($_ENV['TEST_VAR']);
}
```

## Mock Management

### Use Mock Registry for Automatic Cleanup

The base test class automatically tracks and cleans up mocks:

```php
public function testWithMocks(): void
{
    // Automatically registered for cleanup
    $mock = $this->createRegisteredMock(SomeService::class);

    // Configure mock behavior
    $mock->method('doSomething')->willReturn('result');

    // Test code using mock
    $result = $this->service->performAction($mock);

    // Mock is automatically cleaned up in tearDown
}
```

### Handle Final Classes

For final classes that cannot be mocked:

```php
// For final classes, use real instances with controlled dependencies
public function testWithFinalClass(): void
{
    // Use real instance with mocked dependencies
    $filesystemMock = $this->createFilesystemServiceMock();
    $validator = new ConfigurationValidator(); // Final class - use real instance

    $loader = new YamlConfigurationLoader($validator, $securityService, $filesystemMock);

    // Test behavior
}
```

## Cleanup Verification

The test infrastructure automatically verifies:

- **Temporary Files**: No leaked files outside virtual filesystem
- **Environment Variables**: Proper restoration after tests
- **Memory Usage**: Detection of excessive memory consumption
- **Mock Registry**: Reasonable limits on mock objects

### Custom Cleanup Verification

Add custom cleanup checks when needed:

```php
protected function tearDown(): void
{
    // Custom cleanup verification before parent
    $this->assertCustomResourcesCleanedUp();

    parent::tearDown(); // Includes standard cleanup verification
}

private function assertCustomResourcesCleanedUp(): void
{
    // Check for custom resource leaks
    $this->assertEmpty($this->customResourceRegistry, 'Custom resources not cleaned up');
}
```

## Performance Considerations

### Test Isolation vs Performance

The enhanced isolation provides benefits:
- **Reliability**: Tests don't interfere with each other
- **Debugging**: Clearer failure modes when tests fail
- **Maintenance**: Easier to understand and modify tests

Trade-offs:
- **Setup Cost**: Slightly more setup time per test
- **Memory**: Mock registry and virtual filesystem use more memory

### Optimization Strategies

1. **Group Related Tests**: Use test fixtures for expensive setup
2. **Limit Mock Usage**: Only mock what needs to be controlled
3. **Use Real Objects**: When final classes force it, embrace real instances
4. **Virtual Filesystem**: Much faster than real file operations

## Common Patterns

### Testing Configuration Loading

```php
public function testConfigurationWithDefaults(): void
{
    // Empty directory - should use defaults
    $emptyRoot = $this->createTemporaryStructure();
    $config = $this->loader->load($emptyRoot);

    self::assertSame('8.3', $config->getProjectPhpVersion());
}

public function testConfigurationOverrides(): void
{
    // Structured config files
    $yamlContent = 'quality-tools: { project: { php_version: "8.4" } }';
    $this->createVirtualFile('project/.quality-tools.yaml', $yamlContent);

    $config = $this->loader->load($this->projectRoot);

    self::assertSame('8.4', $config->getProjectPhpVersion());
}
```

### Testing Command Execution

```php
public function testCommandExecution(): void
{
    $processMock = $this->mockFactory->createProcessExecutorMock([
        'rector' => ['exitCode' => 0],
        'phpstan' => ['exitCode' => 1] // Simulate failure
    ]);

    $command = new RectorCommand($processMock, $commandBuilder);
    $result = $command->execute($input, $output);

    self::assertSame(0, $result);
}
```

### Testing File Operations

```php
public function testFileValidation(): void
{
    // Test various file scenarios with virtual filesystem
    $this->createVirtualFile('valid.php', '<?php echo "valid";');
    $this->createVirtualFile('invalid.txt', 'not php');

    $validator = new FileValidator($this->testFilesystem);

    self::assertTrue($validator->isValid($this->getVirtualRoot() . '/valid.php'));
    self::assertFalse($validator->isValid($this->getVirtualRoot() . '/invalid.txt'));
}
```

## Migration from Legacy Tests

When updating existing tests:

1. **Extend Appropriate Base Class**: Choose BaseTestCase or FilesystemTestCase
2. **Replace Real Files**: Use virtual filesystem methods
3. **Add Environment Isolation**: Wrap environment changes in withEnvironment()
4. **Use MockFactory**: Replace manual mock creation with factory methods
5. **Remove Manual Cleanup**: Let automatic cleanup handle resource management

## Troubleshooting

### Common Issues

**"Class is final and cannot be doubled"**
- Use real instances for final classes
- Mock their dependencies instead

**"Temporary files were not cleaned up"**
- Check for missing cleanup in manual file operations
- Use virtual filesystem instead of real files

**"Environment variables were not cleaned up"**
- Wrap environment changes in withEnvironment()
- Remove manual $_ENV modifications

**"Memory usage is too high"**
- Check for circular references in mocks
- Limit mock object creation
- Use gc_collect_cycles() manually if needed

### Debug Mode

Enable additional debugging:

```php
public function testWithDebugging(): void
{
    // Force cleanup verification
    $this->performCleanupVerification();

    // Check test isolation
    $this->assertTestIsolation();

    // Verify virtual filesystem state
    $this->assertVirtualFilesystemCleanedUp();
}
```

This testing infrastructure ensures reliable, maintainable, and fast test execution while providing comprehensive verification of test isolation and resource cleanup.
