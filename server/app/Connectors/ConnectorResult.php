<?php

namespace App\Connectors;

class ConnectorResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly ?int $statusCode = null,
    ) {}

    public static function success(array $data): self
    {
        return new self(success: true, data: $data);
    }

    public static function failure(string $error, ?int $statusCode = null): self
    {
        return new self(success: false, error: $error, statusCode: $statusCode);
    }
}
