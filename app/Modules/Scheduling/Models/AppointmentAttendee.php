<?php
namespace App\Modules\Scheduling\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['appointment_id','name','email'])]
class AppointmentAttendee extends Model { protected $table='scheduling_appointment_attendees'; }
