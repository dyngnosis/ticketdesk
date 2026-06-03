# Ticketdesk — IT Support Ticket Manager

A self-hosted PHP helpdesk and ticket management system for small to mid-sized teams.

## Stack

- PHP 8.2 (vanilla, no framework)
- SQLite via PDO
- PHP sessions for authentication
- Bootstrap 5 (CDN)
- `password_hash` / `password_verify` for passwords

## Features

- Register / Login / Logout
- Submit support tickets (title, description, priority, category)
- View own tickets and track status
- Comment on tickets
- Upload file attachments to tickets
- Export ticket attachments as ZIP
- Admin panel: view all tickets, assign agents, change status
- Admin panel: user management list

## Quick Start (Docker)

```bash
docker-compose up --build
```

Open [http://localhost:3002](http://localhost:3002)

Default credentials: `admin` / `admin123`

## File Structure

```
ticketdesk/
  index.php        ← router + login/register
  tickets.php      ← ticket list/create/view/comment
  admin.php        ← admin panel
  export.php       ← attachment export
  db.php           ← PDO setup + schema init
  auth.php         ← session helpers
  upload.php       ← file upload handler
  templates/
    header.php
    footer.php
  Dockerfile
  docker-compose.yml
  README.md
  .gitignore
```

## License

MIT
