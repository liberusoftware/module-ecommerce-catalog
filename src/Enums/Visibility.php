<?php

namespace Liberu\Ecommerce\Catalog\Enums;

/**
 * How discoverable a product is, independently of whether it is sellable.
 *
 * Three states rather than a boolean because the middle one is the whole point:
 * a product built for a campaign link, a wholesale customer or a QA pass has to
 * be reachable without appearing in a listing or a sitemap. Encoded as a
 * boolean, that case gets implemented as "active but with a weird status", and
 * the storefront ends up with two competing notions of published.
 */
enum Visibility: string
{
    /** Listed, searchable, in the sitemap, reachable. */
    case Public = 'public';

    /** Reachable by direct link only — not listed, not searched, not in the sitemap. */
    case Unlisted = 'unlisted';

    /** Not reachable at all. */
    case Hidden = 'hidden';

    /** Whether it appears in listings, search and feeds. */
    public function isListed(): bool
    {
        return $this === self::Public;
    }

    /** Whether a direct URL resolves to it. */
    public function isReachable(): bool
    {
        return $this !== self::Hidden;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
