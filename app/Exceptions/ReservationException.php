<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking rule was broken. The message is written for the customer or staff member,
 * so controllers can show it on screen as it is.
 */
class ReservationException extends RuntimeException
{
}
