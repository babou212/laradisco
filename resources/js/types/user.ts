export type UserStatusType = 'online' | 'idle' | 'dnd' | 'offline';

export interface User {
    id: number;
    username: string;
    email: string;
    email_verified_at: string | null;
    nickname: string | null;
    avatar_path: string | null;
    about_me: string | null;
    custom_status: string | null;
    last_seen_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface OnlineUser {
    id: number;
    username: string;
    display_name: string;
    avatar_path: string | null;
    custom_status: string | null;
    // status is client-side only - determined by WebSocket presence
    status?: UserStatusType;
}
