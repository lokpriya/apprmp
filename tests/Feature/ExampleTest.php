<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path is not a page of its own — it forwards to the public
     * profile dashboard, which is what visitors without an account should see.
     */
    public function test_root_redirects_to_the_public_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('public.dashboard'));
    }
}
