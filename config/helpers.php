<?php
// SLearning - Helpers terpusat: SVG icons, escaping, tanggal, guard login.
// Dipakai oleh semua halaman agar desain selaras & tanpa emoji.

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('tanggal_id')) {
    function tanggal_id($datetime, $with_time = true) {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($datetime);
        if (!$ts) return e($datetime);
        $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $tgl = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)-1] . ' ' . date('Y', $ts);
        if ($with_time && date('H:i:s', $ts) !== '00:00:00') $tgl .= ', ' . date('H:i', $ts) . ' WIB';
        return $tgl;
    }
}

if (!function_exists('svg_icon')) {
    function svg_icon($name, $size = 20, $sw = 2) {
        $a = 'width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$sw.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
        switch ($name) {
            case 'grid': return '<svg '.$a.'><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>';
            case 'book': return '<svg '.$a.'><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
            case 'clipboard': return '<svg '.$a.'><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h4"/></svg>';
            case 'file': return '<svg '.$a.'><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>';
            case 'check': return '<svg '.$a.'><path d="M20 6 9 17l-5-5"/></svg>';
            case 'check-circle': return '<svg '.$a.'><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/></svg>';
            case 'clock': return '<svg '.$a.'><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
            case 'users': return '<svg '.$a.'><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
            case 'user': return '<svg '.$a.'><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4-6 8-6s6.5 2 8 6"/></svg>';
            case 'chart': return '<svg '.$a.'><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>';
            case 'help': return '<svg '.$a.'><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 5 0c0 1.5-2.5 2-2.5 3.5"/><path d="M12 17h.01"/></svg>';
            case 'calendar': return '<svg '.$a.'><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
            case 'upload': return '<svg '.$a.'><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/></svg>';
            case 'award': return '<svg '.$a.'><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/></svg>';
            case 'activity': return '<svg '.$a.'><path d="M3 12h4l3-9 4 18 3-9h4"/></svg>';
            case 'arrow-right': return '<svg '.$a.'><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>';
            case 'arrow-left': return '<svg '.$a.'><path d="M19 12H5"/><path d="m11 18-6-6 6-6"/></svg>';
            case 'plus': return '<svg '.$a.'><path d="M12 5v14M5 12h14"/></svg>';
            case 'trash': return '<svg '.$a.'><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
            case 'eye': return '<svg '.$a.'><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
            case 'eye-off': return '<svg '.$a.'><path d="M17.94 17.94A10.6 10.6 0 0 1 12 19c-6.5 0-10-7-10-7a17.6 17.6 0 0 1 4.06-4.94M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-2.16 3.19M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>';
            case 'x': return '<svg '.$a.'><path d="M18 6 6 18M6 6l12 12"/></svg>';
            case 'key': return '<svg '.$a.'><circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M15 5l3 3M18 8l2 2"/></svg>';
            case 'shield': return '<svg '.$a.'><path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m9 11.5 2 2 4-4.5"/></svg>';
            case 'logout': return '<svg '.$a.'><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>';
            case 'login': return '<svg '.$a.'><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5M15 12H3"/></svg>';
            case 'mail': return '<svg '.$a.'><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>';
            case 'lock': return '<svg '.$a.'><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
            case 'copy': return '<svg '.$a.'><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
            case 'send': return '<svg '.$a.'><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>';
            case 'edit': return '<svg '.$a.'><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>';
            case 'home': return '<svg '.$a.'><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>';
            case 'list': return '<svg '.$a.'><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>';
            case 'star': return '<svg '.$a.'><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>';
            case 'info': return '<svg '.$a.'><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>';
            case 'search': return '<svg '.$a.'><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>';
            default: return '<svg '.$a.'><circle cx="12" cy="12" r="9"/></svg>';
        }
    }
}

if (!function_exists('inisial_nama')) {
    function inisial_nama($nama) {
        $nama = trim((string)$nama);
        if ($nama === '') return 'S';
        $parts = preg_split('/\s+/', $nama);
        $ini = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) $ini .= strtoupper(substr(end($parts), 0, 1));
        return $ini;
    }
}
