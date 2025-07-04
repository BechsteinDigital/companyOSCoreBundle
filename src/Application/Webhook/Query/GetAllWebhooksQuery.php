<?php

namespace CompanyOS\Bundle\CoreBundle\Application\Webhook\Query;

use CompanyOS\Bundle\CoreBundle\Application\Query\Query;

class GetAllWebhooksQuery implements Query
{
    public function __construct(
        public readonly bool $activeOnly = false,
        public readonly ?string $eventType = null,
        public readonly ?string $search = null,
        public readonly int $limit = 50,
        public readonly int $offset = 0
    ) {
    }
} 