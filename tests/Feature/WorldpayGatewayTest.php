<?php

namespace Tests\Feature;

use App\Payments\PaymentGateway;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorldpayGatewayTest extends TestCase
{
    public function test_it_sends_escaped_xml_with_basic_auth_and_accepts_an_authorised_payment(): void
    {
        Http::fake(['worldpay.test/*' => Http::response($this->reply('<orderStatus orderCode="A"><payment><lastEvent>AUTHORISED</lastEvent></payment></orderStatus>'))]);

        $result = app(PaymentGateway::class)->charge($this->payment(address1: '1 <Evil> & "Co" St'));

        $this->assertTrue($result->successful);
        $this->assertSame('AUTHORISED', $result->lastEvent);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://worldpay.test/paymentService.jsp'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('WORLDPAINTBALLAUCCGBP:worldpay-secret'))
            && str_contains($request->body(), '<address1>1 &lt;Evil&gt; &amp; &quot;Co&quot; St</address1>')
            && str_contains($request->body(), '<amount value="15883" currencyCode="AUD" exponent="2"/>')
            && str_contains($request->body(), '<cardHolderName>Jordan Smith</cardHolderName>')
            && str_contains($request->body(), '<date month="12" year="2030"/>'));
    }

    public function test_a_refused_payment_fails(): void
    {
        Http::fake(['worldpay.test/*' => Http::response($this->reply('<orderStatus orderCode="A"><payment><lastEvent>REFUSED</lastEvent></payment></orderStatus>'))]);

        $result = app(PaymentGateway::class)->charge($this->payment());

        $this->assertFalse($result->successful);
        $this->assertSame(PaymentResult::REFUSED_MESSAGE, $result->message);
    }

    public function test_an_error_reply_fails_with_its_code(): void
    {
        Http::fake(['worldpay.test/*' => Http::response($this->reply('<error code="5"><![CDATA[Duplicate Order]]></error>'))]);

        $result = app(PaymentGateway::class)->charge($this->payment());

        $this->assertFalse($result->successful);
        $this->assertSame('5', $result->errorCode);
    }

    public function test_a_reply_without_an_authorised_state_is_not_treated_as_paid(): void
    {
        Http::fake(['worldpay.test/*' => Http::response($this->reply('<orderStatus orderCode="A"><requestInfo/></orderStatus>'))]);

        $this->assertFalse(app(PaymentGateway::class)->charge($this->payment())->successful);
    }

    public function test_unreadable_replies_and_timeouts_are_reported_as_unconfirmed(): void
    {
        Http::fake(['worldpay.test/*' => Http::response('<html>Bad Gateway</html')]);
        $this->assertSame(PaymentResult::UNCONFIRMED_MESSAGE, app(PaymentGateway::class)->charge($this->payment())->message);

        Http::fake(fn () => throw new ConnectionException('Timed out'));
        $this->assertSame(PaymentResult::UNCONFIRMED_MESSAGE, app(PaymentGateway::class)->charge($this->payment())->message);
    }

    private function payment(string $address1 = '1 Example Street'): PaymentRequest
    {
        return new PaymentRequest(
            merchantCode: 'WORLDPAINTBALLAUCCGBP',
            orderCode: 'SAM_1001_REGO261027120000',
            description: 'Regos Portal',
            amount: 15883,
            currency: 'AUD',
            cardNumber: '4111111111111111',
            cardHolderName: 'Jordan Smith',
            expiryMonth: '12',
            expiryYear: '2030',
            securityCode: '123',
            address1: $address1,
            address2: '',
            city: 'Sydney',
            postcode: '2000',
            countryCode: 'AU',
        );
    }

    private function reply(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">'
            .'<paymentService version="1.4" merchantCode="WORLDPAINTBALLAUCCGBP"><reply>'.$inner.'</reply></paymentService>';
    }
}
