<section class="calendar" aria-label="Calendar">
    <header>
        <a href="{{ $calendar['prev'] }}" aria-label="Previous month">&lsaquo;</a>
        <strong>{{ $calendar['label'] }}</strong>
        <a href="{{ $calendar['next'] }}" aria-label="Next month">&rsaquo;</a>
    </header>

    <div class="cal-grid weekdays" aria-hidden="true">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
            <span>{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="cal-grid">
        @foreach ($calendar['days'] as $day)
            <a href="{{ $day['url'] }}"
               @class(['cal-day', 'outside' => ! $day['inMonth'], 'today' => $day['isToday'], 'selected' => $day['isSelected']])
               aria-label="{{ $day['label'] }}"
               @if ($day['isSelected']) aria-current="date" @endif>
                <span class="num">{{ $day['number'] }}</span>
                <span class="dots" aria-hidden="true">
                    @if ($day['open'] > 0) <i class="dot open"></i> @endif
                    @if ($day['done'] > 0) <i class="dot done"></i> @endif
                </span>
            </a>
        @endforeach
    </div>

    <footer>
        <a href="{{ $calendar['today'] }}">Today</a>
        <a href="{{ $calendar['allDays'] }}" @class(['active' => $selected === null])>All days</a>
        <span class="legend"><i class="dot open"></i> open <i class="dot done"></i> done</span>
    </footer>
</section>
