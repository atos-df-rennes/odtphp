<?php

declare(strict_types=1);

namespace Odtphp;

/**
 * Interface for ODF document dependencies used by Segment.
 *
 * This interface defines the minimal contract that Segment expects from
 * an ODF document object, enabling proper type hinting while allowing
 * both the real Odf class and test stubs to satisfy the contract.
 */
interface OdfAwareDependency
{
    /**
     * Get a configuration value
     *
     * @param string $configKey The configuration key to retrieve
     * @return mixed The configuration value, or false if not found
     */
    public function getConfig(string $configKey);

    /**
     * Get the temporary working file path
     *
     * @return string The path to the temporary ODT file
     */
    public function getTmpfile(): string;
}
