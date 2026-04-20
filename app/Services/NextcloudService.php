<?php

namespace App\Services;

use App\Models\Setting;

class NextcloudService
{
    private string $baseUrl;
    private string $user;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim(Setting::get('nextcloud.url', ''), '/');
        $this->user     = Setting::get('nextcloud.service_user', '');
        $this->password = Setting::get('nextcloud.service_password', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->user) && !empty($this->password);
    }

    public function get(string $path): \Illuminate\Http\Client\Response
    {
        return \Http::withBasicAuth($this->user, $this->password)
            ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])
            ->get($this->url($path));
    }

    public function post(string $path, array $data = []): \Illuminate\Http\Client\Response
    {
        return \Http::withBasicAuth($this->user, $this->password)
            ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])
            ->post($this->url($path), $data);
    }

    public function put(string $path, array $data = []): \Illuminate\Http\Client\Response
    {
        return \Http::withBasicAuth($this->user, $this->password)
            ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])
            ->put($this->url($path), $data);
    }

    public function delete(string $path): \Illuminate\Http\Client\Response
    {
        return \Http::withBasicAuth($this->user, $this->password)
            ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])
            ->delete($this->url($path));
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/ocs/v1.php/' . ltrim($path, '/');
    }
}
