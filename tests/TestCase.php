<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Workbench\Database\Seeders\DatabaseSeeder;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * Run a specific seeder before each test.
     */
    protected $seeder = DatabaseSeeder::class;
}
