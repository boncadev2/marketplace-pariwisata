<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class IdempotencyConflictException extends Exception implements ShouldntReport {}
