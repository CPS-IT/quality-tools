<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Support;

/**
 * Test configuration builder for creating test configurations.
 */
final class ConfigurationBuilder
{
    private array $config = [];
    
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Create a new configuration builder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Reset to default configuration.
     */
    public function reset(): self
    {
        $this->config = [
            'quality-tools' => [
                'project' => [
                    'name' => 'test-project',
                    'php_version' => '8.3',
                ],
                'tools' => [],
            ],
        ];
        
        return $this;
    }

    /**
     * Set project name.
     */
    public function withProject(string $name, string $phpVersion = '8.3'): self
    {
        $this->config['quality-tools']['project'] = [
            'name' => $name,
            'php_version' => $phpVersion,
        ];
        
        return $this;
    }

    /**
     * Add tool configuration.
     */
    public function withTool(string $tool, array $config): self
    {
        $this->config['quality-tools']['tools'][$tool] = $config;
        
        return $this;
    }

    /**
     * Add rector configuration.
     */
    public function withRector(array $config = []): self
    {
        $defaultConfig = [
            'enabled' => true,
        ];
        
        $this->config['quality-tools']['tools']['rector'] = array_merge($defaultConfig, $config);
        
        return $this;
    }

    /**
     * Add rector with custom config file.
     */
    public function withRectorConfigFile(string $configFile): self
    {
        return $this->withRector(['config_file' => $configFile]);
    }

    /**
     * Add PHPStan configuration.
     */
    public function withPhpstan(array $config = []): self
    {
        $defaultConfig = [
            'enabled' => true,
            'level' => 6,
        ];
        
        $this->config['quality-tools']['tools']['phpstan'] = array_merge($defaultConfig, $config);
        
        return $this;
    }

    /**
     * Add PHPStan with custom config file.
     */
    public function withPhpstanConfigFile(string $configFile): self
    {
        return $this->withPhpstan(['config_file' => $configFile]);
    }

    /**
     * Add paths configuration.
     */
    public function withPaths(array $scanPaths = [], array $excludePatterns = []): self
    {
        if (!empty($scanPaths)) {
            $this->config['quality-tools']['paths']['scan'] = $scanPaths;
        }
        
        if (!empty($excludePatterns)) {
            $this->config['quality-tools']['paths']['exclude_patterns'] = $excludePatterns;
        }
        
        return $this;
    }

    /**
     * Add performance configuration.
     */
    public function withPerformance(int $memoryLimit = null, int $phpstanMemoryLimit = null): self
    {
        if ($memoryLimit !== null) {
            $this->config['quality-tools']['performance']['memory_limit'] = $memoryLimit;
        }
        
        if ($phpstanMemoryLimit !== null) {
            $this->config['quality-tools']['performance']['phpstan_memory_limit'] = $phpstanMemoryLimit;
        }
        
        return $this;
    }

    /**
     * Build the configuration array.
     */
    public function build(): array
    {
        return $this->config;
    }

    /**
     * Build as YAML string.
     */
    public function buildYaml(): string
    {
        return \Symfony\Component\Yaml\Yaml::dump($this->config, 4, 2, \Symfony\Component\Yaml\Yaml::DUMP_OBJECT_AS_MAP);
    }

    /**
     * Write configuration to file.
     */
    public function writeToFile(string $filePath): void
    {
        file_put_contents($filePath, $this->buildYaml());
    }

    /**
     * Create configuration for auto-discovery testing.
     */
    public static function forAutoDiscoveryTesting(string $projectName = 'test-auto-discovery'): self
    {
        return self::create()
            ->withProject($projectName)
            ->withRector()  // No config_file - should auto-discover
            ->withPhpstan(); // No config_file - should auto-discover
    }

    /**
     * Create configuration with explicit config files.
     */
    public static function withExplicitConfigFiles(
        string $projectName = 'test-explicit',
        array $configFiles = []
    ): self {
        $builder = self::create()->withProject($projectName);
        
        foreach ($configFiles as $tool => $configFile) {
            $builder->withTool($tool, ['enabled' => true, 'config_file' => $configFile]);
        }
        
        return $builder;
    }

    /**
     * Create minimal configuration.
     */
    public static function minimal(string $projectName = 'test-minimal'): self
    {
        return self::create()->withProject($projectName);
    }
}