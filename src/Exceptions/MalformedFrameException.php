<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when a frame is structurally invalid and cannot be parsed honestly.
 */
class MalformedFrameException extends ParseException {}
