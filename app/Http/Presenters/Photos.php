<?php

namespace App\Http\Presenters;

use App\Enums\PhotoKind;
use App\Models\Photo;

/** One photo panel: the photos of one kind attached to one person, marriage or clan. */
class Photos
{
    public function group(PhotoKind $kind, string $type, int $id, ?string $title = null, ?string $sub = null): array
    {
        return [
            'kind' => $kind->value,
            'kindLabel' => $kind->label(),
            'type' => $type,
            'id' => $id,
            'title' => $title ?? $kind->label(),
            'sub' => $sub,
            'group' => $kind !== PhotoKind::Portrait,
            'photos' => Photo::sameAttachment($kind, $type, $id)->inPhotoOrder()->get()->map(fn (Photo $p) => [
                'id' => $p->id,
                'url' => $p->url(),
                'caption' => $p->caption,
                'year' => $p->year,
                'is_primary' => $p->is_primary,
                'width' => $p->width,
                'height' => $p->height,
            ])->values(),
        ];
    }

    public function primary(PhotoKind $kind, string $type, int $id): ?array
    {
        $p = Photo::sameAttachment($kind, $type, $id)->inPhotoOrder()->first();

        return $p ? ['url' => $p->url(), 'caption' => $p->captionLine(), 'width' => $p->width, 'height' => $p->height] : null;
    }
}
