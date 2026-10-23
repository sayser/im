<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function filter_query(array $filters, array $overrides = []): string
{
    $query = [];
    foreach (array_merge($filters, $overrides) as $key => $value) {
        if ($value === null || $value === '' || $value === 0) {
            continue;
        }
        if ($key === 'page' && (int)$value <= 1) {
            continue;
        }
        $query[$key] = $value;
    }
    $built = http_build_query($query);
    return $built === '' ? '' : '?' . $built;
}

function field_error(array $errors, string $key): string
{
    if (!isset($errors[$key]) || $errors[$key] === '') {
        return '';
    }
    return '<small class="field-error">' . e($errors[$key]) . '</small>';
}

function invalid_attr(array $errors, string $key): string
{
    return isset($errors[$key]) && $errors[$key] !== '' ? ' aria-invalid="true"' : '';
}

function or_dash(mixed $value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '<span class="muted">—</span>';
    }
    return e($text);
}

function nl2e(mixed $value): string
{
    $text = (string)$value;
    if (trim($text) === '') {
        return '<span class="muted">—</span>';
    }
    return nl2br(e($text), false);
}

function host_link(mixed $url): string
{
    $url = trim((string)$url);
    if ($url === '' || invalid_url($url) !== null) {
        return $url === '' ? '<span class="muted">—</span>' : e($url);
    }
    $host = parse_url($url, PHP_URL_HOST);
    $label = is_string($host) && $host !== '' ? $host : $url;
    return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($label) . '</a>';
}

function fact_line(string $label, string $value, bool $mono = false, bool $wide = false): string
{
    $text = trim($value);
    $body = $text === '' ? '<span class="muted">—</span>' : e($text);
    return '<div' . ($wide ? ' class="wide"' : '') . '><dt>' . e($label) . '</dt><dd class="' . ($mono ? 'mono' : 'break') . '">' . $body . '</dd></div>';
}

function fact_row(string $label, string $html, bool $wide = false): string
{
    return '<div' . ($wide ? ' class="wide"' : '') . '><dt>' . e($label) . '</dt><dd>' . $html . '</dd></div>';
}

function env_badge(string $name): string
{
    $class = match (strtolower($name)) {
        'prod', 'production' => 'badge prod',
        'test', 'development' => 'badge dev',
        'staging' => 'badge stage',
        default => 'badge',
    };
    return '<span class="' . $class . '">' . e($name) . '</span>';
}

function role_badge(string $role): string
{
    $class = match ($role) {
        'admin' => 'badge role-admin',
        'ops' => 'badge role-ops',
        default => 'badge role-view',
    };
    return '<span class="' . $class . '">' . e(role_label($role)) . '</span>';
}

function select_options(array $rows, int $selected, string $placeholder): string
{
    $html = $placeholder === '' ? '' : '<option value="">' . e($placeholder) . '</option>';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        if (isset($row['active']) && (int)$row['active'] !== 1) {
            $name .= ' (retired)';
        }
        $html .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($name) . '</option>';
    }
    return $html;
}

function icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
        'inventory' => '<path d="M12 3 3 8l9 5 9-5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
        'app' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'db' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
        'decom' => '<path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/>',
        'marks' => '<path d="M20 13.5 13.5 20a2 2 0 0 1-2.8 0L4 13.3V4h9.3L20 10.7a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/>',
        'views' => '<rect x="3" y="4" width="7" height="16" rx="1.5"/><rect x="14" y="4" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="6" rx="1.5"/>',
        'audit' => '<path d="M8 4h7l4 4v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M15 4v4h4M9 13h6M9 17h4"/>',
        'users' => '<path d="M16 19v-1.2A3.8 3.8 0 0 0 12.2 14H7.8A3.8 3.8 0 0 0 4 17.8V19"/><circle cx="10" cy="8" r="3"/><path d="M20 19v-1.1A3.2 3.2 0 0 0 17.4 15"/><path d="M16 5.2a2.6 2.6 0 0 1 0 5"/>',
        'lists' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'key' => '<circle cx="8" cy="14" r="3.2"/><path d="M11 14h9l-2 2 2 2"/>',
        'logout' => '<path d="M10 7V5H5v14h5v-2"/><path d="M10 12h9M16 8l4 4-4 4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
}

function filters_on(array $filters, array $keys): bool
{
    foreach ($keys as $key) {
        $value = $filters[$key] ?? '';
        if ($value !== '' && $value !== 0 && $value !== null) {
            return true;
        }
    }
    return false;
}

function chips(array $rows): string
{
    if (!$rows) {
        return '<span class="muted">—</span>';
    }
    $html = '<span class="chips">';
    foreach ($rows as $row) {
        $html .= '<span class="chip">' . e((string)$row['name']) . '</span>';
    }
    return $html . '</span>';
}

function linked_names(array $rows): string
{
    if (!$rows) {
        return '<span class="muted">—</span>';
    }
    $html = '<span class="chips">';
    foreach ($rows as $row) {
        $html .= '<span class="chip">' . e((string)$row['name']);
        if (($row['lifecycle'] ?? '') === 'decommissioned') {
            $html .= ' <span class="badge decom">DECOM</span>';
        }
        $html .= '</span>';
    }
    return $html . '</span>';
}

function vm_links(array $rows, string $kind): string
{
    if (!$rows) {
        return '<span class="muted">—</span>';
    }
    $html = '<span class="stack">';
    foreach ($rows as $row) {
        $path = ($kind === 'app' ? '/app-vms/' : '/db-vms/') . (int)$row['id'];
        $html .= '<span class="stack-item"><a href="' . e(url($path)) . '">' . e((string)$row['name']) . '</a>';
        if (($row['lifecycle'] ?? '') === 'decommissioned') {
            $html .= ' <span class="badge decom">DECOM</span>';
        }
        $html .= '</span>';
    }
    return $html . '</span>';
}

function soft_url(mixed $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return '<span class="muted">—</span>';
    }
    return str_replace(['/', '?', '&'], ['/<wbr>', '?<wbr>', '&<wbr>'], e($url));
}

function full_link(mixed $url): string
{
    $url = trim((string)$url);
    if ($url === '' || invalid_url($url) !== null) {
        return $url === '' ? '<span class="muted">—</span>' : e($url);
    }
    return '<a class="break" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a>';
}
