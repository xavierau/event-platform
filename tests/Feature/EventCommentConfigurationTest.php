<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Enums\RoleNameEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the admin role first
    Role::create(['name' => RoleNameEnum::ADMIN->value]);
    
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN->value);
    
    $this->organizer = Organizer::factory()->create();
    $this->category = Category::factory()->create();
});

it('can create an event with comments enabled and not requiring approval', function () {
    $this->actingAs($this->admin);
    
    $response = $this->post(route('admin.events.store'), [
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'name' => [
            'en' => 'Test Event',
            'zh-TW' => '測試活動',
            'zh-CN' => '测试活动',
        ],
        'short_summary' => [
            'en' => 'Short summary',
            'zh-TW' => '簡短摘要',
            'zh-CN' => '简短摘要',
        ],
        'description' => [
            'en' => 'Description',
            'zh-TW' => '描述',
            'zh-CN' => '描述',
        ],
        'comments_enabled' => true,
        'comments_require_approval' => false,
        'comment_config' => 'enabled',
    ]);
    
    $response->assertRedirect(route('admin.events.index'));
    
    $event = Event::latest()->first();
    expect($event->comments_enabled)->toBeTrue();
    expect($event->comments_require_approval)->toBeFalse();
    expect($event->comment_config->value)->toBe('enabled');
});

it('can create an event with comments enabled and requiring approval', function () {
    $this->actingAs($this->admin);
    
    $response = $this->post(route('admin.events.store'), [
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'name' => [
            'en' => 'Test Event',
            'zh-TW' => '測試活動',
            'zh-CN' => '测试活动',
        ],
        'short_summary' => [
            'en' => 'Short summary',
            'zh-TW' => '簡短摘要',
            'zh-CN' => '简短摘要',
        ],
        'description' => [
            'en' => 'Description',
            'zh-TW' => '描述',
            'zh-CN' => '描述',
        ],
        'comments_enabled' => true,
        'comments_require_approval' => true,
        'comment_config' => 'moderated',
    ]);
    
    $response->assertRedirect(route('admin.events.index'));
    
    $event = Event::latest()->first();
    expect($event->comments_enabled)->toBeTrue();
    expect($event->comments_require_approval)->toBeTrue();
    expect($event->comment_config->value)->toBe('moderated');
});

it('can create an event with comments disabled', function () {
    $this->actingAs($this->admin);
    
    $response = $this->post(route('admin.events.store'), [
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'name' => [
            'en' => 'Test Event',
            'zh-TW' => '測試活動',
            'zh-CN' => '测试活动',
        ],
        'short_summary' => [
            'en' => 'Short summary',
            'zh-TW' => '簡短摘要',
            'zh-CN' => '简短摘要',
        ],
        'description' => [
            'en' => 'Description',
            'zh-TW' => '描述',
            'zh-CN' => '描述',
        ],
        'comments_enabled' => false,
        'comments_require_approval' => false,
        'comment_config' => 'disabled',
    ]);
    
    $response->assertRedirect(route('admin.events.index'));
    
    $event = Event::latest()->first();
    expect($event->comments_enabled)->toBeFalse();
    expect($event->comments_require_approval)->toBeFalse();
    expect($event->comment_config->value)->toBe('disabled');
});

it('can update an event comment configuration', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => false,
        'comments_require_approval' => false,
        'comment_config' => 'disabled',
    ]);
    
    $this->actingAs($this->admin);
    
    $response = $this->put(route('admin.events.update', $event), [
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'name' => [
            'en' => 'Updated Event',
            'zh-TW' => '更新的活動',
            'zh-CN' => '更新的活动',
        ],
        'short_summary' => [
            'en' => 'Updated summary',
            'zh-TW' => '更新的摘要',
            'zh-CN' => '更新的摘要',
        ],
        'description' => [
            'en' => 'Updated description',
            'zh-TW' => '更新的描述',
            'zh-CN' => '更新的描述',
        ],
        'comments_enabled' => true,
        'comments_require_approval' => true,
        'comment_config' => 'moderated',
    ]);
    
    $response->assertRedirect(route('admin.events.index'));
    
    $event->refresh();
    expect($event->comments_enabled)->toBeTrue();
    expect($event->comments_require_approval)->toBeTrue();
    expect($event->comment_config->value)->toBe('moderated');
});

it('displays correct comment configuration in edit form', function () {
    $event = Event::factory()->create([
        'organizer_id' => $this->organizer->id,
        'category_id' => $this->category->id,
        'comments_enabled' => true,
        'comments_require_approval' => true,
        'comment_config' => 'moderated',
    ]);
    
    $this->actingAs($this->admin);
    
    $response = $this->get(route('admin.events.edit', $event));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Events/Edit')
        ->has('event', fn ($page) => $page
            ->where('comments_enabled', true)
            ->where('comments_require_approval', true)
            ->where('comment_config', 'moderated')
            ->etc()
        )
    );
});