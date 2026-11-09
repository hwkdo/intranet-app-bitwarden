<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Support;

final class UserSecretKind
{
    public const INTRANET_API = 'intranet-api';

    public const OPENWEBUI = 'openwebui';

    public const FIELD_NAME = 'intranet-key-kind';

    public const INSTANCE_FIELD = 'intranet-instance';

    public static function isValid(string $kind): bool
    {
        return in_array($kind, [self::INTRANET_API, self::OPENWEBUI], true);
    }

    public static function instanceUrl(): string
    {
        return rtrim(trim((string) config('app.url')), '/');
    }

    public static function instanceHost(): string
    {
        $url = self::instanceUrl();
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $host = is_string($host) && $host !== '' ? strtolower($host) : 'unknown';

        if (is_int($port) && $port > 0) {
            $host .= ':'.$port;
        }

        return $host;
    }

    public static function instanceLabel(): string
    {
        $env = trim((string) config('app.env'));

        if ($env === '') {
            return self::instanceHost();
        }

        return $env.' · '.self::instanceHost();
    }

    public static function instanceSlug(): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', self::instanceHost()));
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'unknown';
    }

    public static function collectionExternalId(int|string $userId): string
    {
        return 'iu-keys-'.self::instanceSlug().'-'.$userId;
    }

    public static function collectionName(string $email): string
    {
        return 'API-Keys - '.trim($email).' ('.self::instanceLabel().')';
    }

    public static function itemName(string $kind): string
    {
        $name = match ($kind) {
            self::INTRANET_API => 'Intranet API-Token',
            self::OPENWEBUI => 'OpenWebUI API-Token',
            default => throw new \InvalidArgumentException('Unbekannte Schlüssel-Art.'),
        };

        return $name.' ('.self::instanceLabel().')';
    }

    public static function uri(string $kind): string
    {
        $uri = match ($kind) {
            self::INTRANET_API => (string) config('app.url'),
            self::OPENWEBUI => (string) config('openwebui-api-laravel.base_api_url'),
            default => '',
        };

        return rtrim(trim($uri), '/');
    }
}
