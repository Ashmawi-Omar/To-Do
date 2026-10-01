<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeZone;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TodoController extends Controller
{
    private const FILTERS = ['all', 'active', 'completed'];

    public function index(Request $request): View
    {
        $today = $this->today($request);

        // No ?date shows today, ?date=all shows every day.
        $allDays = $request->query('date') === 'all';
        $requestedDate = $allDays ? null : $this->parseDate($request->query('date'));
        $selected = $allDays ? null : ($requestedDate ?? $today);

        $requestedMonth = $this->parseMonth($request->query('month'));
        $month = $requestedMonth ?? ($selected ?? $today)->startOfMonth();

        $filter = in_array($request->query('filter'), self::FILTERS, true)
            ? $request->query('filter')
            : 'all';

        $state = [
            'date' => $allDays ? 'all' : $requestedDate?->toDateString(),
            'month' => $requestedMonth?->format('Y-m'),
            'filter' => $filter === 'all' ? null : $filter,
        ];
        $url = fn (array $overrides = []): string => route('todos.index', array_filter(
            array_merge($state, $overrides),
            fn ($value) => $value !== null,
        ));

        $scope = Todo::query()->when($selected, fn ($query) => $query->onDate($selected->toDateString()));

        $todos = (clone $scope)
            ->when($filter === 'active', fn ($query) => $query->active())
            ->when($filter === 'completed', fn ($query) => $query->completed())
            ->orderByDesc('due_date')
            ->latest()
            ->latest('id')
            ->get();

        $totals = (clone $scope)->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(completed), 0) as done')
            ->first();

        return view('todos.index', [
            'todos' => $todos,
            'filter' => $filter,
            'selected' => $selected,
            'today' => $today,
            'url' => $url,
            'counts' => [
                'all' => (int) $totals->total,
                'active' => (int) $totals->total - (int) $totals->done,
                'completed' => (int) $totals->done,
            ],
            'calendar' => $this->calendar($month, $selected, $today, $url),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string'],
        ]);

        $todo = Todo::create([
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? $this->today($request)->toDateString(),
            'description' => $data['description'] ?? null,
        ]);

        return $this->redirectBack($request, $todo, 'Added to');
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string'],
        ]);

        $todo->update($data);

        return $this->redirectBack($request, $todo, 'Moved to');
    }

    public function toggle(Todo $todo): RedirectResponse
    {
        $todo->update(['completed' => ! $todo->completed]);

        return back();
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $todo->delete();

        return back();
    }

    /**
     * Go back to the page the user was on. If the to-do now belongs to a day
     * other than the one being viewed it would silently vanish, so say where it went.
     */
    private function redirectBack(Request $request, Todo $todo, string $verb): RedirectResponse
    {
        $redirect = back();

        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);
        $viewed = ($query['date'] ?? null) === 'all'
            ? null
            : ($this->parseDate($query['date'] ?? null) ?? $this->today($request));

        $dueDate = $todo->due_date->toDateString();

        if ($viewed !== null && $viewed->toDateString() !== $dueDate) {
            $redirect->with([
                'status' => $verb.' '.$todo->due_date->format('D, j M Y').'.',
                'status_date' => $dueDate,
            ]);
        }

        return $redirect;
    }

    /**
     * Today's date in the user's timezone (from the `tz` cookie), as a UTC
     * midnight so every date in this controller can be compared by day.
     */
    private function today(Request $request): CarbonImmutable
    {
        try {
            $zone = new DateTimeZone((string) $request->cookie('tz') ?: config('app.timezone'));
        } catch (Exception) {
            $zone = new DateTimeZone(config('app.timezone'));
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', CarbonImmutable::now($zone)->toDateString(), 'UTC');
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        return $this->parse($value, '/^\d{4}-\d{2}-\d{2}$/', 'Y-m-d');
    }

    private function parseMonth(mixed $value): ?CarbonImmutable
    {
        return $this->parse($value, '/^\d{4}-\d{2}$/', 'Y-m');
    }

    private function parse(mixed $value, string $pattern, string $format): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match($pattern, $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC');
        } catch (Exception) {
            return null;
        }

        // Rejects overflowing values such as 2026-02-31 or 2026-13.
        return $date && $date->format($format) === $value ? $date : null;
    }

    /**
     * @return array{label: string, prev: string, next: string, today: string, allDays: string, days: list<array<string, mixed>>}
     */
    private function calendar(CarbonImmutable $month, ?CarbonImmutable $selected, CarbonImmutable $today, Closure $url): array
    {
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();

        $stats = Todo::query()->toBase()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('due_date, count(*) as total, coalesce(sum(completed), 0) as done')
            ->groupBy('due_date')
            ->get()
            ->keyBy('due_date');

        $days = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $stats->get($key);
            $total = (int) ($row->total ?? 0);
            $done = (int) ($row->done ?? 0);

            $days[] = [
                'number' => $day->day,
                'inMonth' => $day->month === $month->month,
                'isToday' => $key === $today->toDateString(),
                'isSelected' => $key === $selected?->toDateString(),
                'open' => $total - $done,
                'done' => $done,
                'url' => $url(['date' => $key, 'month' => null]),
                'label' => $day->format('l j F').', '.($total === 0 ? 'nothing planned' : "$done of $total done"),
            ];
        }

        return [
            'label' => $month->format('F Y'),
            'prev' => $url(['month' => $month->subMonth()->format('Y-m')]),
            'next' => $url(['month' => $month->addMonth()->format('Y-m')]),
            'today' => $url(['date' => null, 'month' => null]),
            'allDays' => $url(['date' => 'all', 'month' => null]),
            'days' => $days,
        ];
    }
}
