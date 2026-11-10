<?php

namespace App\Exceptions;

use RuntimeException;

/** Safe messages only; do not expose raw HTTP exceptions, tokens or guest data. */
class HostexException extends RuntimeException {}
