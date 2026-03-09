<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator;
use Cpsit\QualityTools\Console\Runner\ConfigInitRunner;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigInitRequest;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigInitRunner::class)]
final class ConfigInitRunnerTest extends TestCase
{
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_init_runner_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Create vendor structure for VendorDirectoryDetector
        mkdir($this->tempDir . '/vendor/composer', 0o777, true);
        file_put_contents($this->tempDir . '/vendor/autoload.php', "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        if ($this->originalProjectRoot === false) {
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT'], $_SERVER['QT_PROJECT_ROOT']);
        } else {
            putenv('QT_PROJECT_ROOT=' . $this->originalProjectRoot);
            $_ENV['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
            $_SERVER['QT_PROJECT_ROOT'] = $this->originalProjectRoot;
        }

        TestHelper::removeDirectory($this->tempDir);
    }

    public function testDescribeReturnsConfigPath(): void
    {
        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'default');

        $description = $runner->describe($request);

        self::assertEquals('config-init', $description->operationName);
        self::assertStringEndsWith('/.quality-tools.yaml', $description->configPath);
        self::assertEquals('default', $description->info['template']);
    }

    public function testRunCreatesConfigurationFile(): void
    {
        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'default');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);
        self::assertFileExists($this->tempDir . '/.quality-tools.yaml');

        $content = file_get_contents($this->tempDir . '/.quality-tools.yaml');
        self::assertStringContainsString('quality-tools:', $content);
    }

    public function testRunReturnsWarningWhenFileExists(): void
    {
        file_put_contents($this->tempDir . '/.quality-tools.yaml', 'existing: true');

        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'default', force: false);
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);
        self::assertTrue($result->hasWarnings());

        // File should not be overwritten
        self::assertEquals('existing: true', file_get_contents($this->tempDir . '/.quality-tools.yaml'));
    }

    public function testRunOverwritesWithForce(): void
    {
        file_put_contents($this->tempDir . '/.quality-tools.yaml', 'existing: true');

        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'default', force: true);
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $content = file_get_contents($this->tempDir . '/.quality-tools.yaml');
        self::assertStringContainsString('quality-tools:', $content);
        self::assertStringNotContainsString('existing: true', $content);
    }

    public function testRunReturnsErrorForInvalidTemplate(): void
    {
        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'nonexistent');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(1, $result->exitCode);
        self::assertTrue($result->hasErrors());

        $errorMessages = $result->getMessagesBySeverity(MessageSeverity::Error);
        self::assertStringContainsString('Invalid template', $errorMessages[0]->text);
    }

    public function testRunUsesCorrectTemplate(): void
    {
        $runner = $this->createRunner();
        $request = new ConfigInitRequest(template: 'typo3-extension');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $content = file_get_contents($this->tempDir . '/.quality-tools.yaml');
        self::assertStringContainsString('Classes/', $content);
    }

    private function createRunner(): ConfigInitRunner
    {
        $templateGenerator = new ConfigurationTemplateGenerator();
        $configLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configLoader->method('findConfigurationFile')
            ->willReturnCallback(function (string $projectRoot): ?string {
                $path = $projectRoot . '/.quality-tools.yaml';

                return file_exists($path) ? $path : null;
            });

        $filesystemService = $this->createMock(FilesystemService::class);
        $filesystemService->method('writeFile')
            ->willReturnCallback(function (string $path, string $content): void {
                file_put_contents($path, $content);
            });

        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigInitRunner(
            $templateGenerator,
            $configLoader,
            $filesystemService,
            $projectEnv,
        );
    }
}
