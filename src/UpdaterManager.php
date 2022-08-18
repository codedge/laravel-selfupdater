<?php

declare(strict_types=1);

namespace Codedge\Updater;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Contracts\UpdaterContract;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GiteaRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GitlabRepositoryType;
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
    protected Application $app;

    /**
     * @var array<string, SourceRepositoryTypeContract>
     */
    protected array $sources = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function source(string $name = ''): SourceRepositoryTypeContract
    {
        $name = $name ?: $this->getDefaultSourceRepository();

        return $this->sources[$name] = $this->get($name);
    }

    public function getDefaultSourceRepository(): string
    {
        return $this->app['config']['self-update']['default'];
    }

    public function sourceRepository(SourceRepositoryTypeContract $sourceRepository): SourceRepositoryTypeContract
    {
        return new SourceRepository($sourceRepository, $this->app->make(UpdateExecutor::class));
    }

    protected function getConfig(string $name): array
    {
        if (isset($this->app['config']['self-update']['repository_types'][$name])) {
            return $this->app['config']['self-update']['repository_types'][$name];
        }

        return [];
    }

    /*
     * Attempt to get the right source repository instance.
     */
    protected function get(string $name): SourceRepositoryTypeContract
    {
        return $this->sources[$name] ?? $this->resolve($name);
    }

    /**
     * Try to find the correct source repository implementation ;-).
     *
     * @throws InvalidArgumentException
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
     * @throws Exception
     */
    protected function createGithubRepository(): SourceRepositoryTypeContract
    {
        /** @var GithubRepositoryType $factory */
        $factory = $this->app->make(GithubRepositoryType::class);

        return $this->sourceRepository($factory->create());
    }

    protected function createGitlabRepository(): SourceRepositoryTypeContract
    {
        return $this->sourceRepository($this->app->make(GitlabRepositoryType::class));
    }

    protected function createHttpRepository(): SourceRepositoryTypeContract
    {
        return $this->sourceRepository($this->app->make(HttpRepositoryType::class));
    }

    protected function createGiteaRepository(): SourceRepositoryTypeContract
    {
        return $this->sourceRepository($this->app->make(GiteaRepositoryType::class));
    }
}
