<nav class="scheduling-nav" aria-label="Agenda">
 <a href="{{ route('scheduling.admin.index') }}">Agendamentos</a>
 @if(auth()->user()->canOperate())<a href="{{ route('scheduling.admin.appointments.create') }}">Novo agendamento</a>@endif
 <a href="{{ route('scheduling.admin.calendar',['view'=>'day']) }}">Dia</a><a href="{{ route('scheduling.admin.calendar',['view'=>'week']) }}">Semana</a><a href="{{ route('scheduling.admin.calendar',['view'=>'month']) }}">Mês</a><a href="{{ route('scheduling.admin.event-types') }}">Tipos e disponibilidade</a>
</nav>
