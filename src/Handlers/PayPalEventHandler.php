<?php

namespace Vulcan\PayPalWebhook\Handlers;

use SilverStripe\Core\Config\Configurable;
use Stripe\Event;
use Vulcan\PayPalWebhook\PayPalWebhook;

/**
 * Class StripeEventHandler
 *
 * @package Vulcan\PayPalWebhook\Handlers
 */
abstract class PayPalEventHandler
{
    use Configurable;

    /**
     * The dot notated event this handler is responsible for, full list at https://stripe.com/docs/api#event_types, can either be an
     * array, or a string. If handling multiple you should check what $event is in your {@link ::handle()} override
     *
     * @config
     * @var    array
     */
    private static $events = null;

    /**
     * You should override this method in your subclass and create any functionality you need
     * to handle the data from the event
     *
     * @param $event
     * @param array $data
     *
     * @return string
     */
    public static function handle($event, array $data)
    {
        throw new \RuntimeException('You must override "handle" in ' . static::class);
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return PayPalWebhook::config()->get('environment') == 'sandbox';
    }

    /**
     * @return bool
     */
    public function isLive()
    {
        return PayPalWebhook::config()->get('environment') != 'sandbox';
    }
}
