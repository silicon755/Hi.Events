<?php

namespace HiEvents\Services\Infrastructure\Payments\Mpesa;

use HiEvents\DomainObjects\OrderDomainObject;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MpesaPaymentService
{
    private Client $client;
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;

    public function __construct()
    {
        $this->client = new Client();
        // Get these from a secure configuration file or environment variables
        $this->baseUrl = config('services.mpesa.base_url'); 
        $this->consumerKey = config('services.mpesa.consumer_key');
        $this->consumerSecret = config('services.mpesa.consumer_secret');
    }

    private function generateAccessToken(): string
    {
        $credentials = base64_encode("{$this->consumerKey}:{$this->consumerSecret}");
        $response = $this->client->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials", [
            'headers' => [
                'Authorization' => "Basic {$credentials}",
            ]
        ]);
        return json_decode($response->getBody())->access_token;
    }

    public function initiateStkPush(OrderDomainObject $order, string $phoneNumber): bool
    {
        $accessToken = $this->generateAccessToken();
        $timestamp = date('YmdHis');
        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        try {
            $response = $this->client->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $order->getTotalGross(), 
                    'PartyA' => $phoneNumber, 
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $phoneNumber,
                    'CallBackURL' => config('services.mpesa.callback_url'),
                    'AccountReference' => 'HiEvents Order ' . $order->getId(),
                    'TransactionDesc' => 'Payment for event tickets',
                ],
            ]);

            $body = json_decode($response->getBody());
            // You should log the response and handle different outcomes
            if ($body->ResponseCode == '0') {
                // STK push was successful. Store the CheckoutRequestID for callback validation.
                return true;
            }

            Log::error('Mpesa STK Push failed', ['response' => $body]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Mpesa STK Push exception', ['message' => $e->getMessage()]);
            return false;
        }
    }
}