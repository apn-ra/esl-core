<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when end-of-input is reached with an incomplete frame buffered.
 */
class TruncatedFrameException extends ParseException {}
