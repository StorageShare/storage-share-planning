@component('mail::message')
# Planning beschikbaar

Er staat een nieuwe planning voor je klaar!

**Datum:** {{ $planning->planned_date->format('d-m-Y') }}
**Locaties:** {{ $planning->locations->pluck('name')->join(', ') ?: 'Nog geen locatie(s)' }}
@if($planning->start_time)
**Starttijd:** {{ $planning->start_time }}
@endif
@if($planning->start_address)
**Startadres:** {{ $planning->start_address }}
@endif

**Aantal taken:** {{ $planning->planningTasks->count() }}

@if($planning->notes)
**Notities:** {{ $planning->notes }}
@endif

Klik op de knop hieronder om je planning te bekijken en te starten:

@component('mail::button', ['url' => route('my-planning.planning', $planning)])
Bekijk mijn planning
@endcomponent

Bedankt,<br>
{{ config('app.name') }}
@endcomponent 