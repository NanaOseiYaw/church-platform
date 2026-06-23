# Church Management Platform — Technical Documentation

**Version:** 1.0  
**Type:** SaaS Multi-Tenant Architecture  
**Status:** Specification & Architecture Phase

---

## Table of Contents

1. [Project Overview](#1-project-overview)  
2. [System Architecture](#2-system-architecture)  
3. [Tech Stack](#3-tech-stack)  
4. [Multi-Tenant Structure](#4-multi-tenant-structure)  
5. [User & Role System](#5-user--role-system)  
6. [Database Schema](#6-database-schema)  
7. [Feature Modules](#7-feature-modules)  
8. [API Design](#8-api-design)  
9. [Security Requirements](#9-security-requirements)  
10. [Folder Structure](#10-folder-structure)  
11. [Permission Matrix](#11-permission-matrix)  
12. [Development Phases](#12-development-phases)  
13. [Future SaaS Roadmap](#13-future-saas-roadmap)

---

## 1\. Project Overview

The Church Management Platform is a modern, multi-tenant SaaS application built to help churches manage their operations, communications, members, and media — all in one place.

### Goals

- Allow multiple churches to use the platform with full data isolation  
- Provide a public-facing website per church  
- Provide an internal authenticated portal for members and staff  
- Support modular, scalable feature growth  
- Be architected for SaaS expansion from day one — even if only one church uses it initially

### Core Capabilities

| Area | Description |
| :---- | :---- |
| Member Management | Members, roles, departments, profiles |
| Communication | Announcements, notifications, feeds |
| Events | RSVP, recurring, attendance tracking |
| Tasks | Assignment, tracking, comments, attachments |
| Media | Sermons, gallery, file uploads |
| Attendance | Services, meetings, QR-ready |
| Public Website | Church branding, events, sermons, contact |

---

## 2\. System Architecture

### Architecture Overview

┌──────────────────────────────────────────────┐

│               Platform Layer                 │

│         (Super Admin / SaaS Owner)           │

└────────────────────┬─────────────────────────┘

                     │

        ┌────────────┴────────────┐

        │                         │

┌───────▼────────┐       ┌────────▼───────┐

│   Church A     │       │   Church B     │

│  (Tenant 001\)  │       │  (Tenant 002\)  │

└───────┬────────┘       └────────────────┘

        │

   ┌────┴─────────────────────────┐

   │                              │

┌──▼──────────┐         ┌────────▼───────┐

│ Public Site │         │ Internal Portal│

│ (Branding)  │         │ (Auth Required)│

└─────────────┘         └────────────────┘

### Application Layers

The platform is split into two main surfaces per tenant:

**Public Website** — Accessible without login. Displays church branding, events, sermons, announcements, gallery, and contact info.

**Internal Portal** — Requires authentication. Role-based dashboard for members, coordinators, and admins.

### Tenant Isolation Strategy

- Every record in the database carries a `church_id` foreign key  
- All queries are scoped at the service layer to the authenticated tenant  
- No cross-tenant data leakage is possible through standard routes  
- Super Admin access is gated behind a separate guard

---

## 3\. Tech Stack

### Backend

| Component | Technology |
| :---- | :---- |
| Framework | Laravel (latest) |
| Language | PHP 8+ |
| Database | PostgreSQL (preferred) |
| Cache / Queue | Redis |
| Authentication | Laravel Sanctum |
| Permissions | Spatie Laravel Permission |
| Queue System | Laravel Queues (Redis driver) |
| Architecture | Event-driven where appropriate |

### Frontend

| Component | Technology |
| :---- | :---- |
| UI Framework | Vue.js or Blade \+ Alpine.js |
| CSS Framework | Tailwind CSS |
| Design Approach | Component-based, mobile-first |

### Storage

- File storage abstracted via Laravel's Filesystem  
- S3 / Cloudflare R2 compatible  
- Local driver for development

### Infrastructure

- Docker support with environment-based configuration  
- `.env`\-driven setup for all environments (local, staging, production)

---

## 4\. Multi-Tenant Structure

### The `churches` Table

Every tenant is a church record. This is the root of the entire multi-tenant system.

| Column | Type | Description |
| :---- | :---- | :---- |
| `id` | UUID | Primary key |
| `name` | string | Display name of the church |
| `slug` | string | URL-safe identifier |
| `logo` | string | Path to uploaded logo |
| `primary_color` | string | Hex color for branding |
| `timezone` | string | e.g. `Europe/Amsterdam` |
| `language` | string | e.g. `en`, `nl` |
| `domain` | string | Custom domain (future) |
| `subscription_plan` | string | e.g. `free`, `pro`, `enterprise` |
| `settings` | JSON | Flexible config object |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

### Tenant Scoping Rule

Every major table must include `church_id`. All queries are scoped to the authenticated church context automatically through a base service or global scope.

// Example: All department queries are automatically scoped

Department::where('church\_id', $church-\>id)-\>get();

---

## 5\. User & Role System

### Roles

| Role | Scope | Description |
| :---- | :---- | :---- |
| Platform Super Admin | Global | Manages the entire SaaS platform |
| Church Admin | Per Church | Full control of one church |
| Department Coordinator | Per Department | Manages their assigned department |
| Member | Per Church | Standard authenticated member |

### Key Rules

- Users can belong to multiple departments simultaneously  
- A user can hold different roles in different departments  
- Roles are managed with Spatie Laravel Permission  
- Authorization is enforced via Laravel Policies  
- Permissions are granular (e.g. `announcements.create`, `events.edit`)

### Authentication Flow

User visits /login

      │

      ▼

Credentials validated

      │

      ▼

Sanctum token issued

      │

      ▼

Church context resolved (via subdomain or session)

      │

      ▼

Roles & permissions loaded from Spatie

      │

      ▼

User redirected to role-appropriate dashboard

---

## 6\. Database Schema

### Core Tables

#### `users`

`id` · `church_id` · `name` · `email` · `password` · `email_verified_at` · `avatar` · `phone` · `timezone` · `two_factor_secret` · `remember_token` · `deleted_at` · `created_at` · `updated_at`

#### `departments`

`id` · `church_id` · `name` · `slug` · `description` · `cover_image` · `coordinator_id` · `deleted_at` · `created_at` · `updated_at`

#### `department_user` (pivot)

`id` · `department_id` · `user_id` · `role` · `joined_at`

#### `events`

`id` · `church_id` · `department_id` (nullable) · `title` · `description` · `location` · `start_at` · `end_at` · `is_recurring` · `recurrence_rule` · `is_public` · `category` · `created_by` · `deleted_at` · `created_at` · `updated_at`

#### `announcements`

`id` · `church_id` · `department_id` (nullable) · `title` · `body` · `is_pinned` · `is_church_wide` · `published_at` · `created_by` · `deleted_at` · `created_at` · `updated_at`

#### `tasks`

`id` · `church_id` · `department_id` (nullable) · `assigned_to` · `assigned_by` · `title` · `description` · `priority` · `status` · `due_at` · `deleted_at` · `created_at` · `updated_at`

**Task statuses:** `pending` · `in_progress` · `completed` · `overdue`  
**Task priorities:** `low` · `medium` · `high` · `urgent`

#### `attendance`

`id` · `church_id` · `attendable_id` · `attendable_type` (polymorphic: event/service/meeting) · `user_id` · `checked_in_at` · `method` · `notes`

#### `notifications`

`id` · `type` · `notifiable_id` · `notifiable_type` · `data` · `read_at` · `created_at`

#### `files`

`id` · `church_id` · `fileable_id` · `fileable_type` · `uploaded_by` · `name` · `path` · `mime_type` · `size` · `disk` · `created_at`

#### `sermons`

`id` · `church_id` · `title` · `speaker` · `description` · `video_url` · `audio_url` · `thumbnail` · `preached_at` · `deleted_at` · `created_at` · `updated_at`

#### `audit_logs`

`id` · `church_id` · `user_id` · `action` · `model_type` · `model_id` · `old_values` · `new_values` · `ip_address` · `created_at`

### Key Relationships

- `churches` → has many → everything  
- `users` ↔ `departments` → many-to-many via `department_user`  
- `departments` → has many → `events`, `announcements`, `tasks`, `files`  
- `events` / `departments` → has many → `attendance`  
- `tasks` → has many → `comments`, `attachments`  
- `users` → has many → `notifications`

---

## 7\. Feature Modules

The platform is divided into independent modules. Each module contains its routes, controllers, services, models, requests, and policies.

### 7.1 Auth Module

- Registration with email verification  
- Login / Logout  
- Password reset via email  
- Optional 2FA (architecture in place)  
- Sanctum token management  
- Session / token invalidation

### 7.2 Church Module

- Church creation and onboarding  
- Church profile management (logo, colors, timezone)  
- Settings management  
- Subscription plan tracking  
- Custom domain support (future)

### 7.3 Department Module

- Create and manage departments  
- Assign coordinators  
- Manage members (add/remove)  
- Department-level announcements and feeds  
- Document and file storage per department  
- Internal department calendar

### 7.4 Task Module

- Create tasks with priority and due date  
- Assign to user or department  
- Status tracking: `pending → in_progress → completed / overdue`  
- File attachments  
- Comment thread per task  
- Notification on assignment and status change

### 7.5 Events Module

- Public and private events  
- RSVP system  
- Recurring event support (RRULE)  
- Department-specific events  
- Location support  
- Reminders via notification system  
- Attendance tracking per event

### 7.6 Announcements Module

- Church-wide announcements  
- Department-targeted announcements  
- Scheduled publishing  
- Pinned announcements  
- Push to notification system on publish

### 7.7 Attendance Module

- Track attendance per church service, department meeting, or event  
- Record via manual check-in or QR code (future)  
- Attendance history per user  
- Analytics-ready schema

### 7.8 Notification Module

- In-app notifications (stored in DB)  
- Email notifications via queued jobs  
- Push notification hooks (future-ready)

Triggers:

- Task assigned or status changed  
- Announcement published  
- Event reminder  
- Role changed  
- New department member

### 7.9 Sermon Module

- Upload/link sermon recordings (video or audio)  
- Speaker, title, date, description  
- Public and internal visibility  
- Embedded video/audio playback

### 7.10 Media Module

- Upload images, PDFs, documents, music sheets  
- Storage abstracted (local, S3, R2)  
- Polymorphic attachments (attached to department, event, task, etc.)  
- Secure file URL generation

### 7.11 Dashboard Module

**Member dashboard shows:**

- Upcoming events  
- Assigned tasks  
- Recent announcements  
- Department activity feed  
- Notifications

**Church Admin dashboard shows:**

- Attendance summaries  
- Engagement metrics  
- Active departments overview  
- Pending tasks platform-wide  
- Recent audit logs

---

## 8\. API Design

The backend is designed API-first, enabling future mobile app and third-party integrations.

### Principles

- RESTful endpoints with consistent naming  
- Sanctum bearer token authentication  
- Resource transformers (API Resources) for consistent responses  
- Service layer separates business logic from controllers  
- Request classes handle validation

### Endpoint Structure (Examples)

POST   /api/auth/login

POST   /api/auth/logout

GET    /api/auth/me

GET    /api/departments

POST   /api/departments

GET    /api/departments/{id}

PUT    /api/departments/{id}

DELETE /api/departments/{id}

GET    /api/events

POST   /api/events

GET    /api/events/{id}

POST   /api/events/{id}/rsvp

POST   /api/events/{id}/attendance

GET    /api/tasks

POST   /api/tasks

PUT    /api/tasks/{id}

POST   /api/tasks/{id}/comments

GET    /api/announcements

POST   /api/announcements

POST   /api/announcements/{id}/pin

GET    /api/notifications

POST   /api/notifications/{id}/read

### Response Format

{

  "success": true,

  "data": { ... },

  "message": "Operation successful",

  "meta": {

    "page": 1,

    "total": 42

  }

}

---

## 9\. Security Requirements

| Requirement | Implementation |
| :---- | :---- |
| CSRF Protection | Laravel built-in middleware |
| Rate Limiting | `throttle:api` middleware per route group |
| Secure File Uploads | Mime-type validation, size limits, private disk |
| Role Protection | Spatie RBAC \+ Laravel Policies |
| Tenant Isolation | `church_id` scoping at service layer |
| Email Verification | Laravel built-in `MustVerifyEmail` |
| Password Reset | Laravel Password Broker |
| 2FA (future) | Architecture prepared, not enforced |
| Audit Logging | `AuditLog` model records all CUD actions |
| SQL Injection | Eloquent ORM / parameterized queries |
| XSS | Blade templating auto-escapes by default |

---

## 10\. Folder Structure

app/

├── Http/

│   ├── Controllers/

│   │   ├── Auth/

│   │   ├── Church/

│   │   ├── Department/

│   │   ├── Event/

│   │   ├── Task/

│   │   ├── Announcement/

│   │   ├── Attendance/

│   │   ├── Sermon/

│   │   └── Media/

│   ├── Middleware/

│   │   └── ResolveTenant.php

│   └── Requests/

│       └── \[per module\]/

│

├── Models/

│   ├── Church.php

│   ├── User.php

│   ├── Department.php

│   ├── Event.php

│   ├── Task.php

│   ├── Announcement.php

│   ├── Attendance.php

│   ├── Notification.php

│   ├── Sermon.php

│   ├── File.php

│   └── AuditLog.php

│

├── Services/

│   ├── ChurchService.php

│   ├── DepartmentService.php

│   ├── EventService.php

│   ├── TaskService.php

│   ├── AnnouncementService.php

│   ├── AttendanceService.php

│   └── NotificationService.php

│

├── Policies/

│   └── \[per model\]/

│

├── Events/          ← Laravel events (domain events)

├── Listeners/       ← Triggers notifications, logs, etc.

├── Jobs/            ← Queued jobs (email, notifications)

├── Notifications/   ← Laravel Notification classes

└── Observers/       ← Audit logging, auto-scoping

---

## 11\. Permission Matrix

| Action | Super Admin | Church Admin | Coordinator | Member |
| :---- | :---: | :---: | :---: | :---: |
| Manage all churches | ✅ | ❌ | ❌ | ❌ |
| Manage church settings | ✅ | ✅ | ❌ | ❌ |
| Create departments | ✅ | ✅ | ❌ | ❌ |
| Manage department members | ✅ | ✅ | ✅ | ❌ |
| Create church-wide announcements | ✅ | ✅ | ❌ | ❌ |
| Create department announcements | ✅ | ✅ | ✅ | ❌ |
| Create events | ✅ | ✅ | ✅ | ❌ |
| RSVP to events | ✅ | ✅ | ✅ | ✅ |
| Create tasks | ✅ | ✅ | ✅ | ❌ |
| View assigned tasks | ✅ | ✅ | ✅ | ✅ |
| Track attendance | ✅ | ✅ | ✅ | ❌ |
| Upload media/files | ✅ | ✅ | ✅ | ❌ |
| View sermons | ✅ | ✅ | ✅ | ✅ |
| Upload sermons | ✅ | ✅ | ❌ | ❌ |
| View audit logs | ✅ | ✅ | ❌ | ❌ |

---

## 12\. Development Phases

### Phase 1 — Foundation

- Laravel project setup with PostgreSQL and Redis  
- Docker environment configuration  
- Multi-tenant middleware and church resolution  
- Authentication (register, login, email verify, password reset)  
- Spatie permissions setup  
- Base migrations for `churches`, `users`, roles

### Phase 2 — Core Church & Departments

- Church CRUD and settings  
- Department CRUD  
- User–Department relationships  
- Role assignment per department  
- Church Admin and Coordinator dashboards (scaffolding)

### Phase 3 — Communication

- Announcements (church-wide and department)  
- Scheduled announcements  
- In-app notification system  
- Email notifications via queue

### Phase 4 — Events & Attendance

- Events CRUD (public/private, recurring)  
- RSVP system  
- Attendance tracking  
- Event reminders

### Phase 5 — Tasks

- Task creation and assignment  
- Status management  
- Task comments and attachments  
- Overdue detection via scheduled command

### Phase 6 — Media & Sermons

- File upload system (abstracted storage)  
- Sermon module (upload/link)  
- Gallery feature  
- Department document management

### Phase 7 — Public Website

- Church homepage with branding  
- Public events, sermons, announcements, gallery  
- Contact page  
- Donations page (static or integrated)

### Phase 8 — Analytics & Polish

- Church admin analytics dashboard  
- Attendance summaries  
- Engagement metrics  
- Audit log viewer  
- Performance optimization  
- Mobile responsiveness audit

### Phase 9 — SaaS Infrastructure (Future)

- Subscription and billing (Stripe)  
- Church onboarding flow  
- Custom domain support  
- Feature flags per plan  
- White-label branding options

---

## 13\. Future SaaS Roadmap

| Feature | Priority | Notes |
| :---- | :---- | :---- |
| Stripe subscription billing | High | Per-church plans |
| Church onboarding wizard | High | Self-serve signup |
| Custom domain support | Medium | DNS \+ SSL per church |
| Mobile app (React Native) | Medium | Uses existing API |
| QR code attendance | Medium | Camera-based check-in |
| Push notifications | Medium | FCM / APNs |
| Feature flags | Medium | Per subscription tier |
| White-label branding | Low | Full UI theming per church |
| Multi-language support | Low | Already schema-ready |
| API integrations (Zapier, etc.) | Low | Webhook system needed |

---

*This document covers the full architecture specification for the Church Management SaaS Platform. It should be treated as a living document and updated as features are built and decisions are made.*  
