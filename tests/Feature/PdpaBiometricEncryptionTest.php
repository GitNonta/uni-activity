<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PdpaBiometricEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_descriptors_are_encrypted_at_rest_in_database(): void
    {
        $descriptor512 = array_fill(0, 512, 0.045);
        $descriptor128 = array_fill(0, 128, 0.088);

        $user = User::factory()->create([
            'student_id'         => 'PDPA_TEST_01',
            'face_descriptor'    => $descriptor512,
            'face_descriptor_js' => $descriptor128,
        ]);

        // 1. Fetch raw column directly from DB (bypassing Eloquent casts)
        $rawUser = DB::table('users')->where('id', $user->id)->first();
        
        $this->assertNotNull($rawUser->face_descriptor);
        $this->assertNotNull($rawUser->face_descriptor_js);

        // Ensure raw database column does NOT contain plaintext JSON
        $this->assertStringStartsNotWith('[', (string)$rawUser->face_descriptor);
        $this->assertStringStartsNotWith('{', (string)$rawUser->face_descriptor);
        $this->assertStringStartsNotWith('[', (string)$rawUser->face_descriptor_js);
        $this->assertStringStartsNotWith('{', (string)$rawUser->face_descriptor_js);

        // Ensure raw database value looks like Laravel encrypted payload (base64 payload with iv, value, mac)
        $payload = json_decode(base64_decode((string)$rawUser->face_descriptor), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('value', $payload);
        $this->assertArrayHasKey('mac', $payload);

        // 2. Access via Eloquent model (transparent decryption)
        $freshUser = User::find($user->id);
        $this->assertIsArray($freshUser->face_descriptor);
        $this->assertCount(512, $freshUser->face_descriptor);
        $this->assertEquals(0.045, $freshUser->face_descriptor[0]);

        $this->assertIsArray($freshUser->face_descriptor_js);
        $this->assertCount(128, $freshUser->face_descriptor_js);
        $this->assertEquals(0.088, $freshUser->face_descriptor_js[0]);
    }

    public function test_biometric_data_is_hidden_from_json_and_array_serialization(): void
    {
        $user = User::factory()->create([
            'student_id'         => 'PDPA_TEST_02',
            'face_descriptor'    => array_fill(0, 512, 0.01),
            'face_descriptor_js' => array_fill(0, 128, 0.02),
        ]);

        $array = $user->toArray();
        $this->assertArrayNotHasKey('face_descriptor', $array);
        $this->assertArrayNotHasKey('face_descriptor_js', $array);

        $json = $user->toJson();
        $this->assertStringNotContainsString('face_descriptor', $json);
        $this->assertStringNotContainsString('face_descriptor_js', $json);
    }
}
