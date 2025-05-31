<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Wallet\Exceptions\InsufficientKillPointsException;
use App\Modules\Wallet\Exceptions\InsufficientPointsException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /** @test */
    public function unauthenticated_user_cannot_access_wallet_endpoints()
    {
        $this->getJson('/wallet/balance')
            ->assertStatus(401);

        $this->getJson('/wallet/transactions')
            ->assertStatus(401);

        $this->postJson('/wallet/add-points')
            ->assertStatus(401);

        $this->postJson('/wallet/spend-points')
            ->assertStatus(401);

        $this->postJson('/wallet/transfer')
            ->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_get_wallet_balance()
    {
        $this->actingAs($this->user)
            ->getJson('/wallet/balance')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'points_balance',
                    'kill_points_balance',
                    'total_points_earned',
                    'total_points_spent',
                    'total_kill_points_earned',
                    'total_kill_points_spent',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'points_balance' => 0,
                    'kill_points_balance' => 0,
                    'total_points_earned' => 0,
                    'total_points_spent' => 0,
                    'total_kill_points_earned' => 0,
                    'total_kill_points_spent' => 0,
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_can_get_transaction_history()
    {
        // Create some transactions
        $wallet = Wallet::factory()->create(['user_id' => $this->user->id]);
        WalletTransaction::factory()->count(3)->create(['wallet_id' => $wallet->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->getJson('/wallet/transactions')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'transaction_type',
                            'amount',
                            'description',
                            'created_at',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function authenticated_user_can_add_points()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/add-points', [
                'amount' => 100,
                'description' => 'Test points addition',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction' => [
                        'id',
                        'transaction_type',
                        'amount',
                        'description',
                    ],
                    'new_balance' => [
                        'points_balance',
                        'kill_points_balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Points added successfully',
                'data' => [
                    'transaction' => [
                        'amount' => 100,
                        'description' => 'Test points addition',
                    ],
                    'new_balance' => [
                        'points_balance' => 100,
                        'kill_points_balance' => 0,
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_can_add_kill_points()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/add-kill-points', [
                'amount' => 50,
                'description' => 'Test kill points addition',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction' => [
                        'id',
                        'transaction_type',
                        'amount',
                        'description',
                    ],
                    'new_balance' => [
                        'points_balance',
                        'kill_points_balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Kill points added successfully',
                'data' => [
                    'transaction' => [
                        'amount' => 50,
                        'description' => 'Test kill points addition',
                    ],
                    'new_balance' => [
                        'points_balance' => 0,
                        'kill_points_balance' => 50,
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_can_spend_points()
    {
        // First add some points
        $wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'points_balance' => 100]);

        $this->actingAs($this->user)
            ->postJson('/wallet/spend-points', [
                'amount' => 30,
                'description' => 'Test points spending',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction' => [
                        'id',
                        'transaction_type',
                        'amount',
                        'description',
                    ],
                    'new_balance' => [
                        'points_balance',
                        'kill_points_balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Points spent successfully',
                'data' => [
                    'transaction' => [
                        'amount' => 30,
                        'description' => 'Test points spending',
                    ],
                    'new_balance' => [
                        'points_balance' => 70,
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_can_spend_kill_points()
    {
        // First add some kill points
        $wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'kill_points_balance' => 50]);

        $this->actingAs($this->user)
            ->postJson('/wallet/spend-kill-points', [
                'amount' => 20,
                'description' => 'Test kill points spending',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction' => [
                        'id',
                        'transaction_type',
                        'amount',
                        'description',
                    ],
                    'new_balance' => [
                        'points_balance',
                        'kill_points_balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Kill points spent successfully',
                'data' => [
                    'transaction' => [
                        'amount' => 20,
                        'description' => 'Test kill points spending',
                    ],
                    'new_balance' => [
                        'kill_points_balance' => 30,
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_cannot_spend_more_points_than_available()
    {
        // User has 0 points by default
        $this->actingAs($this->user)
            ->postJson('/wallet/spend-points', [
                'amount' => 100,
                'description' => 'Test insufficient points',
            ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient points. Required: 100, Available: 0',
            ]);
    }

    /** @test */
    public function authenticated_user_cannot_spend_more_kill_points_than_available()
    {
        // User has 0 kill points by default
        $this->actingAs($this->user)
            ->postJson('/wallet/spend-kill-points', [
                'amount' => 100,
                'description' => 'Test insufficient kill points',
            ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient kill points. Required: 100, Available: 0',
            ]);
    }

    /** @test */
    public function authenticated_user_can_transfer_points()
    {
        // First add some points to sender
        $wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'points_balance' => 100]);

        $this->actingAs($this->user)
            ->postJson('/wallet/transfer', [
                'recipient_id' => $this->otherUser->id,
                'amount' => 50,
                'description' => 'Test transfer',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'spend_transaction' => [
                        'id',
                        'amount',
                        'description',
                    ],
                    'add_transaction' => [
                        'id',
                        'amount',
                        'description',
                    ],
                    'sender_new_balance' => [
                        'points_balance',
                    ],
                    'recipient_new_balance' => [
                        'points_balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Points transferred successfully',
                'data' => [
                    'sender_new_balance' => [
                        'points_balance' => 50,
                    ],
                    'recipient_new_balance' => [
                        'points_balance' => 50,
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_cannot_transfer_points_to_nonexistent_user()
    {
        $wallet = Wallet::factory()->create(['user_id' => $this->user->id, 'points_balance' => 100]);

        $this->actingAs($this->user)
            ->postJson('/wallet/transfer', [
                'recipient_id' => 99999,
                'amount' => 50,
                'description' => 'Test transfer to nonexistent user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    /** @test */
    public function authenticated_user_cannot_transfer_more_points_than_available()
    {
        // User has 0 points by default
        $this->actingAs($this->user)
            ->postJson('/wallet/transfer', [
                'recipient_id' => $this->otherUser->id,
                'amount' => 100,
                'description' => 'Test transfer insufficient points',
            ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient points. Required: 100, Available: 0',
            ]);
    }

    /** @test */
    public function add_points_validates_required_fields()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/add-points', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'description']);
    }

    /** @test */
    public function add_points_validates_minimum_amount()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/add-points', [
                'amount' => 0,
                'description' => 'Test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function spend_points_validates_required_fields()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/spend-points', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'description']);
    }

    /** @test */
    public function transfer_validates_required_fields()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/transfer', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id', 'amount', 'description']);
    }

    /** @test */
    public function user_cannot_transfer_points_to_themselves()
    {
        $this->actingAs($this->user)
            ->postJson('/wallet/transfer', [
                'recipient_id' => $this->user->id,
                'amount' => 50,
                'description' => 'Test self transfer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }
}
