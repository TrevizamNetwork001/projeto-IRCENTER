<?php
namespace App\Modules\Scheduling\Services;
use App\Models\AuditLog;
final class SchedulingAudit { public function record(string $action, int|string $id, ?int $actor=null, array $values=[]): void { unset($values['token'],$values['cancellation_token'],$values['reschedule_token']); AuditLog::query()->create(['user_id'=>$actor,'action'=>$action,'resource_type'=>'appointment','resource_id'=>(string)$id,'resource_label'=>'Agenda','old_values'=>null,'new_values'=>$values,'ip_address'=>request()?->ip(),'user_agent'=>mb_substr((string)request()?->userAgent(),0,255)]); } }
