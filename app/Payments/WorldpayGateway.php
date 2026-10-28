<?php

namespace App\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Worldpay XML Direct ("CARD-SSL") integration.
 */
class WorldpayGateway implements PaymentGateway
{
    /**
     * Payment states that mean the funds were authorised.
     */
    private const AUTHORISED_EVENTS = ['AUTHORISED', 'CAPTURED', 'SETTLED'];

    /**
     * @param  array{url: string, password: ?string, timeout: int}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function charge(PaymentRequest $request): PaymentResult
    {
        try {
            $response = $this->http
                ->withBasicAuth($request->merchantCode, (string) $this->config['password'])
                ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
                ->connectTimeout(15)
                ->timeout($this->config['timeout'])
                ->withBody($this->toXml($request), 'text/xml; charset=utf-8')
                ->post($this->config['url']);
        } catch (ConnectionException $exception) {
            Log::channel('payments')->critical('Worldpay unreachable', [
                'order_code' => $request->orderCode,
                'error' => $exception->getMessage(),
            ]);

            return PaymentResult::failed(PaymentResult::UNCONFIRMED_MESSAGE);
        }

        return $this->parse($response->body(), $request->orderCode);
    }

    /**
     * Build the paymentService submit document. Every value is escaped.
     */
    public function toXml(PaymentRequest $request): string
    {
        $e = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
            <paymentService version="1.4" merchantCode="{$e($request->merchantCode)}">
              <submit>
                <order orderCode="{$e($request->orderCode)}">
                  <description>{$e($request->description)}</description>
                  <amount value="{$request->amount}" currencyCode="{$e($request->currency)}" exponent="2"/>
                  <orderContent><![CDATA[]]></orderContent>
                  <paymentDetails>
                    <CARD-SSL>
                      <cardNumber>{$e($request->cardNumber)}</cardNumber>
                      <expiryDate>
                        <date month="{$e($request->expiryMonth)}" year="{$e($request->expiryYear)}"/>
                      </expiryDate>
                      <cardHolderName>{$e($request->cardHolderName)}</cardHolderName>
                      <cvc>{$e($request->securityCode)}</cvc>
                      <cardAddress>
                        <address>
                          <address1>{$e($request->address1)}</address1>
                          <address2>{$e($request->address2)}</address2>
                          <address3></address3>
                          <postalCode>{$e($request->postcode)}</postalCode>
                          <city>{$e($request->city)}</city>
                          <countryCode>{$e($request->countryCode)}</countryCode>
                        </address>
                      </cardAddress>
                    </CARD-SSL>
                  </paymentDetails>
                </order>
              </submit>
            </paymentService>
            XML;
    }

    /**
     * Only an explicit AUTHORISED (or later) state counts as success; the
     * legacy integration treated any reply without an error as paid.
     */
    public function parse(string $body, string $orderCode): PaymentResult
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            Log::channel('payments')->critical('Worldpay returned an unreadable reply', ['order_code' => $orderCode]);

            return PaymentResult::failed(PaymentResult::UNCONFIRMED_MESSAGE);
        }

        $error = match (true) {
            isset($xml->reply->error) => $xml->reply->error,
            isset($xml->reply->orderStatus->error) => $xml->reply->orderStatus->error,
            default => null,
        };

        if ($error !== null) {
            return PaymentResult::failed(PaymentResult::REFUSED_MESSAGE, null, (string) $error['code']);
        }

        $lastEvent = (string) ($xml->reply->orderStatus->payment->lastEvent ?? '');

        if (in_array($lastEvent, self::AUTHORISED_EVENTS, true)) {
            return PaymentResult::authorised($lastEvent);
        }

        return PaymentResult::failed(PaymentResult::REFUSED_MESSAGE, $lastEvent !== '' ? $lastEvent : null);
    }
}
