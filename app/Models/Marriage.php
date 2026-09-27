<?php

namespace App\Models;

use App\Enums\MarriageStatus;
use App\Models\Scopes\ClanScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A marriage may span two clans, so it has no clan_id and is never scoped. */
class Marriage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'husband_id' => 'integer',
            'wife_id' => 'integer',
            'status' => MarriageStatus::class,
        ];
    }

    public function husband(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'husband_id')->withoutGlobalScope(ClanScope::class);
    }

    public function wife(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'wife_id')->withoutGlobalScope(ClanScope::class);
    }

    /** Marriage order: date ascending with NULLs last, then id (data-model.md §3). */
    public function scopeInMarriageOrder(Builder $q): Builder
    {
        return $q->orderByRaw('`date` IS NULL')->orderBy('date')->orderBy('id');
    }

    public function scopeOf(Builder $q, int $personId): Builder
    {
        return $q->where(fn ($w) => $w->where('husband_id', $personId)->orWhere('wife_id', $personId));
    }

    public function spouseIdOf(int $personId): ?int
    {
        return $this->husband_id === $personId ? $this->wife_id : $this->husband_id;
    }
}
