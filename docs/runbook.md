# Catalog — runbook

What breaks in production, how to recognise it, and what to do.

## "The whole catalogue vanished from the storefront"

**Most likely:** a backfill that never ran. `products.status` and
`products.visibility` default to `draft` and `hidden`, which is right for a row
being created and wrong for every row that predates the module.

```sql
select status, visibility, count(*) from products group by 1, 2;
```

All `draft`/`hidden` means the adoption step in `docs/adoption.md` was skipped.
Backfill to `active`/`public`.

**Second most likely:** publication. If the storefront calls
`ProductQuery::storefront($storeId, $channelId)`, a product with no publication
row for that channel is correctly invisible.

```sql
select count(*) from products p
  left join ecommerce_catalog_publications pub on pub.product_id = p.id
  where pub.id is null;
```

**Third:** an effective date that closed. `available_until` is **exclusive** —
"until the 1st" means the 1st is over.

```sql
select id, slug, available_from, available_until from products
  where (available_from is not null and available_from > now())
     or (available_until is not null and available_until <= now());
```

## "One product is missing and the rest are fine"

Ask the module rather than guessing:

```php
$product = Liberu\Ecommerce\Catalog\Models\Product::query()->where('slug', '…')->first();

$product->status;                    // active?
$product->visibility;                // public?
$product->isAvailableOn();           // in its own window?
$product->isAvailableOn($channelId); // and published on this channel?
$product->isListedOn($channelId);    // and listed rather than unlisted?
```

The four questions are separate on purpose, and the answer is whichever one is
false. An `unlisted` product is *supposed* to be missing from listings while its
direct URL still works — check whether somebody set that deliberately before
changing it.

## "A product 404s that should be unlisted, not gone"

`findOnChannel()` uses **availability**, not listing, so an unlisted product
resolves by direct link. A 404 means it is `hidden`, `draft`/`discontinued`,
outside its window, or not published to that channel. `hidden` and `unlisted`
are one keystroke apart in an admin form and this is what the mistake looks
like.

## "Menus, breadcrumbs or category pages hang"

A cycle in the category tree. `MoveCategory` refuses to create one, so this
means a row was written around the action — a direct `update`, a seeder, an
import.

```sql
-- direct self-parent; the cheap case
select id, parent_category_id from product_categories where id = parent_category_id;
```

`CategoryQuery::breadcrumb()` is bounded by the row count and will return a
repeating trail rather than hanging, which is the symptom that identifies this.
Fix by re-parenting the offending node with `MoveCategory`, which will then
refuse the bad target and tell you which one it was.

## "Adding a variant fails with a SKU error"

`SkuAlreadyClaimed` names the code. SKUs are unique **across the estate**, not
per product, deliberately — a warehouse and a supplier feed both key on them.

```sql
select sku, count(*) from product_variants where sku is not null group by 1 having count(*) > 1;
```

If the claiming variant was deleted, it really is free: variants are hard
deleted precisely so the code comes back. If the error persists after a delete,
something soft-deleted the *product* — the variants are still there, and
`forceDelete` or an explicit variant removal is what frees them.

## "An import created a second product instead of updating one"

Slugs are made unique by suffix rather than by rejection, so a re-run of a badly
keyed import produces `merino-crew-2`, `merino-crew-3`. That is the intended
behaviour for an operator typing a name and the wrong behaviour for an import,
which should key on its own identifier and call `PublishToChannel`-style upserts.

```sql
select regexp_replace(slug, '-[0-9]+$', '') as base, count(*) from products group by 1 having count(*) > 1;
```

Note that a **soft-deleted** product still holds its slug. That is correct — the
unique index still holds it too.

## "Search traffic to a category dropped"

`catalog.category.moved` is logged at `warning` for exactly this. Every
breadcrumb and canonical URL under a moved node changes with it.

```bash
grep 'catalog.category.moved' storage/logs/laravel.log
```

Telemetry is off by default; if it was off, the event still fired and any
listener could have caught it.

## Turning telemetry on

```dotenv
CATALOG_TELEMETRY=true
CATALOG_TELEMETRY_CHANNEL=stack
```

Runtime-changeable: the subscriber is always registered and checks the config on
each event, so this can be flipped while an incident is open.

Every record carries a stable `event` key (`catalog.product.published`, …).
Levels carry meaning: anything that takes a product away from shoppers —
unpublish, variant removal, leaving `active`, leaving `public`, a category move
— is a `warning`. Everything else is `info`.

**What is never written:** an option's values (merchant content, unbounded), and
no field of this module holds a credential. Product ids, slugs, channel ids and
tag slugs are written, and are the keys to search on.

## Queue and volume notes

A catalogue import writes one telemetry record per product, per variant and per
publication. Leave telemetry off during a bulk import, or point it at a channel
with its own retention.

`Product::isAvailableOn()` runs one query per call by design — it delegates to
the scope so there is only one implementation of the rule. Rendering a list with
it is a query per row; filter with `->availableOn()` in the query instead.
