<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;

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
     * The one-way mirror of a project's issues into a Notion database.
     */
    public function notion(): bool
    {
        return $this->allFilled(['services.notion.api_key']);
    }

    /**
     * Drafting issues from pasted text with Claude: needs the API key on the
     * server and the organization to have switched it on.
     */
    public function drafting(?Organization $organization): bool
    {
        return $organization !== null
            && $organization->ai_drafting_enabled
            && $this->allFilled(['services.anthropic.key']);
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
