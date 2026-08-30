@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(()=>{
 const calendar=document.querySelector('#booking-calendar'),slots=document.querySelector('#booking-slots'),form=document.querySelector('#booking-form'),start=document.querySelector('#selected-start'),selectedZone=document.querySelector('#selected-timezone'),label=document.querySelector('#month-label'),times=document.querySelector('#booking-times'),dateLabel=document.querySelector('#selected-date-label'),picker=document.querySelector('#booking-picker'),formPanel=document.querySelector('#booking-form-panel'),back=document.querySelector('#booking-back'),contextSelection=document.querySelector('#context-selection');
 const shell=document.querySelector('#booking-shell'),calendarActions=document.querySelector('#calendar-step-actions'),calendarContinue=document.querySelector('#calendar-continue'),formBack=document.querySelector('#form-back'),formContinue=document.querySelector('#form-continue'),reviewPanel=document.querySelector('#booking-review-panel'),reviewBack=document.querySelector('#review-back');
 let month=new Date(),selected=null,selectedButton=null,selectedSlot=null;month.setDate(1);
 const calendarUrl=@json($calendarUrl),availabilityUrl=@json($availabilityUrl),token=@json($token),formMode=@json($formMode ?? false),durationMinutes=@json($durationMinutes ?? null),zoneValue=selectedZone.value;
 const monthKey=()=>month.getFullYear()+'-'+String(month.getMonth()+1).padStart(2,'0');
 const capitalize=text=>text.charAt(0).toUpperCase()+text.slice(1);
 const fullDate=date=>capitalize(new Intl.DateTimeFormat('pt-BR',{weekday:'long',day:'numeric',month:'long',timeZone:'UTC'}).format(new Date(date+'T12:00:00Z')));
 const plainDate=date=>new Intl.DateTimeFormat('pt-BR',{day:'2-digit',month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(date+'T12:00:00Z'));
 const timeRange=slot=>slot.label+' – '+new Date(slot.end).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',timeZone:zoneValue});
 const contextDate=document.querySelector('#context-date'),contextTime=document.querySelector('#context-time');
 const setContext=()=>{if(!selectedSlot||!contextSelection)return;contextSelection.hidden=false;contextDate.textContent=plainDate(selected);contextTime.textContent=timeRange(selectedSlot);};
 const setStage=stage=>{if(shell)shell.dataset.bookingStage=stage;};

 function chooseSlot(button,slot){
  selectedSlot=slot;
  slots.querySelectorAll('.slot-row').forEach(row=>row.classList.remove('is-selected'));
  slots.querySelectorAll('.slot-button').forEach(item=>item.setAttribute('aria-pressed','false'));
  button.setAttribute('aria-pressed','true');
  button.closest('.slot-row').classList.add('is-selected');
  start.value=slot.start;selectedZone.value=zoneValue;
  setContext();
  if(calendarContinue)calendarContinue.disabled=false;
 }

 async function selectDate(button,date){
  selected=date;selectedButton=button;selectedSlot=null;start.value='';
  if(calendarContinue)calendarContinue.disabled=true;
  calendar.querySelectorAll('.calendar-date').forEach(item=>{item.classList.remove('is-selected');item.setAttribute('aria-selected','false');});
  button.classList.add('is-selected');button.setAttribute('aria-selected','true');
  times.hidden=false;if(calendarActions)calendarActions.hidden=false;
  dateLabel.textContent=fullDate(date);
  slots.innerHTML='<p class="loading-state" role="status">Carregando horários…</p>';
  try{
   const url=new URL(availabilityUrl,location.origin);url.searchParams.set('date',date);url.searchParams.set('timezone',zoneValue);if(token)url.searchParams.set('token',token);
   const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();
   const data=await response.json();
   slots.innerHTML=data.slots.length?'':'<p class="empty-state">Os horários deste dia acabaram de ficar indisponíveis. Escolha outra data.</p>';
   data.slots.forEach(slot=>{
    const row=document.createElement('div');row.className='slot-row';
    const button=document.createElement('button');button.type='button';button.className='slot-button';button.textContent=slot.label;button.setAttribute('aria-label','Selecionar '+slot.label);button.setAttribute('aria-pressed','false');
    button.onclick=()=>chooseSlot(button,slot);
    row.append(button);
    if(!formMode){
     const advance=document.createElement('button');advance.type='button';advance.className='button button-primary slot-advance';advance.textContent='Confirmar novo horário';
     advance.onclick=()=>{chooseSlot(button,slot);form.requestSubmit();};
     row.append(advance);
    }
    slots.append(row);
   });
  }catch(e){slots.innerHTML='<p class="alert alert-danger" role="alert">Não foi possível carregar os horários. Tente novamente.</p>';}
 }

 async function loadCalendar(){
  calendar.setAttribute('aria-busy','true');
  calendar.innerHTML='<p class="loading-state" role="status">Carregando calendário…</p>';
  const monthName=new Intl.DateTimeFormat('pt-BR',{month:'long'}).format(month);
  label.textContent=monthName.charAt(0).toUpperCase()+monthName.slice(1)+' '+month.getFullYear();
  try{
   const url=new URL(calendarUrl,location.origin);url.searchParams.set('month',monthKey());url.searchParams.set('timezone',zoneValue);if(token)url.searchParams.set('token',token);
   const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();
   const data=await response.json();
   calendar.innerHTML='';
   const firstSunday=new Date(month.getFullYear(),month.getMonth(),1).getDay();
   const firstMonday=(firstSunday+6)%7;
   for(let i=0;i<firstMonday;i++){const spacer=document.createElement('span');spacer.className='calendar-spacer';calendar.append(spacer);}
   Object.entries(data.days).forEach(([date,status])=>{
    const button=document.createElement('button');button.type='button';button.className='calendar-date status-'+status;button.dataset.date=date;button.disabled=status!=='available';button.textContent=String(Number(date.slice(-2)));
    const today=new Date().toLocaleDateString('en-CA',{timeZone:zoneValue});
    if(date===today)button.setAttribute('aria-current','date');
    const statusLabel=status==='available'?'disponível':status==='unavailable'?'indisponível':'fora do período ou antecedência';
    button.setAttribute('aria-label',fullDate(date)+' — '+statusLabel);
    button.onclick=()=>selectDate(button,date);
    calendar.append(button);
   });
  }catch(e){calendar.innerHTML='<p class="alert alert-danger" role="alert">Não foi possível carregar o calendário. Tente novamente.</p>';}
  finally{calendar.setAttribute('aria-busy','false');}
 }

 document.querySelector('#month-prev').onclick=()=>{month.setMonth(month.getMonth()-1);selected=null;times.hidden=true;if(calendarActions)calendarActions.hidden=true;loadCalendar();};
 document.querySelector('#month-next').onclick=()=>{month.setMonth(month.getMonth()+1);selected=null;times.hidden=true;if(calendarActions)calendarActions.hidden=true;loadCalendar();};
 if(back)back.onclick=()=>{formPanel.hidden=true;picker.hidden=false;if(selectedButton)selectedButton.focus();};

 if(formMode){
  const fillReview=()=>{
   document.querySelector('#review-date').textContent=plainDate(selected);
   document.querySelector('#review-time').textContent=selectedSlot?timeRange(selectedSlot):'';
   document.querySelector('#review-name').textContent=document.querySelector('#guest_name').value;
   document.querySelector('#review-email').textContent=document.querySelector('#guest_email').value;
   const phone=document.querySelector('#guest_phone').value,phoneRow=document.querySelector('#review-phone-row');
   if(phone){phoneRow.hidden=false;document.querySelector('#review-phone').textContent=phone;}else phoneRow.hidden=true;
   const company=document.querySelector('#guest_company').value,companyRow=document.querySelector('#review-company-row');
   if(company){companyRow.hidden=false;document.querySelector('#review-company').textContent=company;}else companyRow.hidden=true;
   const notes=document.querySelector('#notes').value,notesRow=document.querySelector('#review-notes-row');
   if(notes){notesRow.hidden=false;document.querySelector('#review-notes').textContent=notes;}else notesRow.hidden=true;
  };

  if(calendarContinue)calendarContinue.onclick=()=>{
   picker.hidden=true;if(calendarActions)calendarActions.hidden=true;
   formPanel.hidden=false;setStage('form');
   document.querySelector('#guest_name').focus();
  };
  if(formBack)formBack.onclick=()=>{
   formPanel.hidden=true;picker.hidden=false;if(calendarActions)calendarActions.hidden=false;setStage('calendar');
   if(selectedButton)selectedButton.focus();
  };
  if(formContinue)formContinue.onclick=()=>{
   if(!form.reportValidity())return;
   fillReview();
   formPanel.hidden=true;reviewPanel.hidden=false;setStage('review');
   document.querySelector('#review-confirm').focus();
  };
  if(reviewBack)reviewBack.onclick=()=>{
   reviewPanel.hidden=true;formPanel.hidden=false;setStage('form');
  };

  if(!formPanel.hidden){
   picker.hidden=true;setStage('form');
   const oldStart=start.value;
   if(oldStart){
    const parsed=new Date(oldStart);
    const parsedEnd=durationMinutes?new Date(parsed.getTime()+durationMinutes*60000):parsed;
    selected=parsed.toLocaleDateString('en-CA',{timeZone:zoneValue});
    selectedSlot={start:oldStart,end:parsedEnd.toISOString(),label:parsed.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',timeZone:zoneValue})};
    setContext();
   }
  }
 }

 loadCalendar();
})();
</script>
@endpush
