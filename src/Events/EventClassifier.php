<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;

/**
 * Classifies normalized events into typed subclasses.
 *
 * Returns the most specific typed event available for a known event name.
 * Unknown event names degrade to RawEvent — never throws on unknown names.
 *
 * The typed event families are:
 * - BackgroundJobEvent:    BACKGROUND_JOB
 * - ChannelLifecycleEvent: CHANNEL_CREATE, CHANNEL_DESTROY, CHANNEL_STATE,
 *                          CHANNEL_ANSWER, CHANNEL_PROGRESS, CHANNEL_PROGRESS_MEDIA
 * - HangupEvent:           CHANNEL_HANGUP, CHANNEL_HANGUP_COMPLETE
 * - CustomEvent:           CUSTOM
 * - RawEvent:              everything else
 *
 * @api
 */
final class EventClassifier
{
    private const CHANNEL_LIFECYCLE_EVENTS = [
        'CHANNEL_CREATE',
        'CHANNEL_DESTROY',
        'CHANNEL_STATE',
        'CHANNEL_ANSWER',
        'CHANNEL_PROGRESS',
        'CHANNEL_PROGRESS_MEDIA',
        'CHANNEL_OUTGOING',
    ];

    private const HANGUP_EVENTS = [
        'CHANNEL_HANGUP',
        'CHANNEL_HANGUP_COMPLETE',
    ];

    public function classify(NormalizedEvent $event): EventInterface
    {
        $name = $event->eventName();

        if ($name === 'BACKGROUND_JOB') {
            return new BackgroundJobEvent($event);
        }

        if (in_array($name, self::CHANNEL_LIFECYCLE_EVENTS, true)) {
            return new ChannelLifecycleEvent($event);
        }

        if (in_array($name, self::HANGUP_EVENTS, true)) {
            return new HangupEvent($event);
        }

        if ($name === 'CUSTOM') {
            return new CustomEvent($event);
        }

        return new RawEvent($event);
    }
}
