<?php

namespace App\Models;

use App\Enums\PhotoKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'clan_id' => 'integer',
            'person_id' => 'integer',
            'marriage_id' => 'integer',
            'kind' => PhotoKind::class,
            'year' => 'integer',
            'is_primary' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** Main first, then year (no year last), then id (implementation-notes.md §7). */
    public function scopeInPhotoOrder(Builder $q): Builder
    {
        return $q->orderByDesc('is_primary')->orderByRaw('`year` IS NULL')->orderBy('year')->orderBy('id');
    }

    /** The photos sharing one attachment: a person, a marriage, or a clan alone. */
    public function scopeSameAttachment(Builder $q, PhotoKind $kind, string $type, int $id): Builder
    {
        $q->where('kind', $kind->value);

        return match ($type) {
            'marriage' => $q->where('marriage_id', $id),
            'clan' => $q->where('clan_id', $id)->whereNull('person_id')->whereNull('marriage_id'),
            default => $q->where('person_id', $id)->whereNull('marriage_id'),
        };
    }

    /** Relative, so it works whichever local address the browser opened. */
    public function url(): string
    {
        return '/storage/'.config('clan.photos.folder').'/'.$this->file_path;
    }

    public function absolutePath(): string
    {
        return Storage::disk('public')->path(config('clan.photos.folder').'/'.$this->file_path);
    }

    /** "Back row, L–R: … (1998)" */
    public function captionLine(): string
    {
        return trim(implode(' ', array_filter([$this->caption, $this->year ? '('.$this->year.')' : null])));
    }
}
