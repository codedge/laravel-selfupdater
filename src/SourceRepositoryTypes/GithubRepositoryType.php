<?php

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use GuzzleHttp\Client;

/**
 * Github.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class GithubRepositoryType implements SourceRepositoryTypeContract
{
    CONST GITHUB_API_URL = 'https://api.github.com';

    /**
     * @var  Client
     */
    protected $client;

    /**
     * @var  array
     */
    protected $config;

    /**
     * Github constructor.
     *
     * @param Client $client
     * @param array $config
     */
    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Check repository if a newer version than the installed one is available
     *
     * @param string $currentVersion
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isNewVersionAvailable($currentVersion = '') : bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if(empty($version)) {
            throw new InvalidArgumentException("No currently installed version specified.");
        }

        return version_compare($version, $this->getVersionAvailable(), '<');
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @return mixed
     */
    public function fetch($version = '')
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));
        $release = $releaseCollection->first();

        if(!empty($version)) {
            $release = $releaseCollection->where('tag_name', $version);
        }
        
        //dd($release);
    }

    /**
     * Get the version that is currenly installed.
     * Example: 1.1.0 or v1.1.0 or "1.1.0 version"
     *
     * @param string $prepend
     * @param string $append
     *
     * @return string
     */
    public function getVersionInstalled($prepend = '', $append = '') : string
    {
        return '';
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append Append a string to the latest version
     *
     * @return string
     */
    public function getVersionAvailable($prepend = '', $append = '') : string
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));

        return $prepend . $releaseCollection->first()->tag_name . $append;
    }

    /**
     * Get all releases for a specific repository
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function getRepositoryReleases()
    {
        return $this->client->request(
            'GET',
            self::GITHUB_API_URL . '/repos/'.$this->config['repository_owner'].'/'.$this->config['repository_name'].'/releases'
        );
    }
}