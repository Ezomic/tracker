<?php

declare(strict_types=1);

namespace App\Services\Drafting;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Never reported: the error reporter would send the request input along,
 * and that holds the pasted text, which must not be stored anywhere.
 */
class IssueDraftFailed extends RuntimeException implements ShouldntReport {}
