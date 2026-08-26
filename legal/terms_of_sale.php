<?php
/**
 * Canonical Terms of Sale — single source for UI + acceptance evidence.
 */

declare(strict_types=1);

const TERMS_OF_SALE_VERSION = '1';
const TERMS_OF_SALE_ACCEPT_METHOD = 'checkbox_waitlist';

/**
 * Structured document shown in the waitlist drawer and archived on accept.
 *
 * @return array{
 *   version: string,
 *   title: string,
 *   lead: string,
 *   meta: string,
 *   heading: string,
 *   sections: list<array{num: string, title: string, body: string}>,
 *   close: string
 * }
 */
function terms_of_sale_document(): array
{
    return [
        'version' => TERMS_OF_SALE_VERSION,
        'title' => 'Terms of Sale',
        'lead' => 'These terms apply to the $49 USD waitlist reservation on MYCOPY.DIGITAL.',
        'meta' => 'Version ' . TERMS_OF_SALE_VERSION . ' · Effective upon purchase',
        'heading' => 'What you buy — and what you do not.',
        'sections' => [
            [
                'num' => '01',
                'title' => 'No refunds',
                'body' =>
                    'All waitlist reservation payments are final. '
                    . 'Once payment is completed, no refund, chargeback-friendly cancellation, '
                    . 'or credit is offered for change of mind, unused access, or delayed studio opening.',
            ],
            [
                'num' => '02',
                'title' => 'What $49 includes',
                'body' =>
                    'The reservation fee covers (1) a paid place on the MYCOPY waitlist and '
                    . '(2) delivery by email of the Protocol Guide book (PDF, 269 pages). '
                    . 'Nothing else is included in this purchase.',
            ],
            [
                'num' => '03',
                'title' => 'Reservation ≠ Protocol processing',
                'body' =>
                    'A waitlist reservation does not automatically grant Protocol processing '
                    . '(listed separately at $5,000 USD, equipment not included). '
                    . 'Protocol processing depends on waitlist position, capacity, and a later separate offer or agreement. '
                    . 'Paying $49 does not create a right to be processed.',
            ],
            [
                'num' => '04',
                'title' => 'Delivery',
                'body' =>
                    'Ticket and Protocol Guide book are sent to the email address confirmed at checkout only. '
                    . 'You are responsible for providing a valid address and checking spam or filters.',
            ],
        ],
        'close' => 'By paying, you confirm you have read and accept these Terms of Sale.',
    ];
}

/** Canonical plain-text snapshot used for hashing and evidence storage. */
function terms_of_sale_plain_text(): string
{
    $doc = terms_of_sale_document();
    $lines = [
        'MYCOPY.DIGITAL — ' . $doc['title'],
        'Version: ' . $doc['version'],
        '',
        $doc['lead'],
        $doc['meta'],
        '',
        $doc['heading'],
        '',
    ];

    foreach ($doc['sections'] as $section) {
        $lines[] = $section['num'] . ' · ' . $section['title'];
        $lines[] = $section['body'];
        $lines[] = '';
    }

    $lines[] = $doc['close'];
    $lines[] = '';

    return implode("\n", $lines);
}

function terms_of_sale_hash(): string
{
    return hash('sha256', terms_of_sale_plain_text());
}

/**
 * Persist an immutable archive copy under data/terms/ (idempotent).
 *
 * @return string Relative path from project root, or empty string on failure
 */
function terms_of_sale_ensure_archive(): string
{
    $version = TERMS_OF_SALE_VERSION;
    $hash = terms_of_sale_hash();
    $short = substr($hash, 0, 16);
    $relative = 'data/terms/v' . $version . '-' . $short . '.txt';
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    $dir = dirname($absolute);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return '';
    }

    if (!is_file($absolute)) {
        $written = @file_put_contents($absolute, terms_of_sale_plain_text(), LOCK_EX);
        if ($written === false) {
            return '';
        }
    }

    return $relative;
}

/**
 * Best-effort client IP (Hostinger / Cloudflare / reverse proxies).
 */
function request_client_ip(): string
{
    $candidates = [];

    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'] as $key) {
        if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
            $candidates[] = trim($_SERVER[$key]);
        }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $candidates[] = trim($part);
        }
    }

    if (!empty($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim($_SERVER['REMOTE_ADDR']);
    }

    foreach ($candidates as $ip) {
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '';
}

/**
 * Evidence bundle recorded when the buyer accepts Terms and starts checkout.
 *
 * @return array{
 *   terms_accepted_at: string,
 *   terms_version: string,
 *   terms_text_hash: string,
 *   terms_snapshot: string,
 *   terms_archive_path: string,
 *   terms_accepted_ip: string,
 *   terms_accepted_ua: string,
 *   terms_accepted_lang: string,
 *   terms_accepted_referer: string,
 *   terms_accept_method: string
 * }
 */
function capture_terms_acceptance_evidence(): array
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) > 512) {
        $ua = substr($ua, 0, 512);
    }

    $lang = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if (strlen($lang) > 180) {
        $lang = substr($lang, 0, 180);
    }

    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if (strlen($referer) > 500) {
        $referer = substr($referer, 0, 500);
    }

    return [
        'terms_accepted_at' => gmdate('c'),
        'terms_version' => TERMS_OF_SALE_VERSION,
        'terms_text_hash' => terms_of_sale_hash(),
        'terms_snapshot' => terms_of_sale_plain_text(),
        'terms_archive_path' => terms_of_sale_ensure_archive(),
        'terms_accepted_ip' => request_client_ip(),
        'terms_accepted_ua' => $ua,
        'terms_accepted_lang' => $lang,
        'terms_accepted_referer' => $referer,
        'terms_accept_method' => TERMS_OF_SALE_ACCEPT_METHOD,
    ];
}
