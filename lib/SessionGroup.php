<?php

namespace FriendsOfRedaxo\DomainSso;

use rex;
use rex_sql;

/**
 * Die Backend-Sitzungen einer Anmeldung auf allen Domains bilden eine Gruppe.
 * Abmelden auf einer Domain beendet alle Sitzungen der Gruppe.
 */
final class SessionGroup
{
    public static function table(): string
    {
        return rex::getTable('domain_sso_session');
    }

    public static function keyOf(string $sessionId): ?string
    {
        $rows = rex_sql::factory()->getArray('SELECT group_key FROM ' . self::table() . ' WHERE session_id = ?', [$sessionId]);
        return $rows ? (string) $rows[0]['group_key'] : null;
    }

    public static function add(string $sessionId, string $groupKey, int $userId, string $host): void
    {
        rex_sql::factory()
            ->setTable(self::table())
            ->setValue('session_id', $sessionId)
            ->setValue('group_key', $groupKey)
            ->setValue('user_id', $userId)
            ->setValue('host', strtolower($host))
            ->setValue('created', rex_sql::datetime())
            ->insertOrUpdate();
    }

    /**
     * Hosts mit noch gültiger Backend-Sitzung in der Gruppe (Sitzung existiert und ist nicht abgelaufen).
     *
     * @return list<string>
     */
    public static function liveHosts(string $groupKey): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT DISTINCT g.host FROM ' . self::table() . ' g JOIN ' . rex::getTable('user_session') . ' s ON s.session_id = g.session_id
             WHERE g.group_key = ? AND UNIX_TIMESTAMP(s.last_activity) >= ?',
            [$groupKey, time() - (int) rex::getProperty('session_duration', 7200) + 60],
        );
        return array_map(static fn (array $r): string => (string) $r['host'], $rows);
    }

    /**
     * Mitglieder der Gruppe (für die Statusanzeige).
     *
     * @return list<array{host: string, session_id: string, last_activity: ?string}>
     */
    public static function members(string $groupKey): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT g.host, g.session_id, s.last_activity FROM ' . self::table() . ' g LEFT JOIN ' . rex::getTable('user_session') . ' s ON s.session_id = g.session_id
             WHERE g.group_key = ? ORDER BY g.created',
            [$groupKey],
        );
        return array_map(static fn (array $r): array => ['host' => (string) $r['host'], 'session_id' => (string) $r['session_id'], 'last_activity' => null !== $r['last_activity'] ? (string) $r['last_activity'] : null], $rows);
    }

    /** Alle Sitzungen der Gruppe beenden (Backend-Sitzungen in rex_user_session löschen) */
    public static function terminate(string $groupKey): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE s FROM ' . rex::getTable('user_session') . ' s JOIN ' . self::table() . ' g ON g.session_id = s.session_id WHERE g.group_key = ?',
            [$groupKey],
        );
        $sql->setQuery('DELETE FROM ' . self::table() . ' WHERE group_key = ?', [$groupKey]);
    }

    public static function renameSession(string $previousId, string $newId): void
    {
        rex_sql::factory()->setQuery('UPDATE ' . self::table() . ' SET session_id = ? WHERE session_id = ?', [$newId, $previousId]);
    }

    /** Einträge ohne zugehörige Backend-Sitzung entfernen */
    public static function cleanup(): void
    {
        rex_sql::factory()->setQuery(
            'DELETE g FROM ' . self::table() . ' g LEFT JOIN ' . rex::getTable('user_session') . ' s ON s.session_id = g.session_id
             WHERE s.session_id IS NULL AND g.created < ?',
            [rex_sql::datetime(time() - 600)],
        );
    }
}
