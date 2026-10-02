<?php

namespace App\Services;

interface RetryPolicy
{
    public function shouldRetry(DeliveryResult $result, int $attemptNumber): bool;

    public function delayForAttempt(int $attemptNumber): int;
}
