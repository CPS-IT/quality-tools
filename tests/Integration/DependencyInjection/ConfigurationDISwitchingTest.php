<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\DependencyInjection;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for dependency injection mode switching between simple and hierarchical configurations.
 *
 * @deprecated mode switching concept obsolete with unified ConfigurationLoader auto-detection
 *
 * Tests the critical capability to switch between configuration implementations via DI container
 * configuration without code changes - essential for safe evolutionary refactoring
 */
final class ConfigurationDISwitchingTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('di_switching_test_');

        // Create test configuration file
        $testConfig = <<<YAML
            quality-tools:
              project:
                name: "di-switch-test"
                php_version: "8.4"
              tools:
                rector:
                  enabled: true
                  level: "typo3-13"
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $testConfig);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test that container can be configured to use simple mode and loads correctly.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testDIContainerSimpleMode(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test that container can be configured to use hierarchical mode and loads correctly.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testDIContainerHierarchicalMode(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test switching from simple to hierarchical mode produces equivalent basic results.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testDIModeSwitchingConsistency(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test rollback capability - switching from hierarchical back to simple mode.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testDIRollbackCapability(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test that commands resolve dependencies correctly in both modes.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testCommandDependencyResolutionInBothModes(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test QualityToolsApplication with different DI configurations.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testApplicationWithDifferentDIModes(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test performance difference between modes (should be minimal).
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testPerformanceDifferenceBetweenModes(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }

    /**
     * Test that mode changes are isolated and don't affect other containers.
     *
     * @deprecated Mode switching obsolete with unified ConfigurationLoader
     */
    public function testModeIsolationBetweenContainers(): void
    {
        $this->markTestSkipped('Mode switching obsolete - ConfigurationLoader auto-detects mode');
    }
}
