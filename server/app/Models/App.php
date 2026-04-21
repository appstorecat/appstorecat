<?php

namespace App\Models;

use App\Casts\PlatformCast;
use App\Enums\DiscoverSource;
use App\Enums\Platform;
use App\Models\Concerns\HasPlatform;
use App\Services\StoreCategoryResolver;
use Carbon\Carbon;
use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property Platform $platform
 * @property string $external_id
 * @property int|null $publisher_id
 * @property int|null $category_id
 * @property string|null $display_name
 * @property string|null $icon_url
 * @property string $origin_country_code
 * @property array|null $supported_locales
 * @property Carbon|null $original_release_date
 * @property bool $is_free
 * @property DiscoverSource|null $discovered_from
 * @property Carbon|null $discovered_at
 * @property Carbon|null $last_synced_at
 * @property bool $is_available
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[OA\Schema(
    schema: 'App',
    required: ['id', 'platform', 'external_id'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android']),
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'display_name', type: 'string', nullable: true, example: 'Instagram'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true),
        new OA\Property(property: 'origin_country_code', type: 'string', example: 'us'),
        new OA\Property(property: 'supported_locales', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'original_release_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'is_free', type: 'boolean', example: true),
        new OA\Property(property: 'is_available', type: 'boolean', example: true),
        new OA\Property(property: 'publisher', ref: '#/components/schemas/Publisher', nullable: true),
        new OA\Property(property: 'category', ref: '#/components/schemas/StoreCategory', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'platform', 'external_id',
    'publisher_id', 'category_id',
    'display_name', 'icon_url', 'origin_country_code',
    'supported_locales', 'original_release_date', 'is_free',
    'discovered_from', 'discovered_at', 'last_synced_at',
    'is_available',
])]
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

    use HasPlatform;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_apps')->withPivot('created_at');
    }

    public function isTrackedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * @return BelongsTo<StoreCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class);
    }

    /**
     * @return HasMany<StoreListing, $this>
     */
    public function storeListings(): HasMany
    {
        return $this->hasMany(StoreListing::class);
    }

    /**
     * @return HasMany<StoreListingChange, $this>
     */
    public function storeListingChanges(): HasMany
    {
        return $this->hasMany(StoreListingChange::class);
    }

    /**
     * @return HasMany<AppMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(AppMetric::class);
    }

    /**
     * @return HasMany<AppVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class)->latest('id');
    }

    /**
     * @return HasOne<SyncStatus, $this>
     */
    public function syncStatus(): HasOne
    {
        return $this->hasOne(SyncStatus::class);
    }

    /**
     * @return HasMany<AppCompetitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(AppCompetitor::class);
    }

    public function displayName(): string
    {
        return $this->display_name ?? $this->external_id;
    }

    public function displayIcon(): ?string
    {
        return $this->icon_url;
    }

    /**
     * Find or create an app from store data without triggering enrichment.
     *
     * @param  array{name?: string, developer?: string, developer_id?: string, genre?: string, genre_id?: string, icon_url?: string, free?: bool, released?: string}  $data
     */
    public static function discover(string $platform, string $externalId, array $data = [], DiscoverSource $source = DiscoverSource::Unknown, string $country = 'us'): ?self
    {
        $app = static::platform($platform)->where('external_id', $externalId)->first();

        if ($app) {
            $updates = [];
            if (! empty($data['name']) && $app->display_name !== $data['name']) {
                $updates['display_name'] = $data['name'];
            }
            if (! empty($data['icon_url']) && $app->icon_url !== $data['icon_url']) {
                $updates['icon_url'] = $data['icon_url'];
            }

            // Backfill publisher/category from richer payloads (chart, identity
            // sync) if they weren't captured on an earlier discovery pass.
            if ($app->publisher_id === null && ! empty($data['developer'])) {
                $publisher = Publisher::findOrCreateByName(
                    $data['developer'],
                    $platform,
                    $data['developer_id'] ?? null,
                );
                $updates['publisher_id'] = $publisher->id;
            }

            if ($app->category_id === null && (! empty($data['genre_id']) || ! empty($data['genre']))) {
                $categoryId = app(StoreCategoryResolver::class)->resolveId(
                    $platform,
                    $data['genre_id'] ?? null,
                    $data['genre'] ?? null,
                    ['source' => 'App::discover(backfill)', 'external_id' => $externalId, 'country' => $country],
                );
                if ($categoryId !== null) {
                    $updates['category_id'] = $categoryId;
                }
            }

            if ($updates !== []) {
                $app->update($updates);
            }

            return $app;
        }

        if (! $source->isEnabled($platform)) {
            return null;
        }

        $publisherId = null;
        if (! empty($data['developer'])) {
            $publisher = Publisher::findOrCreateByName($data['developer'], $platform, $data['developer_id'] ?? null);
            $publisherId = $publisher->id;
        }

        $categoryId = null;
        if (! empty($data['genre_id']) || ! empty($data['genre'])) {
            $categoryId = app(StoreCategoryResolver::class)->resolveId(
                $platform,
                $data['genre_id'] ?? null,
                $data['genre'] ?? null,
                ['source' => 'App::discover', 'external_id' => $externalId, 'country' => $country],
            );
        }

        $app = static::create([
            'platform' => $platform,
            'external_id' => $externalId,
            'publisher_id' => $publisherId,
            'category_id' => $categoryId,
            'display_name' => $data['name'] ?? null,
            'icon_url' => $data['icon_url'] ?? null,
            'origin_country_code' => $country,
            'is_free' => $data['free'] ?? true,
            'original_release_date' => $data['released'] ?? null,
            'discovered_from' => $source,
            'discovered_at' => now(),
        ]);

        return $app;
    }

    public function isIos(): bool
    {
        return $this->platform === Platform::Ios;
    }

    public function isAndroid(): bool
    {
        return $this->platform === Platform::Android;
    }

    protected function casts(): array
    {
        return [
            'platform' => PlatformCast::class,
            'supported_locales' => 'array',
            'is_free' => 'boolean',
            'original_release_date' => 'date',
            'discovered_from' => DiscoverSource::class,
            'discovered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_available' => 'boolean',
        ];
    }
}
