<?php

use FriendsOfRedaxo\DomainSso\InfoCenter\DomainSwitchWidget;
use FriendsOfRedaxo\DomainSso\Sso;
use FriendsOfRedaxo\DomainSso\Token;

/** @var rex_addon $this */

if (PHP_SAPI === 'cli' || !rex_addon::get('yrewrite')->isAvailable()) {
    return;
}

// Sitzungs-ID gewechselt (z. B. Rechte-Wechsel) – Gruppen-Zuordnung mitnehmen
rex_extension::register('SESSION_REGENERATED', [Sso::class, 'onSessionRegenerated']);

// Info Center (AddOn info_center, falls installiert): Widget zum Wechseln zwischen den Domains
if (rex_addon::get('info_center')->isAvailable()) {
    rex_extension::register('PACKAGES_INCLUDED', static function (): void {
        if (!Sso::isEnabled() || !class_exists(\KLXM\InfoCenter\InfoCenter::class)) {
            return;
        }
        $widget = new DomainSwitchWidget();
        // ohne eigene Einstellung im Info Center direkt unter der Suche
        if (!isset(rex_addon::get('info_center')->getConfig('widgets', [])[$widget->getId()]['prio'])) {
            $widget->setPriority(5);
        }
        \KLXM\InfoCenter\InfoCenter::getInstance()->registerWidget($widget);
    });
}

if (rex::isFrontend()) {
    // Ticket einlösen und weiterleiten – nach YRewrite (Domains werden dort bei PACKAGES_INCLUDED geladen), vor jeder Seitenausgabe
    $domainSsoToken = rex_get(Token::PARAM, 'string', '');
    if ('' !== $domainSsoToken) {
        rex_extension::register('PACKAGES_INCLUDED', static fn () => Sso::redeemFromRequest($domainSsoToken));
    }
    return;
}

if (rex::isBackend()) {
    // Nach dem Abmelden: Sitzungen auf allen Domains beenden
    if (rex_get('rex_logged_out', 'boolean', false)) {
        Sso::onLoggedOut();
    }
    // Angemeldet: fehlende Domain-Sitzungen anlegen (Weiterleitungskette)
    rex_extension::register('PAGE_CHECKED', static fn () => Sso::syncBackend(), rex_extension::LATE);
}
