<?php

it('redirects guests from the panel to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('renders the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});
