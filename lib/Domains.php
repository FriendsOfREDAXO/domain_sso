<?php

namespace FriendsOfRedaxo\DomainSso;

use rex_addon;
use rex_request;
use rex_yrewrite;

/**
 * Domains, auf denen die Backend-Anmeldung geteilt wird (aus YRewrite).
 */
final class Domains
{
    /**
     * Alle teilnehmenden Domains: Host (inkl. Port) => Basis-URL der Domain.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $selected = array_map('strtolower', (array) rex_addon::get('domain_sso')->getConfig('hosts', []));
        $out = [];
        foreach (rex_yrewrite::getDomains() as $name => $domain) {
            if ('default' === $name || !$domain->getId()) {
                continue;
            }
            $url = (string) $domain->getUrl();
            $host = self::hostOf($url);
            if ('' === $host || ($selected && !in_array($host, $selected, true))) {
                continue;
            }
            if (!self::isSecureUrl($url) && !rex_addon::get('domain_sso')->getConfig('allow_http', false)) {
                continue;
            }
            $out[$host] = rtrim($url, '/') . '/';
        }
        return $out;
    }

    /**
     * Alle YRewrite-Domains ohne Auswahl-Filter (für die Einstellungen).
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        $out = [];
        foreach (rex_yrewrite::getDomains() as $name => $domain) {
            if ('default' === $name || !$domain->getId()) {
                continue;
            }
            $host = self::hostOf((string) $domain->getUrl());
            if ('' !== $host) {
                $out[$host] = rtrim((string) $domain->getUrl(), '/') . '/';
            }
        }
        return $out;
    }

    public static function current(): string
    {
        return strtolower(trim(rex_request::server('HTTP_HOST', 'string', '')));
    }

    public static function isParticipating(string $host): bool
    {
        return isset(self::all()[strtolower($host)]);
    }

    /** Ziel-URLs der Kette dürfen nur auf teilnehmende Domains zeigen */
    public static function isAllowedUrl(string $url): bool
    {
        $host = self::hostOf($url);
        return '' !== $host && self::isParticipating($host) && (self::isSecureUrl($url) || rex_addon::get('domain_sso')->getConfig('allow_http', false));
    }

    public static function hostOf(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }
        return strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    }

    private static function isSecureUrl(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }
}
