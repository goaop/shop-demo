<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Response
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public int $status = 200,
    ) {}
}
