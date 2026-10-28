<?php

namespace App\Exceptions\Portal;

use Exception;

/**
 * The visitor may not use the portal for this booking. The message is
 * safe to show to the visitor.
 */
class PortalAccessDenied extends Exception
{
    public const DEFAULT_MESSAGE = 'Unable to access the Portal';

    public static function generic(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }
}
