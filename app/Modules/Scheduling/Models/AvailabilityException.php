<?php
namespace App\Modules\Scheduling\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['event_type_id','date','start_time','end_time','type','reason','created_by'])]
class AvailabilityException extends Model { protected $table='scheduling_availability_exceptions'; public function eventType(): BelongsTo{return $this->belongsTo(EventType::class);} protected function casts(): array{return ['date'=>'date'];} }
