<?php

declare(strict_types=1);

/**
 * Test script to validate interface implementations work correctly
 * and DI switching is possible.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Configuration\ConfigurationInterface;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\EnhancedConfiguration;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\FilesystemService;
use Symfony\Component\Filesystem\Filesystem;

echo "🔄 Testing interface implementations...\n\n";

// Test Configuration implements ConfigurationInterface
echo "1. Testing Configuration implements ConfigurationInterface: ";
$config = Configuration::createDefault();
if ($config instanceof ConfigurationInterface) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    exit(1);
}

// Test basic interface methods work
echo "2. Testing Configuration interface methods: ";
$phpVersion = $config->getProjectPhpVersion();
$projectName = $config->getProjectName();
$scanPaths = $config->getScanPaths();
$isEnabled = $config->isToolEnabled('rector');
$conflicts = $config->getConfigurationConflicts(); // Should be empty for simple config
$isHierarchical = $config->isHierarchicalConfiguration(); // Should be false

if ($phpVersion === '8.3' && 
    $scanPaths === ['packages/', 'config/system/'] &&
    $isEnabled === true &&
    $conflicts === [] &&
    $isHierarchical === false) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL - Values: phpVersion=$phpVersion, isHierarchical=$isHierarchical\n";
    exit(1);
}

// Test YamlConfigurationLoader implements ConfigurationLoaderInterface
echo "3. Testing YamlConfigurationLoader implements ConfigurationLoaderInterface: ";
$validator = new ConfigurationValidator();
$security = new SecurityService();
$filesystem = new FilesystemService(new Filesystem());
$loader = new YamlConfigurationLoader($validator, $security, $filesystem);

if ($loader instanceof ConfigurationLoaderInterface) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    exit(1);
}

// Test loader interface methods
echo "4. Testing YamlConfigurationLoader interface methods: ";
$projectRoot = __DIR__;
$supportsConfig = $loader->supportsConfiguration($projectRoot);
$debugInfo = $loader->getConfigurationDebugInfo($projectRoot);
$sources = $loader->getConfigurationSources($projectRoot);
$isHierarchical = $loader->hasHierarchicalConfiguration($projectRoot);

if (is_bool($supportsConfig) && 
    is_array($debugInfo) &&
    is_array($sources) &&
    $isHierarchical === false &&
    $debugInfo['loader_type'] === 'simple') {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    var_dump($debugInfo);
    exit(1);
}

// Test HierarchicalConfigurationLoader implements ConfigurationLoaderInterface  
echo "5. Testing HierarchicalConfigurationLoader implements ConfigurationLoaderInterface: ";
$hierarchicalLoader = new HierarchicalConfigurationLoader($validator, $security, $filesystem);

if ($hierarchicalLoader instanceof ConfigurationLoaderInterface) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    exit(1);
}

// Test EnhancedConfiguration implements ConfigurationInterface
echo "6. Testing EnhancedConfiguration implements ConfigurationInterface: ";
$enhancedConfig = new EnhancedConfiguration(
    data: Configuration::createDefault()->toArray(),
    sourceMap: ['quality-tools.project.php_version' => 'package_defaults'],
    conflicts: [],
    mergeSummary: ['sources_count' => 1]
);

if ($enhancedConfig instanceof ConfigurationInterface) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    exit(1);
}

// Test enhanced config specific methods
echo "7. Testing EnhancedConfiguration specific methods: ";
$source = $enhancedConfig->getConfigurationSource('quality-tools.project.php_version');
$hasConflicts = $enhancedConfig->hasConfigurationConflicts();
$isHierarchical = $enhancedConfig->isHierarchicalConfiguration();
$mergeSummary = $enhancedConfig->getMergeSummary();

if ($source === 'package_defaults' &&
    $hasConflicts === false &&
    $isHierarchical === false &&
    $mergeSummary['sources_count'] === 1) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL - source=$source, hasConflicts=$hasConflicts\n";
    exit(1);
}

// Test that both configurations return same basic data
echo "8. Testing interface compatibility between implementations: ";
$simpleConfig = Configuration::createDefault();
$enhancedConfigFromSame = new EnhancedConfiguration($simpleConfig->toArray());

$simplePhpVersion = $simpleConfig->getProjectPhpVersion();
$enhancedPhpVersion = $enhancedConfigFromSame->getProjectPhpVersion();
$simpleScanPaths = $simpleConfig->getScanPaths();
$enhancedScanPaths = $enhancedConfigFromSame->getScanPaths();

if ($simplePhpVersion === $enhancedPhpVersion &&
    $simpleScanPaths === $enhancedScanPaths) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL\n";
    exit(1);
}

// Test interface type hinting works
echo "9. Testing interface type hinting: ";

function testConfigInterface(ConfigurationInterface $config): string {
    return $config->getProjectPhpVersion();
}

function testLoaderInterface(ConfigurationLoaderInterface $loader): bool {
    return $loader->hasHierarchicalConfiguration(__DIR__);
}

try {
    $phpVersionSimple = testConfigInterface($simpleConfig);
    $phpVersionEnhanced = testConfigInterface($enhancedConfigFromSame);
    $isHierarchicalSimple = testLoaderInterface($loader);
    $isHierarchicalAdvanced = testLoaderInterface($hierarchicalLoader);

    if ($phpVersionSimple === $phpVersionEnhanced &&
        $isHierarchicalSimple === false &&
        $isHierarchicalAdvanced === true) {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL - versions don't match or hierarchical detection wrong\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 All interface tests passed!\n";
echo "✅ Configuration interface implementations are working correctly\n";
echo "✅ DI switching is possible via interface type hints\n";
echo "✅ Both simple and enhanced configurations provide compatible interfaces\n";
echo "✅ Both simple and hierarchical loaders provide compatible interfaces\n";
echo "\nStep 1.1 of Issue 019 Phase 1 completed successfully! 🚀\n";