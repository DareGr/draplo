# CRM — AI Generation Context

## Core Business Logic

A CRM (Customer Relationship Management) system is the central hub for sales teams to track relationships with prospects and customers through the entire sales lifecycle. The fundamental unit of work is the **Deal** — a potential sale moving through a series of pipeline stages from initial contact to close (won or lost). Every interaction with a contact is logged as an **Activity**, creating a complete history that any team member can review.

## Model Relationships

- A **Tenant** represents a company account. All data is scoped to tenant.
- **Contacts** are individual people. Each contact optionally belongs to a **Company**. A contact can be associated with multiple **Deals**.
- **Companies** group contacts under a single organization. Companies have an owner (sales rep) and aggregate revenue from their deals.
- **Deals** move through **PipelineStages** within a **Pipeline**. Each deal has a monetary value, probability percentage, and expected close date. A deal references one contact, optionally one company, and is owned by a sales rep.
- **Pipelines** contain ordered **PipelineStages**. Stages have a position for ordering, a default probability, and flags for win/loss stages. A tenant can have multiple pipelines (e.g., "New Business", "Renewals").
- **Activities** are polymorphic-style records (call, email, meeting, task) linked to both a contact and optionally a deal. They track due dates and completion status for task-type activities.
- **Notes** use a polymorphic relationship (notable_type + notable_id) to attach free-text notes to contacts, companies, or deals.
- **Tags** are applied to contacts, companies, and deals via a pivot table (taggables). They enable flexible categorization beyond fixed fields.
- **CustomFields** define tenant-specific fields for contacts, companies, or deals. The field_type determines rendering (text, number, dropdown, date, checkbox). Options stored as JSON for dropdown types. Actual values are stored in a separate custom_field_values pivot table.

## Migration Ordering

1. Tenant (no dependencies)
2. Pipeline (belongs to tenant)
3. PipelineStage (belongs to pipeline)
4. Company (belongs to tenant)
5. Contact (belongs to tenant, optional company)
6. Deal (belongs to tenant, pipeline, pipeline_stage, contact, optional company)
7. Activity (belongs to tenant, contact, optional deal, user)
8. Note (polymorphic, belongs to tenant, user)
9. Tag (belongs to tenant) + taggables pivot
10. CustomField (belongs to tenant) + custom_field_values pivot

## Key Patterns

**Pipeline & Stages:** Pipelines are ordered collections of stages. When a deal moves between stages, record the stage change timestamp for velocity reporting. Win/loss stage flags determine when a deal is considered closed. Default probability auto-fills when a deal enters a stage.

**Activity Logging:** Every meaningful interaction should create an Activity record. Activities serve dual purpose: historical log and future task reminders. Overdue incomplete activities should surface in the dashboard.

**Custom Fields:** The EAV (Entity-Attribute-Value) pattern allows tenants to extend Contact, Company, and Deal models without schema changes. Field definitions (CustomField) are separate from field values (custom_field_values table with entity_type, entity_id, custom_field_id, value columns).

**Ownership & Assignment:** Contacts, companies, and deals have an owner_id (sales rep). This enables "My Contacts" / "My Deals" filtered views and sales performance reporting per rep.

**Import/Export:** CRM data frequently needs CSV import (migrating from spreadsheets or other CRMs) and export (reporting, backup). Design contact and deal models with this in mind — avoid overly nested JSON columns that complicate flat-file export.
