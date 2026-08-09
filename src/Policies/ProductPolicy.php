<?php

namespace Liberu\Ecommerce\Catalog\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;
use Liberu\Ecommerce\Catalog\Models\Product;

/**
 * Who may act on a product.
 *
 * Tenancy is the whole policy: a product belongs to one team, and an actor
 * works in one team at a time. The team is read off the actor rather than off a
 * Filament panel, so this answers the same way in a console command, a queued
 * import and an API request — the places a panel-shaped check silently allows
 * everything.
 *
 * A product belonging to nobody (`team_id` null) is nobody's to edit. That is
 * deliberately stricter than the read scope, which leaves such rows visible:
 * seeing an orphan is how it gets fixed, editing one is how it gets stolen.
 */
class ProductPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function view(Authenticatable $actor, Product $product): bool
    {
        return $this->ownsIt($actor, $product);
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function update(Authenticatable $actor, Product $product): bool
    {
        // An archived product is a record, not a resource. Editing one rewrites
        // what orders and reports already point at.
        return $this->ownsIt($actor, $product) && $product->status !== ProductStatus::Archived;
    }

    public function delete(Authenticatable $actor, Product $product): bool
    {
        // A product that was ever offered is archived instead, which is why
        // only a draft may go. Deleting cascades to variants, options,
        // publications and every pivot row.
        return $this->ownsIt($actor, $product) && $product->status === ProductStatus::Draft;
    }

    public function changeStatus(Authenticatable $actor, Product $product): bool
    {
        return $this->ownsIt($actor, $product) && ! $product->status->isTerminal();
    }

    /**
     * Publication is separated from `update` on purpose.
     *
     * Editing a description and putting something in front of shoppers are
     * different-sized mistakes, and a host that wants a second pair of eyes on
     * the second one needs somewhere to say so. Same rule today; a distinct
     * ability so it can stop being the same rule without a breaking change.
     */
    public function publish(Authenticatable $actor, Product $product): bool
    {
        return $this->ownsIt($actor, $product) && $product->status !== ProductStatus::Archived;
    }

    public function manageVariants(Authenticatable $actor, Product $product): bool
    {
        return $this->update($actor, $product);
    }

    private function ownsIt(Authenticatable $actor, Product $product): bool
    {
        $teamId = $this->teamOf($actor);

        return $teamId !== null && $product->team_id !== null && (int) $product->team_id === $teamId;
    }

    private function teamOf(Authenticatable $actor): ?int
    {
        $teamId = $actor->current_team_id ?? null;

        return $teamId === null ? null : (int) $teamId;
    }
}
