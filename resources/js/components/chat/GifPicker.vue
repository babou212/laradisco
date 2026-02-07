<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Emits {
    (e: 'select', gifUrl: string): void;
}

const emit = defineEmits<Emits>();

const TENOR_API_KEY = 'AIzaSyAyimkuYQYF_FXVALexPuGQctUWRURdCYQ'; // Default public key
const searchQuery = ref('');
const gifs = ref<any[]>([]);
const loading = ref(false);
const selectedCategory = ref<string>('trending');

const categories = [
    { id: 'trending', label: 'Trending' },
    { id: 'excited', label: 'Excited' },
    { id: 'happy', label: 'Happy' },
    { id: 'sad', label: 'Sad' },
    { id: 'love', label: 'Love' },
    { id: 'angry', label: 'Angry' },
    { id: 'laugh', label: 'Laugh' },
    { id: 'dance', label: 'Dance' },
];

const fetchGifs = async (query?: string) => {
    loading.value = true;
    try {
        const endpoint = query
            ? `https://tenor.googleapis.com/v2/search?q=${encodeURIComponent(query)}&key=${TENOR_API_KEY}&limit=20`
            : `https://tenor.googleapis.com/v2/featured?key=${TENOR_API_KEY}&limit=20`;
        
        const response = await fetch(endpoint);
        const data = await response.json();
        gifs.value = data.results || [];
    } catch (error) {
        console.error('Error fetching GIFs:', error);
        gifs.value = [];
    } finally {
        loading.value = false;
    }
};

const searchGifs = () => {
    if (searchQuery.value.trim()) {
        selectedCategory.value = '';
        fetchGifs(searchQuery.value);
    }
};

const selectCategory = (categoryId: string) => {
    selectedCategory.value = categoryId;
    searchQuery.value = '';
    if (categoryId === 'trending') {
        fetchGifs();
    } else {
        fetchGifs(categoryId);
    }
};

const selectGif = (gif: any) => {
    const gifUrl = gif.media_formats?.gif?.url || gif.media_formats?.tinygif?.url;
    if (gifUrl) {
        emit('select', gifUrl);
    }
};

onMounted(() => {
    fetchGifs();
});
</script>

<template>
    <div class="w-[400px] h-[450px] rounded-lg border border-border bg-background shadow-lg flex flex-col">
        <!-- Search -->
        <div class="p-3 border-b border-border">
            <div class="relative">
                <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search for GIFs"
                    class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    @keydown.enter="searchGifs"
                />
            </div>
        </div>

        <!-- Categories -->
        <div class="flex gap-2 overflow-x-auto p-3 border-b border-border scrollbar-thin">
            <button
                v-for="category in categories"
                :key="category.id"
                class="shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                :class="selectedCategory === category.id
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-accent text-accent-foreground hover:bg-accent/80'"
                @click="selectCategory(category.id)"
            >
                {{ category.label }}
            </button>
        </div>

        <!-- GIF Grid -->
        <div class="flex-1 overflow-y-auto p-3">
            <div v-if="loading" class="flex items-center justify-center h-full">
                <div class="text-sm text-muted-foreground">Loading...</div>
            </div>
            <div v-else-if="gifs.length === 0" class="flex items-center justify-center h-full">
                <div class="text-sm text-muted-foreground">No GIFs found</div>
            </div>
            <div v-else class="grid grid-cols-2 gap-2">
                <button
                    v-for="gif in gifs"
                    :key="gif.id"
                    class="relative aspect-square overflow-hidden rounded-lg bg-accent hover:ring-2 hover:ring-primary transition-all"
                    @click="selectGif(gif)"
                >
                    <img
                        :src="gif.media_formats?.tinygif?.url || gif.media_formats?.gif?.url"
                        :alt="gif.content_description"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                </button>
            </div>
        </div>
    </div>
</template>
