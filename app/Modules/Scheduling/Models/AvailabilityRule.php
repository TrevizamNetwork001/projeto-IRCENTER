<?php
namespace App\Modules\Scheduling\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['event_type_id','day_of_week','start_time','end_time','timezone','active'])]
class AvailabilityRule extends Model { protected $table='scheduling_availability_rules'; public function eventType(): BelongsTo{return $this->belongsTo(EventType::class);} protected function casts(): array{return ['active'=>'boolean'];} }
