<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when replay-envelope inputs disagree about session or ordering facts.
 */
class ReplayConsistencyException extends ReplayException {}
