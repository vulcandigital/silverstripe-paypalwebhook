<?php

namespace Vulcan\PayPalWebhook\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use Vulcan\PayPalWebhook\Handlers\PayPalEventHandler;
use Vulcan\PayPalWebhook\Models\EventOccurrence;
use Vulcan\PayPalWebhook\PayPalWebhook;

/**
 * Class PayPalWebhookController
 *
 * @package Vulcan\PayPalWebhook\Controllers
 */
class PayPalWebhookController extends Controller
{
    private static $allowed_actions = [
        'index'
    ];

    /**
     * @var PayPalWebhook
     */
    protected $client;

    public function init()
    {
        parent::init();

        $this->client = PayPalWebhook::create();
    }

    /**
     * @param HTTPRequest $request
     *
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        $body = $request->getBody();
        $eventJson = json_decode($body, true);
        $webhook = PayPalWebhook::create();

        if (!$eventJson) {
            $this->httpError(422, 'The body did not contain valid json');
        }

        if (PayPalWebhook::config()->get('environment') !== 'sandbox') {
            // webhook simulated events cannot be verified, so no attempt is made to valid events while in sandbox mode
            if (!$webhook->verifyWebhookEvent($request->getHeaders(), $body)) {
                $this->httpError(401);
            }
        }

        $result = $this->delegateEvent($eventJson);

        if (!$result) {
            return $this->getResponse()->setBody('No handlers defined for event ' . $eventJson['event_type']);
        }

        $occurrence = EventOccurrence::create();
        $occurrence->EventID = $eventJson['id'];
        $occurrence->Type = $eventJson['event_type'];
        $occurrence->Data = $body;
        $occurrence->Handlers = implode(PHP_EOL, $result['Handlers']);
        $occurrence->HandlerResponses = implode(PHP_EOL, $result['Responses']);
        $occurrence->write();

        $break = (Director::is_cli()) ? PHP_EOL : "<br/>";
        return $this->getResponse()->setBody(implode($break, $result['Responses']));
    }

    /**
     * Delegate the event to any handlers waiting for it
     *
     * @param array $event
     *
     * @return array|null
     */
    public function delegateEvent(array $event)
    {
        $handlers = $this->client->getHandlers();

        if (!isset($handlers[$event['event_type']])) {
            return null;
        }

        $responses = [];
        /**
         * @var PayPalEventHandler $class
         */
        foreach ($handlers[$event['event_type']] as $class) {
            $response = $class::handle($event['event_type'], $event);
            $responses[] = $class . ':' . $response ?: "NULL";
        }

        return [
            'Handlers'  => $handlers[$event['event_type']],
            'Responses' => $responses
        ];
    }
}
