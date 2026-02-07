# Laradisco — Self-Hosted Private Discord Alternative

## Vision

An open-source, self-hostable private alternative to Discord where users own their own data. Built with Laravel, Vue 3, and Inertia v2.

## Architecture

### Infrastructure

- **Deployment**: Kubernetes cluster
- **Database**: PostgreSQL (with future read replicas)
- **Cache / Pub-Sub**: Redis
- **File Storage**: Self-hosted S3-compatible (MinIO) with persistent storage volumes
- **WebSockets**: Laravel Reverb (multi-pod with Redis pub/sub)
- **Queue**: Laravel Horizon with Redis driver

### Performance Targets

- **Concurrent Users**: 1,000+ minimum
- **Real-time**: Sub-second message delivery via WebSockets
- **Message History**: All messages stored indefinitely

## Core Concepts

### Single Server Model

This application operates as a **single server instance** (not multi-tenant). There is no concept of users creating their own servers — every user belongs to the same server.

### Feature Set (Discord Parity)

#### Text Channels (Phase 1)

- Categories to organize channels
- Text channels within categories
- Channel topics/descriptions
- Pinned messages
- Message history with infinite scroll
- Message editing and deletion
- Message reactions (emoji)
- Message formatting (Markdown)
- File attachments (images, documents, etc.)
- Threads within channels
- Channel-level permission overrides

#### Direct Messages (Phase 1)

- One-to-one direct messages
- Group DMs (Unlimited participants)
- Separate from channel system

#### User Presence (Phase 1)

- Online / Idle / Do Not Disturb / Offline status
- Custom status messages
- "User is typing..." indicators
- Last seen timestamps

#### Permissions Model (Phase 1)

Full Discord-like RBAC:

- **Roles**: Hierarchical roles with color, icon, hoisting
- **Permissions**: Granular permissions (send messages, manage channels, ban users, manage roles, etc.)
- **Role Hierarchy**: Higher roles override lower roles
- **Channel Overrides**: Per-channel permission overrides for roles and individual users
- **Default Role**: @everyone role applied to all users

#### Voice / Video (Phase 2 — Future)

- Voice channels with WebRTC
- Video calling
- Screen sharing

## Data Models Overview

### Core Models

- **User** — Authentication, profile, presence
- **Role** — Permissions grouping with hierarchy
- **Permission** — Individual permission flags
- **Category** — Channel grouping/organization
- **Channel** — Text channels (and future voice channels)
- **ChannelPermissionOverride** — Per-channel role/user overrides
- **Message** — Text messages within channels
- **MessageAttachment** — Files attached to messages
- **MessageReaction** — Emoji reactions on messages
- **Thread** — Threaded conversations within channels
- **DirectMessage** — One-to-one messages
- **DirectMessageGroup** — Group DM conversations
- **UserStatus** — Online presence and custom status

## Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue 3, Inertia v2, Tailwind CSS 4
- **Auth**: Laravel Fortify (headless)
- **Real-time**: Laravel Reverb + Laravel Echo
- **Queue**: Laravel Horizon
- **Search**: Laravel Scout (future)
- **Monitoring**: Laravel Pulse (future)
- **Database**: PostgreSQL
- **Cache**: Redis
- **Storage**: S3-compatible (MinIO)
- **Deployment**: Kubernetes
