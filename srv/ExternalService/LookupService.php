<?php
declare(strict_types=1);

namespace Heurist\ExternalService;

/** Placeholder for future external data lookup providers. */
final class LookupService
{
    public function lookup(array $request): array
    {
        throw new \LogicException('External data lookup is not implemented.');
    }
}
