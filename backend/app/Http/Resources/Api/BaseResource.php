<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    public static $wrap = null;

    protected string $message;

    public function __construct($resource, string $message = '')
    {
        parent::__construct($resource);
        $this->message = $message;
    }

    /**
     * Get the resource data.
     *
     * @return array<string, mixed>
     */
    abstract protected function getResourceData(Request $request): array;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (self::$wrap !== null) {
            return [
                'message' => $this->message ?: $this->getDefaultMessage(),
                self::$wrap => $this->getResourceData($request),
            ];
        }

        return $this->getResourceData($request);
    }

    /**
     * Get the default message for the resource.
     */
    protected function getDefaultMessage(): string
    {
        return 'Resource retrieved successfully';
    }

    /**
     * Convert a relative storage URL to an absolute URL using APP_URL.
     */
    protected function storageUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, '/storage/')) {
            return rtrim(config('app.url'), '/').$url;
        }

        return $url;
    }

    /**
     * Convert an array of screenshot URLs to absolute URLs.
     *
     * @param  array<int, string|array<string, mixed>>|null  $screenshots
     * @return array<int, string|array<string, mixed>>|null
     */
    protected function storageUrls(?array $screenshots): ?array
    {
        if ($screenshots === null) {
            return null;
        }

        return array_map(function ($screenshot) {
            if (is_string($screenshot)) {
                return $this->storageUrl($screenshot);
            }

            if (is_array($screenshot) && isset($screenshot['url'])) {
                $screenshot['url'] = $this->storageUrl($screenshot['url']);
            }

            return $screenshot;
        }, $screenshots);
    }

    /**
     * Format timestamp to ISO8601.
     */
    protected function formatTimestamp(mixed $timestamp): ?string
    {
        if ($timestamp instanceof Carbon) {
            return $timestamp->toIso8601String();
        }
        if ($timestamp instanceof \DateTime || $timestamp instanceof \DateTimeImmutable) {
            return $timestamp->format('Y-m-d\TH:i:sP');
        }

        return null;
    }
}
