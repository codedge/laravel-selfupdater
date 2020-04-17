<?php

declare(strict_types=1);

namespace Codedge\Updater;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Contracts\UpdaterContract;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\HttpRepositoryType;
use Exception;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

/**
 * UpdaterManager.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
final class UpdaterManager implements UpdaterContract
{
    /**
     * Application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $sources = [];

    /**
     * @var array
     */
    protected $customSourceCreators = [];

    /**
     * Create a new Updater manager instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a source repository type instance.
     *
     * @param string $name
     *
     * @return SourceRepositoryTypeContract
     */
    public function source(string $name = ''): SourceRepositoryTypeContract
    {
        $name = $name ?: $this->getDefaultSourceRepository();

        return $this->sources[$name] = $this->get($name);
    }

    /**
     * Get the default source repository type.
     *
     * @return string
     */
    public function getDefaultSourceRepository()
    {
        return $this->app['config']['self-update']['default'];
    }

    /**
     * @param SourceRepositoryTypeContract $sourceRepository
     *
     * @return SourceRepositoryTypeContract
     */
    public function sourceRepository(SourceRepositoryTypeContract $sourceRepository): SourceRepositoryTypeContract
    {
        return new SourceRepository($sourceRepository, $this->app->make(UpdateExecutor::class));
    }

    /**
     * Get the source repository connection configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getConfig(string $name): array
    {
        if (isset($this->app['config']['self-update']['repository_types'][$name])) {
            return $this->app['config']['self-update']['repository_types'][$name];
        }

        return [];
    }

    /**
     * Attempt to get the right source repository instance.
     *
     * @param string $name
     *
     * @return SourceRepositoryTypeContract
     */
    protected function get(string $name)
    {
        return isset($this->sources[$name]) ? $this->sources[$name] : $this->resolve($name);
    }

    /**
     * Try to find the correct source repository implementation ;-).
     *
     * @param string $name
     *
     * @throws InvalidArgumentException
     *
     * @return SourceRepositoryTypeContract
     */
    protected function resolve(string $name): SourceRepositoryTypeContract
    {
        $config = $this->getConfig($name);

        if (empty($config)) {
            throw new InvalidArgumentException("Source repository [{$name}] is not defined.");
        }

        $repositoryMethod = 'create'.ucfirst($name).'Repository';

        return $this->{$repositoryMethod}();
    }

    /**
     * @return SourceRepositoryTypeContract
     * @throws Exception
     */
    protected function createGithubRepository(): SourceRepositoryTypeContract
    {
        /** @var GithubRepositoryType $factory */
        $factory = $this->app->make(GithubRepositoryType::class);

        return $this->sourceRepository($factory->create());
    }

    /**
     * Create an instance for the Http source repository.
     *
     * @return SourceRepositoryTypeContract
     */
    protected function createHttpRepository(): SourceRepositoryTypeContract
    {
        return $this->sourceRepository($this->app->make(HttpRepositoryType::class));
    }
}
