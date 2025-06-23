@component('mail::message')
# Nieuwe taak ter beoordeling

Er is een nieuwe taak die wacht op uw beoordeling.

**Taak:** {{ $task->title }}
**Locatie:** {{ $task->location->name }}
**Aangemaakt door:** {{ $task->creator->name }}

Je kunt de taak hier bekijken en beoordelen:

@component('mail::button', ['url' => route('admin.tasks.review')])
Bekijk taken
@endcomponent

Bedankt,<br>
{{ config('app.name') }}
@endcomponent 