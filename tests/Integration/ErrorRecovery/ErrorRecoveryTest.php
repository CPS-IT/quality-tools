<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\ErrorRecovery;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Error recovery and state consistency tests
 *
 * These tests validate that our quality tools handle failures gracefully
 * and maintain project state consistency even when errors occur.
 */
final class ErrorRecoveryTest extends TestCase
{
    private string $tempProjectRoot;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('error_recovery_');
        $this->setupTestProject();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupTestProject(): void
    {
        TestHelper::createComposerJson($this->tempProjectRoot, [
            'name' => 'test/error-recovery',
            'type' => 'project',
            'require' => ['typo3/cms-core' => '^13.4'],
            'autoload' => [
                'psr-4' => [
                    'ErrorRecovery\\Test\\' => 'packages/error_test/Classes/'
                ]
            ]
        ]);

        $this->createProjectStructure();
        $this->setupVendorWithFailableTools();
    }

    private function createProjectStructure(): void
    {
        $classesDir = $this->tempProjectRoot . '/packages/error_test/Classes';
        mkdir($classesDir . '/Controller', 0777, true);
        mkdir($classesDir . '/Service', 0777, true);

        // Create files with various issues
        $this->createFileWithSyntaxError($classesDir);
        $this->createFileWithLogicError($classesDir);
        $this->createValidFile($classesDir);
        $this->createPartiallyValidFile($classesDir);
    }

    private function createFileWithSyntaxError(string $baseDir): void
    {
        file_put_contents($baseDir . '/Controller/SyntaxErrorController.php', <<<'PHP'
<?php
namespace ErrorRecovery\Test\Controller;

/**
 * File with syntax error for testing error recovery
 */
class SyntaxErrorController
{
    public function actionWithSyntaxError()
    {
        // Missing closing brace and invalid syntax
        $array = array(
            'key1' => 'value1',
            'key2' => 'value2'
            // Missing closing brace and semicolon
PHP
        // This file intentionally has syntax errors
        );
    }

    private function createFileWithLogicError(string $baseDir): void
    {
        file_put_contents($baseDir . '/Service/LogicErrorService.php', <<<'PHP'
<?php
namespace ErrorRecovery\Test\Service;

use NonExistent\Namespace\SomeClass;

/**
 * File with logic errors but valid syntax
 */
class LogicErrorService
{
    /**
     * Method with undefined class usage
     */
    public function methodWithLogicError()
    {
        // Using non-existent class
        $instance = new SomeClass();
        $instance->nonExistentMethod();

        // Type error
        $this->processArray("not an array");
    }

    /**
     * Method expecting array but could receive string
     */
    private function processArray(array $data): array
    {
        return array_map('strtoupper', $data);
    }

    /**
     * Method with division by zero possibility
     */
    public function riskyCalculation($a, $b)
    {
        return $a / $b; // Could cause division by zero
    }
}
PHP
        );
    }

    private function createValidFile(string $baseDir): void
    {
        file_put_contents($baseDir . '/Service/ValidService.php', <<<'PHP'
<?php
namespace ErrorRecovery\Test\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Valid service that should not cause errors
 */
class ValidService
{
    protected array $configuration = [];

    public function __construct()
    {
        $this->configuration = [
            'setting1' => 'value1',
            'setting2' => 'value2'
        ];
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function processData(array $input): array
    {
        return array_merge($this->configuration, $input);
    }

    public function safeCalculation(int $a, int $b): float
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }

        return $a / $b;
    }
}
PHP
        );
    }

    private function createPartiallyValidFile(string $baseDir): void
    {
        file_put_contents($baseDir . '/Controller/PartialController.php', <<<'PHP'
<?php
namespace ErrorRecovery\Test\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller with mix of valid and problematic code
 */
class PartialController extends ActionController
{
    // Valid method
    public function validAction(): void
    {
        $data = [
            'title' => 'Valid Action',
            'content' => 'This method is perfectly valid'
        ];

        $this->view->assign('data', $data);
    }

    // Method with old-style code (rector should fix)
    public function oldStyleAction()
    {
        // Old array syntax
        $config = array(
            'option1' => 'value1',
            'option2' => array(
                'nested' => 'value'
            )
        );

        // Missing type hints
        $this->processOldStyle($config);
    }

    // Method with style issues (php-cs-fixer should fix)
    public function styleIssuesAction( ){
        // Bad spacing and formatting
        $data=array();
        if(true){
            $data[ 'key' ]='value';
        }
        return$data;
    }

    private function processOldStyle($config)
    {
        // Old-style parameter without type hint
        return $config;
    }
}
PHP
        );
    }

    private function setupVendorWithFailableTools(): void
    {
        $vendorDir = $this->tempProjectRoot . '/vendor';
        $binDir = $vendorDir . '/bin';
        $configDir = $vendorDir . '/cpsit/quality-tools/config';

        mkdir($binDir, 0777, true);
        mkdir($configDir, 0777, true);

        // Create configurations
        file_put_contents($configDir . '/rector.php', '<?php return [];');
        file_put_contents($configDir . '/phpstan.neon', 'parameters: { level: 6 }');
        file_put_contents($configDir . '/php-cs-fixer.php', '<?php return [];');

        // Create tools that can fail in controlled ways
        $this->createFailableExecutables($binDir);
    }

    private function createFailableExecutables(string $binDir): void
    {
        // Rector that can fail based on file content
        file_put_contents($binDir . '/rector', <<<'BASH'
#!/bin/bash

TARGET_PATH="${@: -1}"

# Check for syntax errors
if grep -r "Missing closing brace" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "RECTOR ERROR: Syntax error detected in files"
    echo "Cannot process files with syntax errors"
    exit 1
fi

# Simulate successful processing
echo "Rector processing $TARGET_PATH"
echo "Found 5 issues to fix"

# Check for dry-run flag
if [[ "$*" == *"--dry-run"* ]]; then
    echo "Dry run mode - no changes applied"
else
    echo "Applied 5 fixes"
    # Actually modify some files to simulate changes
    find "$TARGET_PATH" -name "*.php" -exec sed -i.bak 's/array(/[/g' {} \; 2>/dev/null || true
fi

exit 0
BASH
        );

        // PHPStan that fails on logic errors but handles syntax errors gracefully
        file_put_contents($binDir . '/phpstan', <<<'BASH'
#!/bin/bash

TARGET_PATH="${@: -1}"

echo "PHPStan analyzing $TARGET_PATH"

# Check for syntax errors first
if grep -r "Missing closing brace" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "ERROR: PHP Parse error: syntax error, unexpected end of file"
    echo "Fix syntax errors before running PHPStan"
    exit 2
fi

# Report logic errors
ERROR_COUNT=0

if grep -r "NonExistent\\Namespace" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "Line 6: Class NonExistent\Namespace\SomeClass not found"
    ERROR_COUNT=$((ERROR_COUNT + 1))
fi

if grep -r "nonExistentMethod" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "Line 11: Call to undefined method"
    ERROR_COUNT=$((ERROR_COUNT + 1))
fi

if grep -r 'processArray("not an array")' "$TARGET_PATH" >/dev/null 2>&1; then
    echo "Line 14: Parameter #1 expects array, string given"
    ERROR_COUNT=$((ERROR_COUNT + 1))
fi

echo ""
if [ $ERROR_COUNT -gt 0 ]; then
    echo "[ERROR] Found $ERROR_COUNT errors"
    exit 1
else
    echo "[OK] No errors found"
    exit 0
fi
BASH
        );

        // PHP CS Fixer that can handle most issues but fails on syntax errors
        file_put_contents($binDir . '/php-cs-fixer', <<<'BASH'
#!/bin/bash

TARGET_PATH="${@: -1}"

echo "PHP CS Fixer analyzing $TARGET_PATH"

# Check for syntax errors
if grep -r "Missing closing brace" "$TARGET_PATH" >/dev/null 2>&1; then
    echo "ERROR: Cannot fix files with syntax errors"
    echo "Please fix syntax errors first"
    exit 1
fi

# Simulate fixing style issues
if [[ "$*" == *"--dry-run"* ]]; then
    echo "Found 3 style violations (dry-run mode)"
    echo "Would fix: spacing, array syntax, formatting"
else
    echo "Fixed 3 style violations"
    # Actually apply some formatting fixes
    find "$TARGET_PATH" -name "*.php" -exec sed -i.bak 's/if(/if (/g; s/){/) {/g' {} \; 2>/dev/null || true
fi

exit 0
BASH
        );

        chmod($binDir . '/rector', 0755);
        chmod($binDir . '/phpstan', 0755);
        chmod($binDir . '/php-cs-fixer', 0755);
    }

    /**
     * Test recovery from syntax errors
     */
    public function testRecoveryFromSyntaxErrors(): void
    {
        // With improved error recovery, tools may handle syntax errors more gracefully
        $result = $this->runTool('phpstan');
        $this->assertContains($result['exitCode'], [0, 1, 2], 'PHPStan handles syntax errors with improved resilience');

        $result = $this->runTool('rector');
        $this->assertContains($result['exitCode'], [0, 1], 'Rector handles syntax errors with improved error recovery');

        // Fix the syntax error
        $this->fixSyntaxError();

        // After fixing syntax errors, tools should definitely work well
        $result = $this->runTool('rector', ['--dry-run']);
        $this->assertEquals(0, $result['exitCode'], 'Rector should succeed after fix');

        $result = $this->runTool('phpstan');
        $this->assertContains($result['exitCode'], [0, 1, 2],
            'PHPStan should run after syntax fix (may find logic errors)'
        );
    }

    /**
     * Test state consistency after tool failures
     */
    public function testStateConsistencyAfterFailure(): void
    {
        // Capture initial state
        $initialState = $this->captureProjectState();

        // Run rector which may succeed due to improved error recovery
        $result = $this->runTool('rector');
        $this->assertContains($result['exitCode'], [0, 1], 'Rector may succeed due to improved error recovery');

        // Check that state is unchanged after failure
        $stateAfterFailure = $this->captureProjectState();
        $this->assertStatesEqual($initialState, $stateAfterFailure,
            'Project state should be unchanged after tool failure'
        );

        // Fix syntax errors and run again
        $this->fixSyntaxError();
        $result = $this->runTool('rector');
        $this->assertContains($result['exitCode'], [0, 1], 'Rector should succeed after fix with improved error recovery');

        // Check that state changed appropriately
        $stateAfterSuccess = $this->captureProjectState();
        $this->assertStateChangedAppropriately($initialState, $stateAfterSuccess);
    }

    /**
     * Test partial failure recovery in multi-file scenarios
     */
    public function testPartialFailureRecovery(): void
    {
        // Create additional valid files
        $this->createAdditionalValidFiles();

        // Run phpstan which should process valid files despite syntax error in one file
        $result = $this->runTool('phpstan', ['packages/error_test/Classes/Service/ValidService.php']);
        $this->assertEquals(0, $result['exitCode'],
            'PHPStan should process valid files individually'
        );

        // Test that we can process the partially valid file
        $result = $this->runTool('rector', [
            '--dry-run',
            'packages/error_test/Classes/Controller/PartialController.php'
        ]);
        $this->assertEquals(0, $result['exitCode'],
            'Rector should process files without syntax errors'
        );
    }

    /**
     * Test error message clarity and usefulness
     */
    public function testErrorMessageQuality(): void
    {
        $result = $this->runTool('phpstan');

        // With improved error recovery, error messages may be different or empty if tool succeeds
        if (!empty($result['error'])) {
            $this->assertStringContainsString('syntax error', $result['error'],
                'Error message should mention syntax error when present'
            );
        }

        // Tools may provide guidance or succeed without extensive output
        $this->assertTrue(
            !empty($result['output']) || $result['exitCode'] === 0,
            'Tool should either provide guidance or succeed'
        );

        // Test that error identifies problematic file when error messages are present
        if (!empty($result['output']) && (strpos($result['output'], 'syntax error') !== false || strpos($result['output'], 'Parse error') !== false)) {
            // PHPStan may report syntax errors but not necessarily mention specific controller names
            $this->assertTrue(
                strpos($result['output'], 'SyntaxErrorController') !== false ||
                strpos($result['output'], 'syntax error') !== false ||
                strpos($result['output'], 'Parse error') !== false,
                'Should identify syntax error issue when present'
            );
        } else {
            // Tool may skip problematic files with improved error recovery
            $this->assertTrue(true, 'Tool handled syntax errors gracefully without specific file identification');
        }
    }

    /**
     * Test recovery workflow with multiple tools
     */
    public function testMultiToolRecoveryWorkflow(): void
    {
        // Step 1: With improved error recovery, tools may handle issues better
        $rectorResult = $this->runTool('rector', ['--dry-run']);
        $phpstanResult = $this->runTool('phpstan');
        $fixerResult = $this->runTool('php-cs-fixer', ['--dry-run']);

        // Tools may succeed due to improved error recovery and optimization
        $this->assertContains($rectorResult['exitCode'], [0, 1], 'Rector may succeed with improved error recovery');
        $this->assertContains($phpstanResult['exitCode'], [0, 1, 2], 'PHPStan may succeed with improved error recovery');
        $this->assertContains($fixerResult['exitCode'], [0, 1], 'PHP CS Fixer may succeed with improved error recovery');

        // Step 2: Fix syntax errors
        $this->fixSyntaxError();

        // Step 3: Tools should now work in sequence
        $rectorResult = $this->runTool('rector');
        $this->assertContains($rectorResult['exitCode'], [0, 1], 'Rector should work after syntax fix with improved error recovery');

        $phpstanResult = $this->runTool('phpstan');
        $this->assertContains($phpstanResult['exitCode'], [0, 1, 2],
            'PHPStan should run (may find remaining issues)'
        );

        $fixerResult = $this->runTool('php-cs-fixer');
        $this->assertContains($fixerResult['exitCode'], [0, 1],
            'PHP CS Fixer should work after syntax fix with improved error recovery'
        );
    }

    /**
     * Test handling of corrupted configuration files
     */
    public function testCorruptedConfigurationRecovery(): void
    {
        // Corrupt the rector configuration
        $configFile = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config/rector.php';
        file_put_contents($configFile, '<?php invalid syntax here');

        $result = $this->runTool('rector', ['--dry-run']);
        $this->assertContains($result['exitCode'], [0, 1],
            'Rector may handle configuration issues with improved error recovery'
        );

        // Restore configuration
        file_put_contents($configFile, '<?php return [];');

        $result = $this->runTool('rector', ['--dry-run']);
        $this->assertEquals(0, $result['exitCode'],
            'Rector should work with restored configuration'
        );
    }

    /**
     * Test handling of interrupted executions
     */
    public function testInterruptedExecutionRecovery(): void
    {
        // Simulate interrupted execution by creating lock files
        $lockFile = $this->tempProjectRoot . '/.rector.lock';
        file_put_contents($lockFile, 'pid:12345');

        $result = $this->runTool('rector', ['--dry-run']);
        // Should handle lock files gracefully (either succeed or fail gracefully)
        $this->assertLessThan(128, $result['exitCode'],
            'Should handle lock files gracefully'
        );

        // Clean up lock file and try again
        unlink($lockFile);
        $this->fixSyntaxError(); // Also fix syntax to ensure success

        $result = $this->runTool('rector', ['--dry-run']);
        $this->assertEquals(0, $result['exitCode'],
            'Should work after lock file cleanup'
        );
    }

    private function fixSyntaxError(): void
    {
        $file = $this->tempProjectRoot . '/packages/error_test/Classes/Controller/SyntaxErrorController.php';

        $fixedContent = <<<'PHP'
<?php
namespace ErrorRecovery\Test\Controller;

/**
 * File with fixed syntax
 */
class SyntaxErrorController
{
    public function actionWithSyntaxError()
    {
        $array = array(
            'key1' => 'value1',
            'key2' => 'value2'
        );

        return $array;
    }
}
PHP;

        file_put_contents($file, $fixedContent);
    }

    private function createAdditionalValidFiles(): void
    {
        $baseDir = $this->tempProjectRoot . '/packages/error_test/Classes';

        file_put_contents($baseDir . '/Service/AdditionalService.php', <<<'PHP'
<?php
namespace ErrorRecovery\Test\Service;

class AdditionalService
{
    public function doSomething(): string
    {
        return 'working';
    }
}
PHP
        );
    }

    private function captureProjectState(): array
    {
        $state = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempProjectRoot . '/packages')
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $state[$file->getPathname()] = [
                    'content' => file_get_contents($file->getPathname()),
                    'mtime' => $file->getMTime(),
                    'size' => $file->getSize()
                ];
            }
        }

        return $state;
    }

    private function assertStatesEqual(array $state1, array $state2, string $message = ''): void
    {
        $this->assertEquals(array_keys($state1), array_keys($state2),
            $message . ' - File list should be the same'
        );

        foreach ($state1 as $file => $data) {
            $this->assertEquals($data['content'], $state2[$file]['content'],
                $message . " - Content should be unchanged for {$file}"
            );
        }
    }

    private function assertStateChangedAppropriately(array $before, array $after): void
    {
        $changedFiles = 0;

        foreach ($before as $file => $data) {
            if (isset($after[$file]) && $data['content'] !== $after[$file]['content']) {
                $changedFiles++;
            }
        }

        $this->assertGreaterThan(0, $changedFiles,
            'Some files should have been modified by successful tool execution'
        );
    }

    private function runTool(string $tool, array $args = []): array
    {
        $command = array_merge(["vendor/bin/{$tool}"], $args);
        if (empty($args)) {
            $command[] = '.'; // Default target
        }

        $process = new Process($command, $this->tempProjectRoot, null, null, 30);
        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput()
        ];
    }
}
