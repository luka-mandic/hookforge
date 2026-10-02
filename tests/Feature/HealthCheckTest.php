<?php

test('returns the application health status', function () {
    $response = $this->get('/health');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('service', 'hookforge');
});
