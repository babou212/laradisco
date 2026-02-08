import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { search as searchRoute } from '@/routes';

const isOpen = ref(false);
const query = ref('');
const results = ref<any[]>([]);
const isLoading = ref(false);
const scope = ref<'all' | 'channel' | 'dm'>('all');

router.on('start', () => {
    isOpen.value = false;
    query.value = '';
    results.value = [];
});

export function useSearch() {
    const search = async () => {
        if (!query.value.trim()) {
            results.value = [];
            return;
        }

        isLoading.value = true;
        // Parse basic filters from query for UI if needed,
        // but we send full query to backend as implemented.

        try {
            const response = await axios.get(searchRoute().url, {
                params: {
                    query: query.value,
                    type: scope.value,
                    // Pass parsed filters if we separate them in UI
                },
            });
            results.value = response.data.data;
            isOpen.value = true;
        } catch (error) {
            console.error('Search failed:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const toggle = () => {
        isOpen.value = !isOpen.value;
    };

    const close = () => {
        isOpen.value = false;
    };

    const setScope = (newScope: 'all' | 'channel' | 'dm') => {
        scope.value = newScope;
        // Clear results if scope changes to avoid confusion?
        // results.value = [];
    };

    return {
        isOpen,
        query,
        results,
        isLoading,
        scope,
        search,
        toggle,
        close,
        setScope,
    };
}
