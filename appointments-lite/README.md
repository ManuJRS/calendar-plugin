# 📅 Appointments Lite – Calendar + WooCommerce Integration

A lightweight WordPress plugin that provides an expandable booking calendar integrated with WooCommerce for paid appointment reservations.

Built to work with:

- Hello Elementor
- Elementor (free version)
- WooCommerce

---

## ✨ Features

- 📆 Monthly booking calendar
- 🔽 Collapsible / expandable UI
- ⏳ Configurable working hours
- 🕒 Configurable slot duration
- 🔐 Prevents double booking
- 💳 WooCommerce checkout integration
- 🔒 Automatically blocks confirmed appointments
- ♻️ Automatic expiration for unpaid reservations
- 🎯 Clean UI/UX with disabled occupied slots

---

## 🏗 How It Works

1. User selects a date and time.
2. Plugin creates a **pending appointment**.
3. User is redirected to WooCommerce checkout.
4. After payment confirmation:
   - Appointment status changes to `confirmed`
   - Time slot becomes unavailable in the calendar.

---

## ⚙️ Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (active)
- Elementor (optional, only for UI placement)

---

## 🚀 Installation

1. Clone or download this repository.
2. Upload the plugin folder to:
3. Activate **Appointments Lite** from WordPress admin.
4. Make sure WooCommerce is active.
5. Configure plugin settings:


---

## 🧩 Usage

Add the calendar anywhere using the shortcode:

In Elementor (free version):

1. Add a Shortcode widget
2. Paste `[apl_calendar]`

---

## 🔧 Settings

Go to:
You can configure:

- Working days
- Working hours
- Slot duration
- Hold time for unpaid reservations
- WooCommerce product ID (auto-created if empty)

---

## 🔁 Appointment Status Flow

| Status     | Description |
|------------|------------|
| pending    | Reserved but unpaid |
| confirmed  | Paid and locked |
| cancelled  | Cancelled order |
| expired    | Hold time exceeded |

---

## 🛡 Double Booking Protection

Slots are blocked based on:

- `start_local`
- `status = pending OR confirmed`

Blocked slots are displayed in the UI as:
ocupado


and cannot be selected.

---

## 🕓 Automatic Expiration

Unpaid appointments expire automatically after the configured hold time.

Handled via WP-Cron.

---

## 🧪 Testing

1. Activate WooCommerce.
2. Enable Cash on Delivery (for quick testing).
3. Book a slot.
4. Complete checkout.
5. Verify:
   - Appointment is confirmed.
   - Slot appears disabled in calendar.

---

## 🧠 Technical Notes

- Slot uniqueness is based on canonical UTC ISO format.
- Timezone is derived from WordPress settings.
- UI blocks slots using `start_local` for robustness.
- AJAX endpoints use nonce verification.
- Cart is limited to one appointment at a time.

---

## 📌 Future Improvements (Roadmap)

- Multiple services with different durations/prices
- Admin calendar overview
- Email notifications
- Google Calendar sync
- REST API endpoints
- Custom database table for high-scale usage

---

## 👨‍💻 Author

Manuel Rejón  
Frontend Developer | WordPress | Vue | TypeScript

---

## 📜 License

GPL-2.0+
