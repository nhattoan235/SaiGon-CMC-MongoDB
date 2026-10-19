<?php

namespace App\Mongo;

use Illuminate\Auth\EloquentUserProvider;

/** Remember-me cookie identifiers are strings; persisted account identifiers remain integers. */
class NumericUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        return ctype_digit((string) $identifier) ? parent::retrieveById((int) $identifier) : null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return ctype_digit((string) $identifier) ? parent::retrieveByToken((int) $identifier, $token) : null;
    }
}
