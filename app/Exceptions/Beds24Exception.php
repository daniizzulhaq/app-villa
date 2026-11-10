<?php

namespace App\Exceptions;

use RuntimeException;

/** Safe, user-facing errors: never include raw API responses or credentials. */
class Beds24Exception extends RuntimeException {}
