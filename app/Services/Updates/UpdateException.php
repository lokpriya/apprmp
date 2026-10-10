<?php

namespace App\Services\Updates;

use RuntimeException;

/**
 * Any refusal or failure in building, verifying or applying an update.
 *
 * The message is shown to the super admin as-is, so it should say what is wrong
 * and what to do about it, in plain words.
 */
class UpdateException extends RuntimeException
{
}
