# Admin Dashboard

Admin dashboard for the Visitor Management System (SecureGate).

## Structure

- `frontend/` — dashboard UI: login, dashboard, visitor book, reports, add visitor, checkout
- `backend/` — self-contained copy of the backend includes/config/API used by the dashboard

Both the admin dashboard and the client form share the same MySQL database (`visitors-management-system`), so visitor registrations appear in the dashboard automatically.
