<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for classes that need access to the service container.
 */
interface ContainerAwareInterface
{
    /**
     * Set the service container.
     */
    public function setContainer(ContainerInterface $container): void;

    /**
     * Get the service container.
     */
    public function getContainer(): ?ContainerInterface;
}
