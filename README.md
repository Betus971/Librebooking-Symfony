# Librebooking-Symfony (OpenSeat / Bookr)

A modern, open-source resource booking and scheduling system built with Symfony 7.4.

This project is a modern reimagining of the core concepts of the original [LibreBooking](https://github.com/LibreBooking/app) project, rewritten from scratch on top of the Symfony framework, leveraging PostgreSQL, Turbo, Stimulus, and AssetMapper.

## Features
- **Resource Management**: Manage rooms, vehicles, equipment, and group them logically.
- **Advanced Scheduling**: Handle repeating reservations, blackouts, and complex availability rules.
- **Approval Workflow**: Assign managers to resource groups to approve or reject requests.
- **Modern Stack**: No Node.js required (AssetMapper), fast navigation (Turbo), and solid backend architecture (Symfony 7.4).

## Requirements
- PHP 8.3+
- PostgreSQL 16+
- Composer

## Installation
```bash
git clone https://github.com/Betus971/Librebooking-Symfony.git
cd Librebooking-Symfony
composer install

# Configure your database in .env.local
# DATABASE_URL="postgresql://user:password@127.0.0.1:5432/booking?serverVersion=16&charset=utf8"

php bin/console doctrine:migrations:migrate
php bin/console symfony:server:start
```

## Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## License
This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.
