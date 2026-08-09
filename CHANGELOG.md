# Changelog

All notable changes to `liberusoftware/ecommerce-catalog` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
versions are bare `MAJOR.MINOR.PATCH` tags — no `v` prefix — per ADR 0005 of the
Ecommerce repository.

## 0.1.0 — 2026-08-09

First release. The module is extracted from
`liberusoftware/ecommerce-laravel`, where these classes shipped as
`App\Models\Product`, `App\Models\ProductCategory`, `App\Models\ProductVariant`,
`App\Models\ProductOption`, `App\Models\ProductCollection` and `App\Models\Tag`.
The namespace is new; the tables are the ones that were already there.

### Added

- **Products.** `Product` stays whole — identity, description, SEO, filing,
  lifecycle, visibility and effective dates in one model. Splitting it would
  buy several models that are always loaded together, always saved together and
  can never disagree.
- **Lifecycle.** `ProductStatus` (`draft → active ⇄ discontinued → archived`)
  owning its own transition table, so no surface keeps a second copy of the
  rules. Archived is terminal, and there is no way back to `draft`.
- **Visibility**, as a separate field from status: `public`, `unlisted`,
  `hidden`. The middle state is the reason it is not a boolean — a product
  built for a campaign link has to be reachable without appearing in a listing,
  and encoded as a boolean that case gets implemented as "active but with a
  weird status".
- **Effective dates at two grains.** A product's window says whether the thing
  is sellable at all; a publication's says whether a given channel carries it.
  The narrower one wins, which is how a line goes live on the outlet channel a
  fortnight after the main one.
- **Channel publication** — `ecommerce_catalog_publications`, a `product_id` ×
  `channel_id` pivot with its own window. `channel_id` is a plain indexed column
  with no foreign key: channels belong to
  `liberusoftware/ecommerce-commerce-core`, which is not a dependency, and a
  package that constrains a table it does not own cannot be installed without
  it. A host that wants a channel's *name* sets `catalog.channel_model`.
- **Variants and options.** `ProductVariant` with up to three positional option
  axes and a SKU unique across the estate — a warehouse, a supplier feed and a
  marketplace listing all key on it, so the last defence is in the schema.
  Variants are hard-deleted so a SKU comes free again. `ProductOption` declares
  an axis and its choices, unique per product by name.
- **Categories** — a tree with one node per product, slugs unique across the
  whole tree because a category's URL is its slug, and `MoveCategory` refusing
  cycles. A cycle is not a wrong answer but a request that never returns.
- **Collections** — merchandised groupings in a chosen order, overlapping the
  tree on purpose: a category is where a product *is*, a collection is where a
  merchant *put* it this month.
- **Tags**, matched on slug so case and spacing fold into one, synced from names
  rather than ids because names are what every tagging surface actually has.
- **Brands and vendors**, as two fields rather than one "manufacturer": a
  shopper filters by the first and a buyer chases the second, and they are
  routinely different answers.
- **Seventeen past-tense domain events**, carrying the model where one still
  exists and ids where it does not.
- **Policies** — `ProductPolicy`, and one `TaxonomyPolicy` serving categories,
  collections, brands and vendors. Team ownership read off the actor rather
  than off a Filament panel, so it answers the same way in a console command, a
  queued import and an API request. A row belonging to nobody is nobody's to
  edit. `publish` is a separate ability from `update` so a host can put a second
  pair of eyes on it without a breaking change.
- **Read models and queries** — `ProductData`, `VariantData`, `CategoryData`,
  `CollectionData`, `BrandData`, `VendorData`, `PublicationData`, and
  `ProductQuery`, `CategoryQuery`, `CollectionQuery`. A presentation package can
  render a product without importing one, which is the boundary rule an `-api`
  adapter is held to.
- **`CatalogAccess`** — the policy questions asked by id, for an adapter that
  holds no model. A missing subject is denied rather than reported.
- **Telemetry** — `Telemetry\DomainEventLogger`, **off by default**. A catalogue
  import writes thousands of records in a minute, and a package that starts
  filling a deployment's log the moment it installs has decided somebody else's
  retention bill. Levels carry meaning: anything that takes a product away from
  shoppers is a `warning`. An option's values are never written.
- **`docs/adoption.md`**, **`docs/domain.md`**, **`docs/runbook.md`**.
- **`tests/SchemaTest.php`** — the migration is as much a public surface as the
  classes are. It asserts the tables, the bare-versus-prefixed naming in both
  directions, the columns a consumer reads, the database defaults (inserted
  through the query builder, so a row written by a seeder or another module's
  migration is covered too), every uniqueness control, what happens to a child
  when its parent goes, and that the tenant and channel columns are left
  unconstrained.

### Deliberately not included

- **No price and no stock**, anywhere. Pricing and Inventory Ledger extend a
  product through their own tables keyed on `products.id` and
  `product_variants.id`. Those two ids are the integration point and they are
  stable. A price column here would make this package the owner of a rule it
  does not enforce.
- **No dependency on `ecommerce-commerce-core`.** Stores and channels are ids.
- **No Filament, Livewire or HTTP surface.** Those are one-to-one presentation
  packages that delegate to the actions, queries and policies here.
