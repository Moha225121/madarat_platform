<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArabicLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_returned_in_arabic(): void
    {
        $this->post('/register', [])
            ->assertSessionHasErrors([
                'name' => 'حقل الاسم مطلوب.',
                'email' => 'حقل البريد الإلكتروني مطلوب.',
                'password' => 'حقل كلمة المرور مطلوب.',
            ]);
    }
}
