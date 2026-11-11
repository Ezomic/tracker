<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The optional third-party integrations. Each one is on only when every
 * setting it needs is filled in, so a fresh install with the env vars left
 * empty (or set to spaces) shows no controls for them and calls nobody.
 */
class Integrations
{
    /**
     * Thijssensoftware ID: single sign-on plus the portal app switcher.
     */
    public function sso(): bool
    {
        return $this->allFilled([
            'services.thijssensoftware.base_url',
            'services.thijssensoftware.client_id',
            'services.thijssensoftware.client_secret',
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function allFilled(array $keys): bool
    {
        foreach ($keys as $key) {
            if (blank(config($key))) {
                return false;
            }
        }

        return true;
    }
}
