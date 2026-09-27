<?php

namespace App\Models;

use App\Models\Scopes\ClanScope;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clan extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'founder_id' => 'integer',
            'founder_spouse_id' => 'integer',
        ];
    }

    public function founder(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'founder_id')->withoutGlobalScope(ClanScope::class);
    }

    public function founderSpouse(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'founder_spouse_id')->withoutGlobalScope(ClanScope::class);
    }

    public function label(): string
    {
        return Format::clanLabel($this);
    }

    public function isFounder(?Person $p): bool
    {
        return $p !== null && $p->clan_id === $this->id
            && ($p->id === $this->founder_id || $p->id === $this->founder_spouse_id);
    }

    /** "Isko and Sela", or "not set". */
    public function foundersText(): string
    {
        $names = array_filter([
            $this->founder ? $this->founder->fullName() : null,
            $this->founderSpouse ? $this->founderSpouse->fullName() : null,
        ]);

        return $names ? implode(' and ', $names) : 'not set';
    }
}
