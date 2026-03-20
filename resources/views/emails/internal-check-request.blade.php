<x-mail::message>
# Controle verzoek: Ruimte {{ $task->room }} ({{ $task->location->name }})

Er is een controle verzoek voor de bovenstaande ruimte.

**Type verzoek:** {{ $requestType }}

**Verzoek:**
Controleer of de ruimte nog steeds op niet actief (zwart) staat in 1stalling, of er geen nieuwe opmerkingen bij staan en of er geen openstaande mail over is in de mailbox (huur@storage-share.nl).

@if($requestType === 'STRIKKER_CHECK')
Als er nog steeds geen reactie is, kun je via onderstaande knop een nieuwe taak aanmaken voor het plakken van een sticker.

<x-mail::button :url="route('photo-workflow.create-sticker-task', ['task' => $task->id])">
Sticker taak aanmaken
</x-mail::button>
@elseif($requestType === 'SECOND_PHOTO_CHECK')
Als er nog steeds geen reactie is na het plakken van de sticker, kun je via onderstaande knop een nieuwe taak aanmaken voor het maken van een nieuwe foto.

<x-mail::button :url="route('photo-workflow.create-new-photo-task', ['task' => $task->id])">
Nieuwe foto taak aanmaken
</x-mail::button>
@elseif($requestType === 'EVACUATION_CHECK')
Als er na de nieuwe foto nog steeds geen reactie is, kun je via onderstaande knop een nieuwe taak aanmaken voor ontruiming.

<x-mail::button :url="route('photo-workflow.create-evacuation-task', ['task' => $task->id])">
Ontruiming inplannen
</x-mail::button>
@endif

Bedankt!
</x-mail::message>
