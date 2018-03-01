# silverstripe-paypalwebhook

This module is a PayPal webhook event handling delegation interface, a subclass can handle one or 
more event and an event can be handled by one or more subclass

## Requirements
* silverstripe/framework: ^4

## Configuration
By default the environment is set to sandbox

```yaml
Vulcan\PayPalWebhook\PayPalWebhook:
  environment: sandbox
  oauth_sandbox_clientid: ".."
  oauth_sandbox_secretid: ".."
  oauth_live_clientid: ".."
  oauth_live_secretid: ".."
  webhook_sandbox_id: ".."
  webhook_live_id: ".."
```

You can also use test keys and the webhook simulator will work fine with this module

> **WARNING**: While this module is in sandbox mode, events will NOT be verified!

## Usage
1. Install and dev/build
1. Add a sandbox webhook endpoint to PayPal that points to https://yourdomain.com/paypal-webhook and ensure that it sends the events you require
2. Create your functionality for your event(s):

```php
<?php

use Vulcan\PayPalWebhook\Handlers\PayPalEventHandler;

class CustomerDisputeHandler extends PayPalEventHandler
{
    private static $events = [
        'CUSTOMER.DISPUTE.CREATED'
    ];

    public static function handle($event, array $data)
    {
        // $event is the string identifier of the event
        return "Do something here";
    }
}
```

Any subclass of `PayPalEventHandler` is detected and requires both the `private static $events`
and `public static function handle($event, $data)` to be defined.

`private static $events` must be defined and can be a string containing a single [event identifier](https://stripe.com/docs/api#event_types) or an array with multiple

`public static function handle($event,$data)` must be defined and should not call the parent. $data will be a `\Stripe\Event` object which has the exact same hierarchy as the JSON response depicted in their examples.
  
## Features
* All *handled* events are logged, along with the responses from their handlers.
* Duplicates are ignored, if PayPal sends the same event more than once it won't be processed, but the logged event will count the occurence
* All events are verified to have been sent from PayPal using the webhook ID you defined in the configuration above

## Why?
Easily introduce new event handling functionality without needing to touch any files relating to other event handling classes.

## License
[BSD-3-Clause](LICENSE.md) - [Vulcan Digital Ltd](https://vulcandigital.co.nz)