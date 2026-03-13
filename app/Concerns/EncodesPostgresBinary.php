<?php

namespace App\Concerns;

/**
 * Safely base64-encodes values that may be PHP stream resources.
 */
trait EncodesPostgresBinary
{
    protected function safeBase64Encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return base64_encode($value);
    }
}
