<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The host's team model
    |--------------------------------------------------------------------------
    |
    | Products, categories, collections, brands and vendors belong to a team,
    | and the team belongs to the application rather than to this package. So
    | the class is resolved here at call time and never imported — a module that
    | names `App\Models\Team` in a `use` statement installs into exactly one
    | application.
    |
    | The default is Jetstream's, which is what every Liberu application uses.
    |
    */

    'team_model' => env('CATALOG_TEAM_MODEL', 'App\\Models\\Team'),

    /*
    |--------------------------------------------------------------------------
    | The channel model
    |--------------------------------------------------------------------------
    |
    | Channel publication is a `channel_id` on this module's own pivot, because
    | channels belong to `liberusoftware/ecommerce-commerce-core` and this
    | package does not depend on it. The id alone is enough for every rule this
    | module enforces.
    |
    | A host that does run Commerce Core — or anything else with a channels
    | table — names the class here, and `ProductPublication::channel()` becomes
    | loadable so a panel can show a name instead of a number. Leave it null and
    | the relation is simply never used.
    |
    */

    'channel_model' => env('CATALOG_CHANNEL_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    |
    | Structured records of this module's own domain events. Off by default: a
    | catalogue import writes thousands of these in a minute, and a package that
    | starts filling a deployment's log the moment it installs has decided
    | somebody else's retention bill.
    |
    | `channel` is a Laravel log channel name, or null for the default one.
    |
    | Nothing here is exclusive — everything the logger writes is a domain event
    | any listener can subscribe to.
    |
    */

    'telemetry' => [
        'enabled' => (bool) env('CATALOG_TELEMETRY', false),
        'channel' => env('CATALOG_TELEMETRY_CHANNEL'),
    ],

];
