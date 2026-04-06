# 🩸 RaktSetu — Emergency Blood Donor Network

**Capstone Web Project · Web Technologies (23CSE404)**

RaktSetu is a real-time emergency blood-matching platform designed to bridge the critical gap between blood donors, patients, and hospitals. Our system instantly matches and alerts nearby eligible donors based on their blood group — because every minute saved can save a life.

---

## 🌐 Live Demo & Preview
- **Live Application:** [http://raktsetu.page.gd/](http://raktsetu.page.gd/)

*(Built securely with native PHP 8 & MySQL)*

---

## 🚨 The Problem
During medical emergencies, finding the exact blood type in time is highly stressful and chaotic. Families often rely on WhatsApp forwards or calling blood banks one by one. There is a critical lack of a centralized, real-time system that immediately pings verified active donors near the hospital.

## 💡 The RaktSetu Solution
RaktSetu automates the SOS process by maintaining a verified registry of active donors. When a critical request is raised, the platform automatically filters eligible donors (tracking their mandatory 90-day cooldown period) and alerts them instantly. 
- **Time-saving:** Connects the needy with the willing instantly.
- **Location-aware:** Matches based on proximity and city algorithms.
- **Safe & Secure:** Ensures donor privacy and health through strict donation cooldown tracking.

---

## ✨ Core Features

### 👤 For Donors
- **Smart Registration:** Secure profile creation with blood type and location.
- **Eligibility Engine:** Automatically tracks the mandatory 90-day cooldown between donations.
- **Real-Time Alerts:** Live dashboard notifications for nearby emergency requests.
- **Donation History:** Personalized dashboard to track successful donations and social impact.

### 🏥 For Hospitals & Patients
- **Emergency Board:** Post blood requests categorized by Urgency (Critical / High / Normal).
- **Live Tracking:** Real-time visibility into how many donors have accepted the request.
- **Inventory Management:** Hospital staff can manage blood bank stocks directly from their portal.
- **Verification System:** Upload hospital certificates for verified requests to prevent fraud.

### 🛡️ For Administrators
- **Role-Based Access Control (RBAC):** Distinct dashboards for Donors, Hospitals, and Admins.
- **Analytics Dashboard:** Visual representation (Canvas Charts) of platform utility and blood demands.
- **Data Moderation:** Granular control over users, requests, and verification of hospital credentials.

---

## 🛠 Tech Stack & Architecture

This project was built entirely from scratch without utilizing heavy frameworks. It emphasizes strong logic, performance, and fundamental web engineering skills.

- **Frontend UI & UX:** HTML5 (Semantic), CSS3 (Responsive, Custom Variables), JavaScript (Vanilla)
- **Frontend Interactivity:** Real-time AJAX polling, Canvas-based Data Charts, DOM manipulation
- **Backend Core:** PHP 8 (Procedural & OOP mix), PDO for secure database interactions
- **Database:** Relational MySQL (8 Normalized Tables)
- **Security Protocols:** 
  - `password_hash()` for secure credential storage
  - Prepared Statements (PDO) to prevent SQL Injection
  - Route Guards to prevent unauthorized URL access


---

## 🚀 Key Technical Highlights

1. **AJAX Real-Time Polling:** The emergency dashboard updates dynamically without page reloads so users see new requests instantly.
2. **Custom Eligibility Engine:** PHP logic that precisely calculates time gaps between donations to ensure donor safety.
3. **Optimized SQL Queries:** Includes table joins (`users` + `blood_requests` + `donor_responses`) to generate live matching lists efficiently.

---

## 🔮 Future Scope
- **Geolocation API Integration:** For precise GPS-based distance matching rather than city-level radius matching.
- **SMS / WhatsApp integration:** Using APIs like Twilio to send instant push notifications directly to donor phones.
- **AI-Based Demand Prediction:** Analyzing historical data to predict seasonal blood shortages in specific cities.

---

## 👨‍💻 Developed By

**SHIV (Krishu)**  
*Computer Science & Engineering*

---
> *"One donation can save three lives. RaktSetu makes sure that donation happens in time."*
