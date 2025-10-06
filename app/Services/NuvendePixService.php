<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NuvendePixService
{
    protected string $apiUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $pixKey;
    protected string $accountId;
    protected ?string $accessToken = null;
    protected ?Carbon $tokenExpiresAt = null;
    protected NuvendePixService $pixService;

    public function __construct()
    {
        $this->apiUrl = env('NUVENDE_API_URL', 'https://api-h.nuvende.com.br'); 
        $this->clientId = env('NUVENDE_CLIENT_ID'); 
        $this->clientSecret = env('NUVENDE_CLIENT_SECRET'); 
        $this->pixKey = env('NUVENDE_PIX_KEY'); 
        $this->accountId = env('NUVENDE_ACCOUNT_ID'); 
    }
 
    function getToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt->isFuture()) {
            return $this->accessToken;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->apiUrl}/api/v2/auth/login", [
                'grant_type' => 'client_credentials',
                'scope' => 'cob.read cob.write',
            ]);

        Log::info('Token request', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (!$response->successful() || !isset($response['access_token'])) {
            throw new \Exception('Falha ao obter token: ' . $response->body());
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiresAt = Carbon::now()->addSeconds($response['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    public function verifyClientUser(): array
    {
        $token = $this->getToken();
        $url = "{$this->apiUrl}/api/v2/auth/user";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post($url);

        if ($response->successful()) {
            return [
                'ok' => true,
                'data' => $response->json(),
            ];
        }

        Log::error('Erro ao verificar informações do client', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'ok' => false,
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    public function createPixCharge(
        float $amount,
        string $payerName = 'Cliente Teste',
        string $cpf = '00000000000',
        string $externalId = null
    ): array {
        $token = $this->getToken();
        $externalId = $externalId ?? uniqid('txn_');

        $payload = [
            'calendario' => ['expiracao' => 3600],
            'devedor' => ['cpf' => $cpf, 'nome' => $payerName],
            'valor' => ['original' => number_format($amount, 2, '.', '')],
            'chave' => $this->pixKey,
            'solicitacaoPagador' => "Pagamento referente ao pedido $externalId",
        ];

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Account-Id' => $this->accountId,
        ];

        Log::info('Preparando Pix Charge', [
            'endpoint' => "{$this->apiUrl}/api/v2/cobranca/cob",
            'headers' => $headers,
            'body' => $payload
        ]);

        $response = Http::withHeaders($headers)
            ->post("{$this->apiUrl}/api/v2/cobranca/cob", $payload);

        Log::info('Resposta Pix Charge', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            return [
                'ok' => true,
                'data' => $response->json(),
            ];
        }

        return $this->analyzeNuvendeError($response, $payload);
    }

    protected function analyzeNuvendeError($response, array $payload): array
    {
        $status = $response->status();
        $body = $response->body();
        $json = $response->json() ?: [];

        $reason = 'Erro desconhecido na API';
        $hint = null;

        if ($status === 401) {
            if (stripos($body, 'Unauthenticated') !== false) {
                $reason = 'Token inválido ou ausente';
                $hint = 'Verifique client_id/client_secret e token Bearer.';
            } elseif (stripos($body, 'Operação não permitida') !== false || stripos($body, 'Operacao nao permitida') !== false) {
                $reason = 'Permissão negada para a operação';
                $hint = 'Provavelmente a chave Pix não está vinculada ao Account-Id informado ou o client_id não tem permissão.';
            } else {
                $reason = '401 - Não autorizado';
                $hint = 'Verifique token/escopos/account_id/chave.';
            }
        }

        if ($status === 404) {
            $reason = 'Rota não encontrada';
            $hint = 'Verifique endpoint e base URL.';
        }

        if ($status === 422 || $status === 400) {
            $reason = 'Dados inválidos';
            $hint = 'Confira campos obrigatórios: calendario, devedor, valor.original, chave.';
        }

        if ($status >= 500) {
            $reason = 'Erro interno do servidor remoto';
            $hint = 'Tentar novamente; se persistir, contatar suporte da Nuvende.';
        }

        return [
            'ok' => false,
            'error' => [
                'message' => $reason,
                'hint' => $hint,
                'remote' => $json ?: $body,
                'payload' => $payload,
            ],
        ];
    }

    public function createPixChargeWithAnalysis(array $paymentRequest): array
    {
        $token = $this->getToken();

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Account-Id' => $this->accountId,
        ];

        Log::info('➡️ Enviando cobrança Pix para Nuvende', [
            'endpoint' => "{$this->apiUrl}/api/v2/cobranca/cob",
            'headers' => $headers,
            'payload' => $paymentRequest,
        ]);

        $response = Http::withHeaders($headers)
            ->post("{$this->apiUrl}/api/v2/cobranca/cob", $paymentRequest);

        Log::info('⬅️ Resposta bruta da Nuvende /cobranca/cob', [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json(),
            'headers' => $response->headers(),
        ]);

        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'body_raw' => $response->body(),
            'json_raw' => $response->json(),
            'headers' => $headers,
            'hint' => 'Veja o log completo para detalhes. Pode ser problema com account_id ou permissões.'
        ];
    }


    public function verifyCredentials(): array
    {
        $report = [
            'client_valid' => false,
            'account_matches' => false,
            'pix_key_valid' => false,
            'details' => null,
        ];

        try {
            $token = $this->getToken();
            $report['client_valid'] = true;

            $responseUser = Http::withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->post("{$this->apiUrl}/api/v2/auth/user");

            if ($responseUser->successful()) {
                $userData = $responseUser->json();
                if (isset($userData['account_id']) && $userData['account_id'] === $this->accountId) {
                    $report['account_matches'] = true;
                }
                $report['details']['user'] = $userData;
            } else {
                $report['details']['user_error'] = $responseUser->body();
            }

            $testPayload = [
                'calendario' => ['expiracao' => 60], 
                'devedor' => ['cpf' => '00000000000', 'nome' => 'Teste Pix'],
                'valor' => ['original' => '0.01'],
                'chave' => $this->pixKey,
                'solicitacaoPagador' => 'Teste de validação de chave Pix'
            ];

            $responsePix = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Account-Id' => $this->accountId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->apiUrl}/api/v2/cobranca/cob", $testPayload);

            if ($responsePix->successful()) {
                $report['pix_key_valid'] = true;
                $report['details']['pix_test'] = $responsePix->json();
            } else {
                $report['details']['pix_test_error'] = $responsePix->body();
            }

        } catch (\Throwable $e) {
            $report['details']['exception'] = $e->getMessage();
        }

        return $report;
    }

    public function diagnostic()
    {
        try {
            $report = $this->pixService->diagnostic();

            $accountReport = $this->pixService->getAccountIdFromClient();

            $envData = [
                'client_id_env' => env('NUVENDE_CLIENT_ID'),
                'account_id_env' => env('NUVENDE_ACCOUNT_ID'),
                'pix_key_env' => env('NUVENDE_PIX_KEY'),
            ];

            $sandboxData = [
                'client_id_sandbox' => $accountReport['client_id'] ?? null,
                'account_id_sandbox' => $accountReport['account_id'] ?? null,
                'matches_env' => isset($accountReport['account_id']) 
                                    ? ($accountReport['account_id'] === env('NUVENDE_ACCOUNT_ID')) 
                                    : false,
            ];

            $report['sandbox_data'] = $sandboxData;
            $report['env_data'] = $envData;
            $report['client_account_id'] = $accountReport['account_id'] ?? null;
            $report['client_id'] = $accountReport['client_id'] ?? null;

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro no diagnóstico Pix', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Falha ao executar diagnóstico',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function getAccountIdFromClient(): array
    {
        try {
            $token = $this->getToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->post("{$this->apiUrl}/api/v2/auth/user");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => $response->body(),
                ];
            }

            $data = $response->json();

            $sandboxAccountId = $data['account_id'] ?? null;

            $matches = $sandboxAccountId === $this->accountId;

            return [
                'success' => true,
                'client_id' => $data['client_id'] ?? null,
                'account_id' => $sandboxAccountId,
                'matches_env' => $matches,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getEnvAccountId(): string
    {
        return $this->accountId;
    }
    
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

 


}