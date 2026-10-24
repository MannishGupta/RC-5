<?php
declare(strict_types=1);
/**
 * Tailwind page shell helpers — consistent class strings for every RC surface.
 * Usage: require once, then rc_tw_page_open / rc_tw_page_header / rc_tw_page_close.
 */
if (!function_exists('rc_tw_h')) {
    function rc_tw_h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('rc_tw_page_open')) {
    function rc_tw_page_open(string $extra = ''): void
    {
        $e = trim($extra);
        echo '<div class="rc-page w-full flex flex-col gap-4' . ($e !== '' ? ' ' . rc_tw_h($e) : '') . '">';
    }
}
if (!function_exists('rc_tw_page_close')) {
    function rc_tw_page_close(): void
    {
        echo '</div>';
    }
}
if (!function_exists('rc_tw_page_header')) {
    /** @param array{kicker?:string,title:string,sub?:string,actions_html?:string} $opts */
    function rc_tw_page_header(array $opts): void
    {
        $kicker = trim((string)($opts['kicker'] ?? ''));
        $title = (string)($opts['title'] ?? '');
        $sub = trim((string)($opts['sub'] ?? ''));
        $actions = (string)($opts['actions_html'] ?? '');
        echo '<header class="rc-page-header flex flex-wrap items-end justify-between gap-3">';
        echo '<div class="min-w-0">';
        if ($kicker !== '') {
            echo '<p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">' . rc_tw_h($kicker) . '</p>';
        }
        echo '<h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">' . rc_tw_h($title) . '</h1>';
        if ($sub !== '') {
            echo '<p class="rc-page-sub text-sm">' . rc_tw_h($sub) . '</p>';
        }
        echo '</div>';
        if ($actions !== '') {
            echo '<div class="rc-page-actions flex flex-wrap items-center gap-2">' . $actions . '</div>';
        }
        echo '</header>';
    }
}
if (!function_exists('rc_tw_card_open')) {
    function rc_tw_card_open(string $extra = ''): void
    {
        $e = trim($extra);
        echo '<section class="rc-page-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm' . ($e !== '' ? ' ' . rc_tw_h($e) : '') . '">';
    }
}
if (!function_exists('rc_tw_card_close')) {
    function rc_tw_card_close(): void
    {
        echo '</section>';
    }
}
if (!function_exists('rc_tw_empty')) {
    function rc_tw_empty(string $icon, string $title, string $hint = ''): void
    {
        echo '<div class="rc-page-empty flex flex-col items-center justify-center text-center min-h-[12rem] p-8 rounded-xl border-2 border-dashed">';
        echo '<i class="fa-solid ' . rc_tw_h($icon) . ' text-3xl opacity-40 mb-3" aria-hidden="true"></i>';
        echo '<p class="text-sm font-semibold text-slate-600">' . rc_tw_h($title) . '</p>';
        if ($hint !== '') {
            echo '<p class="text-xs mt-1 text-slate-400">' . rc_tw_h($hint) . '</p>';
        }
        echo '</div>';
    }
}
