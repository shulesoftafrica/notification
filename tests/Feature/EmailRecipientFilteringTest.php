<?php

namespace Tests\Feature;

use App\Http\Requests\SendBulkMessageRequest;
use App\Http\Requests\SendMessageRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

/**
 * Covers the request validation layer, where blocked recipients are either
 * rejected (single send) or filtered out (bulk send). These tests need no
 * database - the DB backed controller/job behaviour is exercised separately.
 */
class EmailRecipientFilteringTest extends TestCase
{
    /**
     * Run a FormRequest's full validation and return its error bag.
     *
     * @return array<string, array<int, string>>
     */
    protected function validationErrors(string $requestClass, array $payload): array
    {
        $request = $requestClass::create('/api/notifications/send', 'POST', $payload);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);

        try {
            $request->validateResolved();
        } catch (HttpResponseException $e) {
            return $e->getResponse()->getData(true)['errors'] ?? [];
        }

        return [];
    }

    public function test_single_send_is_rejected_for_a_blocked_domain(): void
    {
        $errors = $this->validationErrors(SendMessageRequest::class, [
            'schema_name' => 'demo',
            'channel' => 'email',
            'to' => 'admin@shulesoft.com',
            'subject' => 'Report',
            'message' => 'Body',
        ]);

        $this->assertArrayHasKey('to', $errors);
        $this->assertStringContainsString("'shulesoft.com' is blocked", $errors['to'][0]);
    }

    public function test_single_send_is_rejected_for_a_blocked_extension(): void
    {
        foreach (['winner@lottery.ru', 'promo@offer.de'] as $recipient) {
            $errors = $this->validationErrors(SendMessageRequest::class, [
                'schema_name' => 'demo',
                'channel' => 'email',
                'to' => $recipient,
                'subject' => 'Report',
                'message' => 'Body',
            ]);

            $this->assertArrayHasKey('to', $errors, "{$recipient} should be rejected");
            $this->assertStringContainsString('is blocked', $errors['to'][0]);
        }
    }

    public function test_single_send_is_accepted_for_a_valid_recipient(): void
    {
        foreach (['parent@shulesoft.africa', 'teacher@school.co.tz'] as $recipient) {
            $errors = $this->validationErrors(SendMessageRequest::class, [
                'schema_name' => 'demo',
                'channel' => 'email',
                'to' => $recipient,
                'subject' => 'Report',
                'message' => 'Body',
            ]);

            $this->assertSame([], $errors, "{$recipient} should pass validation");
        }
    }

    public function test_sms_and_whatsapp_recipients_are_untouched_by_the_email_filter(): void
    {
        foreach (['sms', 'whatsapp'] as $channel) {
            $errors = $this->validationErrors(SendMessageRequest::class, [
                'schema_name' => 'demo',
                'channel' => $channel,
                'to' => '+255712345678',
                'message' => 'Body',
            ]);

            $this->assertSame([], $errors, "{$channel} should pass validation");
        }
    }

    public function test_bulk_send_passes_validation_when_only_some_recipients_are_blocked(): void
    {
        $errors = $this->validationErrors(SendBulkMessageRequest::class, [
            'schema_name' => 'demo',
            'channel' => 'email',
            'messages' => [
                ['to' => 'parent@shulesoft.africa', 'subject' => 'Fees', 'message' => 'Body'],
                ['to' => 'admin@shulesoft.com', 'subject' => 'Fees', 'message' => 'Body'],
                ['to' => 'scam@offer.de', 'subject' => 'Fees', 'message' => 'Body'],
            ],
        ]);

        // The batch continues; the controller skips the two blocked recipients.
        $this->assertSame([], $errors);
    }

    public function test_bulk_send_is_rejected_when_every_recipient_is_blocked(): void
    {
        $errors = $this->validationErrors(SendBulkMessageRequest::class, [
            'schema_name' => 'demo',
            'channel' => 'email',
            'messages' => [
                ['to' => 'admin@shulesoft.com', 'subject' => 'Fees', 'message' => 'Body'],
                ['to' => 'scam@offer.ru', 'subject' => 'Fees', 'message' => 'Body'],
            ],
        ]);

        $this->assertArrayHasKey('messages', $errors);
        $this->assertStringContainsString('No valid email recipients', $errors['messages'][0]);
    }
}
