# Restaurant & Cafe Management — Domain Context

## Overview

This is a digital menu and restaurant operations platform (inspired by QRMeni). The core concept: each restaurant creates a digital menu, generates QR codes for each table, and customers scan the QR code to browse the menu, place orders, and call the waiter — all from their phone without downloading an app.

## Domain Terminology

- **Menu Item** — A dish or drink with name, price, description, photo, allergen info, and availability status. Items belong to categories and can carry badges like "New", "Popular", "Chef's Pick", or "Spicy".
- **Category** — A logical section of the menu (e.g., Starters, Main Courses, Desserts, Drinks, Specials). Categories are sorted and can be hidden.
- **Table** — A physical table in the restaurant, identified by a number and optionally a zone (e.g., "Terrace", "VIP", "Indoor"). Each table gets a unique QR code.
- **QR Code** — A generated code that encodes a URL pointing to the restaurant's digital menu with the table pre-selected. Printed and placed on tables. Can be regenerated if compromised.
- **Scan** — An analytics event recorded each time a QR code is scanned. Captures language preference, timestamp, and basic device info for usage analytics.
- **Waiter Call** — A real-time notification from a customer's phone to the staff dashboard. Types include "call waiter", "request bill", and "ask question". Must be delivered instantly via WebSockets.
- **Order** — A collection of order items placed by guests at a table. Flows through statuses: pending, confirmed, preparing, ready, served, completed, cancelled.
- **Template** — A visual theme for the digital menu frontend. Restaurants choose or customize a template that controls colors, layout, fonts, and branding of their public menu page.

## Key Business Rules

1. Menu items must track allergens (gluten, dairy, nuts, shellfish, etc.) as a JSON array — this is a legal requirement in many jurisdictions.
2. QR codes must encode the tenant and table context so the menu loads pre-configured for that specific restaurant and table.
3. Waiter calls must be real-time (WebSocket push) — polling is not acceptable. A 2-second delay means a frustrated customer.
4. The kitchen display is a separate role/view that shows only incoming orders grouped by status, optimized for wall-mounted screens.
5. Multi-language support is essential — tourist areas need menus in 3-5 languages. The MenuItem model should support translations.
6. Orders placed via QR scan are "guest orders" — no authentication required. The order is tied to the table, not a user account.
7. Menu availability should be togglable in real-time — when the kitchen runs out of a dish, staff marks it unavailable and it disappears from the digital menu immediately.

## Analytics Focus

Restaurant owners need a dashboard showing: most/least popular items, peak hours, average order value, QR scan frequency by table/zone, and waiter call response times. This data drives menu optimization and staffing decisions.

## Multi-Tenancy

Each restaurant is a tenant. A single Draplo-generated instance can serve multiple restaurants, each with their own menu, tables, QR codes, staff, and branding. Tenant isolation is critical — one restaurant must never see another's data.
