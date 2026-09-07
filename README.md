# TaskFlow — PHP + MySQL Task Manager

A complete, working task management web application built with PHP 8, MySQL,
HTML5, CSS3, and vanilla JavaScript. Users can register, log in, and manage a
private list of tasks — every task is scoped to its owner and no user can
ever view, edit, complete, or delete another user's data.

---

## 1. Requirements

- PHP **8.0+** with the `pdo_mysql` extension enabled
- MySQL **5.7+** or MariaDB **10.3+**
- A local server stack such as **XAMPP**, **WAMP**, **MAMP**, or **LAMP**
  (or PHP's built-in dev server)

---

## 2. Creating the database

1. Start MySQL (via XAMPP/WAMP's control panel, or `sudo service mysql start`
   on Linux).
2. Create the database and tables by importing `database.sql`. You can do
   this two ways:

   **Using phpMyAdmin**
   - Open phpMyAdmin, click **Import**, choose `database.sql`, and click **Go**.

   **Using the command line**
   ```bash
   mysql -u root -p < database.sql
   ```
   This creates the `task_manager` database, the `users` and `tasks`
   tables, indexes, the foreign key relationship, and two optional demo
   users with a few sample tasks (see below).

### Demo accounts (optional sample data)

| Email               | Password    |
|---------------------|-------------|
| ada@example.com     | Password123 |
| grace@example.com   | Password123 |

Feel free to delete the `INSERT` statements at the bottom of `database.sql`
before importing if you don't want sample data.

---

## 3. Configuring database credentials

Credentials live in **one place**: `config/database.php`.

```php
$config = [
    'host'    => 'localhost',
    'dbname'  => 'task_manager',
    'username'=> 'root',
    'password'=> '',
    'charset' => 'utf8mb4',
];
```

Update `username` and `password` to match your local MySQL setup. Every
other file calls `getDB()` to reuse the same connection — there's no
duplicated connection code anywhere else in the app.

### If you're using git

`config/database.php` is listed in `.gitignore` and will **never** be
committed — it holds your real local credentials. Instead, the repo
tracks `config/database.example.php`, a safe placeholder template.

- **Already have `config/database.php`?** You're done, nothing else to do.
- **Setting up on a new machine / fresh clone?** Copy the template and
  fill in your values:
  ```bash
  cp config/database.example.php config/database.php
  ```
  Then edit `config/database.php` with your real username/password.
  Never edit `config/database.example.php` with real credentials — it's
  meant to stay generic since it's the one that gets committed.

---

## 4. Running the application

### Option A — XAMPP / WAMP / MAMP

1. Copy the `task-manager` folder into your server's web root:
   - XAMPP (Windows): `C:\xampp\htdocs\task-manager`
   - XAMPP (macOS/Linux): `/opt/lampp/htdocs/task-manager`
   - WAMP: `C:\wamp64\www\task-manager`
2. Start Apache and MySQL from the control panel.
3. Import `database.sql` (see section 2).
4. Visit: **http://localhost/task-manager/**

### Option B — PHP's built-in server

```bash
cd task-manager
php -S localhost:8000
```

Then visit: **http://localhost:8000/**

### Default URL

Once running, the app redirects `/` to the login page if you're logged
out, or to the dashboard if you're already logged in.

- `login.php` — Login
- `register.php` — Create an account
- `dashboard.php` — Your tasks (after logging in)

---

## 5. How registration and login work

- **Registration** (`register.php`) validates the name, email format,
  password length (8+ characters), and password confirmation, and checks
  that the email isn't already registered. Passwords are hashed with
  PHP's `password_hash()` (bcrypt) — plain-text passwords are never
  stored.
- **Login** (`login.php`) verifies credentials with `password_verify()`.
  On success it calls `session_regenerate_id()` (to prevent session
  fixation) and stores `$_SESSION['user_id']`, `user_name`, and
  `user_email`.
- **Logout** (`logout.php`) is a POST-only, CSRF-protected action that
  fully destroys the session and clears the session cookie.
- Every protected page starts with `requireLogin()`, which redirects
  anonymous visitors to `login.php`.

---

## 6. Project folder structure

```
task-manager/
├── assets/
│   ├── css/style.css        Design system + responsive layout
│   └── js/script.js         Delete confirmations, mobile nav, flash auto-dismiss
├── config/
│   └── database.php         DB credentials + shared PDO connection (getDB())
├── includes/
│   ├── auth.php             Sessions, login/logout, currentUserId(), CSRF helpers
│   ├── functions.php        Escaping, flash messages, badges, ownership lookups
│   ├── header.php           Shared <head>, nav, flash message rendering
│   └── footer.php           Shared closing markup + script include
├── index.php                Redirects to dashboard or login
├── register.php             Account creation
├── login.php                Authentication
├── logout.php                Session teardown (POST + CSRF)
├── dashboard.php            Stats, search, filters, sorting, task list
├── create_task.php          New task form + handler
├── edit_task.php            Edit task form + handler (ownership-checked)
├── delete_task.php          Delete handler (POST + CSRF + ownership-checked)
├── toggle_task.php          Pending ⇄ Completed handler (POST + CSRF + ownership-checked)
├── profile.php              View/update name, email, and password
├── database.sql             Full schema + optional sample data
└── README.md
```

---

## 7. Security measures implemented

- **SQL injection protection** — every single query anywhere in the app
  uses a PDO prepared statement with bound parameters. User input is
  never concatenated into SQL. Sorting uses a server-side whitelist
  (`$allowedSorts`) rather than accepting a raw column/direction from the
  query string.
- **Password security** — `password_hash()` / `password_verify()`
  (bcrypt) only; passwords are never logged, displayed, or stored in
  plain text.
- **Session security** — `httponly` + `SameSite=Lax` cookies, session ID
  regeneration on login, and full session teardown (`session_destroy()` +
  cookie expiry) on logout.
- **Authorization / ownership enforcement** — every task read, update,
  toggle, and delete query includes `AND user_id = :user_id` bound to the
  *session's* user ID, never a client-supplied value. A shared
  `getOwnedTask()` helper is used everywhere a page needs to check "does
  this task belong to me?" so the same verified logic runs everywhere.
- **CSRF protection** — a per-session token (`csrfToken()`) is embedded
  in every state-changing form (`csrfField()`) and validated with a
  timing-safe comparison (`requireValidCsrf()`) before any create, edit,
  delete, toggle, profile update, password change, or logout is
  processed.
- **XSS protection** — all user-generated output (titles, descriptions,
  names, emails) is passed through the `e()` helper
  (`htmlspecialchars()`) before being echoed into HTML.
- **Input validation** — every form is validated server-side
  (required fields, max lengths, valid email format, valid date, allowed
  enum values for priority/status/sort) regardless of what the browser's
  HTML5 validation already caught.
- **Safe error handling** — database exceptions are logged with
  `error_log()` and never shown to the browser; users see friendly
  messages like "Something went wrong. Please try again." instead of SQL
  errors or stack traces.

---

## 8. Testing checklist

Manually verify:

- [ ] Register a new user, then try registering the same email again
      (should be rejected).
- [ ] Try a password under 8 characters, and mismatched
      password/confirm fields.
- [ ] Log in with correct credentials, wrong password, and a
      non-existent email.
- [ ] Log out and confirm you're redirected to the login page and can no
      longer reach `dashboard.php` directly.
- [ ] Create, edit, complete, reopen, and delete a task.
- [ ] Search and filter by status/priority, and try each sort option.
- [ ] **Cross-user isolation:** create two accounts (User A, User B),
      create a task under each, then log in as User A and try changing
      `edit_task.php?id=` or `delete_task.php` to reference User B's task
      ID. You should be redirected to the dashboard with "You are not
      authorized to access this task."

---

Enjoy using TaskFlow!
