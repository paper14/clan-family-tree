<?php

namespace App\Models;

use App\Enums\ClanParent;
use App\Enums\Relation;
use App\Enums\Sex;
use App\Models\Scopes\ClanScope;
use App\Support\Format;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One person in the registry.
 *
 * The global ClanScope makes plain Person queries MEMBERSHIP queries. Anything about
 * descent — parents, children, spouses, the tree, the outline, print, a person's own page —
 * must use Person::unscoped(). Every relation below already does.
 */
#[ScopedBy([ClanScope::class])]
class Person extends Model
{
    use SoftDeletes;

    protected $table = 'people';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'clan_id' => 'integer',
            'father_id' => 'integer',
            'mother_id' => 'integer',
            'sex' => Sex::class,
            'clan_parent' => ClanParent::class,
            'father_relation' => Relation::class,
            'mother_relation' => Relation::class,
            'is_living' => 'boolean',
            'hide_second_parent' => 'boolean',
            'is_subclan_head' => 'boolean',
            'sibling_order' => 'integer',
            'generation' => 'integer',
        ];
    }

    /** Descent queries: the clan scope explicitly removed. */
    public static function unscoped(): Builder
    {
        return static::withoutGlobalScope(ClanScope::class);
    }

    /** Route binding is unscoped: opening a person from another clan works (and switches clan). */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::unscoped()->where($field ?? 'id', $value)->firstOrFail();
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class)->withTrashed();
    }

    public function father(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'father_id')->withoutGlobalScope(ClanScope::class);
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'mother_id')->withoutGlobalScope(ClanScope::class);
    }

    /** Siblings are ordered by sibling_order only — never by date (planning.md §2.6). */
    public function scopeInSiblingOrder(Builder $q): Builder
    {
        return $q->orderBy('sibling_order')->orderBy('id');
    }

    /** A sibling set is a (father_id, mother_id) pair, compared NULL-safely with <=>. */
    public function scopeSiblingSet(Builder $q, ?int $fatherId, ?int $motherId): Builder
    {
        return $q->whereRaw('father_id <=> ? AND mother_id <=> ?', [$fatherId, $motherId]);
    }

    public function fullName(): string
    {
        return Format::fullName($this);
    }

    public function hasParents(): bool
    {
        return $this->father_id !== null || $this->mother_id !== null;
    }

    /** The parent descended from the founders. */
    public function clanParentId(): ?int
    {
        if ($this->clan_parent === ClanParent::Mother) {
            return $this->mother_id ?? $this->father_id;
        }

        return $this->father_id ?? $this->mother_id;
    }

    /** The other parent — the spouse who married in, or a parent from another clan. */
    public function otherParentId(): ?int
    {
        $c = $this->clanParentId();
        if ($c === null) {
            return null;
        }

        return $c === $this->father_id ? $this->mother_id : $this->father_id;
    }

    public function relationTo(int $parentId): ?Relation
    {
        if ($parentId === $this->father_id) {
            return $this->father_relation;
        }

        return $parentId === $this->mother_id ? $this->mother_relation : null;
    }
}
