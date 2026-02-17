<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Taak afgerond</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; }
        .header { background: #f8f9fa; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .section { margin-bottom: 20px; }
        .label { font-weight: bold; display: block; margin-bottom: 5px; }
        .photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .photo-item img { max-width: 100%; height: auto; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Taak afgerond en goedgekeurd</h2>
        </div>

        @if($planningTask->feedback_owner_name)
            <p>Beste {{ $planningTask->feedback_owner_name }},</p>
        @else
            <p>Beste,</p>
        @endif

        <p>De volgende taak is succesvol afgerond en goedgekeurd:</p>

        <div class="section">
            <span class="label">Locatie:</span>
            {{ $planningTask->location->name ?? 'Onbekend' }}
        </div>

        <div class="section">
            <span class="label">Taak:</span>
            {{ $planningTask->title }}
            <p>{{ $planningTask->description }}</p>
        </div>

        <div class="section">
            <span class="label">Opmerkingen bij afronding:</span>
            {{ $completion->comment ?: 'Geen opmerkingen' }}
        </div>

        @if($planningTask->feedback_information)
            <div class="section">
                <span class="label">Wat moet er gebeuren na het uitvoeren van deze taak:</span>
                {{ $planningTask->feedback_information }}
            </div>
        @endif

        @if($completion->photos->isNotEmpty())
            <div class="section">
                <span class="label">Foto's:</span>
                <div class="photo-grid">
                    @foreach($completion->photos as $photo)
                        <div class="photo-item">
                            {{-- We gebruiken hier de publieke URL van de foto --}}
                            <img src="{{ config('app.url') . '/storage/' . $photo->file_path }}" alt="Foto afronding">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <p>Met vriendelijke groet,<br>
        {{ config('app.name') }}</p>
    </div>
</body>
</html>
