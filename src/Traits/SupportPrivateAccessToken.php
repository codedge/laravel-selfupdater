<?php

declare(strict_types=1);

namespace Codedge\Updater\Traits;

trait SupportPrivateAccessToken
{
    /**
     * @var string
     */
    private $accessTokenPrefix = 'Bearer ';

    /**
     * @var string
     */
    protected $accessToken = '';

    public function getAccessToken($withPrefix = true): string
    {
        if ($withPrefix) {
            return $this->accessTokenPrefix.$this->accessToken;
        }

        return $this->accessToken;
    }

    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    public function hasAccessToken(): bool
    {
        return ! empty($this->accessToken);
    }

    public function setAccessTokenPrefix(string $prefix): void
    {
        $this->accessTokenPrefix = $prefix;
    }

    public function getAccessTokenPrefix(): string
    {
        return $this->accessTokenPrefix;
    }
}
