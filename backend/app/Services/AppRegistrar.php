<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\App;
use App\Models\User;

class AppRegistrar
{
    public function register(User $user, string $externalId, Platform $platform): App
    {
        $app = App::firstOrCreate(
            ['platform' => $platform, 'external_id' => $externalId],
        );

        if (! $app->isTrackedBy($user)) {
            $user->apps()->attach($app->id);
        }

        return $app;
    }
}
