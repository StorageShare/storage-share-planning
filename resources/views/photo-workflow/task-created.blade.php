<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taak Aangemaakt</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center">
        <div class="mb-4 text-green-500">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Taak Succesvol Aangemaakt</h1>
        <p class="text-gray-600 mb-6">De taak "<strong>{{ $task->title }}</strong>" is toegevoegd aan de planning voor locatie {{ $task->location->name }}.</p>
        <p class="text-sm text-gray-500 mb-8">U kunt dit venster nu sluiten.</p>
    </div>
</body>
</html>
