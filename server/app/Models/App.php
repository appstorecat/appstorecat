<?php

namespace App\Models;

use App\Enums\DiscoverSource;
use App\Enums\Platform;
use Carbon\Carbon;
use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property Platform $platform
 * @property string $external_id
 * @property int|null $publisher_id
 * @property int|null $category_id
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
        new OA\Property(property: 'platform', ref: '#/components/schemas/Platform'),
        new OA\Property(property: 'external_id', type: 'string', example: '389801252'),
        new OA\Property(property: 'supported_locales', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'original_release_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'is_free', type: 'boolean', example: true),
        new OA\Property(property: 'publisher', ref: '#/components/schemas/Publisher', nullable: true),
        new OA\Property(property: 'category', ref: '#/components/schemas/StoreCategory', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'platform', 'external_id',
    'publisher_id', 'category_id',
    'display_name', 'display_icon', 'origin_country',
    'supported_locales', 'original_release_date', 'is_free',
    'discovered_from', 'discovered_at', 'last_synced_at',
    'is_available',
])]
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

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
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<AppCompetitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(AppCompetitor::class);
    }

    /**
     * @return HasMany<AppKeywordDensity, $this>
     */
    public function keywordDensities(): HasMany
    {
        return $this->hasMany(AppKeywordDensity::class);
    }

    public function displayName(): string
    {
        return $this->display_name ?? $this->external_id;
    }

    public function displayIcon(): ?string
    {
        return $this->display_icon;
    }

    /**
     * Find or create an app from store data without triggering enrichment.
     *
     * @param  array{name?: string, developer?: string, developer_id?: string, genre?: string, genre_id?: string, icon_url?: string, free?: bool, released?: string}  $data
     */
    public static function discover(string $platform, string $externalId, array $data = [], DiscoverSource $source = DiscoverSource::Unknown, string $country = 'us'): ?self
    {
        $app = static::where('platform', $platform)->where('external_id', $externalId)->first();

        if ($app) {
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

        $categoryId = app(\App\Services\StoreCategoryResolver::class)->resolveId(
            $platform,
            $data['genre_id'] ?? null,
            $data['genre'] ?? null,
            ['source' => 'App::discover', 'external_id' => $externalId, 'country' => $country],
        );

        $app = static::create([
            'platform' => $platform,
            'external_id' => $externalId,
            'publisher_id' => $publisherId,
            'category_id' => $categoryId,
            'display_name' => $data['name'] ?? null,
            'display_icon' => $data['icon_url'] ?? null,
            'origin_country' => $country,
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
            'platform' => Platform::class,
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
