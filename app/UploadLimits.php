<?php
/**
 * Version: 3.2.64 — single source of truth for upload ceilings (Media Kit + Organisation + Status)
 *
 * SOURCE (incoming file before optimise): up to RC_UPLOAD_SOURCE_MAX bytes per file
 * STORED (after SystemDataOptimizer): AppMedia::imageSpec maxKB (e.g. 450–600 KB)
 * BATCH (Media Kit multi-upload): RC_UPLOAD_BATCH_*
 *
 * Host must allow at least SOURCE_MAX via php.ini / .user.ini (PHP) and root web.config requestLimits (IIS).
 * IIS: only RC/web.config — no nested status/web.config.
 */
declare(strict_types=1);

final class UploadLimits
{
    /** Per-file source ceiling (Media Kit, Status photos, company logo source). 12 MB */
    public const SOURCE_MAX = 12 * 1024 * 1024;

    /** Media Kit bulk: max files in one batch */
    public const BATCH_MAX_FILES = 20;

    /** Media Kit bulk: total bytes per request */
    public const BATCH_MAX_BYTES = 80 * 1024 * 1024;

    /** Recommended PHP upload_max_filesize */
    public const PHP_UPLOAD_INI = '40M';

    /** Recommended PHP post_max_size (≥ upload + form fields) */
    public const PHP_POST_INI = '48M';

    /** IIS maxAllowedContentLength (bytes) — must be ≥ POST */
    public const IIS_MAX_REQUEST = 52428800; // 50 MB

    public static function sourceMaxLabel(): string
    {
        return '12 MB';
    }

    public static function exceedsSource(int $bytes): bool
    {
        return $bytes > self::SOURCE_MAX;
    }

    /** Human message for oversize source file */
    public static function oversizeMessage(string $fileName = ''): string
    {
        $n = $fileName !== '' ? $fileName . ' ' : '';
        return $n . 'exceeds the ' . self::sourceMaxLabel() . ' per-file limit. Compress or resize, then try again.';
    }
}
