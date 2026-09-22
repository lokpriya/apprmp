<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Give paginators the { data, links, meta } shape every list page expects.
 *
 * Every paginated page in the app reads `x.meta.total` and `x.meta.last_page`,
 * and the review and publish queues page with `x.links.prev` / `x.links.next`.
 * That is the shape an API resource collection produces — but the controllers
 * handed Inertia a bare LengthAwarePaginator, whose toArray() is flat: `total`
 * and `last_page` sit at the top level, and `links` is an array of page buttons
 * rather than an object with prev/next.
 *
 * So `meta` was undefined on every one of those pages, and nothing crashed,
 * because the markup guards it:
 *
 *     {{ institutions.meta?.total ?? 0 }}                    → always 0
 *     v-if="institutions.meta && institutions.meta.last_page > 1"
 *                                                            → never rendered
 *
 * The result was that every list header read "जम्मा 0" and no list in the
 * application could be paged past its first 20 rows. Reported from the field on
 * the institution list, where a ward admin could reach only 20 of their
 * institutions.
 *
 * Converted here rather than in the templates so the pages keep the contract
 * they were written against, and so the seven affected pages were fixed by one
 * change rather than by editing thirty-five references by hand.
 *
 * (A macro on LengthAwarePaginator would read better at the call sites, but the
 * paginator classes do not use the Macroable trait.)
 */
class InertiaPaginator
{
    public static function format(LengthAwarePaginator $paginator): array
    {
        return [
            'data'  => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
            ],
        ];
    }
}
