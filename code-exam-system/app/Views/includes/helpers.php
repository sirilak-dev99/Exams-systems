<?php
// ---------- ไอคอน ----------
if (!function_exists('ui_icon')) {
    function ui_icon(string $name, int $size = 22): string
    {
        static $paths = [
            'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
            'brand'     => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/><path d="M12 9v5M9.5 11.5h5"/>',
            'book'      => '<path d="M2 4h7a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H2z"/><path d="M22 4h-7a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h8z"/>',
            'bank'      => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
            'clip'      => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v4H9z"/><path d="m9 14 2 2 4-4"/>',
            'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'school'    => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
            'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'layers'    => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
            'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'news'      => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/>',
            'file'      => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
            'file-plus' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M12 12v6M9 15h6"/>',
            'bolt'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
            'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'chev'      => '<path d="m9 6 6 6-6 6"/>',
            'bell'      => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
            'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
            'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'shield'    => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'log'       => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M8 13h2M8 17h2M12 13h4M12 17h4"/>',
            'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
            'play'      => '<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/>',
            'list'      => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
            'tag'       => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'upload'    => '<path d="M7 18a5 5 0 1 1 .9-9.9A6 6 0 0 1 19 10a4 4 0 0 1 0 8"/><path d="M12 12v9M9 15l3-3 3 3"/>',
            'check'     => '<path d="m5 12 5 5 9-10"/>',
            'pencil'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'archive'   => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>',
        ];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }
}

// ---------- วันที่ภาษาไทย ----------
if (!function_exists('thai_date')) {
    function thai_date(?string $value, bool $withTime = false): string
    {
        if ($value === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $value, $m)) {
            return '—';
        }
        $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $out = (int)$m[3] . ' ' . $months[(int)$m[2] - 1] . ' ' . ((int)$m[1] + 543);
        return $withTime && isset($m[4]) ? $out . ' ' . $m[4] . ':' . $m[5] . ' น.' : $out;
    }
}
