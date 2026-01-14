<?php

namespace Tests\Feature;

use App\Models\SharedNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_note()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')->post(route('shared-notes.store'), [
            'title' => 'Test Note',
            'content' => 'This is a test note.',
            'color' => 'yellow',
            'priority' => 'medium',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('shared_notes', [
            'title' => 'Test Note',
            'author_id' => $user->id,
        ]);
    }

    public function test_note_creation_requires_title()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')->post(route('shared-notes.store'), [
            'content' => 'This note has no title.',
        ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_authenticated_user_can_update_note()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $note = SharedNote::create([
            'title' => 'Original Title',
            'content' => 'Original Content',
            'author_id' => $user->id,
            'color' => 'yellow',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($user, 'web')->put(route('shared-notes.update', $note), [
            'title' => 'Updated Title',
            'content' => 'Updated Content',
            'color' => 'blue',
            'priority' => 'high',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('shared_notes', [
            'note_id' => $note->note_id,
            'title' => 'Updated Title',
            'content' => 'Updated Content',
            'color' => 'blue',
            'priority' => 'high',
        ]);
    }

    public function test_note_update_requires_title()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $note = SharedNote::create([
            'title' => 'Original Title',
            'content' => 'Original Content',
            'author_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'web')->put(route('shared-notes.update', $note), [
            'title' => '',
            'content' => 'Updated Content',
        ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_invalid_color_is_rejected()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')->post(route('shared-notes.store'), [
            'title' => 'Test Note',
            'color' => 'rainbow', // Invalid color
        ]);

        $response->assertSessionHasErrors(['color']);
    }
}
