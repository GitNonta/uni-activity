<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * `migrate:fresh` asks for interactive confirmation when it targets a
     * persistent database (e.g. database/database.sqlite), which fails under
     * PHPUnit. --force skips the prompt so RefreshDatabase works everywhere.
     *
     * NOTE: signature must stay untyped to remain compatible with the
     * framework's CanConfigureMigrationCommands trait.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return array_merge(parent::migrateFreshUsing(), [
            '--force' => true,
        ]);
    }
}
