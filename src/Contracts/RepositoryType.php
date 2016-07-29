<?php

namespace Codedge\Updater\Contracts;

interface RepositoryType
{
    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append Append a string to the latest version
     *
     * @return string
     */
    public function getLatestVersion($prepend='', $append='');

    /**
     * Download
     *
     * @return mixed
     */
    public function downloadLatestSource();
}