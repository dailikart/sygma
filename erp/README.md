# Sygma Solar Suite ERP

CRM and project management system for solar installation companies. It covers
leads, quotations, advance payments, material dispatch, installation, DCR / EB /
net-meter process, PM Surya Ghar subsidy tracking, AMC, inventory, suppliers,
expenses and invoices.

Built with CodeIgniter 4.7 (PHP 8.2+), CodeIgniter Shield, MySQL.

- **Setup guide:** [docs/SETUP.md](docs/SETUP.md)
- **User guide (role by role):** [docs/USER_GUIDE.md](docs/USER_GUIDE.md)
- **Product proposal & confirmed project flow:** [docs/PROPOSAL.md](docs/PROPOSAL.md)
- **Live demo setup (with the website):** [docs/DEMO_SETUP.md](docs/DEMO_SETUP.md)

Quick start (details in the setup guide):

```bash
composer install
cp env .env              # then edit database + admin settings
php spark key:generate
php spark migrate --all
php spark db:seed AdminSeeder
php spark serve          # http://localhost:8080
```
