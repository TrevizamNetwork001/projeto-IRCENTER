<?php
namespace App\Modules\Scheduling\Actions;
use App\Modules\Scheduling\Models\{Appointment,AppointmentEvent,EventType};
use App\Modules\Scheduling\Services\{SchedulingAudit,SlotGenerator};
use App\Modules\Scheduling\Notifications\AppointmentConfirmedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Notification};
use Illuminate\Validation\ValidationException;
final class CreateAppointment {
 public function __construct(private SlotGenerator $slots, private SchedulingAudit $audit){}
 public function execute(EventType $eventType,array $data,?int $actor=null): array {
  $cancel=bin2hex(random_bytes(32)); $reschedule=bin2hex(random_bytes(32));
  $appointment=DB::transaction(function()use($eventType,$data,$actor,$cancel,$reschedule){
   $locked=EventType::query()->lockForUpdate()->findOrFail($eventType->id); $start=CarbonImmutable::parse($data['start'],$data['timezone']);
   $available=collect($this->slots->generate($locked,$start->format('Y-m-d'),$data['timezone']))->contains('start',$start->toIso8601String());
   if(!$available) throw ValidationException::withMessages(['start'=>'Este horário não está mais disponível.']);
   $item=Appointment::query()->create(['event_type_id'=>$locked->id,'scheduled_start_at'=>$start->utc(),'scheduled_end_at'=>$start->addMinutes($locked->duration_minutes)->utc(),'timezone'=>$data['timezone'],'status'=>'confirmed','guest_name'=>$data['guest_name'],'guest_email'=>mb_strtolower($data['guest_email']),'guest_phone'=>$data['guest_phone']??null,'guest_company'=>$data['guest_company']??null,'notes'=>$data['notes']??null,'cancellation_token_hash'=>hash('sha256',$cancel),'reschedule_token_hash'=>hash('sha256',$reschedule),'created_by'=>$actor]);
   AppointmentEvent::query()->create(['appointment_id'=>$item->id,'event_type'=>'created','metadata'=>['source'=>$actor?'admin':'public'],'actor_type'=>$actor?'user':'guest','actor_id'=>$actor,'created_at'=>now()]);
   $this->audit->record('appointment.created',$item->id,$actor,['public_id'=>$item->public_id]); return $item;
  },3);
  Notification::route('mail',$appointment->guest_email)->notify(new AppointmentConfirmedNotification($appointment,$cancel,$reschedule));
  return compact('appointment','cancel','reschedule');
 }
}
