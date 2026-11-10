<?php

namespace Jiannius\Autocount\Exceptions;

/**
 * The request never got an answer: connection error or timeout.
 *
 * For a write the outcome is unknown — Autocount may or may not have processed it.
 * The original Laravel `ConnectionException` is kept as `previous`.
 */
class AutocountUnreachable extends \Exception
{
}
