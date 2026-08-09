<?php

namespace Liberu\Ecommerce\Catalog\Data;

use JsonSerializable;
use Liberu\Ecommerce\Catalog\Models\ProductPublication;

/**
 * Where and when a product is carried.
 *
 * `channelId` is a number here too. A consumer that wants a channel's name asks
 * whichever module owns channels; this one has never seen it.
 */
final readonly class PublicationData implements JsonSerializable
{
    public function __construct(
        public int $channelId,
        public ?string $publishedAt,
        public ?string $unpublishedAt,
        public bool $live,
    ) {}

    public static function from(ProductPublication $publication): self
    {
        return new self(
            channelId: (int) $publication->channel_id,
            publishedAt: $publication->published_at?->toIso8601String(),
            unpublishedAt: $publication->unpublished_at?->toIso8601String(),
            live: $publication->isLive(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'published_at' => $this->publishedAt,
            'unpublished_at' => $this->unpublishedAt,
            'live' => $this->live,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
