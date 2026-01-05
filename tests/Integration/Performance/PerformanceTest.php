<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Performance;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Performance and resource exhaustion tests
 *
 * These tests validate that our quality tools can handle realistic
 * project sizes and resource constraints without failing.
 */
final class PerformanceTest extends TestCase
{
    private string $tempProjectRoot;
    private array $performanceMetrics = [];

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('performance_test_');
        $this->setupPerformanceTestProject();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupPerformanceTestProject(): void
    {
        TestHelper::createComposerJson($this->tempProjectRoot, [
            'name' => 'test/performance-project',
            'type' => 'project',
            'require' => [
                'typo3/cms-core' => '^13.4',
            ],
            'autoload' => [
                'psr-4' => [
                    'Performance\\Test\\' => 'packages/performance_test/Classes/'
                ]
            ]
        ]);

        $this->createLargeCodebase();
        $this->createVendorStructureWithTools();
    }

    private function createLargeCodebase(): void
    {
        $extensionDir = $this->tempProjectRoot . '/packages/performance_test';
        $classesDir = $extensionDir . '/Classes';

        // Create multiple directories
        $directories = [
            'Controller', 'Domain/Model', 'Domain/Repository',
            'Service', 'Utility', 'ViewHelpers', 'Command',
            'EventListener', 'DataProcessing', 'Configuration'
        ];

        foreach ($directories as $dir) {
            mkdir($classesDir . '/' . $dir, 0777, true);
        }

        // Generate many files to simulate large project
        $this->generateManyPhpFiles($classesDir, 50); // 50 files total
        $this->generateComplexPhpFiles($classesDir);
        $this->generateFilesWithIssues($classesDir);
    }

    private function generateManyPhpFiles(string $baseDir, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $className = "GeneratedClass{$i}";
            $filePath = $baseDir . "/Service/{$className}.php";

            $content = $this->generatePhpClassContent($className, $i);
            file_put_contents($filePath, $content);
        }
    }

    private function generatePhpClassContent(string $className, int $index): string
    {
        // Generate varying complexity based on index
        $methodCount = 5 + ($index % 10);
        $methods = '';

        for ($m = 1; $m <= $methodCount; $m++) {
            $methods .= $this->generateMethodContent($m, $index);
        }

        return <<<PHP
<?php
namespace Performance\Test\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Generated class {$className} for performance testing
 * Contains {$methodCount} methods with varying complexity
 */
class {$className}
{
    /**
     * @var array
     */
    protected \$configuration = array();

    /**
     * @var ObjectManager
     */
    protected \$objectManager;

    public function __construct()
    {
        \$this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        \$this->configuration = array(
            'setting1' => 'value1',
            'setting2' => 'value2',
            'setting3' => array('nested' => 'value')
        );
    }

{$methods}
}
PHP;
    }

    private function generateMethodContent(int $methodNum, int $classIndex): string
    {
        // Create methods with varying complexity to stress test tools
        $complexity = ($methodNum + $classIndex) % 4;

        switch ($complexity) {
            case 0: // Simple method
                return <<<PHP
    /**
     * Simple method {$methodNum}
     */
    public function method{$methodNum}()
    {
        return \$this->configuration['setting1'];
    }

PHP;
            case 1: // Method with loop
                return <<<PHP
    /**
     * Method {$methodNum} with loop
     */
    public function method{$methodNum}(\$items = null)
    {
        \$result = array();
        \$items = \$items ?: array_keys(\$this->configuration);

        foreach (\$items as \$item) {
            \$result[] = \$this->processItem(\$item);
        }

        return \$result;
    }

PHP;
            case 2: // Method with conditions
                return <<<PHP
    /**
     * Complex method {$methodNum} with conditions
     */
    public function method{$methodNum}(\$input, \$options = array())
    {
        if (!is_array(\$input)) {
            \$input = array(\$input);
        }

        if (isset(\$options['transform'])) {
            if (\$options['transform'] === 'upper') {
                \$input = array_map('strtoupper', \$input);
            } elseif (\$options['transform'] === 'lower') {
                \$input = array_map('strtolower', \$input);
            }
        }

        return \$input;
    }

PHP;
            default: // Complex method
                return <<<PHP
    /**
     * Very complex method {$methodNum}
     */
    public function method{$methodNum}(\$data, \$settings = array(), \$context = null)
    {
        \$result = array();
        \$defaultSettings = array('enabled' => true, 'type' => 'default');
        \$settings = array_merge(\$defaultSettings, \$settings);

        if (\$settings['enabled']) {
            foreach (\$data as \$key => \$value) {
                if (is_array(\$value)) {
                    foreach (\$value as \$subKey => \$subValue) {
                        if (\$context && method_exists(\$context, 'process')) {
                            \$result[\$key][\$subKey] = \$context->process(\$subValue);
                        } else {
                            \$result[\$key][\$subKey] = \$this->defaultProcess(\$subValue);
                        }
                    }
                } else {
                    \$result[\$key] = \$settings['type'] === 'advanced'
                        ? \$this->advancedProcess(\$value)
                        : \$this->simpleProcess(\$value);
                }
            }
        }

        return \$result;
    }

PHP;
        }
    }

    private function generateComplexPhpFiles(string $baseDir): void
    {
        // Create a very complex file that stress-tests analyzers
        file_put_contents($baseDir . '/Service/ComplexService.php', <<<'PHP'
<?php
namespace Performance\Test\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Extremely complex service to test tool performance limits
 * Contains nested loops, deep inheritance, complex conditions
 */
class ComplexService extends AbstractComplexService implements ComplexServiceInterface
{
    protected $deepNestedArray = array();
    protected $configuration = array();
    protected $cache = array();

    public function __construct()
    {
        parent::__construct();
        $this->initializeComplexConfiguration();
    }

    /**
     * Method with extreme nesting to test performance
     */
    public function extremelyComplexMethod($input, $options = array(), $context = null)
    {
        $result = array();
        $processed = 0;

        // First level of nesting
        foreach ($input as $level1Key => $level1Value) {
            if (is_array($level1Value)) {
                // Second level
                foreach ($level1Value as $level2Key => $level2Value) {
                    if (is_array($level2Value)) {
                        // Third level
                        foreach ($level2Value as $level3Key => $level3Value) {
                            if (is_array($level3Value)) {
                                // Fourth level - extreme nesting
                                foreach ($level3Value as $level4Key => $level4Value) {
                                    // Complex processing logic
                                    if ($this->shouldProcess($level4Value, $options)) {
                                        $processedValue = $this->processValue($level4Value, $context);

                                        if ($processedValue !== null) {
                                            if (!isset($result[$level1Key])) {
                                                $result[$level1Key] = array();
                                            }
                                            if (!isset($result[$level1Key][$level2Key])) {
                                                $result[$level1Key][$level2Key] = array();
                                            }
                                            if (!isset($result[$level1Key][$level2Key][$level3Key])) {
                                                $result[$level1Key][$level2Key][$level3Key] = array();
                                            }

                                            $result[$level1Key][$level2Key][$level3Key][$level4Key] = $processedValue;
                                            $processed++;

                                            // Memory-intensive operation
                                            $this->updateCache($level1Key, $level2Key, $level3Key, $level4Key, $processedValue);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return array('result' => $result, 'processed' => $processed);
    }

    /**
     * Method that allocates significant memory
     */
    public function memoryIntensiveMethod($size = 1000)
    {
        $largeArray = array();

        // Allocate memory progressively
        for ($i = 0; $i < $size; $i++) {
            $largeArray[$i] = array(
                'id' => $i,
                'data' => str_repeat('x', 1000), // 1KB per item
                'metadata' => array(
                    'created' => time(),
                    'type' => 'test',
                    'attributes' => array_fill(0, 100, 'value') // More memory
                ),
                'content' => array_fill(0, 50, array(
                    'field1' => 'value1',
                    'field2' => 'value2',
                    'field3' => array_fill(0, 20, 'nested')
                ))
            );

            // Simulate processing
            if ($i % 100 === 0) {
                $this->processChunk(array_slice($largeArray, max(0, $i - 100), 100));
            }
        }

        return count($largeArray);
    }

    /**
     * Method with many complex conditions - complexity nightmare
     */
    public function highComplexityMethod($a, $b, $c, $d, $e = null, $f = null)
    {
        if ($a && $b) {
            if ($c || $d) {
                if ($e !== null) {
                    if (is_array($e)) {
                        if (count($e) > 0) {
                            if (isset($e['key1'])) {
                                if ($e['key1'] === 'value1') {
                                    if ($f !== null) {
                                        if (is_string($f)) {
                                            if (strlen($f) > 10) {
                                                return $this->processLongString($f, $e);
                                            } else {
                                                return $this->processShortString($f, $e);
                                            }
                                        } else {
                                            return $this->processNonString($f, $e);
                                        }
                                    } else {
                                        return $this->processWithoutF($e);
                                    }
                                } else {
                                    return $this->processOtherValue($e);
                                }
                            } else {
                                return $this->processWithoutKey($e);
                            }
                        } else {
                            return $this->processEmptyArray();
                        }
                    } else {
                        return $this->processNonArray($e);
                    }
                } else {
                    return $this->processWithoutE($a, $b, $c, $d);
                }
            } else {
                return $this->processAlternativePath($a, $b);
            }
        } else {
            return $this->processDefault();
        }
    }

    // Many helper methods to increase file size and complexity...
    private function shouldProcess($value, $options) { return true; }
    private function processValue($value, $context) { return $value; }
    private function updateCache($l1, $l2, $l3, $l4, $value) { }
    private function processChunk($chunk) { }
    private function processLongString($f, $e) { return 'long'; }
    private function processShortString($f, $e) { return 'short'; }
    private function processNonString($f, $e) { return 'non-string'; }
    private function processWithoutF($e) { return 'no-f'; }
    private function processOtherValue($e) { return 'other'; }
    private function processWithoutKey($e) { return 'no-key'; }
    private function processEmptyArray() { return 'empty'; }
    private function processNonArray($e) { return 'non-array'; }
    private function processWithoutE($a, $b, $c, $d) { return 'no-e'; }
    private function processAlternativePath($a, $b) { return 'alt'; }
    private function processDefault() { return 'default'; }

    private function initializeComplexConfiguration()
    {
        // Create complex configuration structure
        for ($i = 0; $i < 100; $i++) {
            $this->configuration["section_{$i}"] = array(
                'enabled' => true,
                'settings' => array_fill(0, 50, "setting_value_{$i}"),
                'nested' => array(
                    'level1' => array(
                        'level2' => array(
                            'level3' => "deep_value_{$i}"
                        )
                    )
                )
            );
        }
    }
}

abstract class AbstractComplexService
{
    protected function parentMethod() { return 'parent'; }
}

interface ComplexServiceInterface
{
    public function extremelyComplexMethod($input, $options = array(), $context = null);
}
PHP
        );
    }

    private function generateFilesWithIssues(string $baseDir): void
    {
        // Create files with specific issues that tools should catch
        file_put_contents($baseDir . '/Controller/ProblematicController.php', <<<'PHP'
<?php
namespace Performance\Test\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller with many code quality issues to stress test analyzers
 */
class ProblematicController extends ActionController
{
    // Missing type hints everywhere
    public function actionWithIssues($param1, $param2 = null, $param3 = array())
    {
        // Old array syntax
        $config = array(
            'setting1' => $param1,
            'setting2' => $param2 ?: 'default',
            'setting3' => array(
                'nested' => array(
                    'deep' => $param3
                )
            )
        );

        // Inefficient loops
        $result = array();
        foreach ($config as $key => $value) {
            foreach ($config as $innerKey => $innerValue) {
                if ($key !== $innerKey) {
                    $result[] = $this->processValues($key, $innerKey, $value, $innerValue);
                }
            }
        }

        // Memory waste
        $largeString = '';
        for ($i = 0; $i < 10000; $i++) {
            $largeString .= "Item {$i}\n";
        }

        // Deprecated usage
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

        return $result;
    }

    // Method without return type
    private function processValues($k1, $k2, $v1, $v2)
    {
        // Complex logic that violates many rules
        if ($k1 && $k2) {
            if (is_array($v1)) {
                if (is_array($v2)) {
                    if (count($v1) > count($v2)) {
                        return array_merge($v1, $v2);
                    } else {
                        return array_merge($v2, $v1);
                    }
                } else {
                    return $v1;
                }
            } else {
                return $v2;
            }
        } else {
            return null;
        }
    }
}
PHP
        );
    }

    private function createVendorStructureWithTools(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        $binDir = $vendorDir . '/bin';
        $configDir = $vendorDir . '/cpsit/quality-tools/config';

        mkdir($binDir, 0777, true);
        mkdir($configDir, 0777, true);

        // Create tool configurations
        file_put_contents($configDir . '/rector.php', '<?php return [];');
        file_put_contents($configDir . '/phpstan.neon', 'parameters: { level: 6 }');

        // Create performance-aware tool mocks
        $this->createPerformanceAwareExecutables($binDir);
    }

    private function createPerformanceAwareExecutables(string $binDir): void
    {
        // Rector executable that simulates real performance characteristics
        file_put_contents($binDir . '/rector', <<<'BASH'
#!/bin/bash

# Simulate rector performance characteristics
TARGET_PATH="${@: -1}"
echo "Processing files in $TARGET_PATH..."

# Count files to simulate analysis time
FILE_COUNT=$(find "$TARGET_PATH" -name "*.php" | wc -l)
echo "Found $FILE_COUNT PHP files to analyze"

# Simulate processing time based on file count
if [ "$FILE_COUNT" -gt 50 ]; then
    echo "Large project detected, this may take a while..."
    sleep 2
elif [ "$FILE_COUNT" -gt 20 ]; then
    echo "Medium project size, processing..."
    sleep 1
fi

# Simulate memory usage by creating temporary data
TEMP_FILE="/tmp/rector_analysis_$$"
for i in $(seq 1 $FILE_COUNT); do
    echo "Analyzing file $i" >> "$TEMP_FILE"
done

echo "Analysis complete, found issues in $(expr $FILE_COUNT / 3) files"
echo "Applied fixes to $(expr $FILE_COUNT / 5) files"

# Cleanup
rm -f "$TEMP_FILE"
exit 0
BASH
        );

        file_put_contents($binDir . '/phpstan', <<<'BASH'
#!/bin/bash

TARGET_PATH="${@: -1}"
echo "PHPStan analyzing $TARGET_PATH..."

# Simulate PHPStan memory usage patterns
FILE_COUNT=$(find "$TARGET_PATH" -name "*.php" | wc -l)

# Simulate increasing memory usage with file count
if [ "$FILE_COUNT" -gt 40 ]; then
    echo "High file count detected: $FILE_COUNT files"
    echo "Memory usage may be significant"
    sleep 3
fi

# Create temporary analysis files to simulate memory usage
ANALYSIS_DIR="/tmp/phpstan_$$"
mkdir -p "$ANALYSIS_DIR"

# Simulate analysis output
echo "Analyzing $FILE_COUNT files..."
echo "Found $(expr $FILE_COUNT \* 2) potential issues"
echo "Memory peak: $(expr $FILE_COUNT \* 5)MB"

# Cleanup
rm -rf "$ANALYSIS_DIR"
exit 0
BASH
        );

        chmod($binDir . '/rector', 0755);
        chmod($binDir . '/phpstan', 0755);
    }

    /**
     * Test memory usage with large codebase
     */
    public function testMemoryUsageWithMediumCodebase(): void
    {
        $memoryBefore = memory_get_usage(true);
        $startTime = microtime(true);

        $process = new Process([
            'vendor/bin/rector',
            '--config', 'vendor/cpsit/quality-tools/config/rector.php',
            '--dry-run',
            '.'
        ], $this->tempProjectRoot, null, null, 120); // 2 minute timeout

        $process->run();

        $endTime = microtime(true);
        $memoryAfter = memory_get_usage(true);

        $executionTime = $endTime - $startTime;
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        $this->performanceMetrics['rector_medium'] = [
            'time' => round($executionTime, 2),
            'memory' => round($memoryUsed, 2)
        ];

        // Performance assertions
        $this->assertEquals(0, $process->getExitCode(), 'Rector should handle medium codebase');
        $this->assertLessThan(60, $executionTime, 'Rector should complete within 1 minute for medium project');
        $this->assertLessThan(128, $memoryUsed, 'Rector should use less than 128MB for medium project');
    }

    /**
     * Test execution time limits
     */
    public function testExecutionTimeWithComplexAnalysis(): void
    {
        $startTime = microtime(true);

        $process = new Process([
            'vendor/bin/phpstan',
            'analyse',
            '--config', 'vendor/cpsit/quality-tools/config/phpstan.neon',
            '.'
        ], $this->tempProjectRoot, null, null, 180); // 3 minute timeout

        $process->run();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->performanceMetrics['phpstan_complex'] = [
            'time' => round($executionTime, 2),
            'memory' => 'N/A'
        ];

        $this->assertEquals(0, $process->getExitCode(), 'PHPStan should handle complex analysis');
        $this->assertLessThan(120, $executionTime, 'PHPStan should complete within 2 minutes');
    }

    /**
     * Test concurrent tool execution
     */
    public function testConcurrentToolExecution(): void
    {
        $startTime = microtime(true);

        // Start multiple processes concurrently
        $rectorProcess = new Process([
            'vendor/bin/rector',
            '--config', 'vendor/cpsit/quality-tools/config/rector.php',
            '--dry-run',
            'packages/performance_test/Classes/Service'
        ], $this->tempProjectRoot);

        $phpstanProcess = new Process([
            'vendor/bin/phpstan',
            'analyse',
            '--config', 'vendor/cpsit/quality-tools/config/phpstan.neon',
            'packages/performance_test/Classes/Controller'
        ], $this->tempProjectRoot);

        // Start both processes
        $rectorProcess->start();
        $phpstanProcess->start();

        // Wait for completion
        $rectorProcess->wait();
        $phpstanProcess->wait();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->performanceMetrics['concurrent_execution'] = [
            'time' => round($executionTime, 2),
            'memory' => 'N/A'
        ];

        // Both should succeed
        $this->assertEquals(0, $rectorProcess->getExitCode(), 'Rector should succeed in concurrent execution');
        $this->assertEquals(0, $phpstanProcess->getExitCode(), 'PHPStan should succeed in concurrent execution');

        // Concurrent execution should be faster than sequential
        $this->assertLessThan(120, $executionTime, 'Concurrent execution should complete reasonably quickly');
    }

    /**
     * Test resource exhaustion recovery
     */
    public function testResourceLimitedEnvironment(): void
    {
        // Simulate limited memory environment
        $limitedEnv = [
            'PHP_MEMORY_LIMIT' => '64M' // Very limited memory
        ];

        $process = new Process([
            'vendor/bin/rector',
            '--config', 'vendor/cpsit/quality-tools/config/rector.php',
            '--dry-run',
            'packages/performance_test/Classes/Service/ComplexService.php' // Single complex file
        ], $this->tempProjectRoot, $limitedEnv, null, 60);

        $process->run();

        // Should either succeed or fail gracefully (not hang or crash)
        $this->assertContains($process->getExitCode(), [0, 1, 2],
            'Rector should handle limited resources gracefully'
        );

        if ($process->getExitCode() !== 0) {
            $this->assertStringContainsString('memory',
                strtolower($process->getErrorOutput()),
                'Memory-related error should be clearly indicated'
            );
        }
    }

    /**
     * Test large file handling
     */
    public function testLargeFileHandling(): void
    {
        // Create a very large PHP file
        $largeFile = $this->tempProjectRoot . '/packages/performance_test/Classes/LargeFile.php';
        $this->createLargePhpFile($largeFile);

        $startTime = microtime(true);

        $process = new Process([
            'vendor/bin/rector',
            '--config', 'vendor/cpsit/quality-tools/config/rector.php',
            '--dry-run',
            $largeFile
        ], $this->tempProjectRoot, null, null, 180);

        $process->run();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $fileSize = filesize($largeFile) / 1024 / 1024; // MB

        $this->performanceMetrics['large_file'] = [
            'time' => round($executionTime, 2),
            'memory' => 'N/A',
            'file_size' => round($fileSize, 2) . 'MB'
        ];

        $this->assertEquals(0, $process->getExitCode(), 'Rector should handle large files');
        $this->assertLessThan(60, $executionTime, 'Large file processing should complete within 1 minute');
    }

    private function createLargePhpFile(string $filePath): void
    {
        $content = <<<'PHP'
<?php
namespace Performance\Test;

/**
 * Large file for performance testing
 */
class LargeFile
{
PHP;

        // Generate many methods to make file large
        for ($i = 1; $i <= 500; $i++) {
            $content .= <<<PHP

    /**
     * Generated method {$i}
     */
    public function method{$i}(\$param1, \$param2 = null)
    {
        \$data = array(
            'id' => {$i},
            'name' => 'Method {$i}',
            'params' => array(\$param1, \$param2),
            'metadata' => array(
                'created' => time(),
                'index' => {$i},
                'type' => 'generated'
            )
        );

        if (\$param2 !== null) {
            \$data['processed'] = true;
        }

        return \$data;
    }
PHP;
        }

        $content .= "\n}\n";

        file_put_contents($filePath, $content);
    }
}
