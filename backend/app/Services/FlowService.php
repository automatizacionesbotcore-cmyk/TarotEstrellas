<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlowService
{
    private string $apiKey;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey    = (string) config('services.flow.api_key', '');
        $this->secretKey = (string) config('services.flow.secret_key', '');
        $this->baseUrl   = (string) config('services.flow.base_url', 'https://www.flow.cl/api');
    }

    public function estaConfigurado(): bool
    {
        return $this->apiKey !== '' && $this->secretKey !== '';
    }

    /**
     * Crea una orden de pago en Flow.cl
     * Returns: ['redirect_url' => string, 'token' => string, 'flow_order' => int|null]
     */
    public function crearPago(
        string $commerceOrder,
        string $subject,
        int    $amount,
        string $email,
        string $urlConfirmation,
        string $urlReturn,
        string $currency = 'CLP'
    ): array {
        $params = [
            'apiKey'          => $this->apiKey,
            'commerceOrder'   => $commerceOrder,
            'subject'         => $subject,
            'currency'        => $currency,
            'amount'          => (string) $amount,
            'email'           => $email,
            'urlConfirmation' => $urlConfirmation,
            'urlReturn'       => $urlReturn,
        ];
        $params['s'] = $this->firmar($params);

        $response = Http::asForm()->post($this->baseUrl . '/payment/create', $params);

        if ($response->failed()) {
            throw new \RuntimeException('Flow.cl error: ' . ($response->json('message') ?? $response->body()));
        }

        $data  = $response->json();
        $url   = (string) ($data['url']   ?? '');
        $token = (string) ($data['token'] ?? '');

        if ($url === '' || $token === '') {
            throw new \RuntimeException('Flow.cl no devolvió URL ni token.');
        }

        return [
            'redirect_url' => $url . '?token=' . $token,
            'token'        => $token,
            'flow_order'   => $data['flowOrder'] ?? null,
        ];
    }

    /**
     * Obtiene el estado de un pago por token.
     * Status: 1=pendiente, 2=pagado, 3=rechazado, 4=cancelado
     */
    public function obtenerEstadoPorToken(string $token): array
    {
        $params = [
            'apiKey' => $this->apiKey,
            'token'  => $token,
        ];
        $params['s'] = $this->firmar($params);

        $response = Http::get($this->baseUrl . '/payment/getStatus', $params);

        if ($response->failed()) {
            throw new \RuntimeException('Flow.cl getStatus error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Genera la firma HMAC-SHA256.
     * Algoritmo: params ordenados alfabéticamente → concat key+value → HMAC-SHA256 hex
     */
    public function firmar(array $params): string
    {
        ksort($params);
        $chain = '';
        foreach ($params as $key => $value) {
            $chain .= $key . $value;
        }
        return hash_hmac('sha256', $chain, $this->secretKey);
    }
}
