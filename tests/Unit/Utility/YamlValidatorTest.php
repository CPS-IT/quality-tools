<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Utility\YamlValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class YamlValidatorTest extends TestCase
{
    private YamlValidator $yamlValidator;
    private Filesystem $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->yamlValidator = new YamlValidator();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/yaml-validator-test-' . uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testValidateYamlFilesWithValidFiles(): void
    {
        // Create valid YAML files
        $this->createYamlFile('valid1.yaml', "key1: value1\nkey2: value2");
        $this->createYamlFile('valid2.yml', "items:\n  - item1\n  - item2");

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(2, $results['summary']['total']);
        $this->assertSame(2, $results['summary']['valid']);
        $this->assertSame(0, $results['summary']['invalid']);
        $this->assertCount(2, $results['valid']);
        $this->assertEmpty($results['invalid']);
    }

    public function testValidateYamlFilesWithInvalidFiles(): void
    {
        // Create invalid YAML files
        $this->createYamlFile('invalid.yaml', 'invalid: yaml: content: [');
        $this->createYamlFile('empty.yml', '');
        $this->createYamlFile('string_only.yaml', 'just a string');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(3, $results['summary']['total']);
        $this->assertSame(0, $results['summary']['valid']);
        $this->assertSame(3, $results['summary']['invalid']);
        $this->assertEmpty($results['valid']);
        $this->assertCount(3, $results['invalid']);
    }

    public function testValidateYamlFilesWithMixedFiles(): void
    {
        // Create mix of valid and invalid files
        $this->createYamlFile('valid.yaml', 'key: value');
        $this->createYamlFile('invalid.yaml', 'invalid: [yaml');
        $this->createYamlFile('empty.yml', '');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(3, $results['summary']['total']);
        $this->assertSame(1, $results['summary']['valid']);
        $this->assertSame(2, $results['summary']['invalid']);
        $this->assertCount(1, $results['valid']);
        $this->assertCount(2, $results['invalid']);
    }

    public function testValidateYamlFilesWithNonYamlFiles(): void
    {
        // Create non-YAML files (should be ignored)
        $this->createFile('test.php', '<?php echo "hello";');
        $this->createFile('test.txt', 'text content');
        $this->createYamlFile('valid.yaml', 'key: value');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(1, $results['summary']['total']);
        $this->assertSame(1, $results['summary']['valid']);
        $this->assertSame(0, $results['summary']['invalid']);
    }

    public function testValidateEmptyDirectory(): void
    {
        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(0, $results['summary']['total']);
        $this->assertSame(0, $results['summary']['valid']);
        $this->assertSame(0, $results['summary']['invalid']);
        $this->assertEmpty($results['valid']);
        $this->assertEmpty($results['invalid']);
    }

    public function testValidateSubdirectories(): void
    {
        // Create YAML files in subdirectories
        $subDir = $this->tempDir . '/subdir';
        $this->filesystem->mkdir($subDir);

        $this->createYamlFile('root.yaml', 'root: value');
        $this->createFile($subDir . '/sub.yaml', 'sub: value');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(2, $results['summary']['total']);
        $this->assertSame(2, $results['summary']['valid']);
    }

    public function testGetProblematicFilesSummary(): void
    {
        $this->createYamlFile('invalid.yaml', 'invalid: [yaml');
        $this->createYamlFile('empty.yml', '');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);
        $summary = $this->yamlValidator->getProblematicFilesSummary($results);

        $this->assertCount(2, $summary);
        $this->assertStringContainsString('invalid.yaml: Parse error:', $summary[0] ?? '');
        $this->assertStringContainsString('empty.yml: Empty file', $summary[1] ?? '');
    }

    public function testGetProblematicFilePaths(): void
    {
        $this->createYamlFile('invalid.yaml', 'invalid: [yaml');
        $this->createYamlFile('valid.yaml', 'valid: yaml');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);
        $problematicPaths = $this->yamlValidator->getProblematicFilePaths($results);

        $this->assertCount(1, $problematicPaths);
        $this->assertStringEndsWith('invalid.yaml', $problematicPaths[0]);
    }

    public function testValidateStringYamlReturnsWrongType(): void
    {
        // YAML that parses to string instead of array (Fractor requirement)
        $this->createYamlFile('string.yaml', 'just a string value');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(1, $results['summary']['invalid']);
        $this->assertSame('wrong_type', $results['invalid'][0]['type']);
        $this->assertStringContainsString('string instead of array', $results['invalid'][0]['error']);
    }

    public function testValidateParseError(): void
    {
        // Invalid YAML syntax
        $this->createYamlFile('parse_error.yaml', 'invalid: yaml: [syntax');

        $results = $this->yamlValidator->validateYamlFiles($this->tempDir);

        $this->assertSame(1, $results['summary']['invalid']);
        $this->assertSame('parse_error', $results['invalid'][0]['type']);
        $this->assertStringContainsString('Parse error:', $results['invalid'][0]['error']);
    }

    private function createYamlFile(string $filename, string $content): void
    {
        $this->createFile($filename, $content);
    }

    private function createFile(string $filename, string $content): void
    {
        $filePath = $this->tempDir . '/' . $filename;
        $this->filesystem->dumpFile($filePath, $content);
    }
}
