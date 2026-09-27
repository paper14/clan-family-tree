<?php

namespace App\Enums;

enum PhotoKind: string
{
    case Portrait = 'portrait';
    case Family = 'family';
    case SubclanGroup = 'subclan_group';
    case ClanGroup = 'clan_group';

    public function label(): string
    {
        return match ($this) {
            self::Portrait => 'Portrait',
            self::Family => 'Family picture',
            self::SubclanGroup => 'Subclan group photo',
            self::ClanGroup => 'Clan group photo',
        };
    }

    /** Portraits to 480 px on the long edge, family and group photos to 1400 px. */
    public function maxEdge(): int
    {
        return $this === self::Portrait ? config('clan.photos.portrait_max') : config('clan.photos.group_max');
    }
}
