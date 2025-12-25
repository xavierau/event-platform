<?php

namespace Tests\Unit\TicketHold\Actions;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\TicketHold\Actions\Links\RecordLinkAccessAction;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordLinkAccessActionTest extends TestCase
{
    use RefreshDatabase;

    private RecordLinkAccessAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new RecordLinkAccessAction;
    }

    /**
     * Create a request with a mock session attached.
     */
    private function createRequestWithSession(): Request
    {
        $request = Request::create('/test', 'GET');

        // Create a mock session store
        $session = new Store('test-session', new \Illuminate\Session\ArraySessionHandler(120));
        $session->setId(Str::random(40));
        $session->start();

        $request->setLaravelSession($session);

        return $request;
    }

    private function createLink(): PurchaseLink
    {
        $occurrence = EventOccurrence::factory()->create();
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create(['total_quantity' => 100]);

        $hold = TicketHold::factory()
            ->forOccurrence($occurrence)
            ->forOrganizer($organizer)
            ->createdBy($user)
            ->active()
            ->create();

        HoldTicketAllocation::factory()
            ->forHold($hold)
            ->forTicketDefinition($ticketDefinition)
            ->withQuantity(20)
            ->create();

        return PurchaseLink::factory()
            ->forHold($hold)
            ->active()
            ->create();
    }

    public function test_it_records_access_with_valid_ip(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Test Browser)');

        $access = $this->action->execute($link, $request);

        $this->assertInstanceOf(PurchaseLinkAccess::class, $access);
        $this->assertEquals($link->id, $access->purchase_link_id);
        $this->assertEquals('192.168.1.1', $access->ip_address);
        $this->assertEquals('Mozilla/5.0 (Test Browser)', $access->user_agent);
        $this->assertFalse($access->resulted_in_purchase);
    }

    public function test_it_rejects_invalid_ip_addresses(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->server->set('REMOTE_ADDR', 'not-a-valid-ip');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $access = $this->action->execute($link, $request);

        $this->assertNull($access->ip_address);
    }

    public function test_it_validates_ipv4_addresses(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $access = $this->action->execute($link, $request);

        $this->assertEquals('10.0.0.1', $access->ip_address);
    }

    public function test_it_validates_ipv6_addresses(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->server->set('REMOTE_ADDR', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $access = $this->action->execute($link, $request);

        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $access->ip_address);
    }

    public function test_it_truncates_long_user_agents(): void
    {
        $link = $this->createLink();

        $longUserAgent = str_repeat('A', 600);

        $request = $this->createRequestWithSession();
        $request->headers->set('User-Agent', $longUserAgent);

        $access = $this->action->execute($link, $request);

        $this->assertEquals(500, strlen($access->user_agent));
    }

    public function test_it_handles_user_agent_exactly_at_limit(): void
    {
        $link = $this->createLink();

        $exactLimitUserAgent = str_repeat('B', 500);

        $request = $this->createRequestWithSession();
        $request->headers->set('User-Agent', $exactLimitUserAgent);

        $access = $this->action->execute($link, $request);

        $this->assertEquals(500, strlen($access->user_agent));
        $this->assertEquals($exactLimitUserAgent, $access->user_agent);
    }

    public function test_it_handles_anonymous_access(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();

        $access = $this->action->execute($link, $request, null);

        $this->assertNull($access->user_id);
    }

    public function test_it_records_authenticated_user(): void
    {
        $link = $this->createLink();
        $user = User::factory()->create();

        $request = $this->createRequestWithSession();

        $access = $this->action->execute($link, $request, $user);

        $this->assertEquals($user->id, $access->user_id);
    }

    public function test_it_records_referer_header(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->headers->set('referer', 'https://example.com/source-page');

        $access = $this->action->execute($link, $request);

        $this->assertEquals('https://example.com/source-page', $access->referer);
    }

    public function test_it_records_accessed_at_timestamp(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();

        $access = $this->action->execute($link, $request);

        $this->assertNotNull($access->accessed_at);
        $this->assertTrue($access->accessed_at->isToday());
    }

    public function test_it_sets_resulted_in_purchase_to_false_initially(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();

        $access = $this->action->execute($link, $request);

        $this->assertFalse($access->resulted_in_purchase);
    }

    public function test_it_handles_null_request(): void
    {
        $link = $this->createLink();

        $access = $this->action->execute($link, null);

        $this->assertInstanceOf(PurchaseLinkAccess::class, $access);
        $this->assertNull($access->ip_address);
        $this->assertNull($access->user_agent);
        $this->assertNull($access->referer);
        $this->assertNull($access->session_id);
    }

    public function test_execute_from_data_creates_access_record(): void
    {
        $link = $this->createLink();
        $user = User::factory()->create();

        $accessData = [
            'user_id' => $user->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Custom User Agent',
            'referer' => 'https://external-site.com',
            'session_id' => 'test-session-id-12345',
            'accessed_at' => now()->subHours(2),
        ];

        $access = $this->action->executeFromData($link, $accessData);

        $this->assertInstanceOf(PurchaseLinkAccess::class, $access);
        $this->assertEquals($user->id, $access->user_id);
        $this->assertEquals('192.168.1.100', $access->ip_address);
        $this->assertEquals('Custom User Agent', $access->user_agent);
        $this->assertEquals('https://external-site.com', $access->referer);
        $this->assertEquals('test-session-id-12345', $access->session_id);
        $this->assertFalse($access->resulted_in_purchase);
    }

    public function test_execute_from_data_validates_ip(): void
    {
        $link = $this->createLink();

        $accessData = [
            'ip_address' => 'invalid-ip',
        ];

        $access = $this->action->executeFromData($link, $accessData);

        $this->assertNull($access->ip_address);
    }

    public function test_execute_from_data_truncates_user_agent(): void
    {
        $link = $this->createLink();

        $accessData = [
            'user_agent' => str_repeat('X', 600),
        ];

        $access = $this->action->executeFromData($link, $accessData);

        $this->assertEquals(500, strlen($access->user_agent));
    }

    public function test_execute_from_data_uses_current_time_if_not_provided(): void
    {
        $link = $this->createLink();

        $accessData = [
            'user_id' => null,
        ];

        $access = $this->action->executeFromData($link, $accessData);

        $this->assertNotNull($access->accessed_at);
        $this->assertTrue($access->accessed_at->isToday());
    }

    public function test_execute_from_data_with_empty_array(): void
    {
        $link = $this->createLink();

        $access = $this->action->executeFromData($link, []);

        $this->assertInstanceOf(PurchaseLinkAccess::class, $access);
        $this->assertNull($access->user_id);
        $this->assertNull($access->ip_address);
        $this->assertNull($access->user_agent);
        $this->assertNull($access->referer);
        $this->assertNull($access->session_id);
        $this->assertFalse($access->resulted_in_purchase);
    }

    public function test_multiple_accesses_are_recorded_independently(): void
    {
        $link = $this->createLink();

        $request1 = $this->createRequestWithSession();
        $request1->server->set('REMOTE_ADDR', '192.168.1.1');

        $request2 = $this->createRequestWithSession();
        $request2->server->set('REMOTE_ADDR', '192.168.1.2');

        $access1 = $this->action->execute($link, $request1);
        $access2 = $this->action->execute($link, $request2);

        $this->assertNotEquals($access1->id, $access2->id);
        $this->assertEquals('192.168.1.1', $access1->ip_address);
        $this->assertEquals('192.168.1.2', $access2->ip_address);
        $this->assertEquals(2, $link->accesses()->count());
    }

    public function test_it_handles_localhost_ip(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $access = $this->action->execute($link, $request);

        $this->assertEquals('127.0.0.1', $access->ip_address);
    }

    public function test_it_handles_null_ip_address(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        // Don't set REMOTE_ADDR

        $access = $this->action->execute($link, $request);

        // Should handle gracefully
        $this->assertInstanceOf(PurchaseLinkAccess::class, $access);
    }

    public function test_it_handles_empty_user_agent(): void
    {
        $link = $this->createLink();

        $request = $this->createRequestWithSession();
        $request->headers->set('User-Agent', '');

        $access = $this->action->execute($link, $request);

        $this->assertEquals('', $access->user_agent);
    }
}
