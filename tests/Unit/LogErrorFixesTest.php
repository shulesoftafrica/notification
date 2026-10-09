<?php

namespace Tests\Unit;

use App\Jobs\DispatchMessage;
use App\Services\Adapters\SmsAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regressions for errors found in production storage/logs:
 *  - "has been attempted too many times" (31k SMS jobs used up $tries just waiting behind the throttle)
 *  - Beem "No valid destination number provided" / "Enter a plain text message only"
 *  - View [docs.guides.index] not found
 */
class LogErrorFixesTest extends TestCase
{
    public function test_job_retry_window_depends_on_priority(): void
    {
        $now = time();

        $urgent = (new DispatchMessage(['channel' => 'sms', 'to' => '255700000000', 'message' => 'x'], 1, 'urgent'))->retryUntil();
        $normal = (new DispatchMessage(['channel' => 'sms', 'to' => '255700000000', 'message' => 'x'], 1, 'normal'))->retryUntil();
        $low = (new DispatchMessage(['channel' => 'sms', 'to' => '255700000000', 'message' => 'x'], 1, 'low'))->retryUntil();

        $this->assertEqualsWithDelta(15 * 60, $urgent->getTimestamp() - $now, 5);
        $this->assertEqualsWithDelta(6 * 3600, $normal->getTimestamp() - $now, 5);
        $this->assertEqualsWithDelta(24 * 3600, $low->getTimestamp() - $now, 5);
    }

    public function test_job_queued_before_the_expiry_property_existed_still_gets_a_default_window(): void
    {
        $job = new DispatchMessage(['channel' => 'sms', 'to' => '255700000000', 'message' => 'x']);
        $job->expiresAt = null; // what an old serialized payload unserializes to

        $this->assertEqualsWithDelta(6 * 3600, $job->retryUntil()->getTimestamp() - time(), 5);
    }

    public function test_beem_number_validation(): void
    {
        $this->assertTrue(SmsAdapter::isDeliverableBeemNumber('255712345678'));
        $this->assertTrue(SmsAdapter::isDeliverableBeemNumber('254712345678'), 'other countries are allowed');
        $this->assertFalse(SmsAdapter::isDeliverableBeemNumber('25555719529344'), 'production failure: 255 + too many digits');
        $this->assertFalse(SmsAdapter::isDeliverableBeemNumber('2557123'), 'too short for a Tanzanian number');
        $this->assertFalse(SmsAdapter::isDeliverableBeemNumber(''));
        $this->assertFalse(SmsAdapter::isDeliverableBeemNumber('abc'));
        $this->assertFalse(SmsAdapter::isDeliverableBeemNumber('0712345678'), 'local format is not accepted by Beem');
    }

    public function test_beem_encoding_is_unicode_only_when_needed(): void
    {
        $this->assertSame(0, SmsAdapter::beemEncodingFor('Habari, malipo yako ya TZS 1,000 yamepokelewa.'));
        $this->assertSame(1, SmsAdapter::beemEncodingFor('Karibu — asante 🙂'));
        $this->assertSame(1, SmsAdapter::beemEncodingFor('Café'));
    }

    public function test_invalid_number_never_reaches_beem_and_unicode_is_sent_with_encoding_1(): void
    {
        Http::fake(['apisms.beem.africa/*' => Http::response(['request_id' => 'abc'], 200)]);

        $adapter = new SmsAdapter(['api_key' => 'k', 'secret_key' => 's', 'sender_name' => 'TEST'], 'beem');

        $bad = $adapter->send('+25555719529344', 'hello');
        $this->assertFalse($bad->success);
        Http::assertNothingSent();

        $ok = $adapter->send('+255712345678', 'Asante — karibu');
        $this->assertTrue($ok->success);
        Http::assertSent(function ($request) {
            return $request['encoding'] === 1
                && $request['recipients'][0]['dest_addr'] === '255712345678';
        });
    }

    public function test_docs_guides_views_exist_and_render(): void
    {
        $this->assertStringContainsString('Developer Guides', view('docs.guides.index', ['title' => 'Developer Guides'])->render());
        $this->assertStringContainsString('<h1>Hi</h1>', view('docs.guides.show', [
            'title' => 'T', 'markdown' => '# Hi', 'guide' => 't',
        ])->render());
    }
}
