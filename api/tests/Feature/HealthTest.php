<?php

it('returns the health envelope', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'OK',
            'data' => ['service' => 'dooh-api', 'version' => 'v1'],
            'errors' => null,
            'meta' => null,
        ]);
});

it('wraps unknown routes in the error envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJson(['success' => false, 'message' => 'Not found.', 'data' => null]);
});
