<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di actv-live ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */

/**
 * Minimal i18n service: detects the active language (it/en), loads
 * translation arrays from /lang and exposes t() for views.
 *
 * Priority: ?lang= query param (persisted in a cookie) > cookie > Accept-Language > 'it'.
 */
class I18n {
    public const SUPPORTED = ['it', 'en'];
    public const DEFAULT_LANG = 'it';
    private const COOKIE_NAME = 'actv_lang';

    private static string $lang = self::DEFAULT_LANG;
    private static array $strings = [];
    private static array $fallback = [];
    private static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        self::$lang = self::detect();

        self::$fallback = require BASE_PATH . '/lang/' . self::DEFAULT_LANG . '.php';
        self::$strings = self::$lang === self::DEFAULT_LANG
            ? self::$fallback
            : (require BASE_PATH . '/lang/' . self::$lang . '.php') + self::$fallback;
    }

    private static function detect(): string {
        $requested = $_GET['lang'] ?? null;
        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            // Persiste la scelta per un anno (cookie non HttpOnly: serve leggerlo anche da JS se necessario)
            setcookie(self::COOKIE_NAME, $requested, [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => self::isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            return $requested;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') as $part) {
            $code = strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $code)[0];
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }
        return self::DEFAULT_LANG;
    }

    private static function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '') === 'https');
    }

    public static function lang(): string {
        self::init();
        return self::$lang;
    }

    public static function isDefault(): bool {
        return self::lang() === self::DEFAULT_LANG;
    }

    public static function t(string $key, array $replace = []): string {
        self::init();
        $text = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }

    /** Tutte le stringhe della lingua attiva (per esposizione a JS). */
    public static function all(): array {
        self::init();
        return self::$strings;
    }
}
