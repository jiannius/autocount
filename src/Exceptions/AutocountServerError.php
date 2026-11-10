<?php

namespace Jiannius\Autocount\Exceptions;

/**
 * Autocount answered with a 5xx, or with a reply that is not readable JSON.
 *
 * For a write the outcome is unknown. The code is the HTTP status; the original
 * `RequestException` (when there was one) is kept as `previous`.
 */
class AutocountServerError extends \Exception
{
}
