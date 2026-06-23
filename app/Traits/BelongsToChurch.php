<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToChurch
{
    public static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $builder) {
            if ($churchId = app('church.id')) {
                $builder->where(
                    (new static())->getTable() . '.church_id',
                    $churchId
                );
            }
        });
    }

    /**
     * Bypass the global scope and query by an explicit church ID.
     * Accepts null so call-sites that pass $user->church_id (nullable FK)
     * don't throw a TypeError — in that case no church filter is applied.
     */
    public function scopeForChurch(Builder $query, ?int $churchId): Builder
    {
        $query->withoutGlobalScope('church');

        if ($churchId !== null) {
            $query->where($this->getTable() . '.church_id', $churchId);
        }

        return $query;
    }
}
