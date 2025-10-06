<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NuvendeAuthService
{
    
    public function getToken()
    {
        return Cache::remember('nuvende_token', 3500, function () {
            $clientId = env('NUVENDE_CLIENT_ID');
            $clientSecret = env('NUVENDE_CLIENT_SECRET');

            if (!$clientId || !$clientSecret) {
                throw new \Exception('Client ID ou Client Secret não configurados no .env');
            }

            $response = Http::withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode("$clientId:$clientSecret")
                ])
                ->asForm()
                ->post(env('NUVENDE_API_URL') . '/api/v2/auth/login', [
                    'grant_type' => 'client_credentials',
                    'scope' => 'cob.write cob.read'
                ]);

            if ($response->successful()) {
                return $response['access_token'];
            }

            throw new \Exception('Falha ao autenticar na Nuvende: ' . $response->body());
        });
    }
    
}