<?php

declare(strict_types=1);

namespace CompanyOS\Bundle\CoreBundle\Application\Auth\DTO;

final class OAuthClientResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $clientId,
        public readonly array $scopes,
        public readonly bool $isActive,
        public readonly string $createdAt
    ) {
    }
} 