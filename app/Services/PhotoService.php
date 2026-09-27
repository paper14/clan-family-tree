<?php

namespace App\Services;

use App\Enums\PhotoKind;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photos (planning.md §2.11, implementation-notes.md §7). The app keeps WEB-SIZED COPIES
 * only: portraits to 480 px on the long edge, family and group photos to 1400 px, JPEG at
 * quality 82. Camera originals stay outside the app.
 */
class PhotoService
{
    /**
     * @param  string  $type  person | marriage | clan — what the photo attaches to
     */
    public function store(UploadedFile $file, PhotoKind $kind, string $type, int $attachId, int $clanId, ?string $caption, ?string $year): Photo
    {
        $year = trim((string) $year);
        if ($year !== '' && ! preg_match('/^\d{4}$/', $year)) {
            throw ValidationException::withMessages(['year' => 'Year should be four digits, like 1998 — or leave it blank.']);
        }

        [$jpeg, $w, $h] = $this->resize($file, $kind->maxEdge());

        $name = now()->format('Y').'/'.Str::uuid().'.jpg';
        $disk = Storage::disk('public');
        $disk->put(config('clan.photos.folder').'/'.$name, $jpeg);

        return DB::transaction(function () use ($kind, $type, $attachId, $clanId, $caption, $year, $name, $w, $h) {
            $first = ! Photo::sameAttachment($kind, $type, $attachId)->exists();

            return Photo::create([
                'clan_id' => $clanId,
                'person_id' => $type === 'person' ? $attachId : null,
                'marriage_id' => $type === 'marriage' ? $attachId : null,
                'kind' => $kind,
                'file_path' => $name,
                'caption' => trim((string) $caption) ?: null,
                'year' => $year !== '' ? (int) $year : null,
                'is_primary' => $first, // the first photo of a kind becomes the main one
                'width' => $w,
                'height' => $h,
            ]);
        });
    }

    public function update(Photo $photo, ?string $caption, ?string $year): void
    {
        $year = trim((string) $year);
        if ($year !== '' && ! preg_match('/^\d{4}$/', $year)) {
            throw ValidationException::withMessages(['year' => 'Year should be four digits, like 1998 — or leave it blank.']);
        }
        $photo->update(['caption' => trim((string) $caption) ?: null, 'year' => $year !== '' ? (int) $year : null]);
    }

    public function makePrimary(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            $this->siblings($photo)->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });
    }

    /** Deleting the main photo promotes the next one. */
    public function delete(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            $wasPrimary = $photo->is_primary;
            $this->deleteFile($photo);
            $photo->delete();
            if ($wasPrimary && ($next = $this->siblings($photo)->inPhotoOrder()->first())) {
                $next->update(['is_primary' => true]);
            }
        });
    }

    public function deleteFile(Photo $photo): void
    {
        File::delete($photo->absolutePath());
    }

    private function siblings(Photo $photo)
    {
        [$type, $id] = $photo->marriage_id ? ['marriage', $photo->marriage_id]
            : ($photo->person_id ? ['person', $photo->person_id] : ['clan', $photo->clan_id]);

        return Photo::sameAttachment($photo->kind, $type, $id);
    }

    /** @return array{0: string, 1: int, 2: int} JPEG bytes, width, height */
    private function resize(UploadedFile $file, int $max): array
    {
        $bytes = @file_get_contents($file->getRealPath());
        $img = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        if (! $img) {
            throw ValidationException::withMessages(['file' => 'Couldn’t read that image. Use a JPEG or PNG.']);
        }

        // Camera photos carry their rotation in EXIF; apply it before resizing.
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            $exif = @exif_read_data($file->getRealPath());
            $img = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        // JPEG has no transparency: flatten onto white.
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($out, null, config('clan.photos.jpeg_quality'));
        $jpeg = ob_get_clean();

        return [$jpeg, $nw, $nh];
    }
}
