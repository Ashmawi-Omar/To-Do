<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    protected $fillable = ['title', 'completed', 'due_date'];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('completed', false);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('completed', true);
    }

    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->where('due_date', $date);
    }
}
