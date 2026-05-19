<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FolderColor;
use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Folder',
    required: ['id', 'name', 'color'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Virtual Number'),
        new OA\Property(property: 'color', ref: '#/components/schemas/FolderColorEnum'),
        new OA\Property(property: 'sort_order', type: 'integer', example: 0),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[Fillable([
    'user_id', 'name', 'color', 'sort_order',
])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns the number of apps in this folder. Done as a manual count on
     * the user_apps pivot — a belongsToMany('App, user_apps')->wherePivot(
     * 'folder_id', $this->id) call confuses Eloquent's withCount() subquery
     * builder (the $this->id closure binds null inside the subquery), so
     * counts always came back zero.
     */
    public function appsCount(): int
    {
        return DB::table('user_apps')->where('folder_id', $this->id)->count();
    }

    protected function casts(): array
    {
        return [
            'color' => FolderColor::class,
            'sort_order' => 'integer',
        ];
    }
}
