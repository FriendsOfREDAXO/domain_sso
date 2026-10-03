<?php

namespace FriendsOfRedaxo\DomainSso;

use rex;
use rex_addon;
use rex_backend_login;
use rex_extension_point;
use rex_login;
use rex_request;
use rex_response;
use rex_user;
use rex_user_session;

/**
 * Ablauf der domainübergreifenden Backend-Anmeldung.
 *
 * 1. Nach der Anmeldung (bzw. sobald eine Domain-Sitzung fehlt) erzeugt das Backend je fehlender Domain
 *    ein Einmal-Ticket und leitet über alle Domains und zurück (Top-Level-Weiterleitungen, keine
 *    Drittanbieter-Cookies nötig).
 * 2. Jede Domain löst ihr Ticket im Frontend ein und schreibt die Backend-Anmeldung in die gemeinsame
 *    Frontend-/Backend-Sitzung – inkl. Eintrag in rex_user_session, damit REDAXOs eigene Prüfung greift.
 * 3. Abmelden auf einer Domain beendet die Sitzungen auf allen Domains.
 */
final class Sso
{
    private const SESSION_THROTTLE = 'domain_sso_last_sync';
    private const THROTTLE_SECONDS = 60;

    public static function isEnabled(): bool
    {
        return (bool) rex_addon::get('domain_sso')->getConfig('enabled', true);
    }

    /**
     * Backend (PAGE_CHECKED): fehlen Sitzungen auf anderen Domains, Weiterleitungskette starten.
     */
    public static function syncBackend(): void
    {
        $user = rex::getUser();
        if (!self::isEnabled() || !$user || null !== rex::getImpersonator()) {
            return;
        }
        // nur bei normalen Seitenaufrufen – nie bei Formularen, Ajax, PJAX oder API-Aufrufen
        if ('get' !== rex_request::requestMethod() || rex_request::isXmlHttpRequest() || rex_request::isPJAXRequest() || '' !== rex_request('rex-api-call', 'string', '')) {
            return;
        }
        if (!rex_request::isHttps() && !rex_addon::get('domain_sso')->getConfig('allow_http', false)) {
            return;
        }
        if ((int) rex_session(self::SESSION_THROTTLE, 'int', 0) > time() - self::THROTTLE_SECONDS) {
            return;
        }

        $current = Domains::current();
        $targets = Domains::all();
        // Backend über eine nicht teilnehmende Domain: keine Kette (die Rückkehr dorthin wäre nicht erlaubt)
        if (!isset($targets[$current])) {
            return;
        }
        unset($targets[$current]);
        if (!$targets) {
            return;
        }

        $sessionId = (string) session_id();
        if ('' === $sessionId) {
            return;
        }
        $groupKey = SessionGroup::keyOf($sessionId);
        if (null === $groupKey) {
            $groupKey = bin2hex(random_bytes(16));
            SessionGroup::add($sessionId, $groupKey, $user->getId(), $current);
        }
        $missing = array_diff_key($targets, array_flip(SessionGroup::liveHosts($groupKey)));
        if (!$missing) {
            return;
        }

        rex_set_session(self::SESSION_THROTTLE, time());
        Token::cleanup();
        SessionGroup::cleanup();

        // Kette rückwärts aufbauen: jedes Ticket kennt serverseitig sein nächstes Ziel
        $next = self::currentUrl();
        foreach (array_reverse($missing, true) as $host => $baseUrl) {
            $token = Token::create($user->getId(), $host, $next, $groupKey);
            $next = $baseUrl . '?' . Token::PARAM . '=' . $token;
        }

        rex_response::setHeader('Cache-Control', 'no-store');
        rex_response::sendRedirect($next);
    }

    /**
     * Frontend: Ticket einlösen, Backend-Anmeldung setzen und zum nächsten Ziel weiterleiten.
     */
    public static function redeemFromRequest(string $token): void
    {
        rex_response::setHeader('Cache-Control', 'no-store');
        rex_response::setHeader('Referrer-Policy', 'no-referrer');

        $host = Domains::current();
        $fallback = (rex_request::isHttps() ? 'https' : 'http') . '://' . $host . '/';
        $data = Token::redeem($token, $host);
        if (null === $data) {
            rex_response::sendRedirect($fallback);
        }

        $secure = rex_request::isHttps() || rex_addon::get('domain_sso')->getConfig('allow_http', false);
        if ($data['valid'] && self::isEnabled() && $secure && Domains::isParticipating($host)) {
            self::login($data['user_id'], $data['group_key'], $host);
        }

        rex_response::sendRedirect(Domains::isAllowedUrl($data['next_url']) ? $data['next_url'] : $fallback);
    }

    private static function login(int $userId, string $groupKey, string $host): void
    {
        $user = rex_user::get($userId);
        if (!$user || 1 !== (int) $user->getValue('status')) {
            return;
        }

        rex_login::startSession();
        $login = new rex_backend_login();

        // Bereits als derselbe Benutzer angemeldet und Sitzung gültig: nur der Gruppe zuordnen
        $alreadyLoggedIn = (int) $login->getSessionVar(rex_login::SESSION_USER_ID, 0) === $userId
            && (int) $login->getSessionVar(rex_login::SESSION_LAST_ACTIVITY, 0) > time() - (int) rex::getProperty('session_duration', 7200);
        if (!$alreadyLoggedIn) {
            // neue Sitzungs-ID (Schutz vor Session Fixation), dann wie beim regulären Login die Sitzungswerte setzen
            rex_login::regenerateSessionId();
            $login->setSessionVar(rex_login::SESSION_USER_ID, $userId);
            $login->setSessionVar(rex_login::SESSION_PASSWORD, $user->getValue('password'));
            $login->setSessionVar(rex_login::SESSION_START_TIME, time());
            $login->setSessionVar(rex_login::SESSION_LAST_ACTIVITY, time());
            $login->setSessionVar(rex_backend_login::SESSION_STAY_LOGGED_IN, false);
        }

        // REDAXO akzeptiert eine Backend-Sitzung nur mit Eintrag in rex_user_session
        rex_user_session::getInstance()->storeCurrentSession($login);
        SessionGroup::add((string) session_id(), $groupKey, $userId, $host);
    }

    /**
     * Backend nach dem Abmelden (?rex_logged_out=1): die Sitzungs-ID bleibt beim Abmelden erhalten,
     * darüber werden die Sitzungen der Gruppe auf allen Domains beendet.
     */
    public static function onLoggedOut(): void
    {
        $sessionId = (string) session_id();
        if ('' === $sessionId) {
            return;
        }
        $groupKey = SessionGroup::keyOf($sessionId);
        if (null !== $groupKey) {
            SessionGroup::terminate($groupKey);
        }
    }

    /** @param rex_extension_point<null> $ep */
    public static function onSessionRegenerated(rex_extension_point $ep): void
    {
        $previous = (string) $ep->getParam('previous_id');
        $new = (string) $ep->getParam('new_id');
        if ('' !== $previous && '' !== $new && $previous !== $new) {
            SessionGroup::renameSession($previous, $new);
        }
    }

    private static function currentUrl(): string
    {
        return (rex_request::isHttps() ? 'https' : 'http') . '://' . Domains::current() . rex_request::server('REQUEST_URI', 'string', '/');
    }
}
