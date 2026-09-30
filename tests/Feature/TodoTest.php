<?php

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 12:00:00');
    }

    public function test_index_shows_an_input_and_existing_todos(): void
    {
        Todo::factory()->create(['title' => 'Buy milk']);

        $this->get('/')
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('Buy milk');
    }

    public function test_a_todo_can_be_created(): void
    {
        $this->post('/todos', ['title' => 'Write tests'])->assertRedirect();

        $this->assertDatabaseHas('todos', ['title' => 'Write tests', 'completed' => false]);
    }

    public function test_a_todo_requires_a_non_blank_title(): void
    {
        $this->post('/todos', ['title' => '   '])->assertSessionHasErrors('title');
        $this->post('/todos', ['title' => str_repeat('a', 256)])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_a_todo_can_be_toggled_completed_and_back(): void
    {
        $todo = Todo::factory()->create();

        $this->patch("/todos/{$todo->id}/toggle")->assertRedirect();
        $this->assertTrue($todo->fresh()->completed);

        $this->patch("/todos/{$todo->id}/toggle")->assertRedirect();
        $this->assertFalse($todo->fresh()->completed);
    }

    public function test_a_todo_can_be_edited(): void
    {
        $todo = Todo::factory()->create(['title' => 'Old']);

        $this->put("/todos/{$todo->id}", ['title' => 'New'])->assertRedirect();

        $this->assertSame('New', $todo->fresh()->title);
    }

    public function test_editing_rejects_a_blank_title(): void
    {
        $todo = Todo::factory()->create(['title' => 'Old']);

        $this->put("/todos/{$todo->id}", ['title' => ''])->assertSessionHasErrors('title');

        $this->assertSame('Old', $todo->fresh()->title);
    }

    public function test_a_todo_can_be_deleted(): void
    {
        $todo = Todo::factory()->create();

        $this->delete("/todos/{$todo->id}")->assertRedirect();

        $this->assertModelMissing($todo);
    }

    public function test_filters_show_only_active_or_completed_todos(): void
    {
        Todo::factory()->create(['title' => 'Active one']);
        Todo::factory()->completed()->create(['title' => 'Done one']);

        $this->get('/?filter=active')->assertSee('Active one')->assertDontSee('Done one');
        $this->get('/?filter=completed')->assertSee('Done one')->assertDontSee('Active one');
        $this->get('/')->assertSee('Active one')->assertSee('Done one');
    }

    public function test_an_unknown_filter_falls_back_to_all(): void
    {
        Todo::factory()->create(['title' => 'Active one']);
        Todo::factory()->completed()->create(['title' => 'Done one']);

        $this->get('/?filter=bogus')->assertSee('Active one')->assertSee('Done one');
    }

    public function test_the_creation_date_is_displayed(): void
    {
        Todo::factory()->create(['created_at' => '2026-01-15 10:30:00']);

        $this->get('/')->assertSee('Created')->assertSee('Jan 15, 2026 10:30');
    }

    public function test_titles_are_escaped(): void
    {
        Todo::factory()->create(['title' => '<script>alert(1)</script>']);

        $this->get('/')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_todo_is_created_for_the_chosen_day(): void
    {
        $this->post('/todos', ['title' => 'Dentist', 'due_date' => '2026-10-20'])->assertRedirect();

        $this->assertSame('2026-10-20', Todo::firstWhere('title', 'Dentist')->getRawOriginal('due_date'));
    }

    public function test_a_todo_without_a_day_is_created_for_today(): void
    {
        $this->post('/todos', ['title' => 'Today thing'])->assertRedirect();

        $this->assertSame('2026-10-15', Todo::firstWhere('title', 'Today thing')->getRawOriginal('due_date'));
    }

    public function test_today_follows_the_browser_timezone(): void
    {
        Carbon::setTestNow('2026-10-15 23:30:00');

        $this->withUnencryptedCookie('tz', 'Pacific/Auckland')
            ->post('/todos', ['title' => 'Already tomorrow there']);

        $this->assertSame('2026-10-16', Todo::firstWhere('title', 'Already tomorrow there')->getRawOriginal('due_date'));

        $this->withUnencryptedCookie('tz', 'Pacific/Auckland')
            ->get('/')
            ->assertSee('Friday, 16 October 2026');
    }

    public function test_an_invalid_timezone_cookie_falls_back_to_the_app_timezone(): void
    {
        $this->withUnencryptedCookie('tz', 'Not/AZone')
            ->get('/')
            ->assertOk()
            ->assertSee('Thursday, 15 October 2026');
    }

    public function test_an_invalid_day_is_rejected(): void
    {
        $this->post('/todos', ['title' => 'Bad day', 'due_date' => '2026-02-31'])->assertSessionHasErrors('due_date');
        $this->post('/todos', ['title' => 'Bad day', 'due_date' => 'tomorrow'])->assertSessionHasErrors('due_date');

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_the_day_defaults_to_today(): void
    {
        Todo::factory()->create(['title' => 'Today one']);
        Todo::factory()->onDate('2026-10-16')->create(['title' => 'Tomorrow one']);

        $this->get('/')
            ->assertSee('Thursday, 15 October 2026')
            ->assertSee('Today one')
            ->assertDontSee('Tomorrow one');
    }

    public function test_a_day_can_be_selected(): void
    {
        Todo::factory()->create(['title' => 'Today one']);
        Todo::factory()->onDate('2026-10-16')->create(['title' => 'Tomorrow one']);

        $this->get('/?date=2026-10-16')
            ->assertSee('Friday, 16 October 2026')
            ->assertSee('Tomorrow one')
            ->assertDontSee('Today one');
    }

    public function test_all_days_can_be_shown(): void
    {
        Todo::factory()->create(['title' => 'Today one']);
        Todo::factory()->onDate('2026-10-16')->create(['title' => 'Tomorrow one']);

        $this->get('/?date=all')
            ->assertSee('All days')
            ->assertSee('Today one')
            ->assertSee('Tomorrow one')
            ->assertSee('Fri, 16 Oct 2026');
    }

    public function test_an_invalid_date_falls_back_to_today(): void
    {
        Todo::factory()->create(['title' => 'Today one']);

        $this->get('/?date=2026-02-31')->assertSee('Thursday, 15 October 2026')->assertSee('Today one');
        $this->get('/?date=garbage')->assertSee('Thursday, 15 October 2026')->assertSee('Today one');
    }

    public function test_a_day_can_be_split_into_active_and_completed(): void
    {
        Todo::factory()->onDate('2026-10-16')->create(['title' => 'Open one']);
        Todo::factory()->onDate('2026-10-16')->completed()->create(['title' => 'Finished one']);
        Todo::factory()->completed()->create(['title' => 'Finished today']);

        $this->get('/?date=2026-10-16&filter=completed')
            ->assertSee('Finished one')
            ->assertDontSee('Open one')
            ->assertDontSee('Finished today');

        $this->get('/?date=2026-10-16&filter=active')
            ->assertSee('Open one')
            ->assertDontSee('Finished one');
    }

    public function test_the_counts_only_cover_the_selected_day(): void
    {
        Todo::factory()->count(2)->onDate('2026-10-16')->create();
        Todo::factory()->onDate('2026-10-16')->completed()->create();
        Todo::factory()->create();

        $this->get('/?date=2026-10-16')
            ->assertSeeInOrder(['All', '3', 'Active', '2', 'Completed', '1']);
    }

    public function test_the_calendar_shows_the_month_with_open_and_done_markers(): void
    {
        Todo::factory()->onDate('2026-10-16')->create();
        Todo::factory()->onDate('2026-10-17')->completed()->create();

        $html = $this->get('/?date=2026-10-15')->assertSee('October 2026')->getContent();

        $this->assertStringContainsString('aria-label="Friday 16 October, 0 of 1 done"', $html);
        $this->assertStringContainsString('aria-label="Saturday 17 October, 1 of 1 done"', $html);
        $this->assertStringContainsString('aria-label="Sunday 18 October, nothing planned"', $html);
        // Weeks start on Monday: 1 Oct 2026 is a Thursday, so the grid starts on Mon 28 Sep.
        $this->assertStringContainsString('aria-label="Monday 28 September, nothing planned"', $html);
    }

    public function test_the_calendar_can_browse_other_months_without_changing_the_day(): void
    {
        $this->get('/?date=2026-10-15&month=2026-12')
            ->assertSee('December 2026')
            ->assertSee('Thursday, 15 October 2026')
            ->assertSee('month=2026-11', false)
            ->assertSee('month=2027-01', false);
    }

    public function test_an_invalid_month_falls_back_to_the_selected_month(): void
    {
        $this->get('/?date=2026-10-15&month=2026-13')->assertSee('October 2026');
    }

    public function test_a_todo_can_be_moved_to_another_day(): void
    {
        $todo = Todo::factory()->create();

        $this->put("/todos/{$todo->id}", ['title' => $todo->title, 'due_date' => '2026-10-20'])->assertRedirect();

        $this->assertSame('2026-10-20', $todo->fresh()->getRawOriginal('due_date'));
    }

    public function test_editing_the_title_leaves_the_day_alone(): void
    {
        $todo = Todo::factory()->onDate('2026-10-20')->create();

        $this->put("/todos/{$todo->id}", ['title' => 'Renamed'])->assertRedirect();

        $this->assertSame('2026-10-20', $todo->fresh()->getRawOriginal('due_date'));
    }

    public function test_moving_a_todo_off_the_viewed_day_tells_the_user_where_it_went(): void
    {
        $todo = Todo::factory()->create();

        $this->from('/?date=2026-10-15')
            ->put("/todos/{$todo->id}", ['title' => $todo->title, 'due_date' => '2026-10-20'])
            ->assertSessionHas('status', 'Moved to Tue, 20 Oct 2026.')
            ->assertSessionHas('status_date', '2026-10-20');
    }

    public function test_no_notice_is_shown_when_the_todo_stays_on_the_viewed_day(): void
    {
        $this->from('/?date=2026-10-20')
            ->post('/todos', ['title' => 'Same day', 'due_date' => '2026-10-20'])
            ->assertSessionMissing('status');

        $this->from('/?date=all')
            ->post('/todos', ['title' => 'Everywhere', 'due_date' => '2026-10-21'])
            ->assertSessionMissing('status');
    }

    public function test_adding_to_a_day_other_than_the_viewed_one_shows_a_notice(): void
    {
        $this->from('/')
            ->post('/todos', ['title' => 'Later', 'due_date' => '2026-10-21'])
            ->assertSessionHas('status', 'Added to Wed, 21 Oct 2026.');
    }
}
