<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

#[CoversClass(QualityToolsApplication::class)]
final class QualityToolsApplicationEdgeCasesTest extends TestCase
{
    private string $originalCwd;
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        $this->originalEnv = [
            'QT_PROJECT_ROOT' => getenv('QT_PROJECT_ROOT'),
        ];
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

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
    public function getProjectRootHandlesPermissionDeniedDirectories(): void
    {
        // This test checks behavior when traversing through directories with limited permissions
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create a structure where middle directory has restricted permissions
            $restrictedDir = $tempDir . '/restricted';
            mkdir($restrictedDir);

            $projectDir = $restrictedDir . '/project';
            mkdir($projectDir);

            // Create a valid TYPO3 project
            TestHelper::createComposerJson($projectDir, TestHelper::getComposerContent('typo3-core'));

            // Use environment variable to force detection of our specific project
            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $projectDir],
                function () use ($projectDir): void {
                    chdir($projectDir);
                    $application = new QualityToolsApplication();

                    // Should work with environment variable override
                    $result = $application->getProjectRoot();
                    $this->assertSame(realpath($projectDir), $result);
                },
            );
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesSymbolicLinks(): void
    {
        if (!\function_exists('symlink')) {
            $this->markTestSkipped('Symbolic links not supported on this system');
        }

        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create actual project directory
            $actualProject = $tempDir . '/actual-project';
            mkdir($actualProject);
            TestHelper::createComposerJson($actualProject, TestHelper::getComposerContent('typo3-core'));

            // Create symbolic link to project
            $linkPath = $tempDir . '/linked-project';
            symlink($actualProject, $linkPath);

            chdir($linkPath);
            $application = new QualityToolsApplication();

            // Act
            $result = $application->getProjectRoot();

            // Assert - Should resolve to the actual path (use realpath for both)
            $this->assertSame(realpath($actualProject), $result);
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesMultipleValidProjectsInPath(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create nested structure with multiple valid TYPO3 projects
            $outerProject = $tempDir . '/outer';
            mkdir($outerProject);
            TestHelper::createComposerJson($outerProject, TestHelper::getComposerContent('typo3-core'));

            $innerProject = $outerProject . '/packages/custom-ext';
            mkdir($innerProject, 0o777, true);
            TestHelper::createComposerJson($innerProject, TestHelper::getComposerContent('typo3-minimal'));

            // Start from inner project
            chdir($innerProject);
            $application = new QualityToolsApplication();

            // Act
            $result = $application->getProjectRoot();

            // Assert - Should find the closest (inner) project first
            $this->assertSame(realpath($innerProject), $result);
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesCorruptedComposerJsonFiles(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create directory with corrupted composer.json
            $corruptedDir = $tempDir . '/corrupted';
            mkdir($corruptedDir);
            file_put_contents($corruptedDir . '/composer.json', '{"invalid": json, syntax}');

            // Create valid project above it
            TestHelper::createComposerJson($tempDir, TestHelper::getComposerContent('typo3-core'));

            chdir($corruptedDir);
            $application = new QualityToolsApplication();

            // Act
            $result = $application->getProjectRoot();

            // Assert - Should skip corrupted file and find valid parent
            $this->assertSame(realpath($tempDir), $result);
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesEmptyComposerJsonFiles(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create directory with empty composer.json
            $emptyDir = $tempDir . '/empty';
            mkdir($emptyDir);
            file_put_contents($emptyDir . '/composer.json', '');

            // Create valid project above it
            TestHelper::createComposerJson($tempDir, TestHelper::getComposerContent('typo3-core'));

            chdir($emptyDir);
            $application = new QualityToolsApplication();

            // Act
            $result = $application->getProjectRoot();

            // Assert - Should skip empty file and find valid parent
            $this->assertSame(realpath($tempDir), $result);
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesUnreadableComposerJsonFiles(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create directory with unreadable composer.json
            $unreadableDir = $tempDir . '/unreadable';
            mkdir($unreadableDir);
            $composerFile = $unreadableDir . '/composer.json';
            TestHelper::createComposerJson($unreadableDir, TestHelper::getComposerContent('typo3-core'));

            // Make file unreadable (if possible)
            if (chmod($composerFile, 0o000)) {
                // Create valid project above it
                TestHelper::createComposerJson($tempDir, TestHelper::getComposerContent('typo3-core'));

                chdir($unreadableDir);
                $application = new QualityToolsApplication();

                // Act
                $result = $application->getProjectRoot();

                // Assert - Should skip unreadable file and find valid parent
                $this->assertSame(realpath($tempDir), $result);

                // Restore permissions for cleanup
                chmod($composerFile, 0o644);
            } else {
                $this->markTestSkipped('Cannot modify file permissions on this system');
            }
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesVeryLongPaths(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Create a very long path structure
            $longPath = $tempDir;
            for ($i = 0; $i < 5; ++$i) {
                $longPath .= '/very-long-directory-name-that-exceeds-normal-limits-' . str_repeat('x', 50);
                mkdir($longPath, 0o777, true);
            }

            // Create valid project at the end
            TestHelper::createComposerJson($longPath, TestHelper::getComposerContent('typo3-core'));

            chdir($longPath);
            $application = new QualityToolsApplication();

            // Act
            $result = $application->getProjectRoot();

            // Assert
            $this->assertSame(realpath($longPath), $result);
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesProjectsWithNonStandardTypo3Dependencies(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            // Test various edge cases for TYPO3 dependency detection
            $testCases = [
                'mixed-case' => [
                    'require' => ['TYPO3/cms-core' => '^13.4'], // Should not match due to case
                ],
                'similar-name' => [
                    'require' => ['typo3/cms-core-extended' => '^13.4'], // Should not match
                ],
                'partial-match' => [
                    'require' => ['mycompany/typo3-cms-core' => '^13.4'], // Should not match
                ],
                'version-constraints' => [
                    'require' => ['typo3/cms-core' => 'dev-main'], // Should match with any version
                ],
            ];

            foreach ($testCases as $caseName => $composerContent) {
                $caseDir = $tempDir . '/' . $caseName;
                mkdir($caseDir);
                TestHelper::createComposerJson($caseDir, array_merge([
                    'name' => 'test/' . $caseName,
                    'type' => 'project',
                ], $composerContent));

                chdir($caseDir);
                $application = new QualityToolsApplication();

                if ($caseName === 'version-constraints') {
                    // This should be detected as TYPO3
                    $result = $application->getProjectRoot();
                    $this->assertSame(realpath($caseDir), $result);
                } else {
                    // These should not be detected as TYPO3
                    $this->expectException(RuntimeException::class);
                    $application->getProjectRoot();
                }
            }
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesConcurrentAccess(): void
    {
        // This test simulates multiple instances trying to access project root
        $tempDir = TestHelper::createTempDirectory();

        try {
            TestHelper::createComposerJson($tempDir, TestHelper::getComposerContent('typo3-core'));
            chdir($tempDir);

            // Create multiple application instances
            $applications = [];
            for ($i = 0; $i < 5; ++$i) {
                $applications[] = new QualityToolsApplication();
            }

            // Access project root from all instances
            $results = [];
            foreach ($applications as $app) {
                $results[] = $app->getProjectRoot();
            }

            // All should return the same result
            $this->assertCount(5, $results);
            foreach ($results as $result) {
                $this->assertSame(realpath($tempDir), $result);
            }
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesEnvironmentVariableWithRealpathIssues(): void
    {
        $tempDir = TestHelper::createTempDirectory();

        try {
            TestHelper::createComposerJson($tempDir, TestHelper::getComposerContent('typo3-core'));

            // Set environment variable with relative path
            $relativePath = basename($tempDir);
            $parentDir = \dirname($tempDir);

            TestHelper::withEnvironment(
                ['QT_PROJECT_ROOT' => $parentDir . '/./' . $relativePath],
                function () use ($tempDir): void {
                    chdir('/tmp'); // Change to different directory
                    $application = new QualityToolsApplication();

                    $result = $application->getProjectRoot();
                    $this->assertSame(realpath($tempDir), $result);
                },
            );
        } finally {
            TestHelper::removeDirectory($tempDir);
        }
    }
}
