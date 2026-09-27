<?php

$registry = env('CLAN_REGISTRY_DATABASE', 'clan_registry');
$database = env('DB_DATABASE', $registry);
$isRegistry = $database === $registry;

return [

    /*
    | The real registry database. Destructive commands are refused against it, and
    | plain `migrate` is refused too: migrations go through `php artisan app:migrate`,
    | which backs up first (docs/architecture-local.md §3).
    */
    'registry_database' => $registry,

    // False when the app is running against the demo (or test) database. Demo mode keeps
    // its photos and backups in separate folders so they never mix with the registry's.
    'is_registry' => $isRegistry,
    'database' => $database,

    'backup' => [
        // A folder outside the project, ideally on another drive (architecture-local.md §4).
        'path' => env('CLAN_BACKUP_PATH')
            ? rtrim(env('CLAN_BACKUP_PATH'), '\\/').($isRegistry ? '' : DIRECTORY_SEPARATOR.'demo-'.$database)
            : null,
        // Automatic backups kept; manual ones are never deleted.
        'keep_auto' => (int) env('CLAN_BACKUP_KEEP_AUTO', 20),
        'mysqldump' => env('CLAN_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('CLAN_MYSQL_PATH', 'mysql'),
    ],

    'photos' => [
        // Folder on the public disk (storage/app/public/…); photos.file_path is relative to it.
        'folder' => $isRegistry ? 'photos' : 'photos-'.$database,
        // Web-sized copies only (implementation-notes.md §7).
        'portrait_max' => 480,
        'group_max' => 1400,
        'jpeg_quality' => 82,
    ],

];
