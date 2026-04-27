<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Enums\CompetitorRelationship;
use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreCompetitorRequest',
    description: 'Add a competitor to a tracked app. Supply EITHER `competitor_external_id` (recommended — auto-registers the app row without adding it to the caller\'s watchlist) OR `competitor_app_id` (legacy: internal numeric id of an already-registered app).',
    properties: [
        new OA\Property(property: 'competitor_external_id', type: 'string', example: 'com.aadhk.woinvoice', description: 'Store identifier of the competitor app. Auto-registers the app row if missing.'),
        new OA\Property(property: 'competitor_platform', type: 'string', enum: ['ios', 'android'], example: 'android', description: 'Platform of the competitor. Defaults to the parent app\'s platform when omitted.'),
        new OA\Property(property: 'competitor_app_id', type: 'integer', example: 2, description: 'Legacy: internal numeric `apps.id` of the competitor. Use `competitor_external_id` instead when calling the API directly.'),
        new OA\Property(property: 'relationship', type: 'string', enum: ['direct', 'indirect', 'aspiration'], example: 'direct'),
    ],
)]
class StoreCompetitorRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'competitor_external_id' => ['required_without:competitor_app_id', 'nullable', 'string', 'max:255'],
            'competitor_platform' => ['sometimes', 'nullable', Rule::enum(Platform::class)],
            'competitor_app_id' => ['required_without:competitor_external_id', 'nullable', 'integer', 'exists:apps,id'],
            'relationship' => ['sometimes', Rule::enum(CompetitorRelationship::class)],
        ];
    }
}
