@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">End Checklist Beoordelingen</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Beoordeel ingediende end checklists van medewerkers</p>
    </div>

    <div x-data="endChecklistReview()" x-init="init()">
        <template x-if="loading">
            <div class="flex justify-center items-center py-12">
                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </template>

        <template x-if="!loading && pendingChecklists.length === 0">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Geen te beoordelen checklists</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Er zijn momenteel geen end checklists die beoordeling nodig hebben.</p>
            </div>
        </template>

        <template x-if="!loading && pendingChecklists.length > 0">
            <div class="space-y-6">
                <template x-for="planning in pendingChecklists" :key="planning.id">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        {{-- Planning Header --}}
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Planning van <span x-text="new Date(planning.planned_date).toLocaleDateString('nl-NL')"></span>
                                    </h3>
                                    <div class="mt-1 flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span>Locaties: <span x-text="planning.locations.map(l => l.name).join(', ')"></span></span>
                                        <span>Medewerkers: <span x-text="planning.users.map(u => u.name).join(', ')"></span></span>
                                    </div>
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="getPlanningStatusBadge(planning).class"
                                          x-text="getPlanningStatusBadge(planning).text">
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Checklist Items --}}
                        <div class="px-6 py-4">
                            <div class="grid gap-4">
                                <template x-for="item in planning.end_checklist_items" :key="item.id">
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-start space-x-3">
                                                <div class="flex-shrink-0">
                                                    <template x-if="item.type === 'material'">
                                                        <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"></path>
                                                            </svg>
                                                        </div>
                                                    </template>
                                                    <template x-if="item.type === 'end_action'">
                                                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                            </svg>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.title"></h4>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="item.description"></p>
                                                    <div class="mt-2" x-show="item.photo_url">
                                                        <img :src="item.photo_url" :alt="item.title" class="w-24 h-24 object-cover rounded border cursor-pointer" @click="openReviewModal(item)">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end space-y-2">
                                                {{-- Status Badge --}}
                                                <template x-if="item.status === 'approved'">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Goedgekeurd
                                                    </span>
                                                </template>
                                                <template x-if="item.status === 'rejected'">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Afgewezen
                                                    </span>
                                                </template>
                                                <template x-if="item.status === 'pending'">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                        Wacht op beoordeling
                                                    </span>
                                                </template>
                                                
                                                {{-- Review Button --}}
                                                <template x-if="item.status === 'pending'">
                                                    <button @click="openReviewModal(item)" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                                                        Beoordelen
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        {{-- Admin Notes --}}
                                        <div x-show="item.admin_notes" class="mt-3 p-2 bg-gray-50 dark:bg-gray-700 rounded text-sm">
                                            <strong>Admin opmerkingen:</strong> <span x-text="item.admin_notes"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Review Modal --}}
        <div x-show="isReviewModalOpen" 
             x-transition.opacity 
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Checklist Item Beoordelen</h3>
                    <button @click="isReviewModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <template x-if="reviewingItem">
                    <div>
                        {{-- Item Details --}}
                        <div class="mb-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100" x-text="reviewingItem.title"></h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1" x-text="reviewingItem.description"></p>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="reviewingItem.type === 'material' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="reviewingItem.type === 'material' ? 'Materiaal' : 'Eind Actie'">
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Photo --}}
                        <div class="mb-6" x-show="reviewingItem.photo_url">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ingediende foto:</label>
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                <img :src="reviewingItem.photo_url" :alt="reviewingItem.title" class="w-full h-96 object-contain bg-gray-50 dark:bg-gray-700">
                            </div>
                        </div>

                        {{-- Review Form --}}
                        <form @submit.prevent="submitReview()" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beslissing</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" x-model="reviewStatus" value="approved" class="form-radio text-green-600">
                                        <span class="ml-2 text-green-700 dark:text-green-300">Goedkeuren</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" x-model="reviewStatus" value="rejected" class="form-radio text-red-600">
                                        <span class="ml-2 text-red-700 dark:text-red-300">Afwijzen</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="admin_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Opmerkingen 
                                    <span x-show="reviewStatus === 'rejected'" class="text-red-500">(verplicht bij afwijzing)</span>
                                </label>
                                <textarea x-model="adminNotes" 
                                          id="admin_notes" 
                                          rows="3"
                                          :required="reviewStatus === 'rejected'"
                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"
                                          placeholder="Voeg eventuele opmerkingen toe..."></textarea>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" 
                                        @click="isReviewModalOpen = false"
                                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                    Annuleren
                                </button>
                                <button type="submit" 
                                        :disabled="!reviewStatus || isSubmitting"
                                        class="px-4 py-2 rounded-md text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="reviewStatus === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">
                                    <span x-show="!isSubmitting" x-text="reviewStatus === 'approved' ? 'Goedkeuren' : 'Afwijzen'"></span>
                                    <span x-show="isSubmitting">Bezig...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('endChecklistReview', () => ({
        pendingChecklists: [],
        loading: true,
        isReviewModalOpen: false,
        reviewingItem: null,
        reviewStatus: '',
        adminNotes: '',
        isSubmitting: false,
        
        async init() {
            await this.loadPendingChecklists();
        },
        
        async loadPendingChecklists() {
            try {
                const response = await axios.get('/end-checklists/pending');
                this.pendingChecklists = response.data.plannings;
            } catch (error) {
                console.error('Failed to load pending checklists:', error);
                alert('Er is een fout opgetreden bij het laden van de checklists.');
            } finally {
                this.loading = false;
            }
        },
        
        openReviewModal(item) {
            this.reviewingItem = item;
            this.reviewStatus = '';
            this.adminNotes = '';
            this.isReviewModalOpen = true;
        },
        
        async submitReview() {
            if (!this.reviewingItem || !this.reviewStatus) return;
            
            this.isSubmitting = true;
            
            try {
                await axios.post(`/end-checklist-items/${this.reviewingItem.id}/review`, {
                    status: this.reviewStatus,
                    admin_notes: this.adminNotes
                });
                
                // Update the item in the UI
                this.reviewingItem.status = this.reviewStatus;
                this.reviewingItem.admin_notes = this.adminNotes;
                this.reviewingItem.reviewed_at = new Date().toISOString();
                
                // Close modal and reset form
                this.isReviewModalOpen = false;
                this.reviewingItem = null;
                this.reviewStatus = '';
                this.adminNotes = '';
                
                // Reload data to get updated status
                await this.loadPendingChecklists();
                
                alert('Beoordeling succesvol opgeslagen!');
            } catch (error) {
                console.error('Review submission failed:', error);
                alert(error.response?.data?.message || 'Er is een fout opgetreden bij het opslaan van de beoordeling.');
            } finally {
                this.isSubmitting = false;
            }
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleString('nl-NL');
        },
        
        getPlanningStatusBadge(planning) {
            const allApproved = planning.end_checklist_items.every(item => item.status === 'approved');
            const hasRejected = planning.end_checklist_items.some(item => item.status === 'rejected');
            
            if (allApproved) {
                return { class: 'bg-green-100 text-green-800', text: 'Volledig goedgekeurd' };
            } else if (hasRejected) {
                return { class: 'bg-red-100 text-red-800', text: 'Gedeeltelijk afgewezen' };
            } else {
                return { class: 'bg-orange-100 text-orange-800', text: 'In behandeling' };
            }
        }
    }));
});
</script>
@endpush
@endsection 