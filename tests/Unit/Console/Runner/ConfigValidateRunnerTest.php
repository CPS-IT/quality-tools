<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\Runner\ConfigValidateRunner;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigValidateRequest;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigValidateRunner::class)]
final class ConfigValidateRunnerTest extends TestCase
{
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_validate_runner_test_');
        $this->originalProjectRoot = getenv('QT_PROJECT_ROOT');
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;
        $_SERVER['QT_PROJECT_ROOT'] = $this->tempDir;
        VendorDirectoryDetector::clearCache();

        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
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

    public function testDescribeReturnsOperationName(): void
    {
        $runner = $this->createRunner(configFile: null);
        $request = new ConfigValidateRequest();

        $description = $runner->describe($request);

        self::assertEquals('config-validate', $description->operationName);
    }

    public function testDescribeReturnsConfigPathWhenExists(): void
    {
        $configPath = $this->tempDir . '/.quality-tools.yaml';
        $runner = $this->createRunner(configFile: $configPath);
        $request = new ConfigValidateRequest();

        $description = $runner->describe($request);

        self::assertEquals($configPath, $description->configPath);
    }

    public function testRunReturnsWarningWhenNoConfigFound(): void
    {
        $runner = $this->createRunner(configFile: null);
        $request = new ConfigValidateRequest();
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);
        self::assertTrue($result->hasWarnings());

        $warnings = $result->getMessagesBySeverity(MessageSeverity::Warning);
        self::assertStringContainsString('No YAML configuration file found', $warnings[0]->text);
    }

    public function testRunReportsValidConfiguration(): void
    {
        $configPath = $this->tempDir . '/.quality-tools.yaml';
        $configData = [
            'quality-tools' => [
                'tools' => [
                    'rector' => ['enabled' => true],
                ],
            ],
        ];

        $runner = $this->createRunner(configFile: $configPath, configData: $configData);
        $request = new ConfigValidateRequest();
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $collectorOutput = $collector->getOutput();
        self::assertStringContainsString('Configuration is valid.', $collectorOutput);
    }

    public function testRunReportsInvalidConfigFilePaths(): void
    {
        $configPath = $this->tempDir . '/.quality-tools.yaml';
        $configData = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'config_file' => 'nonexistent/rector.php',
                    ],
                ],
            ],
        ];

        $runner = $this->createRunner(configFile: $configPath, configData: $configData);
        $request = new ConfigValidateRequest();
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);
        self::assertTrue($result->hasWarnings());

        $warnings = $result->getMessagesBySeverity(MessageSeverity::Warning);
        $texts = array_map(static fn ($m): string => $m->text, $warnings);
        self::assertNotEmpty(array_filter($texts, static fn ($t): bool => str_contains((string) $t, 'config_file')));
    }

    public function testRunSkipsAbsoluteConfigFilePaths(): void
    {
        $configPath = $this->tempDir . '/.quality-tools.yaml';
        $configData = [
            'quality-tools' => [
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'config_file' => '/absolute/path/rector.php',
                    ],
                ],
            ],
        ];

        $runner = $this->createRunner(configFile: $configPath, configData: $configData);
        $request = new ConfigValidateRequest();
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);
        // No warnings about absolute paths
        $warnings = $result->getMessagesBySeverity(MessageSeverity::Warning);
        $texts = array_map(static fn ($m): string => $m->text, $warnings);
        self::assertEmpty(array_filter($texts, static fn ($t): bool => str_contains((string) $t, 'config_file')));
    }

    public function testRunIncludesSummaryMessages(): void
    {
        $configPath = $this->tempDir . '/.quality-tools.yaml';
        $configData = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.3',
                    'typo3_version' => '13.4',
                ],
                'tools' => [
                    'rector' => ['enabled' => true],
                    'phpstan' => ['enabled' => true],
                ],
                'paths' => [
                    'scan' => ['packages/', 'config/system/'],
                ],
            ],
        ];

        $runner = $this->createRunner(configFile: $configPath, configData: $configData);
        $request = new ConfigValidateRequest();
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        $infoMessages = $result->getMessagesBySeverity(MessageSeverity::Info);
        $texts = array_map(static fn ($m): string => $m->text, $infoMessages);

        self::assertNotEmpty(array_filter($texts, static fn ($t): bool => str_contains((string) $t, 'test-project')));
        self::assertNotEmpty(array_filter($texts, static fn ($t): bool => str_contains((string) $t, 'rector')));
    }

    /**
     * @param array<string, mixed> $configData
     */
    private function createRunner(?string $configFile, array $configData = []): ConfigValidateRunner
    {
        $configLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configLoader->method('findConfigurationFile')->willReturn($configFile);

        if ($configData !== []) {
            $configuration = $this->createMock(ConfigurationInterface::class);
            $configuration->method('toArray')->willReturn($configData);
            $configLoader->method('load')->willReturn($configuration);
        }

        $configValidator = new ConfigurationValidator();
        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigValidateRunner($configLoader, $configValidator, $projectEnv);
    }
}
