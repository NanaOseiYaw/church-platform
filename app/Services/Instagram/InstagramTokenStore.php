<?php

namespace App\Services\Instagram;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Where the current Instagram access token lives.
 *
 * Meta's long-lived tokens last 60 days and must be refreshed before then —
 * a token not refreshed in time expires for good. Refreshing returns a *new*
 * token, which has to be kept somewhere that survives:
 *
 *  - not .env: the app cannot safely rewrite its own environment file;
 *  - not the cache: every deploy runs `optimize:clear`, which empties it;
 *  - not the church settings JSON: a background write there can race an
 *    admin saving other settings and silently undo their change.
 *
 * So it is kept on the private `local` disk (storage/app/private, never
 * web-served without a signed URL) and encrypted with APP_KEY on top.
 *
 * The token in .env is the starting point. The stored file records a hash of
 * the .env token it descends from; if someone pastes a new token into .env,
 * the hashes differ and the new token takes over instead of a stale stored one.
 */
class InstagramTokenStore
{
    private const PATH = 'instagram/token.json';

    /** The token to use for API calls, or null if none is configured. */
    public function current(): ?string
    {
        $seed = $this->seed();
        if ($seed === null) {
            return null;
        }

        $stored = $this->read();

        return ($stored && ($stored['seed_hash'] ?? null) === $this->hash($seed))
            ? $stored['token']
            : $seed;
    }

    /** Keep a refreshed token, linked to the .env token it descends from. */
    public function save(string $token, ?int $expiresIn): void
    {
        $seed = $this->seed();
        if ($seed === null) {
            return;
        }

        Storage::disk('local')->put(self::PATH, Crypt::encryptString(json_encode([
            'token'        => $token,
            'seed_hash'    => $this->hash($seed),
            'refreshed_at' => now()->toIso8601String(),
            'expires_at'   => $expiresIn ? now()->addSeconds($expiresIn)->toIso8601String() : null,
        ])));
    }

    /** When the current token was last refreshed and when it runs out, if known. */
    public function status(): array
    {
        $seed   = $this->seed();
        $stored = $this->read();
        $fromStore = $seed && $stored && ($stored['seed_hash'] ?? null) === $this->hash($seed);

        return [
            'configured'   => $seed !== null,
            'source'       => $seed === null ? 'none' : ($fromStore ? 'refreshed' : 'env'),
            'refreshed_at' => $fromStore ? ($stored['refreshed_at'] ?? null) : null,
            'expires_at'   => $fromStore ? ($stored['expires_at'] ?? null) : null,
        ];
    }

    private function seed(): ?string
    {
        $token = trim((string) config('services.instagram.access_token'));

        return $token === '' ? null : $token;
    }

    private function read(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($disk->get(self::PATH)), true);
        } catch (DecryptException) {
            // APP_KEY changed or the file is damaged: fall back to the .env token.
            return null;
        }

        return is_array($data) && ! empty($data['token']) ? $data : null;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
