<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Runner;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Runner\ConfigShowRunner;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigShowRequest;
use Cpsit\QualityTools\Messaging\BufferingOutputCollector;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigShowRunner::class)]
final class ConfigShowRunnerTest extends TestCase
{
    private string $tempDir;
    private string|false $originalProjectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_show_runner_test_');
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
        $runner = $this->createRunner();
        $request = new ConfigShowRequest(format: 'yaml');

        $description = $runner->describe($request);

        self::assertEquals('config-show', $description->operationName);
        self::assertEquals('yaml', $description->info['format']);
    }

    public function testRunOutputsYamlFormat(): void
    {
        $configData = [
            'quality-tools' => [
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        $runner = $this->createRunner(configData: $configData);
        $request = new ConfigShowRequest(format: 'yaml');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $output = $collector->getCollected();
        self::assertNotEmpty($output);
        $text = $output[0]['text'];
        self::assertStringContainsString('quality-tools', $text);
    }

    public function testRunOutputsJsonFormat(): void
    {
        $configData = [
            'quality-tools' => [
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        $runner = $this->createRunner(configData: $configData);
        $request = new ConfigShowRequest(format: 'json');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $output = $collector->getCollected();
        self::assertNotEmpty($output);
        $decoded = json_decode($output[0]['text'], true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('quality-tools', $decoded);
    }

    public function testRunReturnsErrorForInvalidFormat(): void
    {
        $runner = $this->createRunner();
        $request = new ConfigShowRequest(format: 'xml');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(1, $result->exitCode);
        self::assertTrue($result->hasErrors());

        $errors = $result->getMessagesBySeverity(MessageSeverity::Error);
        self::assertStringContainsString('Format must be either', $errors[0]->text);
    }

    public function testRunIncludesSourceMessages(): void
    {
        $configData = [
            'quality-tools' => [
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];
        $sources = [
            ['source' => 'package_defaults', 'file_path' => null],
        ];

        $runner = $this->createRunner(configData: $configData, sources: $sources);
        $request = new ConfigShowRequest(format: 'yaml');
        $collector = new BufferingOutputCollector();

        $result = $runner->run($request, $collector);

        self::assertEquals(0, $result->exitCode);

        $infoMessages = $result->getMessagesBySeverity(MessageSeverity::Info);
        $texts = array_map(static fn ($m): string => $m->text, $infoMessages);
        self::assertNotEmpty(array_filter($texts, static fn ($t): bool => str_contains((string) $t, 'Package defaults')));
    }

    /**
     * @param array<string, mixed>                                $configData
     * @param list<array{source: string, file_path: string|null}> $sources
     * @param array<string, string>                               $errors
     */
    private function createRunner(
        array $configData = [],
        array $sources = [],
        array $errors = [],
    ): ConfigShowRunner {
        $configLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configLoader->method('findConfigurationFile')->willReturn(null);

        if ($configData !== []) {
            $configuration = $this->createMock(ConfigurationInterface::class);
            $configuration->method('toArray')->willReturn($configData);
            $configLoader->method('load')->willReturn($configuration);
        }

        $configLoader->method('getConfigurationSources')->willReturn($sources);
        $configLoader->method('getConfigurationErrors')->willReturn($errors);

        $projectEnv = new ProjectEnvironment(new VendorDirectoryDetector());

        return new ConfigShowRunner($configLoader, $projectEnv);
    }
}
