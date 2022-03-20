<?php

declare(strict_types=1);

namespace Codedge\Updater\Traits;

trait SupportPrivateAccessToken
{
    private string $accessTokenPrefix = 'Bearer ';
    protected string $accessToken = '';

    public function getAccessToken(bool $withPrefix = true): string
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
        return !empty($this->accessToken);
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
