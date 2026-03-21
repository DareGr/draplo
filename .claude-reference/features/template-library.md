# Draplo — Template Library Specification

## Template Philosophy

Every SaaS application, regardless of industry, is built from a small set of recurring patterns. A "CRM for dentists" and a "CRM for real estate agents" share 80% of their architecture — contacts, activities, pipeline, communication. The 20% difference is domain-specific models and terminology.

Draplo templates capture that 80% as proven, tested scaffolds. The wizard captures the 20% as user customizations. AI merges both into a project tailored to the user's specific idea.

## How Templates Work

1. User browses template library (20+ templates with descriptions and previews)
2. User selects a template
3. Wizard opens PRE-POPULATED with template defaults (models, roles, integrations)
4. User customizes: renames models, adds/removes fields, adjusts roles, picks integrations
5. AI generates final scaffold = template foundation + user customizations

## Template Structure (Technical)

Each template is stored in `storage/app/templates/{slug}/` and contains:

```
templates/booking-platform/
├── template.json           ← Metadata: name, description, category, preview image
├── wizard-defaults.json    ← Pre-populated wizard data (models, roles, integrations)
├── prompt-context.md       ← Template-specific AI prompt context (cached per template)
├── migrations/             ← Pre-built, tested migration files
├── models/                 ← Pre-built Eloquent models with relationships
├── seeders/                ← Realistic demo data seeder
├── routes/                 ← API route stubs
└── preview/                ← Screenshots/mockups for template library UI
```

### template.json example:
```json
{
    "name": "Booking & Reservation Platform",
    "slug": "booking-platform",
    "category": "operations",
    "description": "Appointment scheduling with calendar, reminders, and online booking. Perfect for salons, clinics, studios, sports courts.",
    "industries": ["healthcare", "beauty", "fitness", "education", "services"],
    "complexity": "medium",
    "models_count": 8,
    "includes": ["multi-tenancy", "calendar", "sms-reminders", "public-booking-page", "stripe"],
    "preview_image": "booking-platform-preview.png"
}
```

### wizard-defaults.json example:
```json
{
    "step_describe": {
        "name": "",
        "description": "Online booking and appointment management platform",
        "problem": "Manual scheduling via phone/Viber leads to double bookings, no-shows, and wasted time"
    },
    "step_users": {
        "app_type": "b2b_saas",
        "roles": [
            {"name": "admin", "description": "Business owner, manages everything", "removable": false},
            {"name": "provider", "description": "Service provider (e.g., hairdresser, dentist, trainer)", "removable": true, "renameable": true},
            {"name": "client", "description": "Books appointments, receives reminders", "removable": false}
        ]
    },
    "step_models": {
        "models": [
            {"name": "Tenant", "locked": true, "description": "Business/venue", "fields": ["name", "address", "phone", "logo", "timezone", "working_hours"]},
            {"name": "Service", "locked": false, "description": "Offered service", "fields": ["name", "duration_minutes", "price", "description", "active"]},
            {"name": "Provider", "locked": false, "description": "Person who delivers the service", "fields": ["name", "email", "phone", "specialization", "working_hours", "active"]},
            {"name": "Appointment", "locked": false, "description": "Scheduled booking", "fields": ["client_id", "provider_id", "service_id", "starts_at", "ends_at", "status", "notes"]},
            {"name": "Client", "locked": false, "description": "Customer who books", "fields": ["name", "email", "phone", "notes"]},
            {"name": "WorkingHours", "locked": false, "description": "Provider availability per day", "fields": ["provider_id", "day_of_week", "start_time", "end_time"]},
            {"name": "BlockedSlot", "locked": false, "description": "Time off / unavailable period", "fields": ["provider_id", "starts_at", "ends_at", "reason"]},
            {"name": "Reminder", "locked": false, "description": "SMS/email reminder", "fields": ["appointment_id", "type", "scheduled_at", "sent_at", "status"]}
        ]
    },
    "step_auth": {
        "multi_tenant": true,
        "auth_method": "sanctum",
        "guest_access": true,
        "guest_description": "Public booking page where clients book without registration"
    },
    "step_integrations": {
        "selected": ["sms", "stripe", "email"],
        "notes": "SMS for appointment reminders, Stripe for online payment/deposit, email for confirmations"
    }
}
```

---

## Complete Template Library (25 Templates)

### Category: OPERATIONS & MANAGEMENT

#### 1. Booking & Reservation Platform
**Use cases:** Hair salons, dental clinics, fitness trainers, padel courts, co-working spaces, car washes, photography studios, tutoring
**Core models:** Tenant, Service, Provider, Appointment, Client, WorkingHours, BlockedSlot, Reminder
**Key features:** Calendar view, conflict detection, public booking page, SMS reminders, no-show tracking, online deposit/payment
**Complexity:** Medium

#### 2. Project Management Tool
**Use cases:** Agency task tracking, freelancer project boards, development sprint planning, construction project oversight
**Core models:** Tenant, Project, Board, Column, Task, TaskComment, TaskAttachment, TeamMember, TimeEntry, Tag
**Key features:** Kanban boards, task assignments, due dates, time tracking, file attachments, activity feed, project templates
**Complexity:** Medium-High

#### 3. Inventory & Stock Management
**Use cases:** Warehouses, retail shops, e-commerce stock, restaurant supplies, hardware stores
**Core models:** Tenant, Product, Category, Warehouse, StockMovement, Supplier, PurchaseOrder, PurchaseOrderItem, StockAlert
**Key features:** Stock levels, low-stock alerts, purchase orders, supplier management, barcode support, stock history, multi-warehouse
**Complexity:** Medium

#### 4. Field Service Management
**Use cases:** HVAC repair, plumbing, electrical, cleaning services, pest control, appliance repair
**Core models:** Tenant, WorkOrder, Client, Technician, ServiceType, Part, PartUsage, Schedule, Invoice, Photo
**Key features:** Work orders with status flow, technician dispatch, parts tracking, before/after photos, client portal, invoice generation
**Complexity:** Medium-High

#### 5. Fleet & Vehicle Management
**Use cases:** Delivery companies, taxi fleets, rental agencies, logistics, construction equipment
**Core models:** Tenant, Vehicle, Driver, Trip, FuelLog, MaintenanceRecord, MaintenanceSchedule, Expense, Document
**Key features:** Vehicle tracking, maintenance schedules with reminders, fuel logging, expense tracking, document expiry alerts (registration, insurance), driver assignments
**Complexity:** Medium

---

### Category: SALES & CRM

#### 6. CRM (Customer Relationship Management)
**Use cases:** Sales teams, real estate agencies, consulting firms, insurance brokers, recruitment agencies
**Core models:** Tenant, Contact, Company, Deal, Pipeline, PipelineStage, Activity, Note, Tag, CustomField
**Key features:** Deal pipeline with drag-drop stages, contact management, activity logging, email integration, custom fields, sales forecasting, import/export
**Complexity:** Medium-High

#### 7. Invoice & Billing Platform
**Use cases:** Freelancers, agencies, consultants, small businesses, accountants
**Core models:** Tenant, Client, Invoice, InvoiceItem, Payment, Expense, ExpenseCategory, TaxRate, RecurringInvoice, BankAccount
**Key features:** Invoice generation (PDF), recurring invoices, payment tracking, expense management, tax calculation, multi-currency, overdue reminders, financial dashboard
**Complexity:** Medium

#### 8. E-commerce / Online Store
**Use cases:** Product shops, digital goods, subscription boxes, dropshipping, marketplace
**Core models:** Tenant, Product, ProductVariant, Category, Cart, CartItem, Order, OrderItem, Customer, Address, Coupon, Review, Wishlist
**Key features:** Product catalog with variants, shopping cart, checkout flow, order management, inventory sync, coupons/discounts, customer reviews, Stripe payments
**Complexity:** High

#### 9. Subscription & Membership Platform
**Use cases:** Gyms, clubs, online communities, premium content, SaaS billing
**Core models:** Tenant, Plan, Subscription, Member, Payment, Invoice, Feature, PlanFeature, UsageRecord, Cancellation
**Key features:** Plan management, recurring billing (Stripe), usage tracking, member portal, upgrade/downgrade, trial periods, cancellation flow, churn analytics
**Complexity:** Medium-High

---

### Category: CONTENT & COMMUNICATION

#### 10. Content Management / Blog Platform
**Use cases:** Multi-author blogs, news sites, documentation, knowledge bases, company intranets
**Core models:** Tenant, Post, Category, Tag, Author, Comment, Media, Page, Menu, MenuItem, Revision
**Key features:** Rich text editor, SEO fields (meta, slug, OG), categories/tags, draft/publish workflow, revision history, media library, RSS feed, sitemap generation
**Complexity:** Medium

#### 11. Newsletter & Email Marketing
**Use cases:** Creator newsletters, marketing campaigns, product updates, community digests
**Core models:** Tenant, Subscriber, SubscriberList, Campaign, CampaignEmail, Template, SendLog, Unsubscribe, Link, LinkClick
**Key features:** Subscriber management with lists/segments, campaign builder, email templates, send scheduling, open/click tracking, unsubscribe handling, analytics dashboard
**Complexity:** Medium-High

#### 12. Helpdesk & Support Ticket System
**Use cases:** Customer support, IT helpdesk, internal request management, bug tracking
**Core models:** Tenant, Ticket, TicketReply, TicketCategory, TicketPriority, Agent, Customer, CannedResponse, Tag, Attachment, SLA
**Key features:** Ticket creation (email + web), assignment, priority/SLA tracking, canned responses, internal notes, customer portal, satisfaction rating, knowledge base
**Complexity:** Medium-High

---

### Category: MARKETPLACE & PLATFORM

#### 13. Two-Sided Marketplace
**Use cases:** Freelancer platforms, service marketplaces, rental platforms, tutoring, local services
**Core models:** Tenant, Listing, ListingCategory, Seller, Buyer, Order, Review, Message, MessageThread, Payout, Dispute, Favorite
**Key features:** Seller profiles, listing management, search/filter, messaging between parties, order flow, review system, escrow/split payments, seller payouts, dispute resolution
**Complexity:** High

#### 14. Job Board / Recruitment Platform
**Use cases:** Niche job boards, company career pages, freelance gig platforms, recruitment agencies
**Core models:** Tenant, Job, JobCategory, Company, Application, Applicant, Resume, SavedJob, JobAlert, Interview
**Key features:** Job posting with rich descriptions, application flow, applicant tracking, company profiles, search/filter, email alerts for new jobs, interview scheduling
**Complexity:** Medium

#### 15. Directory & Listing Platform
**Use cases:** Business directories, restaurant guides, service provider listings, event venues, travel guides
**Core models:** Tenant, Listing, Category, Location, Review, Photo, Claim, Feature, PricingTier, Contact, OperatingHours
**Key features:** Categorized listings, search with filters/map, reviews and ratings, business claiming, featured/promoted listings, contact forms, SEO-optimized pages
**Complexity:** Medium

---

### Category: EDUCATION & LEARNING

#### 16. LMS (Learning Management System)
**Use cases:** Online courses, employee training, school management, certification programs, coaching
**Core models:** Tenant, Course, Module, Lesson, Enrollment, Progress, Quiz, QuizQuestion, QuizAttempt, Certificate, Instructor, Review
**Key features:** Course builder, video/text lessons, progress tracking, quizzes with scoring, certificates, instructor dashboards, student portal, drip content, Stripe for payments
**Complexity:** High

#### 17. School / Academy Management
**Use cases:** Driving schools, language schools, music schools, dance studios, martial arts, swim schools
**Core models:** Tenant, Student, Instructor, Program, Class, Schedule, Attendance, Payment, PaymentPlan, Document, Grade, Certificate
**Key features:** Student enrollment, class scheduling, attendance tracking, payment plans with installments, document management, progress reports, instructor assignment, parent portal
**Complexity:** Medium-High

---

### Category: HEALTH & WELLNESS

#### 18. Patient / Client Record System
**Use cases:** Medical clinics, dental offices, physiotherapy, veterinary, psychology practices, nutrition counseling
**Core models:** Tenant, Patient, Appointment, MedicalRecord, Treatment, Prescription, Document, InsuranceInfo, Billing, Consent
**Key features:** Patient profiles with medical history, appointment scheduling, treatment records, document uploads, GDPR/privacy compliance, billing, consent management
**Complexity:** Medium-High

#### 19. Fitness & Wellness Platform
**Use cases:** Personal trainers, gym management, yoga studios, wellness coaches, nutrition tracking
**Core models:** Tenant, Client, TrainingPlan, Workout, Exercise, WorkoutLog, MealPlan, Meal, BodyMetric, Goal, ProgressPhoto, Subscription
**Key features:** Custom training plans, exercise library, workout logging, meal planning, body metrics tracking, progress photos, client portal, subscription billing
**Complexity:** Medium

---

### Category: HOSPITALITY & FOOD

#### 20. Restaurant & Café Management
**Use cases:** Digital menus, QR ordering, table management, kitchen display, food delivery
**Core models:** Tenant, Category, MenuItem, Table, Order, OrderItem, WaiterCall, Review, Template, QRCode, Scan
**Key features:** Digital menu with images/badges, QR code per table, call waiter notifications, menu templates/themes, multi-language, allergen info, analytics
**Complexity:** Medium

#### 21. Property / Rental Management
**Use cases:** Vacation rentals, apartment management, co-living, Airbnb management, storage units
**Core models:** Tenant, Property, Unit, Booking, Guest, Rate, SeasonalPricing, MaintenanceRequest, Review, CalendarSync, Payout, Message
**Key features:** Property listings, availability calendar, booking management, seasonal pricing, channel sync (Airbnb/Booking), guest communication, maintenance requests, owner payouts
**Complexity:** High

---

### Category: ANALYTICS & MONITORING

#### 22. Analytics / Dashboard Builder
**Use cases:** Business intelligence, KPI tracking, client reporting, data visualization
**Core models:** Tenant, Dashboard, Widget, DataSource, DataConnection, Report, ReportSchedule, Metric, Alert, SharedLink
**Key features:** Drag-drop dashboard builder, multiple chart types, data source connections (API, database, CSV), scheduled reports (PDF/email), alerts on thresholds, shareable links
**Complexity:** High

#### 23. Monitoring & Alerting System
**Use cases:** Uptime monitoring, server health, cron job monitoring, API monitoring, status pages
**Core models:** Tenant, Monitor, MonitorCheck, Incident, StatusPage, StatusPageComponent, AlertChannel, AlertRule, MaintenanceWindow, TeamMember
**Key features:** HTTP/ping/cron monitors, check intervals, incident management, public status pages, alert channels (email, SMS, Slack, Telegram), maintenance windows, response time graphs
**Complexity:** Medium-High

---

### Category: SPECIALIZED

#### 24. Event Management Platform
**Use cases:** Conferences, meetups, workshops, festivals, concerts, community events
**Core models:** Tenant, Event, EventCategory, Venue, Ticket, TicketType, Attendee, Speaker, Sponsor, Schedule, ScheduleItem, CheckIn
**Key features:** Event creation, ticket sales (free + paid), attendee management, speaker profiles, event schedule builder, check-in via QR, sponsor management, event page builder
**Complexity:** Medium-High

#### 25. API-as-a-Service Platform
**Use cases:** API products, data services, developer tools, webhook services, integration platforms
**Core models:** Tenant, ApiKey, Endpoint, RateLimit, UsageLog, Plan, Subscription, Webhook, WebhookLog, Documentation, Changelog
**Key features:** API key management, rate limiting per plan, usage tracking/metering, subscription billing based on usage, auto-generated docs, webhook management, changelog
**Complexity:** Medium-High

---

## Template Categories Summary

| Category                  | Templates | Coverage                                    |
|---------------------------|-----------|---------------------------------------------|
| Operations & Management   | 5         | Booking, projects, inventory, field service, fleet |
| Sales & CRM               | 4         | CRM, invoicing, e-commerce, subscriptions   |
| Content & Communication   | 3         | CMS, newsletters, helpdesk                  |
| Marketplace & Platform    | 3         | Marketplace, job board, directory           |
| Education & Learning      | 2         | LMS, school management                     |
| Health & Wellness         | 2         | Patient records, fitness                    |
| Hospitality & Food        | 2         | Restaurant, property rental                 |
| Analytics & Monitoring    | 2         | Dashboards, uptime monitoring               |
| Specialized               | 2         | Events, API-as-a-Service                    |
| **TOTAL**                 | **25**    |                                             |

## Why These 25 Cover 95% of SaaS Ideas

Almost every SaaS idea is a variation or combination of these patterns:

- "Uber for X" → Two-Sided Marketplace (#13) + Booking (#1)
- "Notion competitor" → Project Management (#2) + CMS (#10)
- "Dental software" → Booking (#1) + Patient Records (#18)
- "Shopify alternative" → E-commerce (#8) + Subscription (#9)
- "Calendly clone" → Booking (#1) simplified
- "Freshdesk competitor" → Helpdesk (#12)
- "Teachable alternative" → LMS (#16) + Subscription (#9)
- "Property management" → Property Rental (#21) + Invoice (#7)
- "Agency management" → CRM (#6) + Project Management (#2) + Invoice (#7)

When a user's idea doesn't fit a single template perfectly, they pick the closest one and customize via the wizard. The AI adapts the template to their specific domain.

## Rollout Plan

**Launch (Month 1):** 5 templates
1. Booking & Reservation Platform (most universal)
2. CRM
3. Invoice & Billing
4. Restaurant & Café Management (our own QRMeni — dogfooded)
5. Project Management Tool

**Month 2:** +5 templates
6. E-commerce / Online Store
7. Helpdesk & Support Ticket
8. Content Management / Blog
9. School / Academy Management
10. Subscription & Membership

**Month 3-4:** +5 templates
11. Two-Sided Marketplace
12. Field Service Management
13. LMS
14. Directory & Listing
15. Event Management

**Month 5-6:** +5 templates
16. Patient / Client Record System
17. Job Board / Recruitment
18. Newsletter & Email Marketing
19. Monitoring & Alerting
20. API-as-a-Service

**Month 7+:** remaining 5 + community requests
21. Inventory & Stock Management
22. Fleet & Vehicle Management
23. Fitness & Wellness
24. Property / Rental Management
25. Analytics / Dashboard Builder
