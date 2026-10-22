<?php

use App\Services\WebhookSigner;

test('signature is prefixed and matches an independently computed hmac', function () {
    $payload = ['id' => 'evt_1', 'amount' => 10];

    $expected = 'sha256='.hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), 'secret');

    expect((new WebhookSigner)->sign($payload, 'secret'))->toBe($expected);
});

test('array and pre-encoded string payloads produce the same signature', function () {
    $signer = new WebhookSigner;
    $payload = ['url' => 'https://example.com/a', 'n' => 1];

    expect($signer->sign($payload, 'secret'))
        ->toBe($signer->sign(json_encode($payload, JSON_UNESCAPED_SLASHES), 'secret'));
});

test('signature changes when the secret or payload changes', function () {
    $signer = new WebhookSigner;

    $base = $signer->sign(['a' => 1], 'secret');

    expect($signer->sign(['a' => 1], 'other-secret'))->not->toBe($base)
        ->and($signer->sign(['a' => 2], 'secret'))->not->toBe($base);
});

test('signature is deterministic', function () {
    $signer = new WebhookSigner;

    expect($signer->sign(['a' => 1], 'secret'))->toBe($signer->sign(['a' => 1], 'secret'));
});

test('slashes are not escaped before signing', function () {
    $signature = (new WebhookSigner)->sign(['u' => 'a/b'], 'secret');

    expect($signature)->toBe('sha256='.hash_hmac('sha256', '{"u":"a/b"}', 'secret'));
});

test('unencodable payloads throw instead of producing a signature', function () {
    (new WebhookSigner)->sign(['bad' => "\xB1\x31"], 'secret');
})->throws(JsonException::class);
