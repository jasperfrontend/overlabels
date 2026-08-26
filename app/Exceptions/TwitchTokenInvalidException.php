<?php

namespace App\Exceptions;

use Exception;

/**
 * Helix answered 401: the user's access token is invalid or expired and the
 * refresh did not save it. Thrown from TwitchApiService so callers can tell
 * "the streamer must re-authenticate" apart from every other failure - the
 * delivery ledger records it as its own outcome, because it is the one
 * failure reason a debrief can hand the streamer as an instruction.
 */
class TwitchTokenInvalidException extends Exception {}
