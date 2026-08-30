<?php
namespace App\Modules\Scheduling\Models;
use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
#[Fillable(['event_type_id','client_id','scheduled_start_at','scheduled_end_at','timezone','status','guest_name','guest_email','guest_phone','guest_company','notes','cancellation_token_hash','reschedule_token_hash','created_by','cancelled_at','completed_at'])]
class Appointment extends Model {
 use BelongsToClient;
 protected $table='scheduling_appointments';
 protected static function booted(): void { static::creating(fn(self $m)=>$m->public_id??=(string)Str::ulid()); }
 public function eventType(): BelongsTo{return $this->belongsTo(EventType::class);} public function client(): BelongsTo{return $this->belongsTo(\App\Models\Client::class);}
 public function events(): HasMany{return $this->hasMany(AppointmentEvent::class);} public function attendees(): HasMany{return $this->hasMany(AppointmentAttendee::class);}
 public function getRouteKeyName(): string{return 'public_id';}
 public function statusLabel(): string{return match($this->status){'confirmed'=>'Confirmado','cancelled'=>'Cancelado','completed'=>'Concluído','no_show'=>'Não compareceu',default=>'Agendado'};}
 protected function casts(): array{return ['scheduled_start_at'=>'immutable_datetime','scheduled_end_at'=>'immutable_datetime','cancelled_at'=>'immutable_datetime','completed_at'=>'immutable_datetime'];}
}
