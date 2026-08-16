<?php

namespace App\Support;

final class DocumentationApiScope
{
    public const CLIENTS_READ = 'documentation.clients.read';

    public const USERS_READ = 'documentation.users.read';

    public const NETWORK_READ = 'documentation.network.read';

    public const PII_READ = 'documentation.pii.read';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CLIENTS_READ,
            self::USERS_READ,
            self::NETWORK_READ,
            self::PII_READ,
        ];
    }
}
