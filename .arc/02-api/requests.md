# Form Request Conventions

## Location
Domain-grouped under `App\Http\Requests\Api\{Domain}\`:

```
Requests/Api/
├── Account/          # LoginRequest, RegisterRequest, UpdateProfileRequest, etc.
└── App/              # StoreAppRequest
```

## Structure

```php
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAppRequest',
    required: ['store_id', 'platform'],
    properties: [
        new OA\Property(property: 'store_id', type: 'string', example: 'com.example.app'),
        new OA\Property(property: 'platform', type: 'string', enum: ['android', 'ios']),
    ],
)]
class StoreAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'string'],
            'platform' => ['required', Rule::enum(Platform::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Store ID is required.',
        ];
    }
}
```

## Rules
- `authorize()` returns `true` — authorization via middleware
- Use `Rule::enum()` for enum validation
- Custom `messages()` for user-friendly errors
- Use Laravel validation rules: `exists:table,column`, `required`, `string`, `Rule::enum()`
- Add `#[OA\Schema]` PHP 8 attributes for Swagger documentation

## DO / DON'T
- DO: Create a Form Request for every endpoint that accepts input
- DO: Use `Rule::enum()` for enum fields
- DO: Add `#[OA\Schema]` attributes for Swagger docs
- DO: Group requests by domain under `Requests/Api/{Domain}/`
- DON'T: Validate inside controllers
- DON'T: Use custom validation classes when built-in rules suffice
