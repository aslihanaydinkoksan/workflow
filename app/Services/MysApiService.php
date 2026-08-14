<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MysApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        // Değerleri config üzerinden güvenle alıyoruz
        $this->baseUrl = rtrim(config('services.mys.url'), '/');
        $this->apiKey = config('services.mys.api_key');
        $this->timeout = config('services.mys.timeout', 5);
    }

    /**
     * MYS'ye kimlik doğrulamalı (API Key) temel HTTP istemcisini oluşturur.
     * Kodu tekrardan (DRY - Don't Repeat Yourself) kurtarır.
     */
    protected function client()
    {
        return Http::timeout($this->timeout)->withHeaders([
            'X-App-Key' => $this->apiKey,
            'Accept'    => 'application/json',
        ]);
    }

    /**
     * MYS'den tüm kullanıcıları çeker.
     *
     * @return array
     * @throws Exception
     */
    public function fetchAllUsers(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/internal/users-all");

            if ($response->successful() && $response->json('success')) {
                return $response->json('users') ?? [];
            }

            throw new Exception("MYS API'den başarısız yanıt döndü veya 'success' false.");
        } catch (Exception $e) {
            // Hatayı logluyoruz ki sonradan "Neden çalışmadı?" dersek loglardan görebilelim.
            Log::error('MysApiService - fetchAllUsers Hatası: ' . $e->getMessage());

            // Controller'ın yakalayacağı temiz bir hata fırlatıyoruz.
            throw new Exception('KÖKSAN Merkezi Yönetim Sistemi (MYS) şu anda ulaşılamaz durumda.');
        }
    }

    /**
     * Tek bir kullanıcının detaylarını çeker (SyncPreview için).
     *
     * @param string $email
     * @param string|null $tcNo
     * @return array|null
     * @throws Exception
     */
    public function fetchUserDetails(string $email, ?string $tcNo = null): ?array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/internal/user-details", [
                'email' => $email,
                'tc_no' => $tcNo
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('user');
            }

            return null; // Kullanıcı bulunamadı
        } catch (Exception $e) {
            Log::error('MysApiService - fetchUserDetails Hatası: ' . $e->getMessage());
            throw new Exception('KÖKSAN Merkezi Yönetim Sistemi (MYS) şu anda ulaşılamaz durumda.');
        }
    }
    /**
     * MYS'den tüm direktörlükleri çeker.
     *
     * @return array
     * @throws Exception
     */
    public function fetchAllDirectorates(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/internal/directorates");

            if ($response->successful() && $response->json('success')) {
                return $response->json('directorates') ?? [];
            }

            throw new Exception("MYS API'den başarısız yanıt döndü veya 'success' false.");
        } catch (Exception $e) {
            Log::error('MysApiService - fetchAllDirectorates Hatası: ' . $e->getMessage());
            throw new Exception('Merkezi Yönetim Sistemi (MYS) şu anda ulaşılamaz durumda.');
        }
    }

    /**
     * MYS'den tüm departmanları çeker.
     *
     * @return array
     * @throws Exception
     */
    public function fetchAllDepartments(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/internal/departments");

            if ($response->successful() && $response->json('success')) {
                return $response->json('departments') ?? [];
            }

            throw new Exception("MYS API'den başarısız yanıt döndü veya 'success' false.");
        } catch (Exception $e) {
            Log::error('MysApiService - fetchAllDepartments Hatası: ' . $e->getMessage());
            throw new Exception('Merkezi Yönetim Sistemi (MYS) şu anda ulaşılamaz durumda.');
        }
    }
}
