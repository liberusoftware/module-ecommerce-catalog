<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog schema: what is sold, how it is organised, and where and when it
 * is offered.
 *
 * `products`, `product_categories`, `product_variants`, `product_options`,
 * `collections`, `collection_items`, `tags` and `product_tag` keep their bare
 * names — they existed in the host before this package did, and
 * `MODULE_DEVELOPMENT.md` §1.5 keeps an extracted table's name so the extraction
 * is a namespace change rather than a data migration. `brands`, `vendors` and
 * the channel publication pivot are invented here, so they carry the module
 * prefix.
 *
 * Every `Schema::create` is guarded. In the host these tables are already there
 * with more columns than this creates, and this migration must be a no-op on
 * that database; on a fresh install it is the whole story.
 *
 * **No price, no stock.** `products` here carries neither. Pricing and Inventory
 * Ledger are sibling modules that extend a product through their own tables
 * keyed on `products.id`, which is the one column of this schema every other
 * module is entitled to know about. A price column here would make this package
 * the owner of a rule it does not enforce.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                // Nullable, with no foreign key: teams belong to the host
                // application, whose table this package must not constrain.
                // Null means nobody, which is what the policies deny on.
                $table->foreignId('team_id')->nullable()->index();
                // The host's column name, kept. A tree, so a category may be
                // deleted only with its descendants — a re-parenting delete
                // would silently move a merchant's catalogue.
                $table->foreignId('parent_category_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['parent_category_id', 'position']);
            });
        }

        if (! Schema::hasTable('ecommerce_catalog_brands')) {
            Schema::create('ecommerce_catalog_brands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ecommerce_catalog_vendors')) {
            Schema::create('ecommerce_catalog_vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                // Who to chase when a line does not arrive. Deliberately thin:
                // a vendor here is a catalogue attribution, not a supplier
                // record with terms and settlement — that belongs to whichever
                // module actually pays them.
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->index();
                // The tenancy grain a shopper sees. A team may run several
                // stores, and store A's shopper must not be shown store B's
                // catalogue, so reads scope on `store_id` and not `team_id`.
                // No foreign key: `stores` belongs to Commerce Core, which is
                // not a dependency of this package.
                $table->foreignId('store_id')->nullable()->index();
                $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
                $table->foreignId('brand_id')->nullable()->constrained('ecommerce_catalog_brands')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('ecommerce_catalog_vendors')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->text('short_description')->nullable();
                $table->text('long_description')->nullable();
                $table->string('featured_image')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('meta_keywords')->nullable();
                // Draft, not active: a product starts not for sale, and
                // somebody decides otherwise. The opposite default publishes
                // a half-entered product the moment a row is inserted.
                $table->string('status')->default('draft')->index();
                // Hidden for the same reason, and separately from status: an
                // active product may be deliberately unlisted (reachable by
                // link, absent from listings and search), which is not a
                // lifecycle state and must not be encoded as one.
                $table->string('visibility')->default('hidden')->index();
                // The window the product is offered in. Null on either end
                // means unbounded — a seasonal line gets both, most products
                // get neither, and nothing has to invent a sentinel date.
                $table->timestamp('available_from')->nullable();
                $table->timestamp('available_until')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->unsignedInteger('position')->default(0);
                $table->softDeletes();
                $table->timestamps();

                // The storefront's query, in the order it filters.
                $table->index(['store_id', 'status', 'visibility']);
            });
        }

        if (! Schema::hasTable('product_options')) {
            Schema::create('product_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('position')->default(1);
                // The choices, as a list. JSON rather than a child table: an
                // option's values are read and written whole, never queried
                // individually, and a table would buy a join for nothing.
                $table->json('values');
                $table->timestamps();

                // One "Size" per product. Two is a data-entry mistake that
                // presents as variants matching the wrong axis.
                $table->unique(['product_id', 'name']);
                $table->index(['product_id', 'position']);
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                // Unique across the estate, not per product: a SKU is what a
                // warehouse, a supplier feed and a marketplace listing all key
                // on, and two products sharing one is a mis-ship waiting to
                // happen. Null is allowed — a single-variant product needs no
                // code — and null is not unique to anything.
                $table->string('sku')->nullable()->unique();
                $table->string('title')->nullable();
                // Three positional option values, matching the host's shape and
                // every marketplace feed this has to speak to. A fourth axis is
                // a modelling smell, not a missing column.
                $table->string('option1')->nullable();
                $table->string('option2')->nullable();
                $table->string('option3')->nullable();
                $table->string('barcode')->nullable();
                $table->decimal('weight', 8, 2)->nullable();
                $table->string('weight_unit')->default('kg');
                $table->boolean('requires_shipping')->default(true);
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();

                $table->index(['product_id', 'position']);
            });
        }

        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('collection_items')) {
            Schema::create('collection_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                // A collection is a merchandised order, so membership carries
                // its position rather than falling back on insertion order.
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['collection_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                // Slugged because tags are addressable — `/tags/summer-sale` is
                // a page, and building it from `name` at request time means the
                // URL changes when somebody fixes a capital letter.
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_tag')) {
            Schema::create('product_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('ecommerce_catalog_publications')) {
            Schema::create('ecommerce_catalog_publications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                // A plain column, indexed, with no foreign key. Channels belong
                // to Commerce Core, which this package does not depend on and
                // must not constrain — publication is a `channel_id` this
                // module keys on, not a relation into another package's model.
                $table->unsignedBigInteger('channel_id')->index();
                // Publication's own window, separate from the product's. The
                // product window says when the thing is sellable at all; this
                // says when a given storefront carries it, which is how a line
                // goes live on the outlet channel a fortnight after the main
                // one. Null `published_at` means live from the moment the row
                // exists.
                $table->timestamp('published_at')->nullable();
                $table->timestamp('unpublished_at')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'channel_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_catalog_publications');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('products');
        Schema::dropIfExists('ecommerce_catalog_vendors');
        Schema::dropIfExists('ecommerce_catalog_brands');
        Schema::dropIfExists('product_categories');
    }
};
