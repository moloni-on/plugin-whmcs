<?php

declare(strict_types=1);

namespace MoloniOn\Exceptions;

/**
 * Thrown when authentication with Moloni ON fails (invalid credentials,
 * expired/rejected tokens, missing company).
 */
class AuthException extends MoloniException
{
}
