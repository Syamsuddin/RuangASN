<?php
namespace App\Services\Ai;

use RuntimeException;

/**
 * Thrown by a provider when an upstream/transport call fails, so the
 * AiProviderManager can catch it and fall back to the next provider.
 */
class AiProviderException extends RuntimeException
{
}
