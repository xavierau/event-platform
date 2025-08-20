<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing events and users
        $events = Event::take(5)->get();
        $users = User::take(10)->get();

        if ($events->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No events or users found. Please run EventSeeder and UserSeeder first.');
            return;
        }

        $this->command->info('Creating comments for events...');

        foreach ($events as $event) {
            // Create 3-8 comments per event
            $commentCount = fake()->numberBetween(3, 8);
            
            $this->command->info("Creating {$commentCount} comments for event: {$event->getTranslation('name', 'en')}");
            
            // Create top-level comments
            $topLevelComments = Comment::factory()
                ->count($commentCount)
                ->for($event)
                ->state(function () use ($users) {
                    return [
                        'user_id' => $users->random()->id,
                        'content' => fake()->randomElement([
                            'This event looks amazing! Can\'t wait to attend.',
                            'The lineup is incredible this year. Definitely getting tickets!',
                            'I went to this last year and it was fantastic. Highly recommend!',
                            'Is there parking available at the venue?',
                            'Are there any age restrictions for this event?',
                            'The ticket prices are very reasonable for what\'s offered.',
                            'Hope the weather will be good for the outdoor sessions.',
                            'This is exactly what our city needs more of. Great initiative!',
                            'I\'ve been waiting for something like this. Thank you organizers!',
                            'Will there be food and drinks available at the venue?',
                            'The speaker lineup is impressive. Looking forward to the keynotes.',
                            'Perfect timing! This fits perfectly into my schedule.',
                            'I hope they live stream some of the sessions for those who can\'t attend.',
                            'The venue is perfect for this type of event.',
                            'Just bought my tickets! So excited!',
                            'Will there be networking opportunities?',
                            'Great to see local talent being featured.',
                            'The early bird pricing was a nice touch.',
                            'I love the variety of sessions planned.',
                            'This is going to be an unforgettable experience!',
                        ]),
                        'status' => fake()->randomElement(['approved', 'approved', 'approved', 'pending']), // 75% approved
                        'votes_up_count' => fake()->numberBetween(0, 15),
                        'votes_down_count' => fake()->numberBetween(0, 3),
                        'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    ];
                })
                ->create();

            // Create some replies (30% chance per comment)
            foreach ($topLevelComments as $parentComment) {
                if (fake()->boolean(30)) {
                    Comment::factory()
                        ->count(fake()->numberBetween(1, 3))
                        ->for($event)
                        ->state(function () use ($users, $parentComment) {
                            return [
                                'user_id' => $users->random()->id,
                                'parent_id' => $parentComment->id,
                                'content' => fake()->randomElement([
                                    'I completely agree with you!',
                                    'Thanks for sharing your experience.',
                                    'Good question! I\'m wondering the same thing.',
                                    'I checked their website and found the answer.',
                                    'Yes, I can confirm this from last year.',
                                    'Great point! I hadn\'t thought of that.',
                                    'I hope the organizers can clarify this.',
                                    'Thanks for the tip!',
                                    'That\'s really helpful information.',
                                    'I\'m also interested in knowing more about this.',
                                    'Same here! Looking forward to it.',
                                    'Thanks for the recommendation.',
                                    'I had the same question.',
                                    'Perfect! That\'s exactly what I needed to know.',
                                    'Appreciate you sharing this.',
                                ]),
                                'status' => 'approved',
                                'votes_up_count' => fake()->numberBetween(0, 8),
                                'votes_down_count' => fake()->numberBetween(0, 2),
                                'created_at' => fake()->dateTimeBetween($parentComment->created_at, 'now'),
                            ];
                        })
                        ->create();
                }
            }
        }

        $totalComments = Comment::count();
        $this->command->info("Created {$totalComments} total comments for events.");
    }
}
