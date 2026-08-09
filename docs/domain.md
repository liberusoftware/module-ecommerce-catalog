# Catalog — the domain

What the module owns, and the shape of its public surface. Anything not listed
here is internal and may change without a major version.

## Aggregates

**Product** — the thing being sold, whole. Identity, description, SEO, filing
(one category, one brand, one vendor), lifecycle, visibility and effective
dates. It is a large model and it stays large: splitting the merchandising
fields from the identity fields from the SEO fields buys three models that are
always loaded together, always saved together and can never disagree.

Lifecycle: `draft → active ⇄ discontinued → archived`; **archived is terminal**,
and there is no way back to `draft`. A product that has been offered has been
seen and linked, and un-finishing it is how a live URL starts 404ing.

**ProductVariant** — one buyable configuration, with up to three positional
option values, a SKU **unique across the estate**, and what it takes to ship it.
Hard-deleted rather than soft-deleted, so a SKU comes free again.

**ProductOption** — one axis a product varies along, and the choices on it. The
axis is declared here; the combinations live on the variants. Unique per product
by name.

**Category** — the merchant's tree. A product sits in exactly one node. Slugs
are unique across the whole tree, not within a parent, because a category's URL
is its slug. Re-parenting goes through `MoveCategory`, which refuses cycles.

**ProductCollection** — a merchandised grouping, in a chosen order, with any
number of products. Table `collections`; the class is `ProductCollection`
because a class called `Collection` in a Laravel package is a permanent import
collision.

**Tag** — a free-form label, shared across the catalogue and deliberately not
team-scoped. Matching is on the slug, so "Water Resistant" and "water resistant"
are one tag.

**Brand** / **Vendor** — who made it, and who the merchant gets it from. Two
fields rather than one "manufacturer", because a shopper filters by the first
and a buyer chases the second, and they are routinely different answers.

**ProductPublication** — a product carried by a channel, for a window.

## Three things that are not the same thing

This trips people up, so it is written down once:

| Question | Answered by |
| --- | --- |
| Is it sellable at all? | `status` — the lifecycle |
| Does it appear in listings, or only by direct link? | `visibility` — `public`, `unlisted`, `hidden` |
| Between when and when? | `available_from` / `available_until`, **and** the publication's own window |

A product is **available** on a channel when it is `active`, inside its own
window, and has a publication for that channel that is inside *its* window. It
is **listed** when it is also `public`. `Product::scopeAvailableOn()` is the one
implementation of that rule; `isAvailableOn()` delegates to it rather than
deciding again in PHP.

The two windows are not redundant. The product's says whether the thing is
sellable anywhere; the publication's says whether this storefront carries it.
That is how a line goes live on the outlet channel a fortnight after the main
one. The narrower one wins.

## What this module does not own

**No price and no stock.** Not an omission and not a gap to be filled later.
Pricing and Inventory Ledger extend a product through their own tables keyed on
`products.id` and `product_variants.id`. Those two ids are the integration
point, and they are stable. A price column here would make this package the
owner of a rule it does not enforce, and two owners of one number is how they
come to disagree.

**No channels and no stores.** `store_id` and `channel_id` are plain indexed
columns with no foreign key. `stores` and `channels` belong to
`liberusoftware/ecommerce-commerce-core`, which is not a dependency: a package
that constrains a table it does not own cannot be installed without it.

## The write surface — actions

Every mutation goes through one of these. They dispatch the domain events and
enforce the invariants; a caller that writes a model directly bypasses both.

| Action | Notes |
| --- | --- |
| `CreateProduct` | Starts `draft` + `hidden`. Unique slug by suffix. `teamId`/`storeId` are named arguments, so an attribute spread cannot decide who owns the row |
| `ChangeProductStatus` | Idempotent. Throws `InvalidStatusTransition` |
| `SetProductVisibility` | Idempotent. No transition table — visibility describes the present |
| `ScheduleAvailability` | Both ends optional. Throws `InvalidAvailabilityWindow` if it closes before it opens. A window in the past is accepted |
| `PublishToChannel` | Upserts the window. Refuses an archived product; allows a draft, so a season can be staged |
| `UnpublishFromChannel` | Closes a live publication, deletes one that never started. Silent when there was none |
| `AddVariant` | Appends. Throws `SkuAlreadyClaimed` |
| `RemoveVariant` | Hard delete, so the SKU comes free |
| `SetProductOption` | Keyed on the name. De-duplicates and re-indexes the values |
| `SyncProductTags` | Takes names, not ids. Creates what is missing, folds case and spacing. Silent when nothing moved |
| `AddProductToCollection` / `RemoveProductFromCollection` | Idempotent both ways |
| `CreateCategory` | Slug unique across the tree. Appends within its parent |
| `MoveCategory` | Refuses a cycle. Idempotent |
| `CreateCollection` / `CreateBrand` / `CreateVendor` | Unique slug by suffix |

## The read surface — queries and data

`Data\` classes are what leaves this module. They are plain readonly objects
implementing `JsonSerializable`, so an `-api` package can serialise a product
without importing one — which is the boundary rule it is held to.

| Query | Returns |
| --- | --- |
| `ProductQuery::paginate($storeId)` | The operator's list: everything, in any state |
| `ProductQuery::storefront($storeId, $channelId, $at)` | The shopper's list: available, listed, on this channel, at this moment |
| `ProductQuery::findOnChannel($slug, $channelId, $at)` | A direct link — **available**, not listed, so an unlisted campaign URL works |
| `ProductQuery::inCategory($id, $channelId)` | The node and everything under it |
| `CategoryQuery::tree()` / `::breadcrumb($id)` | The whole tree from one query; the breadcrumb walk is bounded so a corrupted row cannot hang a render |
| `CollectionQuery::paginate()` / `::products($id, $channelId)` | Merchandised order, filtered to what the shopper may see |

Relations are only reported when the caller loaded them. `ProductData::from()`
on a bare model returns empty lists rather than lazily fetching — a read model
that lazy-loads turns a paginated list into six queries per row.

## Events

Seventeen, all past tense, all carrying the model where one still exists and ids
where it does not. A listener inside this module wants the model; one in Pricing
or Inventory Ledger wants the id.

`ProductCreated`, `ProductStatusChanged`, `ProductVisibilityChanged`,
`ProductAvailabilityScheduled`, `ProductPublished`, `ProductUnpublished`,
`VariantAdded`, `VariantRemoved`, `ProductOptionSet`, `ProductTagsChanged`,
`ProductAddedToCollection`, `ProductRemovedFromCollection`, `CategoryCreated`,
`CategoryMoved`, `CollectionCreated`, `BrandCreated`, `VendorCreated`.

`VariantAdded` is the one the sibling modules care about most: a new sellable id
exists and neither of them has a row for it yet.

## Authorization

Two policies. `ProductPolicy` and `TaxonomyPolicy` — the latter serves
categories, collections, brands and vendors, because they differ in nothing a
policy can see and four copies is four places to forget the orphan case in
three of them.

The rule is team ownership, read off the actor's `current_team_id` rather than
off a Filament panel, so it answers the same way in a console command, a queued
import and an API request. A row belonging to nobody (`team_id` null) is
nobody's to edit — stricter than the read scope on purpose: seeing an orphan is
how it gets fixed, editing one is how it gets stolen.

`ProductPolicy` adds `changeStatus`, `publish` and `manageVariants`. `publish`
is separated from `update` even though the rule is the same today: editing a
description and putting something in front of shoppers are different-sized
mistakes, and a host wanting a second pair of eyes on the second one needs
somewhere to say so.

`Services\CatalogAccess` asks the same questions by id, for a consumer that
holds no model. A missing subject is denied rather than reported.

`Tag` has no policy: a tag is a shared word with no owner, and tagging is
authorized against the product.

## Tables

| Table | Origin |
| --- | --- |
| `products` | Extracted — bare name |
| `product_categories` | Extracted — bare name, and the host's `parent_category_id` column |
| `product_variants`, `product_options` | Extracted — bare names |
| `collections`, `collection_items` | Extracted — bare names |
| `tags`, `product_tag` | Extracted — bare names |
| `ecommerce_catalog_brands` | Invented — module prefix |
| `ecommerce_catalog_vendors` | Invented — module prefix |
| `ecommerce_catalog_publications` | Invented — module prefix |

Every `Schema::create` is guarded by `hasTable`. In the host these tables already
exist with more columns than this creates, and the migration must be a no-op
there; on a fresh install it is the whole story.
