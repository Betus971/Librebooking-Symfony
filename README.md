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
   APP_SECRET=your_generated_secret
   DATABASE_URL="postgresql://postgres:password@127.0.0.1:5432/librebooking?serverVersion=16&charset=utf8"
   # Leave the OAuth values empty to use email/password only
   OAUTH_GOOGLE_ID=your_google_client_id
   OAUTH_GOOGLE_SECRET=your_google_client_secret
   ```

3. **Create the database & schema** (migrations include the required reference data — reservation statuses & types):
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load --append   # optional demo data
   ```

4. **Build the front-end assets**
   ```bash
   npm run build          # or: php bin/console tailwind:build
   ```

5. **Run the app**
   ```bash
   symfony server:start
   ```

### ⏰ Scheduled tasks (cron)

Two console commands power the time-based features — add them to your scheduler:

```cron
*/15 * * * *  php /path/to/app/bin/console app:reservations:send-reminders
*/5  * * * *  php /path/to/app/bin/console app:reservations:auto-release
```

| Command | Role |
|---|---|
| `app:reservations:send-reminders` | Sends reminder emails before upcoming reservations (lead time configurable via `app.reminder.lead_minutes`). |
| `app:reservations:auto-release` | Cancels approved-but-unconfirmed reservations once `auto_release_minutes` has elapsed, and notifies the waitlist. |

---

## 📸 Screenshots

| Homepage | Catalog | Resource detail |
|---|---|---|
| ![Homepage](./docs/homepage.png) | ![Catalog](./docs/catalog.png) | ![Resource detail](./docs/resource-detail.png) |

---

## 🗺️ Roadmap

See [`docs/roadmap.md`](./docs/roadmap.md) for the prioritized feature roadmap. Architecture notes live in [`docs/01-architecture.md`](./docs/01-architecture.md), and the open-source extension strategy in [`docs/12-strategie-open-source.md`](./docs/12-strategie-open-source.md).

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change. Code follows the **PSR-12** standard — see [`CONTRIBUTING.md`](./CONTRIBUTING.md).

## 📄 License

Distributed under the **GNU General Public License v3.0**. See [`LICENSE`](./LICENSE) for details.
