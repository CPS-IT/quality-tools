<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Builds dynamic tool configurations based on resolved paths
 * 
 * This class generates tool-specific configurations that include
 * resolved paths from the path scanning system.
 */
final class ConfigurationBuilder
{
    private Configuration $configuration;
    
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Build Rector configuration with resolved paths
     */
    public function buildRectorConfiguration(): array
    {
        $paths = $this->configuration->getResolvedPathsForTool('rector');
        $config = $this->configuration->getRectorConfig();
        
        return [
            'paths' => $paths,
            'php_version' => $config['php_version'],
            'level' => $config['level'],
            'enabled' => $config['enabled'],
            'project_root' => $this->configuration->getProjectRoot(),
            'vendor_path' => $this->configuration->getVendorPath(),
        ];
    }

    /**
     * Build Fractor configuration with resolved paths
     */
    public function buildFractorConfiguration(): array
    {
        $paths = $this->configuration->getResolvedPathsForTool('fractor');
        $config = $this->configuration->getFractorConfig();
        
        return [
            'paths' => $paths,
            'indentation' => $config['indentation'],
            'enabled' => $config['enabled'],
            'project_root' => $this->configuration->getProjectRoot(),
            'vendor_path' => $this->configuration->getVendorPath(),
        ];
    }

    /**
     * Build PHPStan configuration with resolved paths
     */
    public function buildPhpStanConfiguration(): array
    {
        $paths = $this->configuration->getResolvedPathsForTool('phpstan');
        $config = $this->configuration->getPhpStanConfig();
        
        return [
            'paths' => $paths,
            'level' => $config['level'],
            'memory_limit' => $config['memory_limit'],
            'enabled' => $config['enabled'],
            'project_root' => $this->configuration->getProjectRoot(),
            'vendor_path' => $this->configuration->getVendorPath(),
        ];
    }

    /**
     * Build PHP CS Fixer configuration with resolved paths
     */
    public function buildPhpCsFixerConfiguration(): array
    {
        $paths = $this->configuration->getResolvedPathsForTool('php-cs-fixer');
        $config = $this->configuration->getPhpCsFixerConfig();
        
        return [
            'paths' => $paths,
            'preset' => $config['preset'],
            'enabled' => $config['enabled'],
            'project_root' => $this->configuration->getProjectRoot(),
            'vendor_path' => $this->configuration->getVendorPath(),
        ];
    }

    /**
     * Build TypoScript Lint configuration with resolved paths
     */
    public function buildTypoScriptLintConfiguration(): array
    {
        $paths = $this->configuration->getResolvedPathsForTool('typoscript-lint');
        $config = $this->configuration->getTypoScriptLintConfig();
        
        return [
            'paths' => $paths,
            'indentation' => $config['indentation'],
            'enabled' => $config['enabled'],
            'project_root' => $this->configuration->getProjectRoot(),
            'vendor_path' => $this->configuration->getVendorPath(),
        ];
    }

    /**
     * Get configuration for any tool
     */
    public function buildToolConfiguration(string $tool): array
    {
        return match ($tool) {
            'rector' => $this->buildRectorConfiguration(),
            'fractor' => $this->buildFractorConfiguration(),
            'phpstan' => $this->buildPhpStanConfiguration(),
            'php-cs-fixer' => $this->buildPhpCsFixerConfiguration(),
            'typoscript-lint' => $this->buildTypoScriptLintConfiguration(),
            default => throw new \InvalidArgumentException(sprintf('Unknown tool: %s', $tool))
        };
    }

    /**
     * Generate configuration content for tools that need file-based configs
     */
    public function generateConfigurationFileContent(string $tool): string
    {
        $config = $this->buildToolConfiguration($tool);
        
        return match ($tool) {
            'rector' => $this->generateRectorConfigContent($config),
            'fractor' => $this->generateFractorConfigContent($config),
            default => throw new \InvalidArgumentException(sprintf('File-based configuration not supported for tool: %s', $tool))
        };
    }

    /**
     * Generate Rector configuration file content
     */
    private function generateRectorConfigContent(array $config): string
    {
        $paths = var_export($config['paths'], true);
        $phpVersion = $config['php_version'];
        
        return <<<PHP
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return RectorConfig::configure()
    ->withPaths({$paths})
    ->withPhpVersion(PhpVersion::PHP_{$phpVersion[0]}{$phpVersion[2]})
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withPHPStanConfigs([Typo3Option::PHPSTAN_FOR_RECTOR_PATH])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withConfiguredRule(ExtEmConfRector::class, [
        ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.2.0-8.3.99',
        ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '13.4.0-13.4.99',
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ])
    ->withSkip([
        '**/Configuration/ExtensionBuilder/*',
        NameImportingPostRector::class => [
            'ext_localconf.php',
            'ext_tables.php',
        ],
    ]);
PHP;
    }

    /**
     * Generate Fractor configuration file content
     */
    private function generateFractorConfigContent(array $config): string
    {
        $paths = var_export($config['paths'], true);
        $indentation = $config['indentation'];
        
        return <<<PHP
<?php

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConfiguration;

return FractorConfiguration::configure()
    ->withPaths({$paths})
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withOptions([
        TypoScriptProcessorOption::INDENT_SIZE => {$indentation},
        TypoScriptProcessorOption::INDENT_CHARACTER => PrettyPrinterConfiguration::INDENTATION_STYLE_SPACES,
        TypoScriptProcessorOption::ADD_CLOSING_GLOBAL => true,
        TypoScriptProcessorOption::INCLUDE_EMPTY_LINE_BREAKS => true,
        TypoScriptProcessorOption::INDENT_CONDITIONS => true,
    ]);
PHP;
    }
}