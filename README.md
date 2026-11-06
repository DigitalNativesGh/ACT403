# Level 400 Quiz App (Laravel 12)

A simple timed, camera-on objective quiz.

| Page | URL | Who |
|---|---|---|
| Question upload (CSV), settings, results | `/admin` | Lecturer |
| Student details (index number + UIN) | `/` | Students |
| Questions, with webcam (top-right) and timer | `/quiz` | Students |
| Congratulations + score | `/result` | Students |

No Node/npm build is needed and nothing loads from the internet, so it works on a classroom network.

---

## 1. Install

Requires PHP 8.2+ and Composer.

```bash
composer create-project laravel/laravel quiz-app "^12.0"
```

Copy everything in this folder **into** the new `quiz-app` folder, allowing it to overwrite `routes/web.php`. The files are:

```
app/Http/Controllers/StudentController.php
app/Http/Controllers/QuizController.php
app/Http/Controllers/Admin/AdminController.php
app/Http/Controllers/Admin/QuestionController.php
app/Http/Middleware/AdminAuth.php
app/Models/Question.php  Attempt.php  Setting.php
app/Services/QuestionCsvImporter.php
config/quiz.php
database/migrations/2026_11_05_00000{1,2,3}_*.php
resources/templates/questions_template.csv
resources/views/...  (layouts, student, quiz, admin)
routes/web.php   ← replaces the default
```

Then add your lecturer password to `.env`:

```
ADMIN_PASSWORD=choose-a-strong-password
```

and run:

```bash
cd quiz-app
php artisan migrate
php artisan serve
```

Open http://localhost:8000/admin, log in, upload questions, set the duration and tick **Quiz is open**. Students go to http://localhost:8000/.

(Laravel 12 uses SQLite by default — nothing else to set up. To use MySQL, set `DB_CONNECTION=mysql` and the `DB_*` values in `.env` before migrating.)

## 2. IMPORTANT: the camera needs HTTPS

Browsers only allow camera access on **https://** sites or on **localhost**. If students open the app as `http://192.168.x.x:8000`, the camera will be blocked and they will not be able to start.

Options:
- **Host it online** with HTTPS (any Laravel host — Forge, a VPS with Let's Encrypt, cPanel with AutoSSL, etc.).
- **Classroom network:** put it behind HTTPS on your laptop, e.g. with [Laravel Herd](https://herd.laravel.com) (`herd secure`) or Caddy, or a tunnel such as `cloudflared tunnel --url http://localhost:8000` which gives you a public https link.

## 3. The CSV template

Download it from the dashboard (**Download template**), or use `resources/templates/questions_template.csv`.

```
question,option_a,option_b,option_c,option_d,option_e,correct_answer
Which layer of the OSI model routes packets?,Data Link,Network,Transport,Session,,B
```

- One question per row. Keep the header row exactly as it is.
- `option_a` and `option_b` are required; `option_c`, `option_d`, `option_e` can be blank (fill them in order).
- `correct_answer` = the **letter** of the right option (`A`–`E`). `b`, `Option B` or the exact option text also work.
- In Excel: **File → Save As → CSV UTF-8 (Comma delimited)**. Semicolon CSVs also work.
- If any row has a problem, nothing is imported and you get a list of row numbers to fix.
- Tick **Replace existing questions** to swap the whole bank; untick to add to it.

## 4. How it behaves

- **Student details:** index number + UIN are required, and the Start button stays disabled until the camera works.
- **One attempt per index number.** If a student's browser crashes, they re-enter the same index number + UIN and continue where they left off (answers autosave). To allow a full retake, click **Allow retake** on their row in the results table.
- **Timer** is enforced by the server: it keeps running if the page is closed, and turns amber at 5 min and red at 1 min. At zero the answers are submitted automatically.
- **Camera:** shown live in the top-right corner. If it is switched off or blocked mid-quiz, the page is covered until it is turned back on (the timer keeps running). The video is only shown to the student on their own screen; it is **not recorded or uploaded**.
- **Questions** are shuffled per student. Set *Questions per student* (e.g. 20) to give each student a random subset of the bank.
- **Results:** score and percentage per student, class average, and **Export CSV** for your records. Students see only their score, not the correct answers.

## 5. Settings

Editable on the dashboard: quiz title, duration (minutes), questions per student, open/closed.

In `.env` (optional): `QUIZ_GRACE_SECONDS=60` — how long after the timer ends a late auto-submit is still accepted.
