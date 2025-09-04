# MemoGen – Memorandum Generator

A simple PHP/MySQL web app for managing professional memorandums with user/admin roles.

## Features

- User/admin login and registration
- Admin dashboard with user and memo management
- Users can create, edit, and delete their own memos
- Memo templates (admin-defined)
- Notification system
- Audit log (admin)
- Profile management

## Setup

1. Import `schema.sql` into your MySQL database.
2. Copy all files to your webserver directory.
3. Update `includes/db.php` with your own database credentials.
4. Access `index.php` in your browser.

## Usage

- Register a user or login as admin.
- Admin can promote users to admin by changing their role in the database.
- Use the admin panel to manage users, memos, templates, and view audit logs.

---

**This system is for demonstration purposes and should be secured before use in production.**