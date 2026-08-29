<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name','slug','description','duration_minutes','slot_interval_minutes','location_type','location_value','buffer_before_minutes','buffer_after_minutes','minimum_notice_minutes','maximum_days_ahead','accent','active','host_user_id','created_by'])]
class EventType extends Model
{
    protected $table = 'scheduling_event_types';
    protected static function booted(): void { static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid()); }
    public function rules(): HasMany { return $this->hasMany(AvailabilityRule::class); }
    public function exceptions(): HasMany { return $this->hasMany(AvailabilityException::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function host(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'host_user_id'); }
    public function getRouteKeyName(): string { return 'slug'; }
    protected function casts(): array { return ['active'=>'boolean']; }
}
