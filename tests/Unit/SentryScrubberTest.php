<?php

namespace Tests\Unit;

use App\Support\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\UserDataBag;

/**
 * Proves that household survey PII cannot reach Sentry.
 *
 * This is a data-protection control, not a nicety: the app holds names, phone
 * numbers, citizenship numbers, GPS coordinates, disability status and income
 * for real residents, and Sentry's ingest is outside Nepal. If this test ever
 * fails, error reporting is leaking personal data and must be switched off
 * (unset SENTRY_LARAVEL_DSN) until it passes again.
 */
class SentryScrubberTest extends TestCase
{
    private function eventWithRequest(array $request): Event
    {
        $event = Event::createEvent();
        $event->setRequest($request);

        return $event;
    }

    public function test_the_request_body_is_dropped_entirely(): void
    {
        $event = $this->eventWithRequest([
            'url' => 'https://rong.example/households/12',
            'method' => 'POST',
            'data' => [
                'name_np' => 'राम बहादुर श्रेष्ठ',
                'contact_phone' => '9800000000',
                'national_id' => '123-456-789',
                'latitude' => '27.7172',
                'longitude' => '85.3240',
            ],
        ]);

        $scrubbed = SentryScrubber::handle($event);

        $this->assertArrayNotHasKey('data', $scrubbed->getRequest());
    }

    public function test_the_query_string_is_stripped_from_the_url(): void
    {
        $event = $this->eventWithRequest([
            'url' => 'https://rong.example/households?search=राम+बहादुर&ward_id=3',
            'method' => 'GET',
            'query_string' => 'search=राम+बहादुर&ward_id=3',
        ]);

        $scrubbed = SentryScrubber::handle($event);
        $request = $scrubbed->getRequest();

        $this->assertSame('https://rong.example/households', $request['url']);
        $this->assertArrayNotHasKey('query_string', $request);
    }

    public function test_cookies_and_authorization_headers_are_dropped(): void
    {
        $event = $this->eventWithRequest([
            'url' => 'https://rong.example/admin/settings',
            'method' => 'GET',
            'cookies' => ['laravel_session' => 'abc123', 'XSRF-TOKEN' => 'tok'],
            'headers' => [
                'Cookie' => 'laravel_session=abc123',
                'Authorization' => 'Bearer secret-token',
                'X-XSRF-TOKEN' => 'tok',
                'User-Agent' => 'Mozilla/5.0',
                'Referer' => 'https://rong.example/admin',
            ],
        ]);

        $scrubbed = SentryScrubber::handle($event);
        $request = $scrubbed->getRequest();

        $this->assertArrayNotHasKey('cookies', $request);
        $this->assertArrayNotHasKey('Cookie', $request['headers']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
        $this->assertArrayNotHasKey('X-XSRF-TOKEN', $request['headers']);

        // Genuinely useful diagnostic headers survive.
        $this->assertSame('Mozilla/5.0', $request['headers']['User-Agent']);
        $this->assertSame('https://rong.example/admin', $request['headers']['Referer']);
    }

    public function test_the_route_and_method_survive_so_the_report_is_still_useful(): void
    {
        $event = $this->eventWithRequest([
            'url' => 'https://rong.example/households/12/members/5',
            'method' => 'PUT',
        ]);

        $request = SentryScrubber::handle($event)->getRequest();

        $this->assertSame('https://rong.example/households/12/members/5', $request['url']);
        $this->assertSame('PUT', $request['method']);
    }

    public function test_only_the_internal_user_id_is_kept(): void
    {
        $event = Event::createEvent();
        $event->setUser(UserDataBag::createFromArray([
            'id' => 42,
            'email' => 'enumerator@example.np',
            'username' => 'ram.shrestha',
            'ip_address' => '203.0.113.9',
        ]));

        $user = SentryScrubber::handle($event)->getUser();

        $this->assertSame(42, $user->getId());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getUsername());
        $this->assertNull($user->getIpAddress());
    }

    public function test_extra_context_is_cleared(): void
    {
        $event = Event::createEvent();
        $event->setExtra(['household' => ['name_np' => 'राम बहादुर', 'phone' => '9800000000']]);

        $this->assertSame([], SentryScrubber::handle($event)->getExtra());
    }

    /**
     * The end-to-end assertion: serialise the scrubbed event and confirm no
     * fragment of the original personal data appears anywhere in it, including
     * in a field this test did not anticipate.
     */
    public function test_no_personal_data_survives_serialisation(): void
    {
        $event = $this->eventWithRequest([
            'url' => 'https://rong.example/households?search=राम',
            'method' => 'POST',
            'query_string' => 'search=राम',
            'cookies' => ['laravel_session' => 'sess-abc123'],
            'headers' => ['Cookie' => 'laravel_session=sess-abc123'],
            'data' => [
                'name_np' => 'राम बहादुर श्रेष्ठ',
                'contact_phone' => '9800000000',
                'national_id' => '123-456-789',
            ],
        ]);
        $event->setUser(UserDataBag::createFromArray([
            'id' => 42,
            'email' => 'enumerator@example.np',
            'ip_address' => '203.0.113.9',
        ]));
        $event->setExtra(['income' => 250000]);

        $serialised = json_encode([
            'request' => SentryScrubber::handle($event)->getRequest(),
            'user' => [
                'id' => $event->getUser()?->getId(),
                'email' => $event->getUser()?->getEmail(),
                'ip' => $event->getUser()?->getIpAddress(),
            ],
            'extra' => $event->getExtra(),
        ], JSON_UNESCAPED_UNICODE);

        foreach ([
            'राम बहादुर श्रेष्ठ',
            'राम',
            '9800000000',
            '123-456-789',
            'enumerator@example.np',
            '203.0.113.9',
            'sess-abc123',
            '250000',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $serialised, "Leaked: {$secret}");
        }
    }
}
