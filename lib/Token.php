<?php

namespace FriendsOfRedaxo\DomainSso;

use rex;
use rex_addon;
use rex_sql;

/**
 * Einmal-Tickets: zufällig, nur als SHA-256-Hash gespeichert, kurz gültig, an Benutzer und Ziel-Host gebunden.
 */
final class Token
{
    public const PARAM = 'domain_sso';

    public static function table(): string
    {
        return rex::getTable('domain_sso_token');
    }

    /** Legt ein Ticket an und liefert den Klartext (nur für die URL) */
    public static function create(int $userId, string $host, string $nextUrl, string $groupKey): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ttl = max(10, (int) rex_addon::get('domain_sso')->getConfig('ttl', 60));
        rex_sql::factory()
            ->setTable(self::table())
            ->setValue('token_hash', hash('sha256', $token))
            ->setValue('user_id', $userId)
            ->setValue('host', strtolower($host))
            ->setValue('next_url', $nextUrl)
            ->setValue('group_key', $groupKey)
            ->setValue('created', rex_sql::datetime())
            ->setValue('expires', rex_sql::datetime(time() + $ttl))
            ->insert();
        return $token;
    }

    /**
     * Löst ein Ticket genau einmal ein.
     *
     * @return array{user_id: int, next_url: string, group_key: string, valid: bool}|null null = Ticket unbekannt
     */
    public static function redeem(string $token, string $host): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $sql = rex_sql::factory();
        $rows = $sql->getArray('SELECT user_id, host, next_url, group_key, expires, used FROM ' . self::table() . ' WHERE token_hash = ?', [$hash]);
        if (!$rows) {
            return null;
        }
        $row = $rows[0];
        // atomar als benutzt markieren – parallele Aufrufe gewinnen nur einmal
        $sql->setQuery('UPDATE ' . self::table() . ' SET used = ? WHERE token_hash = ? AND used IS NULL AND expires >= ?', [rex_sql::datetime(), $hash, rex_sql::datetime()]);
        $valid = 1 === $sql->getRows() && hash_equals((string) $row['host'], strtolower($host));
        return [
            'user_id' => (int) $row['user_id'],
            'next_url' => (string) $row['next_url'],
            'group_key' => (string) $row['group_key'],
            'valid' => $valid,
        ];
    }

    /** Abgelaufene Tickets entfernen */
    public static function cleanup(): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE expires < ?', [rex_sql::datetime(time() - 3600)]);
    }
}
