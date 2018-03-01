<?php

namespace Vulcan\PayPalWebhook;

use PayPal\Api\VerifyWebhookSignature;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use Vulcan\PayPalWebhook\Handlers\PayPalEventHandler;

/**
 * Class PayPalWebhook
 *
 * @package Vulcan\PayPalWebhook
 */
class PayPalWebhook implements Flushable
{
    use Injectable, Configurable;

    /**
     * Environment mode, one of "sandbox" or "live". Note that events will not be verified while the environment is set to sandbox mode
     *
     * @config
     * @var    string
     */
    private static $environment = 'sandbox';

    /**
     * Sandbox Client ID
     *
     * @config
     * @var    bool
     */
    private static $oauth_sandbox_clientid = false;

    /**
     * Sandbox Secret ID
     *
     * @config
     * @var    bool
     */
    private static $oauth_sandbox_secretid = false;

    /**
     * Live Client ID
     *
     * @config
     * @var    bool
     */
    private static $oauth_live_clientid = false;

    /**
     * Live Secret ID
     *
     * @var bool
     */
    private static $oauth_live_secretid = false;

    /**
     * Webhook Sandbox ID
     *
     * @var bool
     */
    private static $webhook_sandbox_id = false;

    /**
     * Webhook Live ID
     *
     * @var bool
     */
    private static $webhook_live_id = false;

    /**
     * @var ApiContext
     */
    protected $apiContext;

    /**
     * @var array|bool
     */
    protected $credentials = false;

    /**
     * PayPalWebhook constructor.
     */
    public function __construct()
    {
        $this->credentials = static::getCredentials();
        $this->apiContext = new ApiContext(new OAuthTokenCredential(static::getCredentials()['clientid'], static::getCredentials()['secretid']));
    }

    /**
     * Returns a multi-dimensional array of classes indexed by the event they handle
     *
     * @return array|mixed
     */
    public function getHandlers()
    {
        /**
         * @var CacheInterface $cache
         */
        $cache = Injector::inst()->get(CacheInterface::class . '.paypalWebhook');

        if ($manifest = $cache->get('eventHandlers')) {
            return $manifest;
        }

        $classes = ClassInfo::subclassesFor(PayPalEventHandler::class);
        $manifest = [];

        /**
         * @var PayPalEventHandler $class
         */
        foreach ($classes as $class) {
            if ($class == PayPalEventHandler::class) {
                continue;
            }

            $handlerFor = $class::config()->get('events');

            if (!$handlerFor) {
                throw new \InvalidArgumentException($class . ' is missing private static $events');
            }

            if (is_array($handlerFor)) {
                foreach ($handlerFor as $event) {
                    $manifest[$event][] = $class;
                }
                continue;
            }

            if (!is_string($handlerFor)) {
                throw new \InvalidArgumentException('Invalid type, expecting string or array but got ' . gettype($handlerFor) . ' instead');
            }

            $manifest[$handlerFor] = $class;
        }

        $cache->set('eventHandlers', $manifest);

        return $manifest;
    }

    /**
     * This function is triggered early in the request if the "flush" query
     * parameter has been set. Each class that implements Flushable implements
     * this function which looks after it's own specific flushing functionality.
     *
     * @see FlushMiddleware
     */
    public static function flush()
    {
        /**
         * @var CacheInterface $cache
         */
        $cache = Injector::inst()->get(CacheInterface::class . '.paypalWebhook');
        $cache->delete('eventHandlers');
    }

    /**
     * @param array  $headers
     * @param string $body
     *
     * @return bool
     */
    public function verifyWebhookEvent(array $headers, string $body)
    {
        $headers = array_change_key_case($headers, CASE_UPPER);
        $requiredHeaders = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];

        foreach ($requiredHeaders as $header) {
            if (!isset($headers[$header])) {
                return false;
            }
        }

        try {
            $signatureVerification = new VerifyWebhookSignature();
            $signatureVerification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO']);
            $signatureVerification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID']);
            $signatureVerification->setCertUrl($headers['PAYPAL-CERT-URL']);
            $signatureVerification->setWebhookId($this->getWebhookId());
            $signatureVerification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG']);
            $signatureVerification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME']);
            $signatureVerification->setRequestBody($body);
        } catch (\Exception $e) {
            return false;
        }

        try {
            /**
             * @var \PayPal\Api\VerifyWebhookSignatureResponse $output
             */
            $output = $signatureVerification->post($this->apiContext);
        } catch (\Exception $ex) {
            return false;
        }

        if (strtoupper($output->getVerificationStatus()) == 'FAILED') {
            return false;
        }

        return true;
    }

    /**
     * Gets the API context required by PayPal
     *
     * @return ApiContext
     */
    public static function getContext()
    {
        $auth = static::getCredentials();

        $context = new ApiContext(new OAuthTokenCredential($auth['clientid'], $auth['secretid']));
        $context->setConfig(
            [
            'mode' => static::config()->get('environment')
            ]
        );

        return $context;
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->credentials['clientid'];
    }

    /**
     * @return string
     */
    public function getSecretId()
    {
        return $this->credentials['secretid'];
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->config()->get('environment');
    }

    /**
     * @return string
     */
    public function getWebhookId()
    {
        $webhookId = $this->config()->get('webhook_live_id');

        if ($this->config()->get('environment') == 'sandbox') {
            $webhookId = $this->config()->get('webhook_sandbox_id');
        }

        if (!$webhookId) {
            $environment = static::config()->get('environment');
            throw new \RuntimeException("You are missing webhook_%s_id", $environment);
        }

        return $webhookId;

    }

    /**
     * Gets the configured credentials for the current environment.
     *
     * @return array
     */
    private static function getCredentials()
    {
        $clientId = static::config()->get('oauth_live_clientid');
        $secretId = static::config()->get('oauth_live_secretid');

        if (static::config()->get('environment') == 'sandbox') {
            $clientId = static::config()->get('oauth_sandbox_clientid');
            $secretId = static::config()->get('oauth_sandbox_secretid');
        }

        if (!$clientId || !$secretId) {
            $environment = static::config()->get('environment');
            throw new \RuntimeException("You are missing either oauth_%s_clientid or oauth_%s_secretid", $environment, $environment);
        }

        return [
            'clientid' => $clientId,
            'secretid' => $secretId,
        ];
    }
}
