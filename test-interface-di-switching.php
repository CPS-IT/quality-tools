<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Test;

use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;

/**
 * Test demonstrating interface-based DI switching during refactoring.
 * 
 * This shows how interfaces enable clean dependency injection to switch
 * between implementations during the refactoring phases.
 */

// Example of how BaseCommand would be modified to use interfaces
class TestBaseCommand
{
    private ?ConfigurationInterface $configuration = null;

    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader
    ) {}

    public function getConfiguration(string $projectRoot): ConfigurationInterface
    {
        if ($this->configuration === null) {
            $this->configuration = $this->configurationLoader->load($projectRoot);
        }
        return $this->configuration;
    }

    // All existing methods work unchanged
    public function getProjectPhpVersion(string $projectRoot): string
    {
        return $this->getConfiguration($projectRoot)->getProjectPhpVersion();
    }

    public function getResolvedPathsForTool(string $projectRoot, string $tool): array
    {
        return $this->getConfiguration($projectRoot)->getResolvedPathsForTool($tool);
    }
}

// Example of how ConfigShowCommand would be modified
class TestConfigShowCommand  
{
    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader
    ) {}

    public function execute(string $projectRoot): array
    {
        // Load with hierarchical capabilities
        $configuration = $this->configurationLoader->load($projectRoot);
        
        $result = [
            'config' => $configuration->toArray(),
        ];

        // Enhanced features work if implementation supports them
        if ($configuration->isHierarchicalConfiguration()) {
            $result['sources'] = $configuration->getConfigurationSources();
            $result['conflicts'] = $configuration->getConfigurationConflicts();
            $result['debug'] = $configuration->getComprehensiveDebugInfo();
        }

        return $result;
    }
}

// DI Container configuration during refactoring phases
class DIConfigurationTest
{
    /**
     * Phase 1-2: Use simple configuration wrapper
     */
    public function configurePhase1(): array
    {
        return [
            ConfigurationLoaderInterface::class => SimpleConfigurationLoaderWrapper::class,
            ConfigurationInterface::class => SimpleConfigurationWrapper::class,
        ];
    }

    /**
     * Phase 3: Switch to hierarchical for specific commands
     */
    public function configurePhase3(): array
    {
        return [
            // Different loaders for different commands
            'config.loader.simple' => SimpleConfigurationLoaderWrapper::class,
            'config.loader.hierarchical' => HierarchicalConfigurationLoaderWrapper::class,
            
            // Factory to decide which loader to use
            ConfigurationLoaderInterface::class => ConfigurationLoaderFactory::class,
        ];
    }

    /**
     * Phase 6: Final unified implementation
     */
    public function configureFinal(): array
    {
        return [
            ConfigurationLoaderInterface::class => UnifiedConfigurationLoader::class,
            ConfigurationInterface::class => UnifiedConfiguration::class,
        ];
    }
}

// Factory pattern for switching implementations during refactoring
class ConfigurationLoaderFactory implements ConfigurationLoaderInterface
{
    public function __construct(
        private readonly SimpleConfigurationLoaderWrapper $simpleLoader,
        private readonly HierarchicalConfigurationLoaderWrapper $hierarchicalLoader,
        private readonly string $mode = 'simple'
    ) {}

    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        return match($this->mode) {
            'simple' => $this->simpleLoader->load($projectRoot, $commandLineOverrides),
            'hierarchical' => $this->hierarchicalLoader->load($projectRoot, $commandLineOverrides),
            default => throw new \InvalidArgumentException("Unknown mode: {$this->mode}")
        };
    }

    // Delegate all other methods to appropriate loader based on mode
    public function findConfigurationFile(string $projectRoot): ?string
    {
        return $this->getCurrentLoader()->findConfigurationFile($projectRoot);
    }

    public function supportsConfiguration(string $projectRoot): bool
    {
        return $this->getCurrentLoader()->supportsConfiguration($projectRoot);
    }

    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
    {
        // Force hierarchical for tool-specific loading
        return $this->hierarchicalLoader->loadForTool($projectRoot, $tool, $commandLineOverrides);
    }

    public function hasHierarchicalConfiguration(string $projectRoot): bool
    {
        return $this->hierarchicalLoader->hasHierarchicalConfiguration($projectRoot);
    }

    public function getConfigurationErrors(string $projectRoot): array
    {
        return $this->hierarchicalLoader->getConfigurationErrors($projectRoot);
    }

    public function getConfigurationDebugInfo(string $projectRoot): array
    {
        return $this->getCurrentLoader()->getConfigurationDebugInfo($projectRoot);
    }

    public function getConfigurationSources(string $projectRoot): array
    {
        return $this->hierarchicalLoader->getConfigurationSources($projectRoot);
    }

    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
    {
        return $this->hierarchicalLoader->previewMergedConfiguration($projectRoot, $commandLineOverrides);
    }

    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface
    {
        return $this->simpleLoader->load($projectRoot);
    }

    private function getCurrentLoader(): ConfigurationLoaderInterface
    {
        return match($this->mode) {
            'simple' => $this->simpleLoader,
            'hierarchical' => $this->hierarchicalLoader,
            default => $this->simpleLoader
        };
    }
}

/**
 * Benefits of interface approach:
 * 
 * 1. CLEAN DI SWITCHING:
 *    - Commands depend on interfaces, not concrete classes
 *    - Container configuration switches implementations
 *    - No code changes in consuming classes during refactoring
 * 
 * 2. GRADUAL MIGRATION:
 *    - Phase 1: Simple wrapper implements interface
 *    - Phase 2: Enhanced wrapper implements interface  
 *    - Phase 3: Factory chooses implementation per command
 *    - Phase 6: Unified class implements interface
 * 
 * 3. TYPE SAFETY:
 *    - Interface contract ensures all methods are implemented
 *    - PHP type system catches missing methods during refactoring
 *    - IDE provides proper autocomplete throughout transition
 * 
 * 4. TESTING BENEFITS:
 *    - Mock interfaces instead of concrete classes
 *    - Test different implementations with same test suite
 *    - Contract tests ensure interface compliance
 * 
 * 5. ROLLBACK SAFETY:
 *    - Each phase can be rolled back by changing DI configuration
 *    - No code changes needed in commands/consumers
 *    - Quick switching between implementations for comparison
 */

// Example test demonstrating rollback capability
class RollbackTest
{
    private DIContainer $container;

    public function testCanSwitchImplementations(): void
    {
        // Phase 2: Test with wrapper
        $this->container->configure([
            ConfigurationLoaderInterface::class => ConfigurationWrapperLoader::class
        ]);
        
        $command = $this->container->get(TestBaseCommand::class);
        $result1 = $command->getProjectPhpVersion('/project');
        
        // Rollback to Phase 1: Switch to simple
        $this->container->configure([
            ConfigurationLoaderInterface::class => SimpleConfigurationLoader::class
        ]);
        
        $command = $this->container->get(TestBaseCommand::class);  
        $result2 = $command->getProjectPhpVersion('/project');
        
        // Results should be identical - proves rollback safety
        assert($result1 === $result2);
    }
}