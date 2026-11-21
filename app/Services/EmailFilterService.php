<?php

namespace App\Services;

/**
 * Guards every outbound email recipient.
 *
 * A recipient must be a syntactically valid address on a common, trusted
 * extension. Internal domains (shulesoft.com), disposable inboxes and
 * extensions associated with scam traffic (.de, .ru, ...) are skipped so we
 * never hand them to a provider. Rules live in config/email_filter.php.
 */
class EmailFilterService
{
    public const REASON_EMPTY = 'empty_recipient';
    public const REASON_INVALID_FORMAT = 'invalid_format';
    public const REASON_TOO_LONG = 'too_long';
    public const REASON_BLOCKED_DOMAIN = 'blocked_domain';
    public const REASON_BLOCKED_TLD = 'blocked_tld';
    public const REASON_TLD_NOT_ALLOWED = 'tld_not_allowed';
    public const REASON_BLOCKED_LOCAL_PART = 'blocked_local_part';

    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('email_filter', []);
    }

    /**
     * Whether this address may receive mail.
     */
    public function isAllowed(?string $email): bool
    {
        return $this->check($email)['allowed'];
    }

    /**
     * Human readable rejection reason, or null when the address is allowed.
     */
    public function rejectionReason(?string $email): ?string
    {
        return $this->check($email)['reason'];
    }

    /**
     * Full verdict for an address.
     *
     * @return array{allowed: bool, email: string, domain: ?string, tld: ?string, code: ?string, reason: ?string}
     */
    public function check(?string $email): array
    {
        $normalized = $this->normalize($email);

        if ($normalized === '') {
            return $this->deny('', null, null, self::REASON_EMPTY, 'Recipient email address is empty');
        }

        if (!$this->hasValidFormat($normalized)) {
            return $this->deny($normalized, null, null, self::REASON_INVALID_FORMAT, 'Invalid email address format');
        }

        [$localPart, $domain] = $this->split($normalized);
        $tld = $this->tld($domain);

        if (strlen($normalized) > (int) ($this->config['max_length'] ?? 254)) {
            return $this->deny($normalized, $domain, $tld, self::REASON_TOO_LONG, 'Email address exceeds the maximum allowed length');
        }

        if (strlen($localPart) > (int) ($this->config['max_local_part_length'] ?? 64)) {
            return $this->deny($normalized, $domain, $tld, self::REASON_TOO_LONG, 'Email address local part exceeds the maximum allowed length');
        }

        // Format rules always apply; the rest of the filtering can be disabled.
        if (!($this->config['enabled'] ?? true)) {
            return $this->allow($normalized, $domain, $tld);
        }

        // Explicitly trusted domains bypass every extension rule.
        if ($this->matchesDomain($domain, $this->list('allowed_domains'))) {
            return $this->allow($normalized, $domain, $tld);
        }

        if (in_array($localPart, $this->list('blocked_local_parts'), true)) {
            return $this->deny(
                $normalized,
                $domain,
                $tld,
                self::REASON_BLOCKED_LOCAL_PART,
                "Mailbox '{$localPart}' is not a deliverable recipient"
            );
        }

        if ($this->matchesDomain($domain, $this->list('blocked_domains'))) {
            return $this->deny(
                $normalized,
                $domain,
                $tld,
                self::REASON_BLOCKED_DOMAIN,
                "Email domain '{$domain}' is blocked for outbound notifications"
            );
        }

        if ($tld !== null && in_array($tld, $this->list('blocked_tlds'), true)) {
            return $this->deny(
                $normalized,
                $domain,
                $tld,
                self::REASON_BLOCKED_TLD,
                "Email extension '.{$tld}' is blocked for outbound notifications"
            );
        }

        if ($this->mode() === 'allowlist') {
            $allowedTlds = $this->list('allowed_tlds');

            if (!empty($allowedTlds) && ($tld === null || !in_array($tld, $allowedTlds, true))) {
                return $this->deny(
                    $normalized,
                    $domain,
                    $tld,
                    self::REASON_TLD_NOT_ALLOWED,
                    "Email extension '." . ($tld ?? '') . "' is not an accepted extension"
                );
            }
        }

        return $this->allow($normalized, $domain, $tld);
    }

    /**
     * Split a list of addresses into the ones we may send to and the ones we skip.
     *
     * @param  iterable<string>  $emails
     * @return array{allowed: array<int, string>, blocked: array<int, array{email: string, code: string, reason: string}>}
     */
    public function partition(iterable $emails): array
    {
        $allowed = [];
        $blocked = [];

        foreach ($emails as $email) {
            $result = $this->check(is_string($email) ? $email : '');

            if ($result['allowed']) {
                $allowed[] = $result['email'];
                continue;
            }

            $blocked[] = [
                'email' => $result['email'],
                'code' => $result['code'],
                'reason' => $result['reason'],
            ];
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    /**
     * Lower cased, trimmed address.
     */
    public function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Stricter than filter_var alone: also rejects consecutive/edge dots,
     * IP literal domains, single label domains and malformed domain labels.
     */
    protected function hasValidFormat(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (substr_count($email, '@') !== 1) {
            return false;
        }

        [$localPart, $domain] = $this->split($email);

        if ($localPart === '' || $domain === '') {
            return false;
        }

        if (str_contains($email, '..') || str_starts_with($localPart, '.') || str_ends_with($localPart, '.')) {
            return false;
        }

        // Reject IP literal domains such as user@[192.168.0.1] or user@127.0.0.1
        if (str_starts_with($domain, '[') || preg_match('/^\d+(\.\d+)*$/', $domain)) {
            return false;
        }

        // Domain must be dotted labels ending in an alphabetic TLD of 2+ chars.
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
            $domain
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function split(string $email): array
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return [$email, ''];
        }

        return [substr($email, 0, $at), substr($email, $at + 1)];
    }

    protected function tld(string $domain): ?string
    {
        $labels = explode('.', $domain);
        $tld = end($labels);

        return $tld === '' ? null : $tld;
    }

    /**
     * Exact domain match, or any subdomain of it.
     *
     * @param  array<int, string>  $domains
     */
    protected function matchesDomain(string $domain, array $domains): bool
    {
        foreach ($domains as $candidate) {
            if ($domain === $candidate || str_ends_with($domain, '.' . $candidate)) {
                return true;
            }
        }

        return false;
    }

    protected function mode(): string
    {
        return strtolower((string) ($this->config['mode'] ?? 'allowlist'));
    }

    /**
     * @return array<int, string>
     */
    protected function list(string $key): array
    {
        $values = $this->config[$key] ?? [];

        if (!is_array($values)) {
            return [];
        }

        return array_map(static fn ($value) => strtolower(trim((string) $value)), $values);
    }

    /**
     * @return array{allowed: bool, email: string, domain: ?string, tld: ?string, code: ?string, reason: ?string}
     */
    protected function allow(string $email, ?string $domain, ?string $tld): array
    {
        return [
            'allowed' => true,
            'email' => $email,
            'domain' => $domain,
            'tld' => $tld,
            'code' => null,
            'reason' => null,
        ];
    }

    /**
     * @return array{allowed: bool, email: string, domain: ?string, tld: ?string, code: ?string, reason: ?string}
     */
    protected function deny(string $email, ?string $domain, ?string $tld, string $code, string $reason): array
    {
        return [
            'allowed' => false,
            'email' => $email,
            'domain' => $domain,
            'tld' => $tld,
            'code' => $code,
            'reason' => $reason,
        ];
    }
}
