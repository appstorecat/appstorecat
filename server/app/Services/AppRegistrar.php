<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\App;
use App\Models\User;

class AppRegistrar
{
    /**
     * Make sure the app exists in the catalog AND attach it to the user's
     * watchlist. Used by the "Track" UI button and the `track_app` MCP tool.
     */
    public function register(User $user, string $externalId, Platform $platform): App
    {
        $app = $this->ensureExists($externalId, $platform);

        if (! $app->isTrackedBy($user)) {
            $user->apps()->attach($app->id);
        }

        return $app;
    }

    /**
     * Make sure the app exists in the catalog WITHOUT attaching it to anyone's
     * watchlist. Used when we need a row to reference (e.g. as a competitor)
     * without polluting the caller's tracked-apps list.
     */
    public function ensureExists(string $externalId, Platform $platform): App
    {
        return App::firstOrCreate(
            ['platform' => $platform->value, 'external_id' => $externalId],
        );
    }
}
