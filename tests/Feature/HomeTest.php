<?php

it('renders the overview page', function () {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Home'));
});
