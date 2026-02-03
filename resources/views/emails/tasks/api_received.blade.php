@component('mail::message')
# Nieuwe taak ontvangen via API

Er is een nieuwe taak aangemaakt via de API en deze staat klaar voor beoordeling.

**Taak:** {{ $task->title }}
**Locatie:** {{ $task->location->name }}
@if($task->description)
**Omschrijving:**
{{ $task->description }}
@endif

Jaap kan deze taak nu bekijken, aanpassen en bepalen of het een interne of externe taak is.

@component('mail::button', ['url' => config('app.url') . '/tasks/' . $task->id])
Bekijk taak
@endcomponent

Bedankt,<br>
{{ config('app.name') }}
@endcomponent
