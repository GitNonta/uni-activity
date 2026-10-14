<?php

declare(strict_types=1);

namespace App\Services\PromptPay;

use InvalidArgumentException;

/**
 * ระบบสร้าง payload ของ PromptPay QR ตามมาตรฐานธนาคารแห่งประเทศไทย (EMVCo).
 */
class PromptPayService
{
    public function payload(string $receiver, ?float $amount = null): string
    {
        $r = preg_replace('/[\s-]/', '', trim($receiver));

        if (preg_match('/^0\d{8,9}$/', $r)) {
            $r = '66'.substr($r, 1);
        } elseif (preg_match('/^\d{13}$/', $r)) {
            // เลขบัตรประชาชน 13 หลัก
        } elseif (preg_match('/^\d{15}$/', $r)) {
            $r = '0'.substr($r, 0, 15);
        } else {
            throw new InvalidArgumentException('Invalid PromptPay receiver (ต้องเป็นเบอร์ 09…, เลขบัตร 13 หลัก หรือเลขนิติบุคคล 15 หลัก)');
        }

        $tag29A = $this->f('29', $this->f('00', 'A0000007770101').$this->f('01', $r));

        $payload = $this->f('00', '01')
            .$this->f('01', '12')
            .$tag29A
            .($amount !== null && $amount > 0 ? $this->f('54', number_format($amount, 2, '.', '')) : '')
            .$this->f('53', '764')
            .$this->f('58', 'TH')
            .'6304';

        return $payload.strtoupper(sprintf('%04X', $this->crc16($payload)));
    }

    private function f(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function crc16(string $data): int
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $ch) {
            $crc ^= ord($ch) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return $crc & 0xFFFF;
    }
}
