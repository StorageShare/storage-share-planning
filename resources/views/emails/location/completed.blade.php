@component('mail::message')
# Locatie afgerond

Een locatie is zojuist afgerond door een medewerker en wacht op uw beoordeling.

**Locatie:** {{ $location->name }}
**Planning:** {{ $planning->planned_date?->format('d-m-Y') ?? 'Onbekende datum' }}
**Adres:** {{ $location->full_address }}

Alle taken op deze locatie hebben de status 'ter beoordeling' of 'overgeslagen' gekregen en moeten nu door de admin worden beoordeeld.

@component('mail::button', ['url' => route('admin.tasks.review')])
Bekijk taken ter beoordeling
@endcomponent

Bedankt,<br>
{{ config('app.name') }}
@endcomponent 