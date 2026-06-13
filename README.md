<div align="center">

# 📅 Librebooking

**A modern, open-source resource reservation system** — book meeting rooms, vehicles, equipment, and any shared resource over time slots.

[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Doctrine](https://img.shields.io/badge/Doctrine_ORM-3.6-FC6A31?style=flat-square&logo=doctrine&logoColor=white)](https://www.doctrine-project.org/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-000000?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![DaisyUI](https://img.shields.io/badge/DaisyUI-5-5A0EF8?style=flat-square&logo=daisyui&logoColor=white)](https://daisyui.com/)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)](./LICENSE)

</div>

---

## ✨ Features

### Reservations
- **Booking workflow** — request a slot for a resource, with live availability checking (conflicts, opening hours, blackout periods) and file attachments.
- **Concurrency-safe** — slot creation is serialized per resource with a PostgreSQL advisory lock to prevent double-booking under load.
- **Admin moderation** — approve / reject (with reason) / cancel reservations, scoped per resource group, with a full audit log.
- **Waitlist** — when a slot is full, users join a waitlist and are notified automatically when it frees up.
- **Email notifications** — confirmation, approval, rejection, cancellation, and reminders.
- **Check-in & auto-release** — confirm attendance; unconfirmed slots are released automatically after a configurable delay.

### Resources & scheduling
- **Resource catalog** organized by **categories** and **approval groups**, with search and filtering.
- **Time layouts** — define opening slots (day, start/end time, open/closed) in the admin and assign them to schedules.
- **iCal subscription** — every resource exposes a calendar feed (`webcal`/`.ics`) for Outlook, Google Calendar, or Thunderbird.

### Experience
- **Admin dashboard** powered by **EasyAdmin 5**.
- **Modern UI** built with **Tailwind CSS v4** + **DaisyUI**, with a seamless **light / dark theme** toggle.
- **Internationalization (i18n)** — French & English.
- **Authentication** — local email / password **and** Google OAuth2 SSO (use either or both).

---

## 🧱 Tech stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4+ |
| Framework | Symfony 7.4 (LTS) |
| ORM | Doctrine ORM 3.6 |
| Database | PostgreSQL 16+ (recommended) — Doctrine-agnostic |
| Back-office | EasyAdmin 5 |
| Styling | Tailwind CSS v4 + DaisyUI 5 |
| Front interactions | Symfony UX (Turbo + Stimulus), FullCalendar |
| SSO | Google OAuth2 (KnpUOAuth2ClientBundle) |
| iCal | spatie/icalendar-generator |
| PDF | dompdf |

---

## 🚀 Getting started

### Prerequisites
- PHP **8.4+**
- Composer
- Node.js & npm (for Tailwind / DaisyUI)
- A database — **PostgreSQL 16+** recommended (some features such as the booking advisory lock are PostgreSQL-specific)

### Installation

1. **Clone & install dependencies**
   ```bash
   git clone https://github.com/Betus971/Librebooking-Symfony.git
   cd Librebooking-Symfony
   composer install
   npm install
   ```

2. **Configure your environment** — copy the example file (your secrets stay in the git-ignored `.env.local`):
   ```bash
   cp .env.example .env.local
   ```
   ```env
   APP_SECRET=your_generated_secr