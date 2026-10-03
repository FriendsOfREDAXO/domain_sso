<?php

namespace FriendsOfRedaxo\DomainSso\InfoCenter;

use FriendsOfRedaxo\DomainSso\Domains;
use FriendsOfRedaxo\DomainSso\SessionGroup;
use KLXM\InfoCenter\AbstractWidget;
use rex;
use rex_i18n;
use rex_path;

/**
 * Widget für das Info Center (AddOn info_center): zwischen den Domains der Installation wechseln.
 * Dank domain_sso ist man auf allen Domains angemeldet – im Frontend wie im Backend.
 * Wird nur geladen, wenn info_center installiert und aktiv ist (siehe boot.php).
 */
final class DomainSwitchWidget extends AbstractWidget
{
    protected string $id = 'domain_sso';

    public function __construct()
    {
        parent::__construct();
        $this->title = rex_i18n::msg('domain_sso_ic_title');
    }

    public function render(): string
    {
        $domains = Domains::all();
        if (count($domains) < 2) {
            return '';
        }

        $current = Domains::current();
        $groupKey = '' !== (string) session_id() ? SessionGroup::keyOf((string) session_id()) : null;
        $live = null !== $groupKey ? array_flip(SessionGroup::liveHosts($groupKey)) : [];
        $backend = self::backendPath();

        $items = '';
        foreach ($domains as $host => $baseUrl) {
            $isCurrent = $host === $current;
            $loggedIn = $isCurrent || isset($live[$host]);
            $status = $loggedIn ? rex_i18n::msg('domain_sso_ic_logged_in') : rex_i18n::msg('domain_sso_ic_not_yet');
            $items .= '<li class="domain-sso-ic__item' . ($isCurrent ? ' is-current' : '') . '">'
                . '<span class="domain-sso-ic__dot' . ($loggedIn ? ' is-on' : '') . '" title="' . rex_escape($status) . '" aria-hidden="true"></span>'
                . '<span class="domain-sso-ic__name">' . rex_escape($host)
                . ($isCurrent ? ' <small>(' . rex_escape(rex_i18n::msg('domain_sso_ic_current')) . ')</small>' : '')
                . '<span class="domain-sso-ic__sr">' . rex_escape($status) . '</span></span>'
                . '<span class="domain-sso-ic__links">'
                . '<a href="' . rex_escape($baseUrl) . '"' . ($isCurrent && rex::isFrontend() ? ' aria-current="true"' : '') . '>' . rex_escape(rex_i18n::msg('domain_sso_ic_website')) . '</a>'
                . '<a href="' . rex_escape($baseUrl . $backend) . '"' . ($isCurrent && rex::isBackend() ? ' aria-current="true"' : '') . '>' . rex_escape(rex_i18n::msg('domain_sso_ic_backend')) . '</a>'
                . '</span></li>';
        }

        $hint = count($live) + (isset($live[$current]) ? 0 : 1) < count($domains)
            ? '<p class="domain-sso-ic__hint">' . rex_escape(rex_i18n::msg('domain_sso_ic_hint')) . '</p>'
            : '';

        return $this->wrapContent(self::style() . '<ul class="domain-sso-ic">' . $items . '</ul>' . $hint);
    }

    /** Pfad zum Backend relativ zur Domain, z. B. „redaxo/“ */
    private static function backendPath(): string
    {
        $path = str_replace('\\', '/', rex_path::relative(rex_path::backend(), rex_path::frontend()));
        // Backend liegt nicht unterhalb des Frontend-Ordners: Standardordner annehmen
        if ('' === trim($path, '/') || str_starts_with($path, '/') || str_contains($path, ':')) {
            return 'redaxo/';
        }
        return trim($path, '/') . '/';
    }

    private static function style(): string
    {
        return '<style>
.domain-sso-ic{list-style:none;margin:0;padding:0}
.domain-sso-ic__item{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--widget-border,rgba(0,0,0,.1));font-size:var(--font-size-small,12px)}
.domain-sso-ic__item:last-child{border-bottom:0}
.domain-sso-ic__dot{flex:none;width:8px;height:8px;border-radius:50%;background:var(--text-color-muted,#94a3b8)}
.domain-sso-ic__dot.is-on{background:#22c55e}
.domain-sso-ic__name{flex:1;min-width:0;overflow-wrap:anywhere;color:var(--text-color,inherit);font-weight:600}
.domain-sso-ic__name small{font-weight:400;color:var(--text-color-muted,#94a3b8)}
.domain-sso-ic__sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.domain-sso-ic__links{display:flex;gap:6px;flex:none}
.domain-sso-ic__links a{padding:3px 8px;border:1px solid var(--button-border,rgba(0,0,0,.2));border-radius:4px;color:var(--text-color,inherit);text-decoration:none;white-space:nowrap}
.domain-sso-ic__links a:hover,.domain-sso-ic__links a:focus-visible{background:var(--hover-bg,rgba(0,0,0,.06))}
.domain-sso-ic__links a[aria-current]{opacity:.55}
.domain-sso-ic__hint{margin:8px 0 0;color:var(--text-color-muted,#94a3b8);font-size:var(--font-size-small,12px)}
</style>';
    }
}
