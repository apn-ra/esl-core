<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

/**
 * Event format for ESL event subscriptions.
 */
enum EventFormat: string
{
    case Plain = 'plain';
    case Json  = 'json';
    case Xml   = 'xml';
}
