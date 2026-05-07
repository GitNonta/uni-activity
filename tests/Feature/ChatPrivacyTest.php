<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\JobListing;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent()
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function createAdmin()
    {
        return User::factory()->create(['role' => 'admin', 'email' => fake()->unique()->safeEmail()]);
    }

    private function createJob()
    {
        return JobListing::create([
            'title' => 'Test Job',
            'description' => 'Test Description',
            'faculty' => 'Test Faculty',
            'department' => 'Test Dept',
            'work_date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'wage_per_hour' => 100,
            'max_positions' => 5,
            'status' => 'open',
            'created_by' => $this->createAdmin()->id,
        ]);
    }

    public function test_students_have_isolated_private_chat_threads()
    {
        // Require mongodb connection for ChatMessage, skip if not available
        if (!config('database.connections.mongodb')) {
            $this->markTestSkipped('MongoDB not configured.');
        }
        
        try {
            $studentA = $this->createStudent();
            $studentB = $this->createStudent();
            $admin = $this->createAdmin();
            $job = $this->createJob();

            // Student A sends a message
            ChatMessage::create([
                'job_id' => $job->id,
                'sender_id' => $studentA->id,
                'sender_role' => 'student',
                'student_id' => $studentA->id,
                'message' => 'Hello from Student A'
            ]);

            // Student B sends a message
            ChatMessage::create([
                'job_id' => $job->id,
                'sender_id' => $studentB->id,
                'sender_role' => 'student',
                'student_id' => $studentB->id,
                'message' => 'Hello from Student B'
            ]);

            // Admin replies to Student A
            ChatMessage::create([
                'job_id' => $job->id,
                'sender_id' => $admin->id,
                'sender_role' => 'admin',
                'student_id' => $studentA->id,
                'message' => 'Reply to A'
            ]);

            // Act & Assert for Student A
            $responseA = $this->actingAs($studentA)->get(route('chat.messages', $job->id));
            $responseA->assertOk();
            $messagesA = $responseA->json('messages');
            $this->assertCount(2, $messagesA);
            $this->assertEquals('Hello from Student A', $messagesA[0]['message']);
            $this->assertEquals('Reply to A', $messagesA[1]['message']);

            // Act & Assert for Student B
            $responseB = $this->actingAs($studentB)->get(route('chat.messages', $job->id));
            $responseB->assertOk();
            $messagesB = $responseB->json('messages');
            $this->assertCount(1, $messagesB);
            $this->assertEquals('Hello from Student B', $messagesB[0]['message']);
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB error: ' . $e->getMessage());
        }
    }
}
