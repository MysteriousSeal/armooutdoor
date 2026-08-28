<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    public function test_the_503_page_renders_without_any_database_query(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->view('errors.503')
            ->assertSee('503')
            ->assertSee('Réessayer');

        $this->assertSame(0, $queries, 'The maintenance page must not depend on the database.');
    }
}
