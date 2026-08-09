<?php

declare(strict_types=1);

use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\UsesTestUser;

/*
 * `UsesTestUser` brings `RefreshDatabase` with it, and both halves are wanted:
 * the policies read `current_team_id` off a real actor, and the migrations this
 * package's provider loads need a database to run against.
 */
uses(PackageTestCase::class, UsesTestUser::class)->in(__DIR__);
