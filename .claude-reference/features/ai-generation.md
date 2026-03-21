# Feature: AI Generation Engine

## Overview

The AI generation engine is the core IP of Draplo. It takes structured wizard data (JSON) and produces 7+ project files tailored to the described SaaS application. The quality of this output determines whether the product succeeds or fails.

## System Prompt Strategy

The system prompt is ~3000 tokens and contains:
1. Role definition: "You are a senior Laravel architect generating project scaffolds"
2. Output format: XML file tags with exact file paths
3. Laravel-specific rules: valid PHP syntax, migration ordering, Sanctum patterns
4. Quality standards: realistic field types, proper foreign keys, RESTful endpoints
5. Style guide: how CLAUDE.md should be structured, todo.md phase format, etc.

The system prompt is CACHED via Anthropic's prompt caching. It costs 1.25x on first call, then 0.1x on subsequent calls (90% savings). This is critical for keeping per-generation costs under $0.25.

## User Message Construction

The wizard_data JSON is transformed into a natural language description:

```
Generate a complete project scaffold for the following SaaS application:

**Name:** QRMeni
**Description:** Digital menu platform for cafes and restaurants...
**Problem it solves:** Printed menus are expensive to update...

**App Type:** B2B SaaS
**Multi-tenant:** Yes

**User Roles:**
- admin: Venue owner, manages menu and settings
- staff: Waiter, receives call notifications  
- guest: Anonymous, scans QR, views menu (no auth)

**Core Models:**
- Tenant: A venue (cafe/restaurant) — fields: name, address, logo
- Category: Menu category — fields: name, icon, sort_order
- MenuItem: Single menu item — fields: name, price, image, available
- Table: Physical table with QR — fields: number, qr_uuid
- WaiterCall: Call waiter request — fields: table_id, status

**Integrations:** File storage (MinIO), WebSockets (Reverb)

Generate all files wrapped in <file path="...">...</file> tags.
```

## Output Validation

After parsing, validate:
1. All 7 expected files are present
2. Migration files contain valid PHP (regex check for `Schema::create`)
3. Architecture.md contains at least one table definition
4. Todo.md contains at least 10 checkbox items
5. No file exceeds 50KB (sanity check)

If validation fails, retry generation once. If second attempt fails, mark project as `failed` and notify user.

## Testing Strategy

Maintain a test suite of 5+ diverse project descriptions. After any system prompt change, run all test cases and manually review output quality. Automated checks verify structure; human review verifies content quality.

Test cases should cover:
- Simple app (3 models, no tenancy)
- Complex SaaS (10+ models, multi-tenant, multiple integrations)
- Marketplace (two-sided: buyers + sellers)
- API-only service (no frontend)
- App with real-time features (WebSockets)
