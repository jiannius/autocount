<?php

namespace Jiannius\Autocount\Exceptions;

/**
 * No token could be obtained, so the real request was never sent.
 *
 * Covers: the login call failing (HTTP error or connection error — the original is
 * kept as `previous`), a login reply without a JWTToken, and missing configuration
 * (URL, app id, user id, password), which stops the request before anything is sent.
 *
 * The short name of this class is a contract with consumers; do not rename it.
 */
class AutocountLoginFailed extends \Exception
{
}
