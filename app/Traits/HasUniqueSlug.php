<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Automatic, tenant-aware unique slug generation.
 *
 * ─── Quick-start ────────────────────────────────────────────────────────────
 *
 *   use App\Traits\HasUniqueSlug;
 *
 *   class Department extends Model
 *   {
 *       use HasUniqueSlug;
 *
 *       protected function slugConfig(): array
 *       {
 *           return ['from' => 'name', 'to' => 'slug', 'scope' => 'church_id'];
 *       }
 *   }
 *
 * ─── Collision behaviour ────────────────────────────────────────────────────
 *
 *   "Media Team"  →  media-team
 *   "Media Team"  →  media-team-2   (same church)
 *   "Media Team"  →  media-team-3
 *
 *   A different church can still use "media-team" — scoping is per-tenant.
 *
 * ─── Design notes ───────────────────────────────────────────────────────────
 *
 *  • One prefix-scan query ("slug LIKE 'base%'") fetches all candidates in a
 *    single round-trip. Index-friendly on every major DB engine.
 *
 *  • Exact-match numbering: false positives from the LIKE scan (e.g.
 *    'media-team-setup') sit in the candidates array but are never matched by
 *    the `in_array("{$base}-{$i}", ...)` loop, so they have zero effect.
 *
 *  • Soft-deleted records are included automatically. Reusing the slug of a
 *    deleted resource would silently break bookmarks and SEO tombstones.
 *
 *  • TOCTOU window: a tiny race remains between the prefix scan and the INSERT.
 *    The database's UNIQUE constraint is the final safety net; the application
 *    layer catches collisions before they hit the DB in the overwhelming
 *    majority of cases. For a church SaaS where two admins simultaneously name
 *    a department identically, the DB will throw a QueryException which the
 *    default exception handler surfaces as a 422 — the user can simply retry.
 *
 *  • The trait is ready for Event, Announcement, and Sermon. Wire it up once
 *    those tables gain a `slug` column and a UNIQUE(church_id, slug) index.
 */
trait HasUniqueSlug
{
    // ── Configuration ──────────────────────────────────────────────────────────

    /**
     * Override in the model to customise slug behaviour.
     *
     * @return array{from: string, to: string, scope: string|null}
     */
    protected function slugConfig(): array
    {
        return [
            'from'  => 'name',   // Source attribute to slugify
            'to'    => 'slug',   // Target attribute to store the slug in
            'scope' => null,     // Column for tenant scoping, e.g. 'church_id'
        ];
    }

    // ── Eloquent lifecycle hook ────────────────────────────────────────────────

    /**
     * Laravel calls bootHasUniqueSlug() automatically for every model that
     * uses this trait. No manual ::boot() override is needed in the model.
     */
    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function (self $model): void {
            $cfg = $model->slugConfig();
            $to  = $cfg['to'] ?? 'slug';

            // Only generate when the slug is absent — allows callers to supply
            // an explicit slug (e.g. an import script) and bypass generation.
            if (! empty($model->{$to})) {
                return;
            }

            $model->{$to} = static::makeUniqueSlug(
                source:      $model->{$cfg['from'] ?? 'name'},
                scopeColumn: $cfg['scope'] ?? null,
                scopeValue:  isset($cfg['scope']) ? $model->{$cfg['scope']} : null,
            );
        });
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Generate a slug that is unique within the given scope.
     *
     * This is a public static method so it can be called from controllers,
     * seeders, import jobs, or anywhere else that needs a guaranteed-unique
     * slug before the record is persisted.
     *
     * @param  string       $source       The human-readable string to slugify.
     * @param  string|null  $scopeColumn  DB column that defines uniqueness scope.
     *                                    Pass null for global uniqueness (e.g. Church).
     * @param  mixed        $scopeValue   Value of that column (e.g. the church_id int).
     * @param  int|null     $ignoreId     Exclude this primary key from the collision
     *                                    check — useful for a "regenerate slug" action
     *                                    after a record already exists.
     */
    public static function makeUniqueSlug(
        string $source,
        ?string $scopeColumn = null,
        mixed $scopeValue = null,
        ?int $ignoreId = null,
    ): string {
        $base = Str::slug($source);

        // Str::slug returns '' for strings with no ASCII-compatible characters.
        // Fall back to a generic token so the slug column is never left empty.
        if ($base === '') {
            $base = 'item';
        }

        $cfg = (new static)->slugConfig();
        $col = $cfg['to'] ?? 'slug';

        $existing = static::existingSlugsLike($base, $col, $scopeColumn, $scopeValue, $ignoreId);

        // Happy path: base slug is not taken.
        if (! in_array($base, $existing, strict: true)) {
            return $base;
        }

        // Scan from 2 upward and return the first gap.
        // Example: if ['media-team', 'media-team-2', 'media-team-3'] exist,
        // returns 'media-team-4'. If only ['media-team', 'media-team-3'] exist,
        // returns 'media-team-2' (fills the gap — avoids confusing numbering jumps).
        $i = 2;
        while (in_array("{$base}-{$i}", $existing, strict: true)) {
            $i++;
        }

        return "{$base}-{$i}";
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Fetch all slugs that start with $base in one indexed prefix scan.
     *
     * @return list<string>
     */
    private static function existingSlugsLike(
        string $base,
        string $slugColumn,
        ?string $scopeColumn,
        mixed $scopeValue,
        ?int $ignoreId,
    ): array {
        $query = static::query()
            ->where($slugColumn, 'like', $base . '%');

        if ($scopeColumn !== null && $scopeValue !== null) {
            $query->where($scopeColumn, $scopeValue);
        }

        if ($ignoreId !== null) {
            $query->where((new static)->getKeyName(), '!=', $ignoreId);
        }

        // Include soft-deleted records so their slugs remain reserved.
        // class_uses_recursive() traverses the full inheritance chain.
        if (in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive(static::class),
            strict: true,
        )) {
            $query->withTrashed();
        }

        return $query->pluck($slugColumn)->all();
    }
}
