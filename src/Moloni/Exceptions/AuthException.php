<?php

declare(strict_types=1);

namespace Moloni\Exceptions;

/**
 * Thrown when authentication with Moloni ON fails (invalid credentials,
 * expired/rejected tokens, missing company).
 */
class AuthException extends MoloniException
{
}
