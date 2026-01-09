<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use Cpsit\QualityTools\Utility\ProjectMetrics;
use PHPUnit\Framework\TestCase;

final class ProjectAnalyzerTest extends TestCase
{
    private ProjectAnalyzer $projectAnalyzer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->projectAnalyzer = new ProjectAnalyzer();
        $this->tempDir = sys_get_temp_dir() . '/qt_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testAnalyzeProjectWithEmptyDirectory(): void
    {
        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertInstanceOf(ProjectMetrics::class, $metrics);
        $this->assertEquals(0, $metrics->getTotalFileCount());
        $this->assertEquals(0, $metrics->getPhpFileCount());
        $this->assertEquals('small', $metrics->getProjectSize());
    }

    public function testAnalyzeProjectWithPhpFiles(): void
    {
        $this->createFile('test.php', '<?php echo "hello world"; function test() { return true; }');
        $this->createFile('src/Class.php', '<?php class TestClass { public function method() { if (true) { return 1; } } }');

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertEquals(2, $metrics->getTotalFileCount());
        $this->assertEquals(2, $metrics->getPhpFileCount());
        $this->assertGreaterThan(0, $metrics->getPhpComplexityScore());
        $this->assertEquals('small', $metrics->getProjectSize());
    }

    public function testAnalyzeProjectWithMixedFileTypes(): void
    {
        $this->createFile('test.php', '<?php echo "test";');
        $this->createFile('config.yaml', 'key: value');
        $this->createFile('data.json', '{"test": true}');
        $this->createFile('config.xml', '<?xml version="1.0"?><root></root>');
        $this->createFile('setup.ts', 'plugin.tx_test.value = 1');

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertEquals(5, $metrics->getTotalFileCount());
        $this->assertEquals(1, $metrics->getPhpFileCount());
        $this->assertEquals(1, $metrics->yaml['fileCount']);
        $this->assertEquals(1, $metrics->json['fileCount']);
        $this->assertEquals(1, $metrics->xml['fileCount']);
        // The .ts file should be detected as TypoScript based on content
        $this->assertEquals(1, $metrics->typoscript['fileCount'] ?? $metrics->other['fileCount']);
    }

    public function testAnalyzeProjectExcludesVendorDirectory(): void
    {
        $this->createFile('test.php', '<?php echo "test";');
        $this->createFile('vendor/package/test.php', '<?php echo "vendor";');

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertEquals(1, $metrics->getTotalFileCount());
        $this->assertEquals(1, $metrics->getPhpFileCount());
    }

    public function testAnalyzeProjectWithInvalidDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a directory');

        $this->projectAnalyzer->analyzeProject('/non/existent/path');
    }

    public function testProjectSizeClassification(): void
    {
        for ($i = 0; $i < 150; ++$i) {
            $this->createFile("file_$i.php", '<?php echo "test";');
        }

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertEquals('medium', $metrics->getProjectSize());
        $this->assertEquals(150, $metrics->getTotalFileCount());
    }

    public function testTypoScriptFileDetection(): void
    {
        $this->createFile('setup.ts', 'plugin.tx_test.value = 1');
        $this->createFile('constants.txt', 'styles.content.get = CONTENT');
        $this->createFile('regular.txt', 'This is just regular text');

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        // Both files should be detected as TypoScript based on content
        $this->assertEquals(2, $metrics->typoscript['fileCount']);
        $this->assertEquals(1, $metrics->other['fileCount']);
    }

    public function testComplexityEstimation(): void
    {
        $complexCode = '<?php
        class TestClass {
            public function complexMethod($param) {
                if ($param) {
                    for ($i = 0; $i < 10; $i++) {
                        if ($i % 2 === 0) {
                            while (true) {
                                break;
                            }
                        }
                    }
                    switch ($param) {
                        case 1:
                            return true;
                        case 2:
                            return false;
                    }
                }
                return null;
            }
        }';

        $this->createFile('complex.php', $complexCode);

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertGreaterThan(10, $metrics->getPhpComplexityScore());
    }

    public function testFileLinesCounting(): void
    {
        $multiLineCode = "<?php\necho 'line 1';\necho 'line 2';\necho 'line 3';";
        $this->createFile('multiline.php', $multiLineCode);

        $metrics = $this->projectAnalyzer->analyzeProject($this->tempDir);

        $this->assertEquals(4, $metrics->getPhpLines());
    }

    private function createFile(string $path, string $content): void
    {
        $fullPath = $this->tempDir . '/' . $path;
        $directory = \dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents($fullPath, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
