<?php

namespace Tests\Unit;

use App\Services\EmailFilterService;
use Tests\TestCase;

class EmailFilterServiceTest extends TestCase
{
    protected EmailFilterService $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new EmailFilterService(require base_path('config/email_filter.php'));
    }

    public function test_it_allows_common_valid_addresses(): void
    {
        $allowed = [
            'parent@shulesoft.africa',
            'head.teacher@shulesoft.africa',
            'info@school.co.tz',
            'admin@mwenge.ac.tz',
            'john.doe+fees@gmail.com',
            'accounts@school.org',
            'support@service.io',
            'finance@company.co.ke',
        ];

        foreach ($allowed as $email) {
            $check = $this->filter->check($email);
            $this->assertTrue($check['allowed'], "{$email} should be allowed, got: " . ($check['reason'] ?? ''));
        }
    }

    public function test_it_blocks_the_internal_shulesoft_com_domain_and_its_subdomains(): void
    {
        foreach (['admin@shulesoft.com', 'test@mail.shulesoft.com'] as $email) {
            $check = $this->filter->check($email);

            $this->assertFalse($check['allowed'], "{$email} should be blocked");
            $this->assertSame(EmailFilterService::REASON_BLOCKED_DOMAIN, $check['code']);
        }
    }

    public function test_it_blocks_scam_associated_extensions(): void
    {
        foreach (['winner@lottery.de', 'agent@mail.ru', 'promo@offer.xyz', 'free@inbox.tk'] as $email) {
            $check = $this->filter->check($email);

            $this->assertFalse($check['allowed'], "{$email} should be blocked");
            $this->assertSame(EmailFilterService::REASON_BLOCKED_TLD, $check['code']);
        }
    }

    public function test_it_blocks_uncommon_extensions_in_allowlist_mode(): void
    {
        $check = $this->filter->check('someone@domain.zuerich');

        $this->assertFalse($check['allowed']);
        $this->assertSame(EmailFilterService::REASON_TLD_NOT_ALLOWED, $check['code']);
    }

    public function test_it_blocks_malformed_addresses(): void
    {
        $malformed = [
            '',
            'not-an-email',
            'missing@tld',
            'two@@at.com',
            'trailing.dot.@gmail.com',
            'double..dot@gmail.com',
            'ip@127.0.0.1',
            'spaced address@gmail.com',
        ];

        foreach ($malformed as $email) {
            $this->assertFalse(
                $this->filter->isAllowed($email),
                "'{$email}' should be rejected as malformed"
            );
        }
    }

    public function test_it_blocks_disposable_and_placeholder_domains(): void
    {
        foreach (['x@mailinator.com', 'x@example.com', 'x@yopmail.com'] as $email) {
            $this->assertFalse($this->filter->isAllowed($email), "{$email} should be blocked");
        }
    }

    public function test_it_blocks_non_deliverable_mailboxes(): void
    {
        $check = $this->filter->check('noreply@gmail.com');

        $this->assertFalse($check['allowed']);
        $this->assertSame(EmailFilterService::REASON_BLOCKED_LOCAL_PART, $check['code']);
    }

    public function test_it_normalises_case_and_whitespace(): void
    {
        $check = $this->filter->check('  Parent@ShuleSoft.Africa  ');

        $this->assertTrue($check['allowed']);
        $this->assertSame('parent@shulesoft.africa', $check['email']);
    }

    public function test_partition_splits_recipients(): void
    {
        $result = $this->filter->partition([
            'parent@shulesoft.africa',
            'admin@shulesoft.com',
            'teacher@school.co.tz',
            'scam@offer.ru',
            'broken-address',
        ]);

        $this->assertSame(['parent@shulesoft.africa', 'teacher@school.co.tz'], $result['allowed']);
        $this->assertCount(3, $result['blocked']);
    }

    public function test_disabling_the_filter_still_rejects_malformed_addresses(): void
    {
        $config = require base_path('config/email_filter.php');
        $config['enabled'] = false;
        $filter = new EmailFilterService($config);

        $this->assertTrue($filter->isAllowed('admin@shulesoft.com'));
        $this->assertTrue($filter->isAllowed('winner@lottery.ru'));
        $this->assertFalse($filter->isAllowed('not-an-email'));
    }

    public function test_blocklist_mode_only_rejects_listed_extensions(): void
    {
        $config = require base_path('config/email_filter.php');
        $config['mode'] = 'blocklist';
        $filter = new EmailFilterService($config);

        $this->assertTrue($filter->isAllowed('someone@domain.zuerich'));
        $this->assertFalse($filter->isAllowed('someone@domain.ru'));
        $this->assertFalse($filter->isAllowed('someone@shulesoft.com'));
    }
}
