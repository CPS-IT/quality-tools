<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait for classes that need access to the service container.
 */
trait ContainerAwareTrait
{
    protected ?ContainerInterface $container = null;

    /**
     * Set the service container.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Get the service container.
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get a service from the container.
     */
    protected function getService(string $serviceId): object
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container not available. Call setContainer() first.');
        }

        return $this->container->get($serviceId);
    }

    /**
     * Check if a service exists in the container.
     */
    protected function hasService(string $serviceId): bool
    {
        if ($this->container === null) {
            return false;
        }

        return $this->container->has($serviceId);
    }
}
