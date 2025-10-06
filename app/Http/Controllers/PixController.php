<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use App\Services\NuvendePixService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PixController extends Controller
{
    protected NuvendePixService $pixService;

    public function __construct(NuvendePixService $pixService)
    {
        $this->pixService = $pixService;
    }

    public function create(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payer_name' => 'nullable|string',
            'cpf' => 'nullable|string',
            'external_id' => 'nullable|string',
        ]);

        try {
            $payload = [
                'calendario' => ['expiracao' => 3600],
                'devedor' => [
                    'cpf' => $request->cpf ?? '00000000000',
                    'nome' => $request->payer_name ?? 'Cliente Teste',
                ],
                'valor' => ['original' => number_format($request->amount, 2, '.', '')],
                'chave' => env('NUVENDE_PIX_KEY'),
                'solicitacaoPagador' => 'Pedido ' . ($request->external_id ?? uniqid()),
            ];

            $result = $this->pixService->createPixChargeWithAnalysis($payload);

          if (!$result['ok']) {
            Log::error('Erro ao criar cobrança Pix', ['result' => $result]);

            $userMessage = "❌ Falha ao criar cobrança Pix";

            $errorDetails = [
                'Erro' => $result['json_raw']['error'] ?? 'Erro desconhecido',
                'Status HTTP' => $result['status'] ?? 'N/A',
                'Dica' => "Verifique se o account_id, token e permissões estão corretos",
                'Headers enviados' => $result['headers'] ?? [],
                'Payload completo' => $result['body_raw'] ?? $result,
            ];

            return response()->json([
                'success' => false,
                'mensagem' => $userMessage,
                'detalhes' => $errorDetails,
            ], 400);
        }



            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro inesperado ao criar cobrança Pix',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkCredentials()
    {
        try {
            $result = $this->pixService->verifyCredentials();

            return response()->json([
                'success' => true,
                'report' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar credenciais',
                'details' => $e->getMessage(),
            ], 500);
        }
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
            $tokenReport = $this->pixService->diagnostic();
            Log::info('Diagnóstico completo', $tokenReport);


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
    
    public function showForm()
    {
        return view('pix.form'); 
    }

    public function showTokens()
    {
        try {
            $envData = [
                'client_id_env' => env('NUVENDE_CLIENT_ID'),
                'account_id_env' => env('NUVENDE_ACCOUNT_ID'),
                'pix_key_env' => env('NUVENDE_PIX_KEY'),
            ];

            $token = $this->pixService->getToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->post("{$this->pixService->getApiUrl()}/api/v2/auth/user");


            $sandboxData = $response->successful() ? $response->json() : [];

            Log::info('Diagnóstico de tokens', [
                'env_data' => $envData,
                'sandbox_data' => $sandboxData,
            ]);

            return response()->json([
                'success' => true,
                'env_data' => $envData,
                'sandbox_data' => $sandboxData,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar tokens e account_id', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Falha ao buscar tokens',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

}