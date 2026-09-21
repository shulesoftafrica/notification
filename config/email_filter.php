<?php

/*
|--------------------------------------------------------------------------
| Email Recipient Filtering
|--------------------------------------------------------------------------
|
| Every outbound email recipient is checked against these rules before a
| provider is contacted (see App\Services\EmailFilterService). Recipients
| that do not pass are skipped - never queued, never sent, never retried.
|
| The comma separated env overrides below are additive for the blocklists
| and replace the defaults for `allowed_tlds` when provided.
|
*/

$csv = static function (?string $value): array {
    if (empty($value)) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn ($item) => strtolower(trim($item, " \t\n\r\0\x0B.")),
        explode(',', $value)
    )));
};

return [

    /*
    | Master switch. Turning this off lets every syntactically valid address
    | through (format validation is always applied).
    */
    'enabled' => env('EMAIL_FILTER_ENABLED', true),

    /*
    | 'allowlist' - only addresses whose TLD appears in `allowed_tlds` are sent.
    |               Strictest, and the default: it keeps us on common, known
    |               good extensions instead of chasing new scam TLDs forever.
    | 'blocklist' - everything is sent except `blocked_domains` / `blocked_tlds`.
    */
    'mode' => env('EMAIL_FILTER_MODE', 'allowlist'),

    /*
    | Maximum overall address length (RFC 5321) and local part length.
    */
    'max_length' => 254,
    'max_local_part_length' => 64,

    /*
    | Domains that are never mailed, including their subdomains.
    | shulesoft.com is an internal/legacy domain - real client mail goes to
    | shulesoft.africa, which is allow listed below.
    */
    'blocked_domains' => array_values(array_unique(array_merge([
        'shulesoft.com',
        'example.com',
        'example.net',
        'example.org',
        'test.com',
        'localhost',
        'localhost.localdomain',
        'invalid',
        // Throwaway / disposable inbox providers
        'mailinator.com',
        'yopmail.com',
        'guerrillamail.com',
        'sharklasers.com',
        'trashmail.com',
        'tempmail.com',
        'temp-mail.org',
        '10minutemail.com',
        'getnada.com',
        'dispostable.com',
        'maildrop.cc',
        'throwawaymail.com',
        'fakeinbox.com',
    ], $csv(env('EMAIL_FILTER_BLOCKED_DOMAINS'))))),

    /*
    | Domains that always pass, whatever their TLD. Checked before every
    | other rule except format validation.
    */
    'allowed_domains' => array_values(array_unique(array_merge([
        'shulesoft.africa',
        'shulesoft.co',
    ], $csv(env('EMAIL_FILTER_ALLOWED_DOMAINS'))))),

    /*
    | Extensions we refuse outright. Applied in both modes, so these stay
    | blocked even if someone widens `allowed_tlds`.
    */
    'blocked_tlds' => array_values(array_unique(array_merge([
        // Explicitly requested
        'de', 'ru',
        // Other extensions heavily associated with spam / fraud traffic
        'su', 'by', 'kp', 'ir', 'cn',
        'tk', 'ml', 'ga', 'cf', 'gq', 'pw', 'gdn',
        'top', 'xyz', 'buzz', 'icu', 'cyou', 'sbs', 'rest', 'quest', 'uno',
        'click', 'link', 'loan', 'work', 'men', 'date', 'review', 'stream',
        'download', 'racing', 'win', 'bid', 'party', 'trade', 'webcam',
        'science', 'faith', 'cricket', 'accountant', 'country', 'kim',
        'mom', 'zip', 'mov',
    ], $csv(env('EMAIL_FILTER_BLOCKED_TLDS'))))),

    /*
    | Common, trusted extensions. Only used when mode is 'allowlist'.
    | Matching is on the final label, so co.tz, ac.tz and or.tz all pass
    | through the 'tz' entry.
    */
    'allowed_tlds' => $csv(env('EMAIL_FILTER_ALLOWED_TLDS')) ?: [
        // Generic
        'com', 'net', 'org', 'info', 'biz', 'int', 'edu', 'gov', 'mil',
        'io', 'co', 'app', 'dev', 'ai', 'cloud', 'online', 'site', 'tech',
        'email', 'school', 'academy', 'education', 'ngo',
        // Africa
        'africa',
        'tz', 'ke', 'ug', 'rw', 'bi', 'ss', 'et', 'so', 'dj', 'er', 'sd',
        'cd', 'zm', 'mw', 'mz', 'zw', 'bw', 'na', 'za', 'ao', 'mg', 'mu',
        'sc', 'km', 'ng', 'gh', 'sn', 'ci', 'cm', 'bf', 'ne', 'td',
        'cg', 'gn', 'lr', 'sl', 'gm', 'tg', 'bj', 'eg', 'ma', 'tn',
        'ly', 'dz',
        // NOTE: ml, ga, cf and gq are African ccTLDs but are free-registration
        // extensions dominated by fraud, so they stay in blocked_tlds above.
        // Other common destinations
        'us', 'uk', 'ca', 'au', 'nz', 'ie', 'in', 'pk', 'bd', 'lk', 'ph',
        'my', 'sg', 'id', 'jp', 'kr', 'nl', 'se', 'no', 'dk', 'fi', 'es',
        'it', 'pt', 'fr', 'ch', 'at', 'be', 'pl', 'br', 'mx', 'ar', 'cl',
        'ae', 'sa', 'qa', 'om', 'tr', 'il',
    ],

    /*
    | Mailbox names that are never real recipients.
    */
    'blocked_local_parts' => array_values(array_unique(array_merge([
        'noreply',
        'no-reply',
        'no_reply',
        'donotreply',
        'do-not-reply',
        'do_not_reply',
        'mailer-daemon',
        'postmaster',
        'bounce',
        'bounces',
        'abuse',
        'null',
        'void',
    ], $csv(env('EMAIL_FILTER_BLOCKED_LOCAL_PARTS'))))),

];
