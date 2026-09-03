<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceDiagramTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_view_student_activity_sequence_diagram(): void
    {
        $response = $this->get(route('sequence.diagram'));

        $response->assertOk()
            ->assertSee('Student')
            ->assertSee('Activity Pages')
            ->assertSee('Participation')
            ->assertSee('Login Page')
            ->assertSee('ขอพรีรีจิสเตอร์')
            ->assertSee('ผู้ใช้ยังไม่ได้ล็อกอิน');
    }
}
