<?php

test('api health endpoint returns ok', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});
