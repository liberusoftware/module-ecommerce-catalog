# Ecommerce: Catalog

> This package is the authoritative, provider-neutral implementation of Catalog. It owns domain behavior and data; optional API, Filament, Livewire, React, Vue, and Nuxt packages translate its public contracts for their surfaces.

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-catalog?sort=semver)](https://github.com/liberusoftware/module-ecommerce-catalog/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-catalog/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-catalog/actions/workflows/tests.yml)

Products, variants, categories, collections, tags, brands, vendors, channel
publication, visibility and effective dates.

## Features

- Fully compatible with **Laravel 13**, **PHP 8.5**, and **Pest 5**.
- Built following the domain-driven design guidelines of the Liberu architecture.
- Reusable, presenting a clean public contract and boundaries.
- Adheres to the strict database, security, and authorization standards of Liberu.

## Requirements

- **PHP 8.5**
- **Composer 2**
- A supported database (e.g. MySQL, PostgreSQL, SQLite)

## Quick start

To install this package via Composer, run:

```bash
composer require liberusoftware/ecommerce-catalog
```

Installing boots nothing. The module ships no `extra.laravel.providers`, so
`ModuleManagerServiceProvider` is the only thing that registers it, and only
when the deployment names it:

```dotenv
MODULES_ENABLED=ecommerce-catalog
```

## Three questions this module keeps apart

A product is described by three independent facts, and conflating any two of
them is the mistake this design exists to prevent:

| Question | Answered by |
| --- | --- |
| Is it sellable at all? | `status` — `draft → active ⇄ discontinued → archived` |
| Does it appear in listings, or only by direct link? | `visibility` — `public`, `unlisted`, `hidden` |
| Between when, and on which storefront? | its own effective dates, **and** a per-channel publication window |

```php
use Liberu\Ecommerce\Catalog\Actions\{CreateProduct, ChangeProductStatus, PublishToChannel};
use Liberu\Ecommerce\Catalog\Enums\ProductStatus;

$product = (new CreateProduct())->handle('Merino Crew', teamId: 7, storeId: 3);
(new ChangeProductStatus())->handle($product, ProductStatus::Active);
(new PublishToChannel())->handle($product, channelId: 1);
```

## What this module does not own

**No price and no stock.** Not an omission. Pricing and Inventory Ledger extend
a product through their own tables keyed on `products.id` and
`product_variants.id` — those two ids are the integration point, and they are
stable. A price column here would make this package the owner of a rule it does
not enforce.

**No stores and no channels.** `store_id` and `channel_id` are plain indexed
columns with no foreign key, because those tables belong to
`liberusoftware/ecommerce-commerce-core`, which is not a dependency. A host that
wants a channel's *name* rather than its number names the class in config.

### What the host owns

**The team model.** Every owned model resolves `config('catalog.team_model')` at
call time, defaulting to `App\Models\Team`. An application whose team model
lives elsewhere publishes the config and says so:

```bash
php artisan vendor:publish --tag=catalog-config
```

**Store scoping.** `products.store_id` is populated by the host — this module
never writes it. `Product::scopeForStore()` is here for callers that want the
filter explicitly.

**A backfill, if you already have a catalogue.** `status` and `visibility`
default to `draft` and `hidden`, which is right for a row being created and
wrong for every row that already sells. See the adoption guide.

## Documentation

- [Adoption guide](docs/adoption.md) — install, enable, and what the host must supply
- [The domain](docs/domain.md) — aggregates, actions, queries, events, authorization, tables
- [Runbook](docs/runbook.md) — what breaks in production and what to do about it
- [Changelog](CHANGELOG.md)
- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-catalog/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-catalog" alt="Contributors to liberusoftware/module-ecommerce-catalog">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-catalog/graphs/contributors).
