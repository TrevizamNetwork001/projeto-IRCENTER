<?php
namespace App\Modules\Scheduling\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['appointment_id','event_type','metadata','actor_type','actor_id','created_at'])]
class AppointmentEvent extends Model { public $timestamps=false; protected $table='scheduling_appointment_events'; public function appointment(): BelongsTo{return $this->belongsTo(Appointment::class);} protected function casts(): array{return ['metadata'=>'array','created_at'=>'immutable_datetime'];} }
