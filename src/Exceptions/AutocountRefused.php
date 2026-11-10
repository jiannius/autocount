<?php

namespace Jiannius\Autocount\Exceptions;

/**
 * Autocount answered, and said `Status: Fail`.
 *
 * The message is Autocount's own `Message`, unchanged. Autocount ran (or declined to
 * run) the request and told us so — this is a definite answer, not an unknown outcome.
 *
 * The short name of this class is a contract with consumers; do not rename it.
 */
class AutocountRefused extends \Exception
{
}
