<?php

namespace App\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Strips personally identifiable information from Sentry events before they
 * leave the server.
 *
 * This application holds household survey data — names, phone numbers, GPS
 * coordinates, citizenship numbers, disability status and income — collected by
 * local governments. None of it may be transmitted to an error-monitoring
 * service abroad. Sentry is useful here only for *where* the code broke, never
 * for *whose record* broke it.
 *
 * Implemented as a class with a static entry point rather than a closure in
 * config/sentry.php on purpose: `php artisan config:cache` (which production
 * runs) cannot serialise a closure, but it exports a [class, method] array fine.
 *
 * The default posture is deny: request bodies, query strings and cookies are
 * dropped wholesale rather than filtered key by key, because a survey form can
 * grow a new PII field at any time and an allow-list would silently start
 * leaking it.
 */
class SentryScrubber
{
    /**
     * Headers worth keeping for diagnosis. Everything else — Cookie,
     * Authorization, X-XSRF-TOKEN and any custom header — is discarded.
     */
    private const SAFE_HEADERS = [
        'accept',
        'accept-encoding',
        'accept-language',
        'content-type',
        'host',
        'referer',
        'user-agent',
        'x-inertia',
        'x-requested-with',
    ];

    public static function handle(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            $event->setRequest([
                // The route is the whole point of the report; the query string is
                // not, and it routinely carries ward, settlement and search terms.
                'url' => self::stripQueryString($request['url'] ?? null),
                'method' => $request['method'] ?? null,
                'headers' => self::filterHeaders($request['headers'] ?? []),
                // 'data', 'query_string' and 'cookies' are deliberately absent:
                // a household or member form body is pure PII.
            ]);
        }

        // Keep the internal user id for correlating reports; drop everything that
        // identifies the person behind it.
        $user = $event->getUser();
        if ($user !== null) {
            $event->setUser(
                \Sentry\UserDataBag::createFromArray(array_filter([
                    'id' => $user->getId(),
                ], fn ($v) => $v !== null))
            );
        }

        $event->setExtra([]);

        return $event;
    }

    private static function stripQueryString(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $position = strpos($url, '?');

        return $position === false ? $url : substr($url, 0, $position);
    }

    /**
     * @param  array<string,mixed>  $headers
     * @return array<string,mixed>
     */
    private static function filterHeaders(array $headers): array
    {
        $safe = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SAFE_HEADERS, true)) {
                $safe[$name] = $value;
            }
        }

        return $safe;
    }
}
