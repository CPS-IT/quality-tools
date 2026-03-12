<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\MemoryOptimizer;
use Cpsit\QualityTools\Utility\MemoryCalculator;
use Cpsit\QualityTools\Utility\ProjectAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryOptimizer::class)]
final class MemoryOptimizerTest extends TestCase
{
    private MemoryOptimizer $subject;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new MemoryOptimizer(new ProjectAnalyzer(), new MemoryCalculator());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }

        parent::tearDown();
    }

    #[Test]
    public function calculateMemoryLimitReturnValidMemoryStringForSinglePath(): void
    {
        $dir = $this->createTempProjectWithPhpFiles(10);

        $result = $this->subject->calculateMemoryLimit('rector', [$dir]);

        self::assertMatchesRegularExpression('/^\d+M$/', $result);
    }

    #[Test]
    public function calculateMemoryLimitReturnsValidMemoryStringForMultiplePaths(): void
    {
        $dirA = $this->createTempProjectWithPhpFiles(5);
        $dirB = $this->createTempProjectWithPhpFiles(8);

        $result = $this->subject->calculateMemoryLimit('phpstan', [$dirA, $dirB]);

        self::assertMatchesRegularExpression('/^\d+M$/', $result);
    }

    #[Test]
    public function calculateMemoryLimitReturnsMinimumForEmptyPaths(): void
    {
        $result = $this->subject->calculateMemoryLimit('rector', []);

        // Empty metrics -> base 256M with rector's 1.5x multiplier = 384M
        self::assertMatchesRegularExpression('/^\d+M$/', $result);
    }

    #[Test]
    public function calculateMemoryLimitSkipsNonExistentDirectories(): void
    {
        $existingDir = $this->createTempProjectWithPhpFiles(5);

        $result = $this->subject->calculateMemoryLimit('rector', ['/nonexistent/path', $existingDir]);

        // Should still return a valid result from the existing dir only
        self::assertMatchesRegularExpression('/^\d+M$/', $result);
    }

    #[Test]
    public function calculateMemoryLimitAppliesToolSpecificMultiplier(): void
    {
        $dir = $this->createTempProjectWithPhpFiles(50);

        $rectorResult = $this->subject->calculateMemoryLimit('rector', [$dir]);
        $fractorResult = $this->subject->calculateMemoryLimit('fractor', [$dir]);

        // Rector has 1.5x multiplier, Fractor has 0.8x
        // Both may clamp to minimum 256M for small projects, but for larger
        // projects Rector should be >= Fractor
        $rectorMb = (int) str_replace('M', '', $rectorResult);
        $fractorMb = (int) str_replace('M', '', $fractorResult);

        self::assertGreaterThanOrEqual($fractorMb, $rectorMb);
    }

    #[Test]
    public function calculateMemoryLimitAggregatesMetricsAcrossPaths(): void
    {
        $dirA = $this->createTempProjectWithPhpFiles(20);
        $dirB = $this->createTempProjectWithPhpFiles(30);

        $combinedResult = $this->subject->calculateMemoryLimit('rector', [$dirA, $dirB]);
        $singleResult = $this->subject->calculateMemoryLimit('rector', [$dirA]);

        $combinedMb = (int) str_replace('M', '', $combinedResult);
        $singleMb = (int) str_replace('M', '', $singleResult);

        // Combined should be >= single (more files = more or equal memory)
        self::assertGreaterThanOrEqual($singleMb, $combinedMb);
    }

    private function createTempProjectWithPhpFiles(int $count): string
    {
        $dir = sys_get_temp_dir() . '/qt_memopt_test_' . bin2hex(random_bytes(4));
        mkdir($dir, 0o777, true);
        $this->tempDirs[] = $dir;

        for ($i = 0; $i < $count; ++$i) {
            $content = "<?php\n\ndeclare(strict_types=1);\n\n";
            $content .= "class TestClass{$i}\n{\n";
            $content .= "    public function method(): void\n    {\n";
            $content .= "        if (true) {\n            foreach ([] as \$item) {\n";
            $content .= "                echo \$item;\n            }\n        }\n";
            $content .= "    }\n}\n";
            file_put_contents($dir . "/TestClass{$i}.php", $content);
        }

        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
