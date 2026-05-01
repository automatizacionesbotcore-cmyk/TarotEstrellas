<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaypalService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId     = (string) config('services.paypal.client_id', '');
        $this->clientSecret = (string) config('services.paypal.client_secret', '');
        $this->baseUrl      = (string) config('services.paypal.base_url', 'https://api-m.paypal.com');
    }

    public function estaConfigurado(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal_access_token', 28800, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                throw new \RuntimeException('PayPal auth error: ' . $response->body());
            }

            return (string) ($response->json('access_token') ?? '');
        });
    }

    /**
     * Crea una orden PayPal y devuelve ['order_id', 'approval_url']
     */
    public function crearOrden(
        string $referenceId,
        float  $amount,
        string $currency,
        string $description,
        string $returnUrl,
        string $cancelUrl
    ): array {
        $token = $this->accessToken();

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $referenceId,
                'description'  => $description,
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value'         => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
                'brand_name'          => 'TarotEstrellas',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $response = Http::withToken($token)->post($this->baseUrl . '/v2/checkout/orders', $body);

        if ($response->failed()) {
            throw new \RuntimeException('PayPal create order error: ' . $response->body());
        }

        $data        = $response->json();
        $orderId     = (string) ($data['id'] ?? '');
        $approvalUrl = '';

        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        if ($orderId === '' || $approvalUrl === '') {
            throw new \RuntimeException('PayPal no devolvió order_id ni approval_url.');
        }

        return ['order_id' => $orderId, 'approval_url' => $approvalUrl];
    }

    /**
     * Captura el pago de una orden aprobada por el usuario.
     */
    public function capturarOrden(string $orderId): array
    {
        $token    = $this->accessToken();
        $response = Http::withToken($token)
            ->post($this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');

        if ($response->failed()) {
            throw new \RuntimeException('PayPal capture error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Obtiene el detalle de una orden.
     */
    public function obtenerOrden(string $orderId): array
    {
        $token    = $this->accessToken();
        $response = Http::withToken($token)
            ->get($this->baseUrl . '/v2/checkout/orders/' . $orderId);

        if ($response->failed()) {
            throw new \RuntimeException('PayPal get order error: ' . $response->body());
        }

        return $response->json() ?? [];
    }
}
