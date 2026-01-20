<x-modal name="location-map" maxWidth="4xl">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Locatie Kaart
            </h3>
            <button x-on:click="$dispatch('close-modal', 'location-map')" class="text-gray-400 hover:text-gray-500">
                <span class="sr-only">Sluiten</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="relative w-full h-[600px] rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700">
            <div id="google-map" class="w-full h-full" style="min-height: 80vh;"></div>
            <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800">
                <span class="text-gray-500 dark:text-gray-400">Kaart laden...</span>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="button" x-on:click="$dispatch('close-modal', 'location-map')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors duration-150 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Sluiten
            </button>
        </div>
    </div>
</x-modal>

@push('scripts')
<script>
    (g=>{var  a,m,p,t,k,e=window.google=window.google||{},h=e.maps=e.maps||{},s=h.importLibrary=h.importLibrary||((...a)=>h.load.apply(h,a));if(!h.url){p=new URLSearchParams(Object.entries({key:"{{ config('services.google_maps.api_key') }}",v:"weekly"}));t=document.createElement("script");t.src="https://maps.googleapis.com/maps/api/js?"+p;t.async=!0;h.url=t.src;t.onerror=()=>h.mapLoadError=!0;document.head.append(t)}})();

    let map;
    let markers = [];

    async function initMap() {
        const { Map } = await google.maps.importLibrary("maps");
        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

        const locations = {!! $locations->map(fn($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'lat' => $l->latitude,
            'lng' => $l->longitude,
            'address' => $l->full_address
        ])->toJson() !!};

        // Filter out locations without coordinates
        const validLocations = locations.filter(l => l.lat && l.lng);

        if (validLocations.length === 0) {
            document.getElementById('map-loading').innerHTML = '<span class="text-red-500">Geen locaties met coördinaten gevonden.</span>';
            return;
        }

        // Default center (Netherlands)
        const center = { lat: 52.1326, lng: 5.2913 };

        map = new Map(document.getElementById("google-map"), {
            zoom: 7,
            center: center,
            mapId: "LOCATION_MAP_ID",
        });

        const bounds = new google.maps.LatLngBounds();

        validLocations.forEach(location => {
            const position = { lat: parseFloat(location.lat), lng: parseFloat(location.lng) };

            const marker = new AdvancedMarkerElement({
                map: map,
                position: position,
                title: location.name,
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `<div class="p-2 text-gray-900">
                            <h4 class="font-bold">${location.name}</h4>
                            <p class="text-sm">${location.address}</p>
                          </div>`,
            });

            marker.addListener("click", () => {
                infoWindow.open({
                    anchor: marker,
                    map,
                });
            });

            bounds.extend(position);
            markers.push(marker);
        });

        if (validLocations.length > 0) {
            map.fitBounds(bounds);
        }

        document.getElementById('map-loading').style.display = 'none';
    }

    function addLocationFromMap(locationId) {
        const btn = document.querySelector(`.add-location-btn[data-location-id="${locationId}"]`);
        if (btn) {
            btn.click();
            // Optional: visual feedback in infoWindow
            alert('Locatie toegevoegd aan planning');
        }
    }

    // Initialize map when modal opens
    window.addEventListener('open-modal', (event) => {
        if (event.detail === 'location-map') {
            if (!map) {
                // Short delay to ensure modal is visible and div has dimensions
                setTimeout(() => {
                    initMap();
                }, 200);
            } else {
                // Resize map if already initialized
                google.maps.event.trigger(map, 'resize');
            }
        }
    });
</script>
@endpush
