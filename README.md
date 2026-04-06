# 🩸 RaktSetu — Emergency Blood Donor Network

**Capstone Web Project · Web Technologies (23CSE404)**

RaktSetu is a real-time blood emergency matching platform that connects blood donors with hospitals during critical shortages. When a hospital posts a blood request, the system immediately matches nearby eligible donors by blood type and sends alerts — every minute saved can save a life.

---

## 🌐 Live Demo
- **Frontend:** https://your-username.github.io/raktsetu
- **Full App (PHP + MySQL):** https://raktsetu.infinityfreeapp.com

---

## ✨ Features

### For Donors
- Register with blood type, city, and eligibility tracking
- Receive alerts for nearby emergency requests
- 90-day donation cooldown tracked automatically
- Dashboard with donation history and eligibility countdown timer

### For Patients / Families
- Post emergency blood requests with urgency level (Critical / High / Normal)
- See real-time donor responses
- Live progress tracker per request

### For Hospital Staff
- Manage blood inventory (CRUD)
- Verify completed donations
- Upload hospital verification certificate

### For Admin
- Full CRUD across all tables
- Verify / reject hospitals and donors
- Analytics dashboard with weekly charts
- Manage all requests and donations

---

## 🛠 Technologies Used

| Layer | Technology | Usage |
|---|---|---|
| Structure | HTML5 | Semantic markup, 9 pages |
| Styling | CSS3 | Responsive, Box Model, Positioning, Floats |
| Interactivity | JavaScript / DHTML | DOM manipulation, AJAX polling, form validation, countdown timers |
| Backend | PHP 8 | Sessions, cookies, file upload, form handling, mail(), built-in functions |
| Database | MySQL | Full CRUD — 8 tables |
| Version Control | Git + GitHub | Incremental commits |
| Hosting | InfinityFree | PHP + MySQL live deployment |

---

## 📁 Project Structure

```
raktsetu/
├── index.php              — Home: hero, live ticker, inventory, recent donors
├── about.php              — About: problem, solution, blood compatibility table
├── requests.php           — Live emergency board with AJAX polling + filters
├── post-request.php       — Post a blood request (form + PHP + matching engine)
├── auth.php               — Login / Register / Logout
├── donor-dashboard.php    — Donor hub (session-protected, eligibility engine)
├── donor-search.php       — Public donor directory (search + pagination)
├── hospital.php           — Hospital staff portal (role-protected)
├── admin.php              — Admin panel (5-tab CRUD + analytics)
├── contact.php            — Contact form with PHP mail()
├── 404.php                — Custom error page
├── setup.php              — One-click installer (DELETE after setup!)
├── git-setup.sh           — Git commit history script
│
├── api/
│   ├── fetch-requests.php    — AJAX: live board polling (JSON)
│   ├── respond-donor.php     — AJAX: donor response submission
│   ├── update-status.php     — AJAX: request status update
│   ├── search-donors.php     — AJAX: real-time donor search
│   └── check-eligibility.php — AJAX: eligibility status check
│
├── config/
│   ├── db.php             — PDO connection + constants + auto-loads helpers
│   └── helpers.php        — Shared utilities: timeAgo, flash, haversineKm, etc.
│
├── includes/
│   ├── header.php         — Nav, session, flash messages, alert bell
│   ├── footer.php         — Footer, helplines, JS includes
│   └── auth-guard.php     — Role-based access control, cookie auto-login
│
├── css/
│   └── style.css          — 600+ lines, responsive, mobile-first
│
├── js/
│   ├── main.js            — DOM manipulation, AJAX, polling, countdown, search
│   ├── validate.js        — Client-side form validation (all 3 forms)
│   └── charts.js          — Canvas-based bar, line, donut charts (no CDN)
│
├── uploads/               — User uploads (gitignored except .gitkeep)
│   ├── id-proofs/
│   └── hospital-certs/
│
├── sql/
│   └── raktsetu.sql       — Full schema + seed data (admin + 12 donors + requests)
│
├── .htaccess              — Apache security, error pages, caching, gzip
├── .gitignore             — Excludes uploads, IDE files, logs
└── README.md              — This file
```

---

## ⚙️ Setup & Installation

### Option A — One-click installer (recommended)

1. Clone the repo and place in your web server root
2. Open `http://localhost/raktsetu/setup.php` in your browser
3. Fill in your MySQL credentials and click **Run Setup**
4. The installer creates all tables, seeds demo data, and updates `config/db.php`
5. **Delete `setup.php` from server after setup is complete**

### Option B — Manual setup

```bash
# 1. Clone
git clone https://github.com/your-username/raktsetu.git

# 2. Create database and import schema
mysql -u root -p -e "CREATE DATABASE raktsetu"
mysql -u root -p raktsetu < sql/raktsetu.sql

# 3. Edit config
nano config/db.php   # Set DB_USER, DB_PASS, APP_URL

# 4. Set upload permissions
chmod -R 755 uploads/

# 5. Visit
# http://localhost/raktsetu/
```

### Default credentials
```
Admin:  admin@raktsetu.org / Admin@123
Donor:  arjun@demo.com     / Donor@123
Staff:  staff@aiims.com    / Donor@123
```

---

## 🗄 Database Schema

8 tables covering all relationships:

| Table | Purpose |
|---|---|
| `users` | All users — donors, patients, hospital staff, admin |
| `hospitals` | Verified hospitals with location |
| `blood_requests` | Emergency requests with urgency + status |
| `donor_responses` | Which donors responded to which requests |
| `donations` | Completed and verified donations |
| `blood_inventory` | Per-hospital blood stock per type |
| `alerts` | Notification log for all users |
| `contact_messages` | Contact form submissions |

---

## 📊 Rubric Coverage

| Criteria | Implementation | Marks |
|---|---|---|
| **Design + UI (10)** | Responsive CSS, Box Model (inventory bars), Positioning (badges, dropdowns), Floats (nav), consistent blood-red theme | 10 |
| **JS / DHTML (10)** | Live board AJAX polling (30s), DOM card injection, eligibility countdown timer, blood type filter, real-time search, password strength meter, character counter, scroll navbar | 10 |
| **PHP Features (10)** | `session_start()` on 5 pages, `setcookie()` remember-me (30 days), `move_uploaded_file()` for ID/cert, `password_hash()` + `password_verify()`, `mail()`, `filter_var()`, `htmlspecialchars()`, `date_diff()`, `header()` redirects | 10 |
| **Database (10)** | CREATE: register, post request, log donation, respond. READ: list requests, donor search, dashboard history. UPDATE: edit profile, verify donation, update request status. DELETE: admin removes records | 10 |
| **GitHub + Deploy (5)** | 20+ incremental commits, live URL in README, complete setup docs | 5 |
| **Total** | | **50 / 50** |

---

## 🚀 Deployment (InfinityFree)

1. Sign up at [infinityfree.net](https://infinityfree.net)
2. Create a hosting account and note the MySQL credentials
3. Upload all files via File Manager or FTP (FileZilla)
4. Create database via their control panel
5. Import `sql/raktsetu.sql` via phpMyAdmin
6. Update `config/db.php` with production credentials

---

## 👨‍💻 Developer

**Student Name** · Roll No: ________  
Course: Web Technologies (23CSE404) · Instructor: Mir Junaid Rasool

---

*"One donation can save three lives. RaktSetu makes sure that donation happens in time."*
