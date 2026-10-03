<?php

use FriendsOfRedaxo\DomainSso\Domains;
use FriendsOfRedaxo\DomainSso\SessionGroup;

/** @var rex_addon $this */
$addon = rex_addon::get('domain_sso');
$csrf = rex_csrf_token::factory('domain_sso_settings');

if (rex_post('save', 'boolean') && $csrf->isValid()) {
    $available = Domains::available();
    $hosts = array_values(array_intersect(rex_post('hosts', 'array[string]', []), array_keys($available)));
    $addon->setConfig('enabled', rex_post('enabled', 'boolean', false));
    $addon->setConfig('hosts', $hosts);
    $addon->setConfig('ttl', max(10, min(600, rex_post('ttl', 'int', 60))));
    $addon->setConfig('allow_http', rex_post('allow_http', 'boolean', false));
    echo rex_view::success(rex_i18n::msg('domain_sso_saved'));
}

if (count(Domains::all()) < 2) {
    echo rex_view::warning(rex_i18n::msg('domain_sso_no_domains'));
}

$selected = (array) $addon->getConfig('hosts', []);
$hostOptions = '';
foreach (Domains::available() as $host => $url) {
    $id = 'domain-sso-host-' . md5($host);
    $hostOptions .= '<div class="checkbox"><label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="hosts[]" value="' . rex_escape($host) . '"' . (in_array($host, $selected, true) ? ' checked' : '') . '> '
        . rex_escape($host) . (str_starts_with($url, 'https://') ? '' : ' <span class="label label-warning">HTTP</span>') . '</label></div>';
}

$content = '<fieldset>'
    . '<div class="checkbox"><label for="domain-sso-enabled"><input type="checkbox" id="domain-sso-enabled" name="enabled" value="1"' . ($addon->getConfig('enabled', true) ? ' checked' : '') . '> ' . rex_i18n::msg('domain_sso_enabled') . '</label>'
    . '<p class="help-block">' . rex_i18n::msg('domain_sso_enabled_notice') . '</p></div>'
    . '<div class="form-group"><span class="control-label"><strong>' . rex_i18n::msg('domain_sso_hosts') . '</strong></span>' . $hostOptions
    . '<p class="help-block">' . rex_i18n::msg('domain_sso_hosts_notice') . '</p></div>'
    . '<div class="form-group"><label for="domain-sso-ttl">' . rex_i18n::msg('domain_sso_ttl') . '</label>'
    . '<input class="form-control" style="max-width: 10rem" type="number" min="10" max="600" id="domain-sso-ttl" name="ttl" value="' . (int) $addon->getConfig('ttl', 60) . '"></div>'
    . '<div class="checkbox"><label for="domain-sso-http"><input type="checkbox" id="domain-sso-http" name="allow_http" value="1"' . ($addon->getConfig('allow_http', false) ? ' checked' : '') . '> ' . rex_i18n::msg('domain_sso_allow_http') . '</label></div>'
    . '</fieldset>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('domain_sso_settings'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', '<button class="btn btn-save" type="submit" name="save" value="1">' . rex_i18n::msg('form_save') . '</button>', false);
echo '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField() . $fragment->parse('core/page/section.php') . '</form>';

// Status der eigenen Sitzungsgruppe
$groupKey = SessionGroup::keyOf((string) session_id());
if (null === $groupKey) {
    $status = '<p>' . rex_i18n::msg('domain_sso_status_none') . '</p>';
} else {
    $limit = time() - (int) rex::getProperty('session_duration', 7200);
    $rows = '';
    foreach (SessionGroup::members($groupKey) as $member) {
        $active = null !== $member['last_activity'] && strtotime($member['last_activity']) >= $limit;
        $rows .= '<tr><td>' . rex_escape($member['host']) . '</td><td>' . ($member['last_activity'] ? rex_escape(rex_formatter::intlDateTime($member['last_activity'], IntlDateFormatter::SHORT)) : '–') . '</td>'
            . '<td>' . ($active ? '<span class="label label-success">' . rex_i18n::msg('domain_sso_state_active') . '</span>' : '<span class="label label-default">' . rex_i18n::msg('domain_sso_state_ended') . '</span>') . '</td></tr>';
    }
    $status = '<table class="table table-striped"><thead><tr><th>' . rex_i18n::msg('domain_sso_col_domain') . '</th><th>' . rex_i18n::msg('domain_sso_col_activity') . '</th><th>' . rex_i18n::msg('domain_sso_col_state') . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('domain_sso_status'), false);
$fragment->setVar('body', $status, false);
echo $fragment->parse('core/page/section.php');
