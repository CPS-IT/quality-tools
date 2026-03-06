<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationDiscovery;
use Cpsit\QualityTools\Configuration\ConfigurationHierarchy;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Service\ErrorFactory;
use Cpsit\QualityTools\Service\ErrorHandler;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use Exception;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractToolCommand extends BaseCommand
{
    private ?ErrorHandler $errorHandler = null;
    private ?OutputInterface $output = null;

    public function __construct(ConfigurationLoaderInterface $configurationLoader)
    {
        parent::__construct($configurationLoader);
    }

    abstract public function getToolName(): string;

    /**
     * Template method that defines the common execution flow for all tool commands.
     * This method provides a consistent structure while allowing tool-specific customization.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Store output for use in helper methods
        $this->output = $output;

        try {
            // Show optimization details by default unless disabled
            if (!$this->isOptimizationDisabled($input)) {
                $this->showOptimizationDetails($input, $output, $this->getToolName());
            }

            // Resolve configuration path
            $configPath = $this->resolveConfigPath($this->getDefaultConfigFileName(), $input->getOption('config'));

            // Validate tool-specific configuration if needed
            $this->validateToolConfig($input, $output, $configPath);

            // Handle path resolution
            $targetPaths = $this->resolveTargetPaths($input, $output);

            // Build the command array for the specific tool
            $command = $this->buildToolCommand($input, $output, $configPath, $targetPaths);

            // Execute pre-processing hooks if needed
            $this->executePreProcessingHooks($input, $output, $targetPaths);

            // Determine memory limit
            $memoryLimit = $this->getToolMemoryLimit($input, $output);

            // Execute the process
            $exitCode = $this->executeProcess($command, $input, $output, $memoryLimit, $this->getToolName());

            // Execute post-processing hooks if needed
            $this->executePostProcessingHooks($input, $output, $exitCode);

            return $exitCode;
        } catch (\Throwable $e) {
            // Handle cleanup on exception
            $this->handleExecutionException($e, $input, $output);

            // Use enhanced error handling
            return $this->getErrorHandler()->handleException($e, $output, $output->isVerbose());
        }
    }

    /**
     * Get the default configuration file name for the tool.
     */
    abstract protected function getDefaultConfigFileName(): string;

    /**
     * Build the command array for the specific tool.
     */
    abstract protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array;

    /**
     * Validate tool-specific configuration (optional override).
     */
    protected function validateToolConfig(InputInterface $input, OutputInterface $output, string $configPath): void
    {
        // Default: no validation required
    }

    /**
     * Resolve target paths for the tool.
     */
    protected function resolveTargetPaths(InputInterface $input, OutputInterface $output): array
    {
        $customPath = $input->getOption('path');

        if ($customPath !== null) {
            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->directoryExists($customPath)) {
                throw ErrorFactory::directoryNotFound($customPath);
            }

            $resolvedPath = $filesystemService->realpath($customPath);
            if ($output->isVerbose()) {
                $output->writeln(\sprintf('<comment>Analyzing custom path: %s</comment>', $customPath));
            }

            return [$resolvedPath];
        }

        // Use resolved paths from configuration
        $resolvedPaths = $this->getResolvedPathsForTool($input, $this->getToolName());

        if ($output->isVerbose()) {
            if (!empty($resolvedPaths)) {
                $output->writeln(\sprintf(
                    '<comment>Analyzing resolved paths: %s</comment>',
                    implode(', ', $resolvedPaths),
                ));
            } else {
                $output->writeln('<comment>Using default path discovery</comment>');
            }
        }

        return $resolvedPaths;
    }

    /**
     * Get memory limit for the tool.
     */
    protected function getToolMemoryLimit(InputInterface $input, OutputInterface $output): ?string
    {
        if ($this->isOptimizationDisabled($input)) {
            return null;
        }

        $memoryLimit = $this->getOptimalMemoryLimit($input, $this->getToolName());

        if ($output->isVerbose()) {
            $output->writeln(\sprintf('<info>Using automatic memory limit: %s</info>', $memoryLimit));
        }

        return $memoryLimit;
    }

    /**
     * Execute pre-processing hooks (optional override).
     */
    protected function executePreProcessingHooks(InputInterface $input, OutputInterface $output, array $targetPaths): void
    {
        // Default: no pre-processing
    }

    /**
     * Execute post-processing hooks (optional override).
     */
    protected function executePostProcessingHooks(InputInterface $input, OutputInterface $output, int $exitCode): void
    {
        // Default: no post-processing
    }

    /**
     * Handle cleanup when an exception occurs (optional override).
     */
    protected function handleExecutionException(\Throwable $exception, InputInterface $input, OutputInterface $output): void
    {
        // Default: no special cleanup
    }

    /**
     * Get error handler instance.
     */
    protected function getErrorHandler(): ErrorHandler
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = new ErrorHandler();
        }

        return $this->errorHandler;
    }

    /**
     * Get target path for tool compatibility.
     */
    #[\Override]
    protected function getTargetPath(InputInterface $input): string
    {
        return $this->getTargetPathForTool($input, $this->getToolName());
    }

    /**
     * Resolve configuration path for tool commands.
     * Supports custom config paths, auto-discovery, and package defaults.
     */
    protected function resolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        if ($this->output && $this->output->isVerbose()) {
            $this->output->writeln(\sprintf('<comment>Resolving configuration for file: %s</comment>', $configFile));
        }

        if ($customConfigPath !== null) {
            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->fileExists($customConfigPath)) {
                throw ErrorFactory::configFileNotFound($customConfigPath, $customConfigPath);
            }

            // Apply secure path validation for tool config paths
            try {
                $filesystemService = $this->getFilesystemService();
                $projectRoot = $this->getProjectRoot();

                return $filesystemService->validateConfigurationPath(
                    $customConfigPath,
                    $projectRoot,
                    $this->getToolName(),
                );
            } catch (\Exception $e) {
                throw new \RuntimeException(\sprintf('Security validation failed for custom config file "%s": %s', $customConfigPath, $e->getMessage()), 0, $e);
            }
        }

        // Try to auto-discover configuration files
        $toolName = $this->getToolName();
        $discoveredConfigPath = $this->discoverToolConfigurationFile($toolName);
        if ($discoveredConfigPath !== null) {
            return $discoveredConfigPath;
        }

        // Fall back to package defaults
        $vendorPath = $this->findVendorPath();
        $defaultConfigPath = $vendorPath . '/cpsit/quality-tools/config/' . $configFile;

        $filesystemService = $this->getFilesystemService();
        if (!$filesystemService->fileExists($defaultConfigPath)) {
            // Provide helpful error message with discovery details
            $searchedLocations = $this->getSearchedConfigLocations($toolName, $configFile);
            throw $this->createConfigNotFoundError($defaultConfigPath, $toolName, $searchedLocations);
        }

        if ($this->output && $this->output->isVerbose()) {
            $this->output->writeln(\sprintf('<info>Using package default configuration: %s</info>', $defaultConfigPath));
        }

        return $defaultConfigPath;
    }

    /**
     * Discover tool configuration file using ConfigurationDiscovery.
     */
    private function discoverToolConfigurationFile(string $toolName): ?string
    {
        try {
            // Get configuration discovery service
            $projectRoot = $this->getProjectRoot();
            $hierarchy = new ConfigurationHierarchy($projectRoot);
            $discovery = new ConfigurationDiscovery(
                $hierarchy,
                $this->getFilesystemService(),
                new SecurityService(),
                new ConfigurationValidator(),
                new ToolConfigurationValidationService(),
            );

            // Debug output for verbose mode
            if ($this->output && $this->output->isVerbose()) {
                $this->output->writeln(\sprintf('<comment>Discovering configuration for %s...</comment>', $toolName));
            }

            // Check if tool has a custom configuration file
            if ($discovery->hasToolConfiguration($toolName)) {
                $configPath = $discovery->getToolConfigurationPath($toolName);
                if ($configPath !== null) {
                    if ($this->output && $this->output->isVerbose()) {
                        $this->output->writeln(\sprintf('<info>Found configuration: %s</info>', $configPath));
                    }

                    // Validate the path for security
                    return $this->getFilesystemService()->validateConfigurationPath(
                        $configPath,
                        $projectRoot,
                        $toolName,
                    );
                }
            } elseif ($this->output && $this->output->isVerbose()) {
                $this->output->writeln('<comment>No custom configuration found, using package defaults</comment>');
            }
        } catch (\Exception $e) {
            if ($this->output && $this->output->isVerbose()) {
                $this->output->writeln(\sprintf('<comment>Configuration discovery failed: %s</comment>', $e->getMessage()));
            }
            // Silently fail and fall back to default
            // This allows the command to continue with package defaults
        }

        return null;
    }

    /**
     * Get searched configuration locations for error reporting.
     */
    private function getSearchedConfigLocations(string $toolName, string $configFile): array
    {
        $locations = [];
        $projectRoot = $this->getProjectRoot();

        // Standard locations checked by ConfigurationDiscovery
        $standardLocations = [
            $projectRoot . '/' . $configFile,
            $projectRoot . '/config/' . $configFile,
            $projectRoot . '/.config/' . $configFile,
            $projectRoot . '/quality-tools/' . $configFile,
        ];

        foreach ($standardLocations as $location) {
            $locations[] = $location;
        }

        // Package default location
        try {
            $vendorPath = $this->findVendorPath();
            $locations[] = $vendorPath . '/cpsit/quality-tools/config/' . $configFile;
        } catch (\Exception) {
            // Vendor path detection failed, skip
        }

        return $locations;
    }

    /**
     * Create detailed configuration not found error.
     */
    private function createConfigNotFoundError(string $defaultPath, string $toolName, array $searchedLocations): \Exception
    {
        // Add searched locations to troubleshooting
        $troubleshooting = [
            'Create a custom configuration file in your project root or config/ directory',
            'Use --config option to specify a custom configuration file',
            'Ensure cpsit/quality-tools package is properly installed',
        ];

        if (!empty($searchedLocations)) {
            $troubleshooting[] = \sprintf('Searched locations: %s', implode(', ', $searchedLocations));
        }

        return ErrorFactory::configFileNotFound($defaultPath, null);
    }

    /**
     * Find vendor path for tool commands.
     */
    #[\Override]
    protected function findVendorPath(): string
    {
        $projectRoot = $this->getProjectRoot();
        $detector = $this->getVendorDirectoryDetector();

        try {
            $vendorPath = $detector->detectVendorPath($projectRoot);

            // Validate that cpsit/quality-tools is installed in detected vendor directory
            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->directoryExists($vendorPath . '/cpsit/quality-tools')) {
                throw new \RuntimeException(\sprintf('cpsit/quality-tools package not found in detected vendor directory: %s. Please ensure the package is properly installed.', $vendorPath));
            }

            return $vendorPath;
        } catch (VendorDirectoryNotFoundException $e) {
            // Fallback to old hardcoded detection for backward compatibility
            $vendorPaths = [
                $projectRoot . '/app/vendor',  // TYPO3 with app/vendor structure
                $projectRoot . '/vendor',      // Standard composer structure
            ];

            $filesystemService = $this->getFilesystemService();
            foreach ($vendorPaths as $vendorPath) {
                if ($filesystemService->directoryExists($vendorPath) && $filesystemService->directoryExists($vendorPath . '/cpsit/quality-tools')) {
                    return $vendorPath;
                }
            }

            throw new \RuntimeException(\sprintf('Could not detect vendor directory. Automatic detection failed: %s. Also checked fallback paths: %s', $e->getMessage(), implode(', ', $vendorPaths)));
        }
    }

    /**
     * Get vendor directory detector service.
     */
    #[\Override]
    protected function getVendorDirectoryDetector(): VendorDirectoryDetector
    {
        if ($this->hasService(VendorDirectoryDetector::class)) {
            return $this->getService(VendorDirectoryDetector::class);
        }

        return new VendorDirectoryDetector();
    }
}
