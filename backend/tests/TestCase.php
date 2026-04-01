<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Disable FK checks before dropping tables so RefreshDatabase works with MySQL.
     */
    protected function setUpTraits(): array
    {
        $uses = parent::setUpTraits();

        if (isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class])) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        return $uses;
    }
}
