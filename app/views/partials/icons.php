<?php
/* Line icons used across the public site. */
if (!function_exists('nx_icon')) {
    function nx_icon(string $n): string {
        $p = [
            'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9"/>',
            'shield' => '<path d="M12 3 5 6v6c0 4.2 3 7.6 7 9 4-1.4 7-4.8 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'bolt' => '<path d="M13 3 5 14h6l-1 7 8-11h-6z"/>',
            'pen' => '<path d="M3 21c3-1 5-3 6-6l8-8 3 3-8 8c-3 1-5 3-6 6"/><path d="m14 7 3 3"/>',
            'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 19c.8-3.4 3.2-5 6-5s5.2 1.6 6 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c1.6.6 2.6 2.2 3 5"/>',
            'building' => '<path d="M4 20V6l8-3 8 3v14"/><path d="M9 20v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01"/><path d="M2 20h20"/>',
            'network' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="18" r="2.5"/><circle cx="19" cy="18" r="2.5"/><path d="M12 7.5v4M7 16.5l4-4.5M17 16.5 13 12"/>',
            'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="m9.5 15 2 2 3.5-3.5"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'certificate' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7"/><path d="M14 3v5h5M14 3l5 5v3"/><circle cx="17.5" cy="16" r="2.5"/><path d="m16 18.2-.8 3.3 2.3-1.2 2.3 1.2-.8-3.3"/><path d="M8 9h3M8 13h4"/>',
            'text' => '<path d="M4 7V5h16v2M12 5v14M9 19h6"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.7 5.6 3.7 9s-1.2 6.4-3.7 9c-2.5-2.6-3.7-5.6-3.7-9S9.5 5.6 12 3z"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'layout' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.8-4M4 4v4h4M4 13a8 8 0 0 0 14.8 4M20 20v-4h-4"/>',
            'laptop' => '<rect x="5" y="5" width="14" height="10" rx="1.5"/><path d="M3 19h18"/>',
            'stack' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'cash' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M7 12h.01M17 12h.01"/>',
            'chev' => '<path d="m7 10 5 5 5-5"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        ][$n] ?? '';
        return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}
