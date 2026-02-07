import { router } from '@inertiajs/vue3';
import type { UserStatusType } from '@/types';

/**
 * Composable for updating the current user's presence status
 */
export function usePresenceUpdater() {
    const updateStatus = (
        status: UserStatusType,
        customStatus: string | null = null,
    ) => {
        router.post(
            '/presence',
            {
                status,
                custom_status: customStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: [],
            },
        );
    };

    return {
        updateStatus,
    };
}
