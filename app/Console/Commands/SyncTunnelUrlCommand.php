<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTunnelUrlCommand extends Command
{
    protected $signature   = 'tunnel:sync {--url= : กำหนด URL เองโดยตรงแทนการอ่านจาก docs/active_url.json}';
    protected $description = 'ซิงค์ APP_URL ใน .env ให้ตรงกับ Cloudflare Tunnel ล่าสุดจาก docs/active_url.json';

    public function handle(): int
    {
        $newUrl = (string) $this->option('url');

        if (empty($newUrl)) {
            $jsonPath = base_path('docs/active_url.json');
            if (File::exists($jsonPath)) {
                $data = json_decode(File::get($jsonPath), true);
                $newUrl = (string) ($data['url'] ?? '');
            }
        }

        if (empty($newUrl)) {
            $this->error('❌ ไม่พบ Cloudflare Tunnel URL ใน docs/active_url.json');
            return self::FAILURE;
        }

        $newUrl = rtrim($newUrl, '/');
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('❌ ไม่พบไฟล์ .env');
            return self::FAILURE;
        }

        $envContent = File::get($envPath);
        $oldUrl = (string) config('app.url');

        if (preg_match('/^APP_URL=(.*)$/m', $envContent, $matches)) {
            $envContent = preg_replace('/^APP_URL=.*$/m', "APP_URL={$newUrl}", $envContent);
        } else {
            $envContent .= "\nAPP_URL={$newUrl}\n";
        }

        File::put($envPath, $envContent);

        $this->info("✅ ซิงค์ Cloudflare Tunnel สำเร็จ!");
        $this->line("   - URL เดิม: {$oldUrl}");
        $this->line("   - URL ใหม่: <fg=green;options=bold>{$newUrl}</>");

        $this->call('config:clear');

        return self::SUCCESS;
    }
}
