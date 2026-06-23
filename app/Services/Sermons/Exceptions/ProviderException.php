<?php

namespace App\Services\Sermons\Exceptions;

use RuntimeException;

/**
 * Thrown when a sermon provider (YouTube, Vimeo, etc.) returns an error
 * or the channel/video cannot be found.
 *
 * Controllers catch this and surface it as a validation error or flash message.
 */
class ProviderException extends RuntimeException
{
    public static function apiError(string $provider, string $message, int $code = 0): static
    {
        return new static("[{$provider}] API error: {$message}", $code);
    }

    public static function channelNotFound(string $provider, string $input): static
    {
        return new static("[{$provider}] Channel not found for input: {$input}");
    }

    public static function notConfigured(string $provider): static
    {
        return new static("[{$provider}] API key is not configured. Set YOUTUBE_API_KEY in .env");
    }
}
