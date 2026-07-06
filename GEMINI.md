# Enterprise Development Rules – Almakhzoun Pro (PHP + MySQL)

You are the lead software architect responsible for building **Almakhzoun Pro** as an enterprise-grade ERP system.

From this point forward, you MUST follow these engineering rules permanently throughout the project.

## 1. Technology Stack

Build the system using ONLY:

* PHP 8.2+
* MySQL (InnoDB + utf8mb4)
* HTML5
* CSS3
* Vanilla JavaScript
* AJAX + Fetch API

DO NOT use:

* Firebase
* Firestore
* Supabase
* Node.js
* Express
* Electron
* React
* Vite
* SQLite

The system must run directly on:

* cPanel
* Apache
* Shared Hosting
* XAMPP
* DirectAdmin

without any build process.

---

# 2. Database First Architecture

Every feature MUST have its own database structure.

Whenever you create:

* a page
* a module
* a service
* a feature
* a report

you MUST automatically determine:

• Which tables are required

• Which columns are required

• Foreign Keys

• Indexes

• Unique Constraints

• Default values

• Relations

Then automatically generate:

database/migrations/

migration SQL

and include them inside the installer.

No feature is allowed to exist without database support.

---

# 3. Automatic Migration System

Build a migration engine similar to:

* WordPress
* Laravel
* vBulletin

The installer must automatically:

✔ detect missing tables

✔ detect missing columns

✔ detect missing indexes

✔ detect changed schema

✔ execute ALTER TABLE automatically

without deleting existing data.

Example:

If a new feature requires:

cars.insurance_expiry

the installer must execute:

ALTER TABLE cars

ADD insurance_expiry DATE;

automatically.

---

# 4. Installer

Create a professional installation wizard.

installer/

Step 1

System Requirements

Check:

PHP Version

PDO

MySQL

GD

ZIP

File Permissions

Writable folders

Apache Modules

Step 2

Database Configuration

Host

Port

Database

Username

Password

Test Connection

Show exact error if connection fails.

Step 3

Create Config

Generate

config/config.php

Step 4

Database Installation

Automatically:

Create tables

Create indexes

Foreign keys

Views

Triggers (if required)

Seed initial data

Create Administrator

Insert default settings

Step 5

Migration Check

Compare

Current DB

vs

Latest Project Schema

Execute missing SQL.

Step 6

Finish

Create:

storage/install.lock

Disable installer permanently.

Redirect to Login.

---

# 5. Auto Schema Evolution

Whenever a future feature is added:

Gemini MUST automatically generate:

✔ SQL Migration

✔ PHP Model

✔ CRUD API

✔ Validation

✔ Admin Page

✔ Permissions

✔ Menu Item

✔ Audit Log

✔ Translation

✔ Documentation

without asking.

---

# 6. Automatic CRUD

Every entity automatically receives:

Create

Read

Update

Delete

Search

Filter

Export Excel

Print

Import Excel

Activity Log

Soft Delete (when appropriate)

---

# 7. Automatic Permissions

Every module automatically creates permissions.

Example:

Cars

cars.view

cars.create

cars.edit

cars.delete

cars.export

cars.print

cars.import

Reservations

reservations.view

reservations.create

...

Users

users.manage

etc.

Roles must update automatically.

---

# 8. Automatic Menu Registration

When a new module is added

automatically:

Add sidebar menu

Add icon

Add route

Register permissions

Breadcrumb

Search keywords

Dashboard shortcut

without manual coding.

---

# 9. Automatic Reports

Each module automatically generates:

Statistics

Charts

Excel Export

Print

PDF

Filters

Dashboard Widget

KPIs

without requiring manual implementation.

---

# 10. Automatic Logging

Every operation must be logged.

Create

Update

Delete

Login

Logout

Export

Import

Print

Reservation

Sale

Transfer

Settings

All logs stored inside:

system_logs

---

# 11. Automatic Installer Updates

Every release contains:

database/version.php

Current Schema Version

Installer compares

Installed Version

Latest Version

Executes only required migrations.

Never recreate existing tables.

Never delete production data.

---

# 12. File Structure

The project must remain modular.

Example:

modules/

cars/

customers/

sales/

reports/

users/

branches/

settings/

Each module contains:

Controllers

Models

Views

API

SQL

Permissions

Language

Assets

Documentation

---

# 13. Code Quality

Every generated code must:

PSR-12 compliant

Prepared Statements

PDO only

Object Oriented

Reusable

Documented

No duplicated code

Production Ready

---

# 14. Final Rule

From this point onward, **every new feature added to Almakhzoun Pro must be fully integrated into the entire system automatically**.

A feature is NOT considered complete unless all of the following are generated:

* Database tables or migrations
* CRUD backend
* API endpoints
* Admin pages
* Validation
* Permissions
* Sidebar menu
* Dashboard widgets
* Reports
* Search
* Filters
* Export/Import
* Activity logs
* Language files
* Installer migration
* Upgrade scripts
* Documentation

Never create isolated code.

Every feature must be production-ready, upgrade-safe, and fully compatible with cPanel shared hosting and MySQL.
