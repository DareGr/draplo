# Booking & Reservation Platform — Template Context

## Core Business Logic

This is a multi-tenant appointment scheduling platform where each tenant represents a business (salon, clinic, studio, sports court). Businesses offer services delivered by providers to clients during available time slots.

The core flow: Client visits public booking page → selects service → picks available provider → chooses time slot (checking WorkingHours and BlockedSlots) → creates Appointment → receives SMS/email confirmation → gets reminder 24h and 1h before.

## Model Relationships

- **Tenant** is the root entity. Has many Services, Providers, Clients, Appointments.
- **Service** belongs to Tenant. Has a duration in minutes and a price. Many Appointments reference a Service.
- **Provider** belongs to Tenant. A person who delivers services (hairdresser, dentist, trainer). Has WorkingHours per day of week and BlockedSlots for time off. Has many Appointments.
- **Appointment** is the central model. Belongs to Client, Provider, and Service. Has start/end timestamps and a status (scheduled, confirmed, completed, cancelled, no_show).
- **Client** belongs to Tenant. Not an authenticated user — books via public page. Has name, email, phone for communication.
- **WorkingHours** belongs to Provider. Defines availability per day of week (Monday-Sunday) with start_time and end_time.
- **BlockedSlot** belongs to Provider. Represents unavailable periods (vacation, sick leave, lunch break).
- **Reminder** belongs to Appointment. Tracks scheduled SMS/email notifications with status (pending, sent, failed).

## Migration Ordering

1. Tenant (no FK)
2. Service (tenant_id FK)
3. Provider (tenant_id FK)
4. Client (tenant_id FK)
5. Appointment (client_id, provider_id, service_id FK)
6. WorkingHours (provider_id FK)
7. BlockedSlot (provider_id FK)
8. Reminder (appointment_id FK)

## Key Patterns

- **Availability check**: Before creating an appointment, verify the provider has WorkingHours for that day and no overlapping BlockedSlots or existing Appointments.
- **Tenant scoping**: All queries must be scoped to the current tenant via a global scope on tenant_id.
- **Public booking page**: Unauthenticated route that accepts tenant slug, shows services and available slots.
- **Status flow**: Appointment goes scheduled → confirmed → completed (or cancelled/no_show at any point).

## Integration Notes

- **SMS (Infobip/Twilio)**: Send appointment confirmation immediately, reminders at 24h and 1h before start time. Use Laravel notifications with a custom SMS channel.
- **Stripe**: Optional payment/deposit at booking time. Use Laravel Cashier for checkout session creation.
- **Email**: Confirmation email on booking, reminder emails alongside SMS, cancellation notifications.
