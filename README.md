Travel Order Management Application
A web application for managing business travel requests, approvals, expenses, and reports. The interface is in Croatian.

The project brings the travel order workflow into one place: an employee creates an order, a manager reviews it, and travel details and expenses are used to calculate the settlement.
Features
- Session-based login and user roles: administrator, manager, and employee.
- User administration and assignment of supervisors.
- Creation and editing of travel orders, including destinations, purpose, transport, dates, and times.
- Approval workflow with pending, approved, and rejected statuses.
- Expense entries with receipt attachments and written travel reports.
- Calculation of daily allowances, meal deductions, mileage reimbursement, and advances.
- Dashboard, reports, CSV exports, and a printable travel order view.
Technology
PHP, PDO, MySQL/MariaDB, HTML, CSS, and browser-side JavaScript. No PHP framework is used.
Run locally
Requirements
- PHP 8.2 with the PDO MySQL extension (the supplied database export records PHP 8.2.4).
- MySQL or MariaDB (the supplied export was generated with MariaDB 10.4).
- A local database administration tool or SQL client.
Setup
1. Clone the repository:
   git clone https://github.com/Luka0082/putni-nalozi.git
   cd putni-nalozi
2. Create a local database named evidencija_putnih_naloga using UTF-8, then import evidencija_putnih_naloga.sql into it. Use a separate local database: the SQL file includes records as well as the schema.
3. Create db.php in the project root. This file is excluded from Git. Set your own local database credentials:
   <?php
   $pdo = new PDO(
       'mysql:host=127.0.0.1;dbname=evidencija_putnih_naloga;charset=utf8mb4',
       'YOUR_DATABASE_USER',
       'YOUR_DATABASE_PASSWORD',
       [
           PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
           PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       ]
   );
4. Ensure the uploads/ directory is writable by PHP.
5. Configure a local login. The SQL export contains password hashes, not documented plaintext demo passwords. Generate a hash for a password of your choice:
   php -r 'echo password_hash("REPLACE_WITH_LOCAL_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
   In your local database, update the lozinka field of a chosen administrator account (uloga_id = 1) with that hash. Use the account's email to sign in. Do not commit passwords or modified database dumps containing private data.
6. Start the local development server:
   php -S 127.0.0.1:8000
7. Open http://127.0.0.1:8000/login.php.
Key files
File	Purpose
login.php	Login and session creation
dashboard.php	Travel order overview
kreiraj_nalog.php, uredi_nalog.php	Order creation and editing
detalji_naloga.php	Expenses, attachments, and travel report
obradi_status.php	Approval status handling
obracun.php	Travel expense calculations
izvjestaji.php	Reports
print_nalog.php	Printable order
evidencija_putnih_naloga.sql	Database export


Suggested manual testing
These are review scenarios, not a record of executed tests. No automated test suite is included.
- Try valid and invalid logins; verify access after logout.
- Compare the actions available to each user role.
- Create an order, edit it, and follow the approval/rejection workflow.
- Check allowance calculations around 8, 12, and 24 hours and with different meal selections.
- Compare mileage, expenses, and advances against the displayed settlement.
- Add a receipt and compare report/CSV values with the order details.
Project notes
This repository is a portfolio project. The instructions above describe local development; they are not a production deployment guide. Calculation rates are defined in obracun.php and should be reviewed for the intended use. The application has not been independently security-audited.
Author
Luka Mladinović
