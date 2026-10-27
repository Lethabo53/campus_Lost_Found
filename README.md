# Campus Lost & Found

A web system where students at Richfield Centurion Campus report lost and found items, and the campus admin checks every report and arranges the hand-over.

**Team: Tech Titans** — Lethabo Mokoena, Ashley Nkadimeng, Adelaide Chokwe

## What it does

- Students sign up with a student email (`40` + 7 digits `@my.richfield.ac.za`) and their first name and surname.
- Students report **lost** or **found** items. Every report goes to the **admin first**.
- After the admin approves a report, it appears on Browse for everyone, with a 30-day (720 hours) claim countdown.
- Found items are protected by **two security questions**. An owner proves the item is theirs by answering them. An answer is accepted when it shares at least one meaningful word with the finder's answer.
- A verified claim goes to the **admin**, who arranges the hand-over. The finder's details stay private.
- If the admin rejects a report, the student sees the reason (Messages, Home page, My reports) and can **edit and resubmit** it. Students can edit a report only until it is approved.
- Students and the admin talk through a **Messages** page.
- Finders earn **points and snack credits**. Credits are redeemed for a code that is collected at the snack venue.
- Only the admin can send unclaimed items (after 30 days) to **donation**.
- **Admin console:** Approvals, Claims (with completed hand-overs), Donations, Snack codes, Messages and Reports (summary, by category, monthly trend, outcomes, rewards; CSV download and print).
- A rule-based **chatbot** answers common questions and looks up a student's own ticket.

## Built with

HTML, CSS, JavaScript (no framework), PHP 8 with PDO, and MySQL / MariaDB. Runs on XAMPP.

## How to run it (XAMPP, Windows)

1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `Frontend` folder into your XAMPP web folder (for example `C:\xampp\htdocs\htdocs\campus_Lost_Found\Frontend`).
3. Open phpMyAdmin (`http://localhost/phpmyadmin`), choose **Import**, and import `Frontend/database.sql`. This creates the `campus_lost_found` database with all tables.
   - To upgrade an existing database without deleting data, use `Frontend/upgrade_admin.sql` instead.
4. Check the database settings at the top of `Frontend/db.php` (host `localhost`, user `root`, empty password by default).
5. Open the site in your browser, for example `http://localhost/htdocs/campus_Lost_Found/Frontend/index.html`.

The admin account is created by `database.sql`. **Change its password before using the system for real.**

## Main files

| File | Purpose |
|---|---|
| `index.html`, `auth.html` | Welcome page, sign in and sign up |
| `home.html`, `browse.html`, `item-detail.html` | Home, browse reports, item page (claim, edit, message the admin) |
| `report-lost.html`, `report-found.html` | Report forms |
| `messages.html` | Student ↔ admin messages |
| `rewards.html` | Points, snack credits and snack codes |
| `admin.html` | Public dashboard and leaderboard |
| `admin-console.html` | Admin only: approvals, claims, donations, snack codes, messages, reports |
| `db.php` | Database connection and sign-in helpers |
| `signup.php`, `login.php`, `logout.php`, `me.php` | Accounts and sessions |
| `report-lost.php`, `report-found.php`, `edit_item.php` | Create and edit reports |
| `verify_claim.php` | Checks the two security answers |
| `admin_review.php`, `admin_action.php`, `admin_reports.php`, `admin_redemptions.php`, `donations.php` | Admin actions and reports |
| `messages.php`, `chatbot.php`, `matches.php` | Messages, chatbot, matching of lost and found items |
| `database.sql`, `upgrade_admin.sql` | Full database, and upgrade for an existing database |

## Backup

Run `backup.bat` to save the website files and the database into `C:\LostFound_Backups\<date-time>`. Restore the database by importing the saved `.sql` file in phpMyAdmin.

## Security notes

- Everything except the welcome page and the sign-in, sign-up and password pages requires sign-in, and the server checks this too.
- Passwords are stored hashed. Found-item photos are blurred and security answers are never shown to students.
- Messages are visible only to the student and the admin.
- No email server is set up, so the password reset link is shown on screen. Replace this with real email before real use.
