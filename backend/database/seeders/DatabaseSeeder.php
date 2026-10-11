<?php

namespace Database\Seeders;

use App\Enums\IdeaStatus;
use App\Enums\ResultsVisibility;
use App\Models\Idea;
use App\Models\User;
use App\Models\Voting;
use App\Services\BallotService;
use App\Services\IdeaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Synthetic product-feedback community: users, ideas with votes and status
 * history, a roadmap and three votings (active, closed, scheduled). State is
 * produced through the domain services, so counters and history are consistent.
 */
class DatabaseSeeder extends Seeder
{
    private const IDEAS = [
        'Dark mode for the whole app', 'Export reports to Excel', 'Two-factor authentication',
        'Calendar sync with external calendars', 'Bulk edit for customer records', 'Custom fields on customer cards',
        'Recurring appointments', 'Waiting list when a slot is full', 'SMS reminders the day before',
        'Mobile app for staff', 'Public booking page in more languages', 'Gift cards and vouchers',
        'Loyalty points program', 'Inventory low-stock alerts', 'Tip payouts report',
        'Keyboard shortcuts in the calendar', 'Drag and drop to reschedule', 'Audit log of changes',
        'Role-based permissions per branch', 'Online prepayment for bookings', 'Customer birthday greetings',
        'Faster search in customer list', 'Merge duplicate customers', 'Print-friendly daily schedule',
        'Webhook for new bookings', 'Feedback survey after visit', 'Staff performance dashboard',
        'Multi-currency support', 'Offline mode for the tablet', 'Import customers from CSV',
    ];

    /** Plausible, generic motivations (English, no lorem ipsum) rotated across the ideas. */
    private const DESCRIPTIONS = [
        'We do this by hand every week and it takes most of a Monday morning.',
        'Customers keep asking for it at the front desk; it would save a lot of back-and-forth.',
        'Our team switched from a tool that had this, and it is the thing we miss most.',
        'Would let us stop exporting data into spreadsheets just to answer simple questions.',
        'Small change, but it removes a frequent source of mistakes during busy hours.',
        'Needed for our second location, where staff work on shared tablets.',
        'Several regulars mentioned it in feedback this month.',
        'Would make onboarding new staff much faster.',
    ];

    public function run(IdeaService $ideas, BallotService $ballots): void
    {
        fake()->seed(2026);

        User::factory()->admin()->create(['name' => 'Alex Admin', 'email' => 'admin@example.test']);
        User::factory()->create(['name' => 'Morgan Member', 'email' => 'member@example.test']);
        $users = User::factory()->count(40)->create()->concat(User::all())->unique('id')->values();
        $admin = User::where('email', 'admin@example.test')->sole();

        foreach (self::IDEAS as $i => $title) {
            $idea = Idea::create([
                'author_id' => $users->random()->id,
                'title' => $title,
                'description' => self::DESCRIPTIONS[$i % count(self::DESCRIPTIONS)],
                'status' => IdeaStatus::New,
                'created_at' => CarbonImmutable::now()->subDays(120 - $i * 3),
            ]);

            // Popularity follows a long tail: a few ideas collect most of the votes.
            $voters = $users->shuffle()->take((int) round(40 * (1 / (1 + $i * 0.35))) + fake()->numberBetween(0, 3));
            foreach ($voters as $voter) {
                $ideas->toggleVote($idea, $voter);
            }
        }

        $journeys = [
            'Dark mode for the whole app' => [IdeaStatus::Planned, IdeaStatus::InProgress, IdeaStatus::Done],
            'Export reports to Excel' => [IdeaStatus::Planned, IdeaStatus::InProgress],
            'Two-factor authentication' => [IdeaStatus::Planned, IdeaStatus::InProgress],
            'Calendar sync with external calendars' => [IdeaStatus::UnderReview, IdeaStatus::Planned],
            'Recurring appointments' => [IdeaStatus::Planned],
            'Waiting list when a slot is full' => [IdeaStatus::Planned],
            'SMS reminders the day before' => [IdeaStatus::Planned, IdeaStatus::InProgress, IdeaStatus::Done],
            'Keyboard shortcuts in the calendar' => [IdeaStatus::UnderReview],
            'Multi-currency support' => [IdeaStatus::UnderReview, IdeaStatus::Declined],
            'Offline mode for the tablet' => [IdeaStatus::Declined],
        ];

        foreach ($journeys as $title => $steps) {
            $idea = Idea::where('title', $title)->sole();
            foreach ($steps as $status) {
                $ideas->changeStatus($idea, $status, $admin, $status === IdeaStatus::Declined ? 'Out of scope for this year.' : null);
            }
        }

        $this->votings($users, $admin, $ballots);
    }

    private function votings($users, User $admin, BallotService $ballots): void
    {
        $active = Voting::create([
            'created_by' => $admin->id,
            'title' => 'Q3 focus: what should we build next?',
            'description' => 'Pick up to two. Results are visible after you vote.',
            'starts_at' => CarbonImmutable::now()->subDays(3),
            'ends_at' => CarbonImmutable::now()->addDays(11),
            'max_choices' => 2,
            'results_visibility' => ResultsVisibility::AfterVote,
        ]);
        foreach (['Calendar sync with external calendars', 'Recurring appointments', 'Waiting list when a slot is full', 'Mobile app for staff'] as $i => $title) {
            $idea = Idea::where('title', $title)->sole();
            $active->options()->create(['idea_id' => $idea->id, 'label' => $idea->title, 'position' => $i + 1]);
        }

        // The demo "member" account has not voted yet, so the voting UI starts from a fresh ballot.
        $optionIds = $active->options()->pluck('id')->all();
        foreach ($users->where('email', '!=', 'member@example.test')->shuffle()->take(26) as $voter) {
            $picked = fake()->randomElements($optionIds, fake()->numberBetween(1, 2));
            $ballots->cast($active, $voter, $picked);
        }

        $closed = Voting::create([
            'created_by' => $admin->id,
            'title' => 'Which report do you use most?',
            'starts_at' => CarbonImmutable::now()->subDays(40),
            'ends_at' => CarbonImmutable::now()->addDays(1),
            'max_choices' => 1,
            'results_visibility' => ResultsVisibility::Always,
        ]);
        foreach (['Revenue', 'Staff performance', 'Customer retention', 'Inventory'] as $i => $label) {
            $closed->options()->create(['label' => $label, 'position' => $i + 1]);
        }
        $closedOptions = $closed->options()->pluck('id')->all();
        foreach ($users->shuffle()->take(30) as $voter) {
            $ballots->cast($closed, $voter, [fake()->randomElement($closedOptions)]);
        }
        $closed->update(['closed_at' => CarbonImmutable::now()->subDays(10)]);

        $scheduled = Voting::create([
            'created_by' => $admin->id,
            'title' => 'Naming the new mobile app',
            'starts_at' => CarbonImmutable::now()->addDays(5),
            'ends_at' => CarbonImmutable::now()->addDays(19),
            'max_choices' => 1,
            'results_visibility' => ResultsVisibility::AfterClose,
        ]);
        foreach (['Pocket', 'Desk', 'Daybook'] as $i => $label) {
            $scheduled->options()->create(['label' => $label, 'position' => $i + 1]);
        }
    }
}
