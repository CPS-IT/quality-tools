<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationHierarchy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for configuration hierarchy and precedence rules.
 */
#[CoversClass(ConfigurationHierarchy::class)]
final class ConfigurationHierarchyTest extends TestCase
{
    private string $tempDir;
    private ConfigurationHierarchy $hierarchy;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/quality-tools-test-' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        $this->hierarchy = new ConfigurationHierarchy($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testPrecedenceLevelsAreCorrectlyOrdered(): void
    {
        $expectedOrder = [
            'command_line',
            'project_root',
            'config_dir',
            'tool_specific',
            'tool_config_dir',
            'package_config',
            'global',
            'package_defaults',
        ];

        $this->assertEquals($expectedOrder, ConfigurationHierarchy::PRECEDENCE_LEVELS);
    }

    public function testGetPrecedenceLevel(): void
    {
        $this->assertEquals(0, $this->hierarchy->getPrecedenceLevel('command_line'));
        $this->assertEquals(1, $this->hierarchy->getPrecedenceLevel('project_root'));
        $this->assertEquals(6, $this->hierarchy->getPrecedenceLevel('global'));
        $this->assertEquals(7, $this->hierarchy->getPrecedenceLevel('package_defaults'));
        $this->assertEquals(8, $this->hierarchy->getPrecedenceLevel('unknown_source'));
    }

    public function testHasHigherPrecedence(): void
    {
        $this->assertTrue($this->hierarchy->hasHigherPrecedence('command_line', 'project_root'));
        $this->assertTrue($this->hierarchy->hasHigherPrecedence('project_root', 'package_defaults'));
        $this->assertFalse($this->hierarchy->hasHigherPrecedence('package_defaults', 'command_line'));
    }

    public function testGetConfigurationFilePathsReturnsExpectedStructure(): void
    {
        $paths = $this->hierarchy->getConfigurationFilePaths();

        $this->assertIsArray($paths);
        $this->assertArrayHasKey('project_root', $paths);
        $this->assertArrayHasKey('config_dir', $paths);
        $this->assertArrayHasKey('tool_specific', $paths);
        $this->assertArrayHasKey('tool_config_dir', $paths);

        // Check project root files
        $this->assertContains($this->tempDir . '/quality-tools.yaml', $paths['project_root']);
        $this->assertContains($this->tempDir . '/.quality-tools.yaml', $paths['project_root']);
        $this->assertContains($this->tempDir . '/quality-tools.yml', $paths['project_root']);

        // Check config directory files
        $this->assertContains($this->tempDir . '/config/quality-tools.yaml', $paths['config_dir']);

        // Check tool-specific files
        $this->assertContains($this->tempDir . '/rector.php', $paths['tool_specific']);
        $this->assertContains($this->tempDir . '/phpstan.neon', $paths['tool_specific']);
    }

    public function testGetExistingConfigurationFilesWithNoFiles(): void
    {
        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();
        $this->assertEmpty($existingFiles);
    }

    public function testGetExistingConfigurationFilesWithProjectConfig(): void
    {
        // Create a project configuration file
        file_put_contents($this->tempDir . '/quality-tools.yaml', 'quality-tools: {}');

        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        $this->assertArrayHasKey('project_root', $existingFiles);
        $this->assertCount(1, $existingFiles['project_root']);

        $fileInfo = $existingFiles['project_root'][0];
        $this->assertEquals($this->tempDir . '/quality-tools.yaml', $fileInfo['path']);
        $this->assertEquals('yaml', $fileInfo['type']);
        $this->assertNull($fileInfo['tool']);
    }

    public function testGetExistingConfigurationFilesWithToolConfig(): void
    {
        // Create a tool-specific configuration file
        file_put_contents($this->tempDir . '/rector.php', '<?php return [];');

        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        $this->assertArrayHasKey('tool_specific', $existingFiles);
        $this->assertCount(1, $existingFiles['tool_specific']);

        $fileInfo = $existingFiles['tool_specific'][0];
        $this->assertEquals($this->tempDir . '/rector.php', $fileInfo['path']);
        $this->assertEquals('php', $fileInfo['type']);
        $this->assertEquals('rector', $fileInfo['tool']);
    }

    public function testGetExistingConfigurationFilesWithMultipleConfigs(): void
    {
        // Create multiple configuration files
        file_put_contents($this->tempDir . '/quality-tools.yaml', 'quality-tools: {}');
        mkdir($this->tempDir . '/config', 0o777, true);
        file_put_contents($this->tempDir . '/config/quality-tools.yaml', 'quality-tools: {}');
        file_put_contents($this->tempDir . '/rector.php', '<?php return [];');
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters: {}');

        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        $this->assertArrayHasKey('project_root', $existingFiles);
        $this->assertArrayHasKey('config_dir', $existingFiles);
        $this->assertArrayHasKey('tool_specific', $existingFiles);

        $this->assertCount(1, $existingFiles['project_root']);
        $this->assertCount(1, $existingFiles['config_dir']);
        $this->assertCount(2, $existingFiles['tool_specific']);
    }

    public function testHasToolConfigOverrideWithNoConfig(): void
    {
        $this->assertFalse($this->hierarchy->hasToolConfigOverride('rector'));
        $this->assertFalse($this->hierarchy->hasToolConfigOverride('phpstan'));
    }

    public function testHasToolConfigOverrideWithRectorConfig(): void
    {
        file_put_contents($this->tempDir . '/rector.php', '<?php return [];');

        $this->assertTrue($this->hierarchy->hasToolConfigOverride('rector'));
        $this->assertFalse($this->hierarchy->hasToolConfigOverride('phpstan'));
    }

    public function testHasToolConfigOverrideWithConfigDirConfig(): void
    {
        mkdir($this->tempDir . '/config', 0o777, true);
        file_put_contents($this->tempDir . '/config/phpstan.neon', 'parameters: {}');

        $this->assertTrue($this->hierarchy->hasToolConfigOverride('phpstan'));
        $this->assertFalse($this->hierarchy->hasToolConfigOverride('rector'));
    }

    public function testGetDebugInfoReturnsComprehensiveInformation(): void
    {
        // Create some test files
        file_put_contents($this->tempDir . '/quality-tools.yaml', 'quality-tools: {}');
        file_put_contents($this->tempDir . '/rector.php', '<?php return [];');

        $debugInfo = $this->hierarchy->getDebugInfo();

        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('project_root', $debugInfo);
        $this->assertArrayHasKey('package_root', $debugInfo);
        $this->assertArrayHasKey('precedence_levels', $debugInfo);
        $this->assertArrayHasKey('all_potential_files', $debugInfo);
        $this->assertArrayHasKey('existing_files', $debugInfo);
        $this->assertArrayHasKey('tool_overrides', $debugInfo);

        $this->assertEquals($this->tempDir, $debugInfo['project_root']);
        $this->assertEquals(ConfigurationHierarchy::PRECEDENCE_LEVELS, $debugInfo['precedence_levels']);

        // Check tool overrides
        $this->assertTrue($debugInfo['tool_overrides']['rector']);
        $this->assertFalse($debugInfo['tool_overrides']['phpstan']);
    }

    public function testToolConfigFileMappings(): void
    {
        $expectedMappings = [
            'rector' => ['rector.php'],
            'phpstan' => ['phpstan.neon', 'phpstan.neon.dist'],
            'php-cs-fixer' => ['.php-cs-fixer.dist.php', '.php-cs-fixer.php'],
            'typoscript-lint' => ['typoscript-lint.yml'],
            'fractor' => ['fractor.php'],
        ];

        $this->assertEquals($expectedMappings, ConfigurationHierarchy::TOOL_CONFIG_FILES);
    }

    public function testFilePatternDefinitions(): void
    {
        $patterns = ConfigurationHierarchy::FILE_PATTERNS;

        $this->assertArrayHasKey('project_root', $patterns);
        $this->assertArrayHasKey('config_dir', $patterns);
        $this->assertArrayHasKey('tool_specific', $patterns);
        $this->assertArrayHasKey('tool_config_dir', $patterns);
        $this->assertArrayHasKey('package_config', $patterns);

        // Check some specific patterns
        $this->assertContains('quality-tools.yaml', $patterns['project_root']);
        $this->assertContains('config/quality-tools.yaml', $patterns['config_dir']);
        $this->assertContains('rector.php', $patterns['tool_specific']);
        $this->assertContains('config/rector.php', $patterns['tool_config_dir']);
    }

    public function testMergeStrategies(): void
    {
        $expectedStrategies = [
            'arrays' => 'merge_unique',
            'objects' => 'deep_merge',
            'scalars' => 'override',
            'paths' => 'resolve_relative',
        ];

        $this->assertEquals($expectedStrategies, ConfigurationHierarchy::MERGE_STRATEGIES);
    }

    public function testSpecialKeys(): void
    {
        $expectedSpecialKeys = [
            'paths' => 'path_resolution',
            'exclude' => 'path_resolution',
            'scan' => 'path_resolution',
            'config_file' => 'tool_config_override',
        ];

        $this->assertEquals($expectedSpecialKeys, ConfigurationHierarchy::SPECIAL_KEYS);
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
