<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking rule was broken. The message is safe to show to the trainer.
 */
class BookingException extends RuntimeException
{
    public static function sessionFull(): self
    {
        return new self('Η συνεδρία είναι γεμάτη.');
    }

    public static function sessionCancelled(): self
    {
        return new self('Η συνεδρία έχει ακυρωθεί.');
    }

    public static function alreadyBooked(): self
    {
        return new self('Ο πελάτης έχει ήδη κράτηση σε αυτή τη συνεδρία.');
    }

    public static function clientBusy(string $clientName): self
    {
        return new self("Ο/Η {$clientName} έχει ήδη ραντεβού εκείνη την ώρα.");
    }

    public static function trainerBusy(): self
    {
        return new self('Υπάρχει ήδη συνεδρία ή κλεισμένη ώρα σε αυτό το διάστημα.');
    }

    public static function notAClient(): self
    {
        return new self('Μόνο πελάτες μπορούν να κάνουν κράτηση.');
    }
}
