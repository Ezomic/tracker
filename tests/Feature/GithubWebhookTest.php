<?php

declare(strict_types=1);

beforeEach(function () {
    config(['services.github.webhook_secret' => 'test-secret']);
});

function signGithubPayload(string $body, string $secret = 'test-secret'): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

it('accepts a request with a valid signature', function () {
    $body = json_encode(['action' => 'ping']);

    $this->postJson('/api/webhooks/github', json_decode($body, true), [
        'X-Hub-Signature-256' => signGithubPayload($body),
    ])->assertNoContent();
});

it('rejects a request with an invalid signature', function () {
    $body = json_encode(['action' => 'ping']);

    $this->postJson('/api/webhooks/github', json_decode($body, true), [
        'X-Hub-Signature-256' => 'sha256=not-the-real-signature',
    ])->assertUnauthorized();
});

it('rejects a request with no signature header', function () {
    $this->postJson('/api/webhooks/github', ['action' => 'ping'])
        ->assertUnauthorized();
});

it('rejects every request when no webhook secret is configured', function () {
    config(['services.github.webhook_secret' => null]);
    $body = json_encode(['action' => 'ping']);

    $this->postJson('/api/webhooks/github', json_decode($body, true), [
        'X-Hub-Signature-256' => signGithubPayload($body),
    ])->assertUnauthorized();
});
