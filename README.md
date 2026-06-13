
# Librebooking - Resource Management System

Librebooking is an open-source web application designed to handle resource reservations (meeting rooms, vehicles, IT equipment, etc.) powered by Symfony 7.

## Features 🚀

- **Resource Management**: Manage meeting rooms, company vehicles, and equipment effortlessly.
- **Admin Dashboard**: Easy creation, modification, and deletion of resources, users, and schedules powered by EasyAdmin 5.
- **User Portal**: Make reservations, manage your profile, and view your bookings through an interactive calendar.
- **Dark Mode**: A beautiful, modern, and responsive user interface built with Tailwind CSS v4, featuring a seamless Dark Mode toggle.
- **Authentication**:
  - Classic account system (Email / Password).
  - Google OAuth2 Single Sign-On (SSO) integration.
- **Internationalization (i18n)**: Fully localized in both English and French.

## Prerequisites

- PHP 8.4+
- Any Doctrine-supported database (SQLite, MySQL, MariaDB, PostgreSQL, etc.)
- Composer
- Node.js & npm (for Tailwind CSS)

## Installation 🛠️

1. **Clone the repository**
   ```bash
   git clone <repository_url>
   cd Librebooking-Symfony
   ```

2. **Install PHP and JS dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Configuration**
   Copy `.env.example` to `.env.local` and configure your database and OAuth credentials (`.env.local` is git-ignored, so your secrets stay out of version control):
   ```bash
   cp .env.example .env.local
   ```
   ```env
   APP_SECRET=your_generated_secret

   # --- DATABASE CONFIGURATION EXAMPLES ---
   # Example for SQLite (zero-config, perfect for quick local testing)
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

   # Example for PostgreSQL
   # DATABASE_URL="postgresql://postgres:password@127.0.0.1:5432/librebooking?serverVersion=16&charset=utf8"

   # Example for MySQL / MariaDB
   # DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=8.0.32&charset=utf8mb4"

   OAUTH_GOOGLE_ID=your_google_client_id
   OAUTH_GOOGLE_SECRET=your_google_client_secret
   ```

4. **Database Setup & Fixtures**
   Librebooking uses **Doctrine ORM**, making it database-agnostic. 
   
   If you are running the default **PostgreSQL** setup, run:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load --append
   ```

   If you are switching to another database engine (like **SQLite** or **MySQL**), the existing PostgreSQL migrations might fail. In this case, initialize the schema directly instead:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:create
   php bin/console doctrine:fixtures:load --append
   ```

5. **Build Tailwind Assets**
   ```bash
   npm run build
   ```

6. **Start the Development Server**
   ```bash
   symfony server:start
   ```

## Screenshots 📸

### Homepage
![Homepage](./docs/homepage.png)

### Resource Catalog
![Catalog](./docs/catalog.png)

### Resource Detail
![Resource Detail](./docs/resource-detail.png)

*(Feel free to replace these placeholder images in the `docs/` folder with your own screenshots!)*

## Technologies Used

- [Symfony 7](https://symfony.com/) (PHP Framework)
- [EasyAdmin 5](https://symfony.com/bundles/EasyAdminBundle/current/index.html) (Back-office)
- [Tailwind CSS v4](https://tailwindcss.com/) (Styling)
- [Doctrine ORM](https://www.doctrine-project.org/) (Database)
- [KnpUOAuth2ClientBundle](https://github.com/knpuniversity/oauth2-client-bundle) (SSO)

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](https://choosealicense.com/licenses/mit/)
