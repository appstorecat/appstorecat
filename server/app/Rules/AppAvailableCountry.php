<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\App;
use App\Models\AppMetric;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AppAvailableCountry implements ValidationRule
{
    public function __construct(
        private readonly string $platform,
        private readonly string $externalId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // other rules (required, size) handle the empty case
        }

        $app = App::platform($this->platform)
            ->where('external_id', $this->externalId)
            ->first();

        if (! $app) {
            return; // route resolver will 404 — nothing to validate against
        }

        $latestId = AppMetric::query()
            ->where('app_id', $app->id)
            ->where('country_code', $value)
            ->max('id');

        if ($latestId === null) {
            return; // no metric captured yet for this country — accept optimistically
        }

        $isAvailable = (bool) AppMetric::query()
            ->whereKey($latestId)
            ->value('is_available');

        if (! $isAvailable) {
            $fail("The app is not available on the App Store in {$value}.");
        }
    }
}
