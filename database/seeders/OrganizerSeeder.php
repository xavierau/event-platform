<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organizer;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Support\Facades\DB;

class OrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Create organizer entities with comprehensive team structures
        $adminRoleName = RoleNameEnum::ADMIN->value;

        $adminRole = null;
        if (class_exists(Role::class)) {
            $adminRole = Role::where('name', $adminRoleName)->where('guard_name', 'web')->first();
            if (!$adminRole) {
                $this->command->error("Role '{$adminRoleName}' not found. Make sure RolePermissionSeeder has run.");
                return;
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Cannot assign Admin role.');
            return;
        }

        $organizersData = [
            [
                'organizer_name' => [
                    'en' => 'Event Corp',
                    'zh-TW' => '活動公司',
                    'zh-CN' => '活动公司'
                ],
                'organizer_slug' => 'event-corp',
                'organizer_description' => [
                    'en' => 'Professional event organization company specializing in corporate conferences and trade shows',
                    'zh-TW' => '專業活動組織公司，專門從事企業會議和貿易展覽',
                    'zh-CN' => '专业活动组织公司，专门从事企业会议和贸易展览'
                ],
                'contact_email' => 'contact@eventcorp.com',
                'contact_phone' => '+1-555-0100',
                'website_url' => 'https://eventcorp.com',
                'team' => [
                    [
                        'name' => 'John Smith',
                        'email' => 'john@eventcorp.com',
                        'role' => 'owner',
                        'permissions' => [], // Owners get all permissions by default
                        'is_owner_admin' => true,
                    ],
                    [
                        'name' => 'Sarah Johnson',
                        'email' => 'sarah@eventcorp.com',
                        'role' => 'manager',
                        'permissions' => [
                            OrganizerPermissionEnum::CREATE_EVENTS->value,
                            OrganizerPermissionEnum::EDIT_EVENTS->value,
                            OrganizerPermissionEnum::DELETE_EVENTS->value,
                            OrganizerPermissionEnum::MANAGE_TEAM->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                    [
                        'name' => 'Michael Chen',
                        'email' => 'michael@eventcorp.com',
                        'role' => 'staff',
                        'permissions' => [
                            OrganizerPermissionEnum::CREATE_EVENTS->value,
                            OrganizerPermissionEnum::EDIT_EVENTS->value,
                            OrganizerPermissionEnum::VIEW_BOOKINGS->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                    [
                        'name' => 'Emily Rodriguez',
                        'email' => 'emily@eventcorp.com',
                        'role' => 'viewer',
                        'permissions' => [
                            OrganizerPermissionEnum::VIEW_EVENTS->value,
                            OrganizerPermissionEnum::VIEW_BOOKINGS->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                ],
            ],
            [
                'organizer_name' => [
                    'en' => 'Music Fest Group',
                    'zh-TW' => '音樂節集團',
                    'zh-CN' => '音乐节集团'
                ],
                'organizer_slug' => 'music-fest-group',
                'organizer_description' => [
                    'en' => 'Premier music festival and concert organization group with international reach',
                    'zh-TW' => '具有國際影響力的頂級音樂節和演唱會組織集團',
                    'zh-CN' => '具有国际影响力的顶级音乐节和演唱会组织集团'
                ],
                'contact_email' => 'info@musicfestgroup.com',
                'contact_phone' => '+1-555-0200',
                'website_url' => 'https://musicfestgroup.com',
                'team' => [
                    [
                        'name' => 'Alex Turner',
                        'email' => 'alex@musicfestgroup.com',
                        'role' => 'owner',
                        'permissions' => [],
                        'is_owner_admin' => true,
                    ],
                    [
                        'name' => 'Jessica Lee',
                        'email' => 'jessica@musicfestgroup.com',
                        'role' => 'manager',
                        'permissions' => [
                            OrganizerPermissionEnum::CREATE_EVENTS->value,
                            OrganizerPermissionEnum::EDIT_EVENTS->value,
                            OrganizerPermissionEnum::PUBLISH_EVENTS->value,
                            OrganizerPermissionEnum::VIEW_ANALYTICS->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                    [
                        'name' => 'David Park',
                        'email' => 'david@musicfestgroup.com',
                        'role' => 'staff',
                        'permissions' => [
                            OrganizerPermissionEnum::CREATE_EVENTS->value,
                            OrganizerPermissionEnum::EDIT_EVENTS->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                ],
            ],
            [
                'organizer_name' => [
                    'en' => 'Community Connect',
                    'zh-TW' => '社區連結',
                    'zh-CN' => '社区连接'
                ],
                'organizer_slug' => 'community-connect',
                'organizer_description' => [
                    'en' => 'Local community event organizer focused on bringing neighborhoods together',
                    'zh-TW' => '專注於將社區聚集在一起的在地社區活動組織者',
                    'zh-CN' => '专注于将社区聚集在一起的本地社区活动组织者'
                ],
                'contact_email' => 'hello@communityconnect.org',
                'contact_phone' => '+1-555-0300',
                'website_url' => 'https://communityconnect.org',
                'team' => [
                    [
                        'name' => 'Maria Garcia',
                        'email' => 'maria@communityconnect.org',
                        'role' => 'owner',
                        'permissions' => [],
                        'is_owner_admin' => false, // Not platform admin
                    ],
                    [
                        'name' => 'James Wilson',
                        'email' => 'james@communityconnect.org',
                        'role' => 'staff',
                        'permissions' => [
                            OrganizerPermissionEnum::CREATE_EVENTS->value,
                            OrganizerPermissionEnum::EDIT_EVENTS->value,
                            OrganizerPermissionEnum::VIEW_BOOKINGS->value,
                        ],
                        'is_owner_admin' => false,
                    ],
                    // Include a pending invitation example
                    [
                        'name' => 'Lisa Brown',
                        'email' => 'lisa@communityconnect.org',
                        'role' => 'viewer',
                        'permissions' => [OrganizerPermissionEnum::VIEW_EVENTS->value],
                        'is_owner_admin' => false,
                        'pending_invitation' => true,
                    ],
                ],
            ],
        ];

        foreach ($organizersData as $data) {
            // Create organizer entity
            $organizer = Organizer::firstOrCreate(
                ['slug' => $data['organizer_slug']],
                [
                    'name' => $data['organizer_name'],
                    'slug' => $data['organizer_slug'],
                    'description' => $data['organizer_description'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'website_url' => $data['website_url'],
                    'is_active' => true,
                    'created_by' => 1, // Will be updated to the owner's ID
                ]
            );

            $this->command->info("Created/found organizer: {$data['organizer_slug']}");

            // Create team members
            foreach ($data['team'] as $member) {
                // Create or find the user
                $user = User::firstOrCreate(
                    ['email' => $member['email']],
                    [
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                // Assign platform admin role if needed
                if ($member['is_owner_admin'] && !$user->hasRole($adminRoleName)) {
                    $user->assignRole($adminRole);
                    $this->command->info("Assigned '{$adminRoleName}' role to {$member['email']}");
                }

                // Check if user is already associated with organizer
                if (!$organizer->users()->where('user_id', $user->id)->exists()) {
                    $pivotData = [
                        'role_in_organizer' => $member['role'],
                        'permissions' => json_encode($member['permissions']),
                        'joined_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Handle pending invitations
                    if (isset($member['pending_invitation']) && $member['pending_invitation']) {
                        $pivotData['invitation_accepted_at'] = null;
                        $pivotData['invited_by'] = $organizer->users()->where('role_in_organizer', 'owner')->first()?->id ?? 1;
                        $this->command->info("Created pending invitation for {$member['email']}");
                    } else {
                        $pivotData['invitation_accepted_at'] = now();
                    }

                    $organizer->users()->attach($user->id, $pivotData);
                    $this->command->info("Associated {$member['email']} as {$member['role']} of {$data['organizer_slug']}");
                } else {
                    $this->command->info("User {$member['email']} already associated with {$data['organizer_slug']}");
                }

                // Update organizer's created_by to the owner
                if ($member['role'] === 'owner' && $organizer->created_by === 1) {
                    $organizer->update(['created_by' => $user->id]);
                }
            }

            $teamCount = count($data['team']);
            $activeCount = collect($data['team'])->where('pending_invitation', '!=', true)->count();
            $pendingCount = $teamCount - $activeCount;

            $this->command->info("Team structure for {$data['organizer_slug']}: {$activeCount} active members" .
                ($pendingCount > 0 ? ", {$pendingCount} pending invitations" : ""));
        }

        $totalOrganizers = Organizer::count();
        $totalActiveMembers = DB::table('organizer_users')->whereNotNull('invitation_accepted_at')->count();
        $totalPendingInvitations = DB::table('organizer_users')->whereNull('invitation_accepted_at')->count();

        $this->command->info('Organizer seeding completed successfully:');
        $this->command->info("- {$totalOrganizers} organizers created");
        $this->command->info("- {$totalActiveMembers} active team members");
        $this->command->info("- {$totalPendingInvitations} pending invitations");
    }
}
