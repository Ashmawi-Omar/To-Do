@extends('layouts.app')

@section('content')
    <div class="layout">
        @include('todos._calendar')

        <div class="day-panel">
            <h2>
                @if ($selected)
                    {{ $selected->format('l, j F Y') }}
                    @if ($selected->isSameDay($today)) <span class="badge">Today</span> @endif
                @else
                    All days
                @endif
            </h2>

            @if (session('status'))
                <p class="notice" role="status">
                    {{ session('status') }}
                    <a href="{{ $url(['date' => session('status_date'), 'month' => null]) }}">Go to that day</a>
                </p>
            @endif

            <form method="POST" action="{{ route('todos.store') }}" class="add-form">
                @csrf
                <input type="text" name="title" placeholder="What needs to be done?" maxlength="255"
                       autocomplete="off" autofocus required aria-label="New to-do">
                <textarea name="description" placeholder="Notes (optional)" aria-label="Description"></textarea>
                <input type="date" name="due_date" value="{{ ($selected ?? $today)->toDateString() }}" aria-label="Date">
                <button type="submit">Add</button>
            </form>

            @error('title')
                <p class="error" role="alert">{{ $message }}</p>
            @enderror
            @error('due_date')
                <p class="error" role="alert">{{ $message }}</p>
            @enderror

            <nav class="filters" aria-label="Filter to-dos">
                @foreach (['all' => 'All', 'active' => 'Active', 'completed' => 'Completed'] as $key => $label)
                    <a href="{{ $url(['filter' => $key === 'all' ? null : $key]) }}"
                       @class(['active' => $filter === $key])
                       @if ($filter === $key) aria-current="page" @endif>
                        {{ $label }} <span class="count">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <ul class="todos">
                @forelse ($todos as $todo)
                    <li @class(['todo', 'done' => $todo->completed]) x-data="{ editing: false }">
                        <form method="POST" action="{{ route('todos.toggle', $todo) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="check"
                                    aria-label="{{ $todo->completed ? 'Mark as active' : 'Mark as completed' }}">
                                @if ($todo->completed) &#10003; @endif
                            </button>
                        </form>

                        <div class="body" x-show="!editing">
                            <span class="title" x-on:dblclick="editing = true; $nextTick(() => $refs.input.select())">{{ $todo->title }}</span>
                            @if ($todo->description)
                                <span class="description">{{ $todo->description }}</span>
                            @endif
                            <span class="meta">
                                @if ($selected === null)
                                    <span class="due">{{ $todo->due_date->format('D, j M Y') }}</span> &middot;
                                @endif
                                Created
                                <time datetime="{{ $todo->created_at->toIso8601String() }}"
                                      x-text="new Date($el.dateTime).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })">
                                    {{ $todo->created_at->format('M j, Y H:i') }}
                                </time>
                            </span>
                        </div>

                        <form method="POST" action="{{ route('todos.update', $todo) }}" class="edit-form"
                              x-show="editing" x-cloak>
                            @csrf
                            @method('PUT')
                            <input type="text" name="title" value="{{ $todo->title }}" maxlength="255" required
                                   x-ref="input" x-on:keydown.escape="editing = false; $refs.input.value = @js($todo->title)"
                                   aria-label="Edit to-do">
                            <textarea name="description" placeholder="Notes (optional)" aria-label="Description">{{ $todo->description }}</textarea>
                            <input type="date" name="due_date" value="{{ $todo->due_date->toDateString() }}" required
                                   aria-label="Date">
                            <button type="submit" class="save-btn">Save</button>
                            <button type="button" class="cancel-btn" 
                                x-on:click="editing = false; $refs.input.value = @js($todo->title); $refs.desc.value = @js($todo->description ?? ''); $refs.date.value = @js($todo->due_date->toDateString())">
                                Undo
                            </button>
                        </form>

                        <button type="button" class="link" x-show="!editing"
                                x-on:click="editing = true; $nextTick(() => $refs.input.select())">Edit</button>

                        <form method="POST" action="{{ route('todos.destroy', $todo) }}" x-show="!editing">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="delete" aria-label="Delete to-do">&times;</button>
                        </form>
                    </li>
                @empty
                    <li class="empty">
                        @if ($filter === 'active') Nothing left to do. ðŸŽ‰
                        @elseif ($filter === 'completed') Nothing completed yet.
                        @elseif ($selected) Nothing planned for this day. Add something above!
                        @else No to-dos yet. Add one above!
                        @endif
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
