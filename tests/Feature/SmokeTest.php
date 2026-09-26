<?php

use App\Models\Tenant;
use App\Models\User;

it('redirects guests from the panel to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('renders the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('sends a user whose session is out of date back to the login page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    // AuthenticateSession logs a user out when the password hash stored in
    // their session no longer matches, as it does after a password change.
    $this->actingAs($user)
        ->withSession(['password_hash_web' => 'a password hash from before the change'])
        ->get("/{$tenant->getKey()}/tasks")
        ->assertRedirect('/login');

    $this->assertGuest();
});
