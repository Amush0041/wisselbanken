<?php

namespace App\Exceptions\Rbac;

use RuntimeException;

class SodConflictException extends RuntimeException
{
    /** @param array<string> $conflictingRoles human-readable names of the blocking roles */
    public function __construct(public readonly array $conflictingRoles)
    {
        $names = implode(', ', $conflictingRoles);
        parent::__construct("SoD conflict: user already holds [{$names}].");
    }
}
