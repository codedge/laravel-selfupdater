<?php

declare(strict_types=1);

namespace Codedge\Updater\Traits;

trait SupportPrivateAccessToken
{
    /**
     * @var string
     */
    private static $accessTokenPrefix = 'Bearer ';

    /**
     * @var string
     */
    protected $accessToken = '';

    /**
     * Get the access token.
     *
     * @param bool $withPrefix
     *
     * @return string
     */
    public function getAccessToken($withPrefix = true): string
    {
        if ($withPrefix) {
            return self::$accessTokenPrefix.$this->accessToken;
        }

        return $this->accessToken;
    }

    /**
     * Set access token.
     *
     * @param string $token
     */
    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Check if an access token has been set.
     *
     * @return bool
     */
    public function hasAccessToken(): bool
    {
        return ! empty($this->accessToken);
    }
}
