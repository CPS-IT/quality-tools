<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Service container factory using Symfony DI component.
 *
 * Provides centralized service configuration and dependency injection
 * for the quality tools package architecture.
 */
final class ServiceContainer
{
    private static ?ContainerBuilder $container = null;

    /**
     * Get the configured service container.
     */
    public static function getContainer(): ContainerBuilder
    {
        if (self::$container === null) {
            self::$container = self::buildContainer();
        }

        return self::$container;
    }

    /**
     * Reset the container (useful for testing).
     */
    public static function reset(): void
    {
        self::$container = null;
    }

    /**
     * Build and configure the service container.
     */
    private static function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // Load service definitions
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Compile the container for optimal performance
        $container->compile();

        return $container;
    }

    /**
     * Get a service by its class name or service ID.
     */
    public static function get(string $serviceId): object
    {
        $container = self::getContainer();

        if (!$container->has($serviceId)) {
            throw new \InvalidArgumentException(\sprintf('Service "%s" not found in container', $serviceId));
        }

        return $container->get($serviceId);
    }

    /**
     * Check if a service is registered.
     */
    public static function has(string $serviceId): bool
    {
        return self::getContainer()->has($serviceId);
    }
}
