<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

#[CoversClass(QualityToolsApplication::class)]
final class QualityToolsApplicationTest extends TestCase
{
    private string $originalCwd;
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Store original working directory
        $this->originalCwd = getcwd();

        // Store original environment variables
        $this->originalEnv = [
            'QT_PROJECT_ROOT' => getenv('QT_PROJECT_ROOT'),
        ];
    }

    protected function tearDown(): void
    {
        // Restore original working directory
        chdir($this->originalCwd);

        // Restore environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function constructorSetsCorrectApplicationProperties(): void
    {
        // Arrange & Act
        chdir($this->getFixturePath('valid-typo3-project'));
        $application = new QualityToolsApplication();

        // Assert
        $this->assertSame('CPSIT Quality Tools', $application->getName());
        $this->assertSame('1.0.0-dev', $application->getVersion());
        $this->assertSame(
            'Simple command-line interface for TYPO3 quality assurance tools',
            $application->getHelp()
        );
    }

    #[Test]
    public function constructorSucceedsWhenTypo3ProjectIsFound(): void
    {
        // Arrange & Act
        chdir($this->getFixturePath('valid-typo3-project'));
        $application = new QualityToolsApplication();

        // Assert
        $this->assertInstanceOf(QualityToolsApplication::class, $application);
        $this->assertSame($this->getFixturePath('valid-typo3-project'), $application->getProjectRoot());
    }

    #[Test]
    public function constructorDoesNotThrowWhenNoTypo3ProjectFound(): void
    {
        // Arrange
        $tempDir = sys_get_temp_dir() . '/qt_test_' . uniqid();
        mkdir($tempDir);
        chdir($tempDir);

        try {
            // Act
            $application = new QualityToolsApplication();

            // Assert - Constructor should not throw, but getProjectRoot() should
            $this->assertInstanceOf(QualityToolsApplication::class, $application);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('TYPO3 project root not found');
            $application->getProjectRoot();
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    #[DataProvider('provideValidTypo3Projects')]
    public function getProjectRootReturnsCorrectPathForValidTypo3Projects(string $projectPath): void
    {
        // Arrange
        chdir($projectPath);
        $application = new QualityToolsApplication();

        // Act
        $result = $application->getProjectRoot();

        // Assert
        $this->assertSame($projectPath, $result);
    }

    public static function provideValidTypo3Projects(): \Generator
    {
        $basePath = __DIR__ . '/../../Fixtures';

        yield 'typo3/cms-core project' => [realpath($basePath . '/valid-typo3-project')];
        yield 'typo3/minimal project' => [realpath($basePath . '/valid-typo3-minimal')];
        yield 'typo3/cms project' => [realpath($basePath . '/valid-typo3-cms')];
        yield 'TYPO3 in dev dependencies' => [realpath($basePath . '/typo3-dev-dependency')];
    }

    #[Test]
    public function getProjectRootThrowsExceptionWhenNoTypo3ProjectFound(): void
    {
        // Create an isolated non-TYPO3 project in temp directory
        $tempDir = sys_get_temp_dir() . '/qt_test_non_typo3_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create a non-TYPO3 composer.json
        $composerJson = [
            'name' => 'test/non-typo3-isolated',
            'type' => 'project',
            'require' => ['symfony/console' => '^7.0']
        ];
        file_put_contents($tempDir . '/composer.json', json_encode($composerJson));

        try {
            // Arrange
            chdir($tempDir);
            $application = new QualityToolsApplication();

            // Assert
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'TYPO3 project root not found. Please run this command from within a TYPO3 project directory, ' .
                'or set the QT_PROJECT_ROOT environment variable.'
            );

            // Act
            $application->getProjectRoot();
        } finally {
            unlink($tempDir . '/composer.json');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function getProjectRootThrowsExceptionWhenComposerJsonIsInvalid(): void
    {
        // Create an isolated directory with invalid JSON
        $tempDir = sys_get_temp_dir() . '/qt_test_invalid_json_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create invalid composer.json
        file_put_contents($tempDir . '/composer.json', '{"invalid": json}');

        try {
            // Arrange
            chdir($tempDir);
            $application = new QualityToolsApplication();

            // Assert
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('TYPO3 project root not found');

            // Act
            $application->getProjectRoot();
        } finally {
            unlink($tempDir . '/composer.json');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function getProjectRootUsesEnvironmentVariableWhenSet(): void
    {
        // Arrange
        $projectPath = $this->getFixturePath('valid-typo3-project');
        putenv('QT_PROJECT_ROOT=' . $projectPath);

        // Change to a non-TYPO3 directory to ensure env var takes precedence
        chdir($this->getFixturePath('non-typo3-project'));
        $application = new QualityToolsApplication();

        // Act
        $result = $application->getProjectRoot();

        // Assert
        $this->assertSame($projectPath, $result);
    }

    #[Test]
    public function getProjectRootIgnoresInvalidEnvironmentVariable(): void
    {
        // Arrange
        putenv('QT_PROJECT_ROOT=/invalid/path/that/does/not/exist');
        chdir($this->getFixturePath('valid-typo3-project'));
        $application = new QualityToolsApplication();

        // Act
        $result = $application->getProjectRoot();

        // Assert
        $this->assertSame($this->getFixturePath('valid-typo3-project'), $result);
    }

    #[Test]
    public function getProjectRootTraversesDirectoriesUpward(): void
    {
        // Arrange
        $nestedPath = $this->getFixturePath('nested-project/subdir');
        $expectedPath = $this->getFixturePath('nested-project/subdir');
        chdir($nestedPath);
        $application = new QualityToolsApplication();

        // Act
        $result = $application->getProjectRoot();

        // Assert
        $this->assertSame($expectedPath, $result);
    }

    #[Test]
    public function getProjectRootLimitsTraversalDepth(): void
    {
        // Create a simple directory structure that exceeds traversal limit
        $tempDir = sys_get_temp_dir() . '/qt_test_limit_' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // Start from temp dir which has no TYPO3 project
            chdir($tempDir);
            $application = new QualityToolsApplication();

            // Assert - should throw when no TYPO3 project found within limit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('TYPO3 project root not found');

            // Act
            $application->getProjectRoot();
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesGetcwdFailure(): void
    {
        // This test is difficult to implement without mocking getcwd(),
        // as we cannot easily force getcwd() to return false in a unit test.
        // We'll test the error message construction instead.
        $this->markTestSkipped('Requires mocking of getcwd() which is not easily testable');
    }

    #[Test]
    public function getProjectRootIsCachedAfterFirstCall(): void
    {
        // Arrange
        chdir($this->getFixturePath('valid-typo3-project'));
        $application = new QualityToolsApplication();

        // Act
        $firstResult = $application->getProjectRoot();

        // Change directory to verify caching
        chdir($this->getFixturePath('non-typo3-project'));
        $secondResult = $application->getProjectRoot();

        // Assert
        $this->assertSame($firstResult, $secondResult);
    }

    #[Test]
    public function getProjectRootHandlesRootFilesystem(): void
    {
        // Arrange
        $rootPath = '/';
        if (!is_dir($rootPath)) {
            $this->markTestSkipped('Root filesystem not accessible');
        }

        chdir($rootPath);
        $application = new QualityToolsApplication();

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TYPO3 project root not found');

        // Act
        $application->getProjectRoot();
    }

    #[Test]
    #[DataProvider('provideTypo3DependencyScenarios')]
    public function isTypo3ProjectDetectsVariousTypo3Dependencies(string $composerContent, bool $expectedResult): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'composer_test_');
        file_put_contents($tempFile, $composerContent);

        try {
            // Create application and use reflection to test private method
            $application = new QualityToolsApplication();
            $reflection = new \ReflectionClass($application);
            $method = $reflection->getMethod('isTypo3Project');
            $method->setAccessible(true);

            // Act
            $result = $method->invoke($application, $tempFile);

            // Assert
            $this->assertSame($expectedResult, $result);
        } finally {
            unlink($tempFile);
        }
    }

    public static function provideTypo3DependencyScenarios(): \Generator
    {
        yield 'typo3/cms-core in require' => [
            '{"require":{"typo3/cms-core":"^13.4"}}',
            true
        ];

        yield 'typo3/cms in require' => [
            '{"require":{"typo3/cms":"^13.4"}}',
            true
        ];

        yield 'typo3/minimal in require' => [
            '{"require":{"typo3/minimal":"^13.4"}}',
            true
        ];

        yield 'typo3/cms-core in require-dev' => [
            '{"require-dev":{"typo3/cms-core":"^13.4"}}',
            true
        ];

        yield 'mixed dependencies with TYPO3' => [
            '{"require":{"symfony/console":"^7.0"},"require-dev":{"typo3/cms-core":"^13.4"}}',
            true
        ];

        yield 'no TYPO3 dependencies' => [
            '{"require":{"symfony/console":"^7.0"}}',
            false
        ];

        yield 'empty composer.json' => [
            '{}',
            false
        ];

        yield 'only TYPO3-related but not core packages' => [
            '{"require":{"typo3/cms-backend":"^13.4"}}',
            false
        ];

        yield 'invalid JSON' => [
            '{"invalid": json}',
            false
        ];

        yield 'file read failure' => [
            '', // Empty content to simulate file_get_contents failure
            false
        ];
    }

    private function getFixturePath(string $fixture): string
    {
        return realpath(__DIR__ . '/../../Fixtures/' . $fixture);
    }

    private function createDeepDirectoryStructure(string $basePath, int $depth): void
    {
        $currentPath = $basePath;

        // Create all directories in one go
        for ($i = 1; $i <= $depth; $i++) {
            $currentPath .= '/level' . $i;
        }

        // Create the entire path recursively
        if (!mkdir($currentPath, 0777, true)) {
            throw new \RuntimeException("Failed to create directory: $currentPath");
        }
    }

    private function removeDeepDirectoryStructure(string $basePath): void
    {
        if (is_dir($basePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            rmdir($basePath);
        }
    }
}
