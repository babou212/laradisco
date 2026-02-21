import axios from 'axios';
import {
    Room,
    RoomEvent,
    Track,
    type RemoteParticipant,
    type LocalParticipant,
} from 'livekit-client';
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import echo from '@/lib/echo';

export interface VoiceParticipant {
    id: number;
    username: string;
    displayName: string;
    avatarPath: string | null;
    isMuted: boolean;
    isSpeaking: boolean;
}

interface VoiceChannelInfo {
    id: number;
    name: string;
}

const VOICE_SESSION_KEY = 'voice_channel_state';

export const useVoiceStore = defineStore('voice', () => {
    const isConnected = ref(false);
    const isConnecting = ref(false);
    const currentChannel = ref<VoiceChannelInfo | null>(null);

    const isMicMuted = ref(false);
    const isSoundMuted = ref(false);

    let room: Room | null = null;
    let isReconnecting = false;

    const participants = ref<Map<string, VoiceParticipant>>(new Map());

    const channelParticipants = ref<Map<number, VoiceParticipant[]>>(new Map());

    const currentParticipants = computed<VoiceParticipant[]>(() => {
        return Array.from(participants.value.values());
    });

    /**
     * Get participants for a specific voice channel (from event-based tracking)
     */
    function getChannelParticipants(channelId: number): VoiceParticipant[] {
        return channelParticipants.value.get(channelId) ?? [];
    }

    /**
     * Initialize a voice channel's participants from server-provided data.
     * Called on page load with data from Inertia props.
     */
    function initializeChannelParticipants(
        channelId: number,
        participants: Array<{ id: number; username: string; display_name: string; avatar_path: string | null }>,
    ): void {
        const participantList = participants.map((u) => ({
            id: u.id,
            username: u.username,
            displayName: u.display_name,
            avatarPath: u.avatar_path,
            isMuted: false,
            isSpeaking: false,
        }));
        channelParticipants.value.set(channelId, participantList);
    }

    /**
     * Subscribe to a voice channel's join/leave events via a private channel.
     * Unlike presence channels, this does NOT register the listener as a participant.
     */
    function subscribeToChannelPresence(channelId: number): void {
        echo.private(`voice.channel.${channelId}`)
            .listen('.voice.joined', (data: { user: { id: number; username: string; display_name: string; avatar_path: string | null }; channel_id: number }) => {
                const current = channelParticipants.value.get(channelId) ?? [];
                if (!current.some((p) => p.id === data.user.id)) {
                    current.push({
                        id: data.user.id,
                        username: data.user.username,
                        displayName: data.user.display_name,
                        avatarPath: data.user.avatar_path,
                        isMuted: false,
                        isSpeaking: false,
                    });
                    channelParticipants.value.set(channelId, [...current]);
                }
            })
            .listen('.voice.left', (data: { user_id: number; channel_id: number }) => {
                const current = channelParticipants.value.get(channelId) ?? [];
                channelParticipants.value.set(
                    channelId,
                    current.filter((p) => p.id !== data.user_id),
                );
            });
    }

    /**
     * Unsubscribe from a voice channel's events.
     */
    function unsubscribeFromChannelPresence(channelId: number): void {
        echo.leave(`voice.channel.${channelId}`);
        channelParticipants.value.delete(channelId);
    }

    /**
     * Join a voice channel — connects to LiveKit via token from backend.
     */
    async function joinChannel(channelId: number, channelName: string): Promise<void> {
        // If already in a channel, leave first
        if (isConnected.value && currentChannel.value) {
            await leaveChannel();
        }

        isConnecting.value = true;

        try {
            // Request token from backend
            const response = await axios.post(`/channels/${channelId}/voice/join`);
            const { token, url } = response.data;

            // Create and configure the LiveKit room
            room = new Room({
                adaptiveStream: true,
                dynacast: true,
            });

            // Wire up event handlers before connecting
            setupRoomEventHandlers(room);

            // Connect to the room
            await room.connect(url, token);

            // Enable microphone (audio only — no video)
            await room.localParticipant.setMicrophoneEnabled(true);

            // Set state
            currentChannel.value = { id: channelId, name: channelName };
            isConnected.value = true;
            isMicMuted.value = false;
            isSoundMuted.value = false;

            // Add local participant
            addLocalParticipant(room.localParticipant);

            // Add existing remote participants
            room.remoteParticipants.forEach((participant) => {
                addRemoteParticipant(participant);
            });
        } catch (error) {
            console.error('Failed to join voice channel:', error);
            await cleanupRoom();
            throw error;
        } finally {
            isConnecting.value = false;
        }
    }

    /**
     * Leave the current voice channel.
     */
    async function leaveChannel(): Promise<void> {
        if (!isConnected.value || !currentChannel.value) {
            return;
        }

        const channelId = currentChannel.value.id;

        try {
            await axios.post(`/channels/${channelId}/voice/leave`);
        } catch {
            // Best-effort — the server will detect disconnection anyway
        }

        await cleanupRoom();
    }

    /**
     * Toggle microphone mute state.
     */
    async function toggleMic(): Promise<void> {
        if (!room || !isConnected.value) {
            return;
        }

        const newMuted = !isMicMuted.value;
        await room.localParticipant.setMicrophoneEnabled(!newMuted);
        isMicMuted.value = newMuted;

        // Update local participant's mute state
        const localId = room.localParticipant.identity;
        const participant = participants.value.get(localId);
        if (participant) {
            participant.isMuted = newMuted;
            participants.value.set(localId, { ...participant });
        }
    }

    /**
     * Toggle sound (deafen) state — mutes all incoming audio.
     */
    function toggleSound(): void {
        if (!room || !isConnected.value) {
            return;
        }

        isSoundMuted.value = !isSoundMuted.value;

        // Mute/unmute all remote audio tracks
        room.remoteParticipants.forEach((participant) => {
            participant.audioTrackPublications.forEach((publication) => {
                if (publication.track) {
                    if (isSoundMuted.value) {
                        publication.track.detach();
                    } else {
                        const element = publication.track.attach();
                        element.style.display = 'none';
                        document.body.appendChild(element);
                    }
                }
            });
        });
    }

    /**
     * Set up LiveKit room event handlers.
     */
    function setupRoomEventHandlers(lkRoom: Room): void {
        // Participant joined
        lkRoom.on(RoomEvent.ParticipantConnected, (participant: RemoteParticipant) => {
            addRemoteParticipant(participant);
        });

        // Participant left
        lkRoom.on(RoomEvent.ParticipantDisconnected, (participant: RemoteParticipant) => {
            participants.value.delete(participant.identity);
            participants.value = new Map(participants.value);
        });

        // Track subscribed — auto-attach audio
        lkRoom.on(RoomEvent.TrackSubscribed, (track) => {
            if (track.kind === Track.Kind.Audio && !isSoundMuted.value) {
                const element = track.attach();
                element.style.display = 'none';
                document.body.appendChild(element);
            }
        });

        // Track unsubscribed — detach audio element
        lkRoom.on(RoomEvent.TrackUnsubscribed, (track) => {
            track.detach();
        });

        // Active speaker changes — show green outline
        lkRoom.on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
            const speakerIdentities = new Set(speakers.map((s) => s.identity));

            participants.value.forEach((participant, identity) => {
                const wasSpeaking = participant.isSpeaking;
                const isNowSpeaking = speakerIdentities.has(identity);

                if (wasSpeaking !== isNowSpeaking) {
                    participant.isSpeaking = isNowSpeaking;
                    participants.value.set(identity, { ...participant });
                }
            });

            participants.value = new Map(participants.value);
        });

        // Disconnection
        lkRoom.on(RoomEvent.Disconnected, () => {
            cleanupRoom();
        });

        // Attempt to handle audio autoplay restrictions
        lkRoom.on(RoomEvent.AudioPlaybackStatusChanged, () => {
            if (!lkRoom.canPlaybackAudio) {
                lkRoom.startAudio();
            }
        });
    }

    /**
     * Add the local participant to the participants map.
     */
    function addLocalParticipant(localParticipant: LocalParticipant): void {
        const userId = parseInt(localParticipant.identity, 10);
        participants.value.set(localParticipant.identity, {
            id: userId,
            username: localParticipant.name ?? localParticipant.identity,
            displayName: localParticipant.name ?? localParticipant.identity,
            avatarPath: null,
            isMuted: false,
            isSpeaking: false,
        });
        participants.value = new Map(participants.value);
    }

    /**
     * Add a remote participant to the participants map.
     */
    function addRemoteParticipant(participant: RemoteParticipant): void {
        const userId = parseInt(participant.identity, 10);
        participants.value.set(participant.identity, {
            id: userId,
            username: participant.name ?? participant.identity,
            displayName: participant.name ?? participant.identity,
            avatarPath: null,
            isMuted: !participant.isMicrophoneEnabled,
            isSpeaking: participant.isSpeaking,
        });
        participants.value = new Map(participants.value);

        // Listen for mute state changes on this participant
        participant.on('trackMuted', () => {
            const p = participants.value.get(participant.identity);
            if (p) {
                p.isMuted = true;
                participants.value.set(participant.identity, { ...p });
                participants.value = new Map(participants.value);
            }
        });

        participant.on('trackUnmuted', () => {
            const p = participants.value.get(participant.identity);
            if (p) {
                p.isMuted = false;
                participants.value.set(participant.identity, { ...p });
                participants.value = new Map(participants.value);
            }
        });
    }

    /**
     * Clean up the room connection and reset state.
     */
    async function cleanupRoom(): Promise<void> {
        if (room) {
            // Detach all audio elements
            room.remoteParticipants.forEach((participant) => {
                participant.audioTrackPublications.forEach((publication) => {
                    publication.track?.detach();
                });
            });

            room.disconnect();
            room = null;
        }

        participants.value = new Map();
        currentChannel.value = null;
        isConnected.value = false;
        isConnecting.value = false;
        isMicMuted.value = false;
        isSoundMuted.value = false;
    }

    /**
     * Save voice channel state to sessionStorage before page unload
     * so we can reconnect after a refresh.
     */
    function handleBeforeUnload(): void {
        if (currentChannel.value && isConnected.value) {
            sessionStorage.setItem(VOICE_SESSION_KEY, JSON.stringify(currentChannel.value));
        }

        // Disconnect from LiveKit synchronously (best-effort cleanup)
        if (room) {
            room.disconnect();
            room = null;
        }
    }

    /**
     * Attempt to reconnect to a voice channel after a page refresh.
     * Reads saved state from sessionStorage and auto-rejoins.
     */
    async function attemptReconnect(): Promise<void> {
        const saved = sessionStorage.getItem(VOICE_SESSION_KEY);
        sessionStorage.removeItem(VOICE_SESSION_KEY);

        if (!saved || isConnected.value || isConnecting.value || isReconnecting) {
            return;
        }

        isReconnecting = true;

        try {
            const channel = JSON.parse(saved) as VoiceChannelInfo;
            await joinChannel(channel.id, channel.name);
        } catch {
            // Reconnection failed — clean up stale server-side cache
            try {
                const channel = JSON.parse(saved) as VoiceChannelInfo;
                await axios.post(`/channels/${channel.id}/voice/leave`);
            } catch {
                // Best-effort cleanup
            }
        } finally {
            isReconnecting = false;
        }
    }

    // Register beforeunload handler to persist voice state across refreshes
    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', handleBeforeUnload);
    }

    return {
        isConnected,
        isConnecting,
        currentChannel,
        isMicMuted,
        isSoundMuted,
        participants,
        currentParticipants,
        channelParticipants,
        joinChannel,
        leaveChannel,
        toggleMic,
        toggleSound,
        getChannelParticipants,
        initializeChannelParticipants,
        subscribeToChannelPresence,
        unsubscribeFromChannelPresence,
        attemptReconnect,
    };
});
