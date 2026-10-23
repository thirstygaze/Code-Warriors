<?php

namespace Tests\Feature;

use App\Models\CanteenNotification;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StaffOrder;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CanteenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_is_public_and_student_can_only_view_their_own_orders(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $ownOrder = $this->makeOrder($student);
        $this->makeOrder($otherStudent);

        $this->getJson('/api/menu')->assertOk()->assertJsonStructure(['data']);

        Sanctum::actingAs($student);
        $this->getJson('/api/student/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_id', $ownOrder->order_id)
            ->assertJsonCount(1, 'data');
    }

    public function test_api_login_checks_the_database_role_and_password(): void
    {
        User::factory()->create([
            'email' => 'student@hnu.edu.ph',
            'password' => Hash::make('correct-password'),
            'role' => 'student',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'student@hnu.edu.ph',
            'password' => 'correct-password',
            'role' => 'admin',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'student@hnu.edu.ph',
            'password' => 'wrong-password',
            'role' => 'student',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'student@hnu.edu.ph',
            'password' => 'correct-password',
            'role' => 'student',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_student_can_register_and_is_signed_in(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Taylor Student',
            'email' => 'taylor@hnu.edu.ph',
            'student_number' => 'HNU-2026-001',
            'grade_level' => 'Grade 12',
            'password' => 'student-password-123',
            'password_confirmation' => 'student-password-123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.role', 'student');

        $userId = $response->json('user.id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'taylor@hnu.edu.ph',
            'role' => 'student',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('students', [
            'user_id' => $userId,
            'student_number' => 'HNU-2026-001',
            'grade_level' => 'Grade 12',
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $userId);
    }

    public function test_student_registration_rejects_duplicate_email_or_student_number(): void
    {
        $existingUser = User::factory()->create(['email' => 'existing@hnu.edu.ph']);
        $existingUser->studentProfile()->create([
            'student_number' => 'HNU-2026-002',
            'grade_level' => null,
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Another Student',
            'email' => 'existing@hnu.edu.ph',
            'student_number' => 'HNU-2026-002',
            'password' => 'student-password-123',
            'password_confirmation' => 'student-password-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'student_number']);
    }

    public function test_password_reset_is_non_enumerating_and_revokes_existing_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'student@hnu.edu.ph',
            'password' => Hash::make('old-password-123'),
            'role' => 'student',
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@hnu.edu.ph'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
        Notification::assertSentTo($user, ResetPassword::class);

        $user->createToken('old-session');
        $resetToken = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
            'role' => 'student',
        ])->assertOk();
    }

    public function test_all_account_roles_can_change_their_own_password(): void
    {
        foreach (['student', 'staff', 'admin'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'password' => Hash::make('old-password-123'),
            ]);
            $currentToken = $user->createToken('current-session');
            $user->createToken('other-session');
            Auth::forgetGuards();
            $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
            $this->withToken($currentToken->plainTextToken)->getJson('/api/auth/me')
                ->assertOk()
                ->assertJsonPath('user.id', $user->id);

            $this->withToken($currentToken->plainTextToken)->postJson('/api/auth/change-password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])->assertOk()
                ->assertJsonPath('message', 'Your password has been changed successfully.');

            $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => $user->id,
                'name' => 'other-session',
            ]);
        }
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_change_requires_authentication(): void
    {
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnauthorized();
    }

    public function test_role_middleware_rejects_students_from_staff_and_admin_routes(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/staff/orders')->assertForbidden();
        $this->getJson('/api/admin/reports')->assertForbidden();
        $this->getJson('/api/student/orders')->assertOk();
    }

    public function test_staff_can_process_orders_but_cannot_manage_school_users(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student);
        $order->payment()->update(['payment_status' => 'paid']);
        Sanctum::actingAs($staff);

        $this->patchJson("/api/staff/orders/{$order->order_id}/status", ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');
        $this->patchJson("/api/staff/orders/{$order->order_id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Refund paid orders in PayMongo before cancelling them.');
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_staff_can_view_categories_and_create_menu_items(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $staff->staffProfile()->create(['stall_name' => 'Taylor’s Tasty Stall']);
        $category = Category::create(['category_name' => 'Meals']);

        Sanctum::actingAs($staff);

        $this->getJson('/api/staff/categories')
            ->assertOk()
            ->assertJsonPath('data.0.category_id', $category->category_id)
            ->assertJsonPath('data.0.category_name', 'Meals');

        $this->postJson('/api/staff/menu-items', [
            'category_id' => $category->category_id,
            'item_name' => 'Chicken rice',
            'description' => 'Rice bowl with chicken.',
            'price' => 65,
            'quantity' => 12,
        ])->assertCreated()
            ->assertJsonPath('data.item_name', 'Chicken rice')
            ->assertJsonPath('data.owner_staff_id', $staff->id)
            ->assertJsonPath('data.staff.stall_name', 'Taylor’s Tasty Stall')
            ->assertJsonPath('data.inventory.quantity', 12);
    }

    public function test_staff_menu_and_inventory_are_isolated_and_other_staff_cannot_modify_items(): void
    {
        $firstStaff = User::factory()->create(['role' => 'staff']);
        $secondStaff = User::factory()->create(['role' => 'staff']);
        $firstStaff->staffProfile()->create(['stall_name' => 'First Stall']);
        $secondStaff->staffProfile()->create(['stall_name' => 'Second Stall']);
        $category = Category::create(['category_name' => 'Meals']);
        $firstItem = MenuItem::create(['category_id' => $category->category_id, 'item_name' => 'First dish', 'price' => 50, 'owner_staff_id' => $firstStaff->id]);
        $firstItem->inventory()->create(['quantity' => 5]);
        $secondItem = MenuItem::create(['category_id' => $category->category_id, 'item_name' => 'Second dish', 'price' => 60, 'owner_staff_id' => $secondStaff->id]);
        $secondItem->inventory()->create(['quantity' => 8]);

        Sanctum::actingAs($firstStaff);

        $this->getJson('/api/staff/menu-items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'First dish');
        $this->getJson('/api/staff/inventory')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_id', $firstItem->item_id);

        $this->patchJson("/api/staff/menu-items/{$firstItem->item_id}", [
            'item_name' => 'Updated first dish',
            'description' => 'Updated description.',
            'price' => 55,
            'category_id' => $category->category_id,
            'image_url' => 'https://example.com/first-dish.jpg',
        ])->assertOk()
            ->assertJsonPath('data.item_name', 'Updated first dish')
            ->assertJsonPath('data.description', 'Updated description.')
            ->assertJsonPath('data.price', '55.00');

        $this->patchJson("/api/staff/menu-items/{$secondItem->item_id}", ['availability' => false])->assertNotFound();
        $this->putJson("/api/staff/inventory/{$secondItem->item_id}", ['quantity' => 0])->assertNotFound();
        $this->assertDatabaseHas('menu_items', [
            'item_id' => $firstItem->item_id,
            'item_name' => 'Updated first dish',
            'price' => '55.00',
        ]);
        $this->assertDatabaseHas('menu_items', ['item_id' => $secondItem->item_id, 'item_name' => 'Second dish']);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['stall_name' => 'First Stall'])
            ->assertJsonFragment(['stall_name' => 'Second Stall']);
    }

    public function test_staff_order_queue_only_contains_paid_orders(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $paidOrder = $this->makeOrder($student);
        $paidOrder->payment()->update(['payment_status' => 'paid']);
        $this->makeOrder($student);
        Sanctum::actingAs($staff);

        $this->getJson('/api/staff/orders')
            ->assertOk()
            ->assertJsonPath('data.data.0.order_id', $paidOrder->order_id)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_student_checkout_reserves_inventory_and_creates_order_items(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        Http::fake([
            'https://api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_order',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/test-order',
                        'payment_intent' => ['id' => 'pi_test_order'],
                    ],
                ],
            ], 201),
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['category_name' => 'Meals']);
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Test lunch',
            'price' => 50,
            'availability' => true,
        ]);
        Inventory::create(['item_id' => $item->item_id, 'quantity' => 4, 'reorder_level' => 1]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/student/orders', [
            'pickup_time' => '12:30',
            'items' => [['item_id' => $item->item_id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', '100.00')
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/test-order')
            ->assertJsonPath('data.payment.payment_status', 'pending');

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'item_id' => $item->item_id, 'quantity' => 2]);
        $this->assertDatabaseHas('inventory', ['item_id' => $item->item_id, 'quantity' => 2]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'payment_status' => 'pending',
            'provider_checkout_id' => 'cs_test_order',
            'provider_payment_intent_id' => 'pi_test_order',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
            && $request['data']['attributes']['line_items'][0]['amount'] === 5000
            && $request['data']['attributes']['line_items'][0]['currency'] === 'PHP'
            && $request['data']['attributes']['reference_number'] === $response->json('data.payment.reference_number'));
    }

    public function test_student_can_place_cash_order_without_creating_paymongo_checkout(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['category_name' => 'Meals']);
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Cash lunch',
            'price' => 45,
            'availability' => true,
        ]);
        Inventory::create(['item_id' => $item->item_id, 'quantity' => 3, 'reorder_level' => 1]);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/student/orders', [
            'pickup_time' => '12:30',
            'payment_method' => 'cash',
            'items' => [['item_id' => $item->item_id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('message', 'Order saved. Pay cash at pickup; staff will confirm the payment before preparation.')
            ->assertJsonPath('data.payment.payment_provider', 'cash')
            ->assertJsonPath('data.payment.payment_method', 'cash')
            ->assertJsonPath('data.payment.payment_status', 'pending');

        Http::assertNothingSent();
        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('inventory', ['item_id' => $item->item_id, 'quantity' => 2]);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'payment_provider' => 'cash', 'payment_status' => 'pending']);
    }

    public function test_staff_must_confirm_cash_before_order_can_be_prepared(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student);
        $order->payment()->update([
            'payment_provider' => 'cash',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/staff/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.payment.payment_provider', 'cash')
            ->assertJsonPath('data.data.0.payment.payment_status', 'pending');

        $this->patchJson("/api/staff/orders/{$order->order_id}/status", ['status' => 'preparing'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only paid orders can be prepared.');

        $this->postJson("/api/staff/orders/{$order->order_id}/cash-payment")
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'paid');
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->order_id,
            'payment_provider' => 'cash',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'order_id' => $order->order_id,
        ]);

        $this->patchJson("/api/staff/orders/{$order->order_id}/status", ['status' => 'preparing'])->assertOk();
    }

    public function test_checkout_creation_failure_marks_order_failed_and_releases_inventory(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        Http::fake(['https://api.paymongo.com/v2/checkout_sessions' => Http::response(['errors' => []], 422)]);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['category_name' => 'Meals']);
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Test lunch',
            'price' => 50,
            'availability' => true,
        ]);
        Inventory::create(['item_id' => $item->item_id, 'quantity' => 4, 'reorder_level' => 1]);
        Sanctum::actingAs($student);

        $this->postJson('/api/student/orders', [
            'pickup_time' => '12:30',
            'items' => [['item_id' => $item->item_id, 'quantity' => 2]],
        ])->assertStatus(502);

        $this->assertDatabaseHas('orders', ['user_id' => $student->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('payments', ['payment_status' => 'failed']);
        $this->assertDatabaseHas('inventory', ['item_id' => $item->item_id, 'quantity' => 4]);
    }

    public function test_paymongo_paid_webhook_is_verified_and_idempotent(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsk_test_example');
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->makeOrder($student);
        $payment = $order->payment;
        $payment->update(['provider_checkout_id' => 'cs_test_order']);
        $payload = [
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => [
                    'id' => 'cs_test_order',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => $payment->reference_number,
                        'payment_intent' => ['id' => 'pi_test_order'],
                        'payments' => [[
                            'attributes' => ['source' => ['type' => 'gcash']],
                        ]],
                    ],
                ],
            ],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_test_example');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ];

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], $headers, $body)->assertOk();

        $this->assertDatabaseHas('payments', [
            'payment_id' => $payment->payment_id,
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
            'provider_payment_intent_id' => 'pi_test_order',
        ]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'order_id' => $order->order_id,
        ]);
    }

    public function test_paymongo_nested_event_envelope_marks_checkout_paid(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsk_test_example');
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->makeOrder($student);
        $payment = $order->payment;
        $payment->update(['provider_checkout_id' => 'cs_test_nested_order']);
        $payload = [
            'data' => [
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'cs_test_nested_order',
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $payment->reference_number,
                            'metadata' => ['order_id' => (string) $order->order_id],
                            'payment_intent' => ['id' => 'pi_test_nested_order'],
                            'payments' => [[
                                'attributes' => ['source' => ['type' => 'gcash']],
                            ]],
                        ],
                    ],
                ],
            ],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_test_example');

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ], $body)->assertOk()->assertJsonPath('received', true);

        $this->assertDatabaseHas('payments', [
            'payment_id' => $payment->payment_id,
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
            'provider_payment_intent_id' => 'pi_test_nested_order',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'order_id' => $order->order_id,
        ]);
    }

    public function test_paymongo_webhook_rejects_invalid_signatures(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsk_test_example');

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't='.now()->timestamp.',te=invalid',
        ], '{"data":{}}')->assertUnauthorized();
    }

    public function test_paymongo_webhook_accepts_standard_signature_header_format(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsk_test_example');
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $order = $this->makeOrder($student);
        $payment = $order->payment;
        $payment->update(['provider_checkout_id' => 'cs_test_order']);
        $payload = [
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => [
                    'id' => 'cs_test_order',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => $payment->reference_number,
                        'payment_intent' => ['id' => 'pi_test_order'],
                        'payments' => [[
                            'attributes' => ['source' => ['type' => 'gcash']],
                        ]],
                    ],
                ],
            ],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_test_example');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ];

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], $headers, $body)
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'payment_id' => $payment->payment_id,
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
            'provider_payment_intent_id' => 'pi_test_order',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'order_id' => $order->order_id,
        ]);
    }

    public function test_admin_role_changes_keep_student_and_staff_profiles_in_sync(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}", ['is_active' => true])->assertOk();

        $this->patchJson("/api/admin/users/{$student->id}", [
            'role' => 'staff',
            'employee_number' => 'EMP-100',
            'position' => 'Canteen staff',
            'stall_name' => 'Campus Bites',
        ])->assertOk()->assertJsonPath('data.role', 'staff');
        $this->assertDatabaseMissing('students', ['user_id' => $student->id]);
        $this->assertDatabaseHas('staff', ['user_id' => $student->id, 'employee_number' => 'EMP-100', 'stall_name' => 'Campus Bites']);
        $this->patchJson("/api/admin/users/{$student->id}", ['stall_name' => 'Campus Bites Plus'])
            ->assertOk()
            ->assertJsonPath('data.staff_profile.stall_name', 'Campus Bites Plus');
        $this->assertDatabaseHas('staff', ['user_id' => $student->id, 'stall_name' => 'Campus Bites Plus']);

        $this->patchJson("/api/admin/users/{$student->id}", [
            'role' => 'student',
            'student_number' => '2026-100',
            'grade_level' => 'Grade 12',
        ])->assertOk()->assertJsonPath('data.role', 'student');
        $this->assertDatabaseHas('students', ['user_id' => $student->id, 'student_number' => '2026-100']);
        $this->assertDatabaseMissing('staff', ['user_id' => $student->id]);
    }

    public function test_admin_can_deactivate_and_reactivate_an_account_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$student->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);

        $this->patchJson("/api/admin/users/{$student->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => true]);
    }

    public function test_admin_can_permanently_delete_an_account_while_preserving_order_and_payment_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student);
        $token = $student->createToken('student-session');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$student->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Account permanently deleted.');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseHas('orders', ['order_id' => $order->order_id, 'user_id' => null]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->order_id, 'payment_status' => 'pending']);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_admin_cannot_permanently_delete_its_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot permanently delete your own administrator account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_deleting_staff_keeps_staff_order_audit_with_anonymized_staff_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student);
        $staffOrder = StaffOrder::create([
            'staff_id' => $staff->id,
            'order_id' => $order->order_id,
            'assigned_at' => now(),
            'action' => 'preparing',
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$staff->id}")->assertOk();

        $this->assertDatabaseHas('staff_orders', ['staff_order_id' => $staffOrder->staff_order_id, 'staff_id' => null]);
    }

    public function test_staff_cancellation_restores_reserved_inventory(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::create(['category_name' => 'Meals']);
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Test lunch',
            'price' => 50,
            'availability' => true,
        ]);
        Inventory::create(['item_id' => $item->item_id, 'quantity' => 3, 'reorder_level' => 1]);
        $order = $this->makeOrder($student);
        OrderItem::create([
            'order_id' => $order->order_id,
            'item_id' => $item->item_id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);
        Sanctum::actingAs($staff);

        $this->patchJson("/api/staff/orders/{$order->order_id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('inventory', ['item_id' => $item->item_id, 'quantity' => 5]);
    }

    public function test_student_notifications_are_scoped_to_the_authenticated_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $ownNotification = CanteenNotification::create([
            'user_id' => $student->id,
            'message' => 'Your order is ready.',
        ]);
        CanteenNotification::create([
            'user_id' => $otherStudent->id,
            'message' => 'Another student’s order is ready.',
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/student/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.notification_id', $ownNotification->notification_id)
            ->assertJsonCount(1, 'data.data');
    }

    private function makeOrder(User $student): Order
    {
        $order = Order::create([
            'user_id' => $student->id,
            'order_date' => today(),
            'pickup_time' => '12:00',
            'status' => 'pending',
            'total_amount' => 50,
        ]);

        $order->payment()->create([
            'payment_provider' => 'paymongo',
            'amount' => 50,
            'payment_status' => 'pending',
            'reference_number' => 'SB-'.str_pad((string) $order->order_id, 10, '0', STR_PAD_LEFT),
        ]);

        return $order->fresh('payment');
    }
}
