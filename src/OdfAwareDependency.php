<?php

declare(strict_types=1);

namespace Odtphp;

/**
 * Interface for ODF document dependencies used by Segment.
 *
 * This interface defines the minimal contract that Segment expects from
 * an ODF document object, enabling proper type hinting while allowing
 * both the real Odf class and test stubs to satisfy the contract.
 *
 * @phpstan-type OdfConfig array{
 *     ZIP_PROXY: class-string<\Odtphp\Zip\ZipInterface>,
 *     DELIMITER_LEFT: string,
 *     DELIMITER_RIGHT: string,
 *     PATH_TO_TMP: string|null
 * }
 */
interface OdfAwareDependency
{
    /**
     * Get a configuration value by key.
     *
     * Supported configuration keys and their types:
     * - 'ZIP_PROXY': Returns a class-string for the ZIP handler (PhpZipProxy or PclZipProxy)
     * - 'DELIMITER_LEFT': Returns the left delimiter string (default: '{')
     * - 'DELIMITER_RIGHT': Returns the right delimiter string (default: '}')
     * - 'PATH_TO_TMP': Returns the temporary directory path or null (default: null)
     * - Any other key: Returns false
     *
     * @param string $configKey The configuration key to retrieve
     * @return mixed The configuration value, or false if key is not found
     */
    public function getConfig(string $configKey);

    /**
     * Get the temporary working file path
     *
     * @return string The path to the temporary ODT file
     */
    public function getTmpfile(): string;
}
