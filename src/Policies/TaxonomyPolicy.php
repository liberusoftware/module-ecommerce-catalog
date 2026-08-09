<?php

namespace Liberu\Ecommerce\Catalog\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may act on the things products hang from — categories, collections,
 * brands and vendors.
 *
 * One policy for four models rather than four identical ones. They differ in
 * nothing a policy can see: each carries a nullable `team_id`, and the rule is
 * ownership. Four copies would be four places for the orphan case to be
 * forgotten in three of them.
 *
 * `Tag` is absent on purpose. A tag is a shared word with no owner, so there is
 * nothing to scope; tagging goes through `SyncProductTags`, which is authorized
 * against the product.
 */
class TaxonomyPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function view(Authenticatable $actor, Model $subject): bool
    {
        return $this->ownsIt($actor, $subject);
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function update(Authenticatable $actor, Model $subject): bool
    {
        return $this->ownsIt($actor, $subject);
    }

    public function delete(Authenticatable $actor, Model $subject): bool
    {
        return $this->ownsIt($actor, $subject);
    }

    private function ownsIt(Authenticatable $actor, Model $subject): bool
    {
        $teamId = $this->teamOf($actor);
        $owner = $subject->getAttribute('team_id');

        return $teamId !== null && $owner !== null && (int) $owner === $teamId;
    }

    private function teamOf(Authenticatable $actor): ?int
    {
        $teamId = $actor->current_team_id ?? null;

        return $teamId === null ? null : (int) $teamId;
    }
}
