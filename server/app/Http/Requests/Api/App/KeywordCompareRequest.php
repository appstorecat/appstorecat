<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\App;

use App\Models\App;
use App\Models\AppCompetitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'KeywordCompareRequest',
    required: ['app_ids'],
    properties: [
        new OA\Property(property: 'app_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [2, 3]),
        new OA\Property(property: 'version_ids', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer'), example: ['2' => 5, '3' => 8]),
        new OA\Property(property: 'locale', type: 'string', example: 'en-US'),
        new OA\Property(property: 'ngram', type: 'integer', enum: [1, 2, 3, 4], example: 1),
    ],
)]
class KeywordCompareRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        $app = App::platform($this->route('platform'))
            ->where('external_id', $this->route('externalId'))
            ->first();

        $competitorAppIds = $app
            ? AppCompetitor::where('user_id', $this->user()->id)
                ->where('app_id', $app->id)
                ->pluck('competitor_app_id')
                ->all()
            : [];

        return [
            'app_ids' => ['required', 'array', 'max:5'],
            'app_ids.*' => ['required', 'integer', Rule::in($competitorAppIds)],
            'version_ids' => ['sometimes', 'array'],
            'version_ids.*' => ['integer', 'exists:app_versions,id'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ngram' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4'],
        ];
    }
}
