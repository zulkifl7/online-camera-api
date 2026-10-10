<?php

namespace App\Support;

final class AdminPermission
{
    public const CLIENTS_READ = 'clients.read';

    public const CLIENTS_WRITE = 'clients.write';

    public const COLLECTIONS_WRITE = 'collections.write';

    public const PHOTOS_READ = 'photos.read';

    public const PHOTOS_RESTORE = 'photos.restore';

    public const PHOTOS_PERMANENTLY_DELETE = 'photos.permanently_delete';

    public const AUDIT_LOGS_READ = 'audit_logs.read';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CLIENTS_READ,
            self::CLIENTS_WRITE,
            self::COLLECTIONS_WRITE,
            self::PHOTOS_READ,
            self::PHOTOS_RESTORE,
            self::PHOTOS_PERMANENTLY_DELETE,
            self::AUDIT_LOGS_READ,
        ];
    }
}
