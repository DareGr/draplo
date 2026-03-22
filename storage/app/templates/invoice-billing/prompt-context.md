# Invoice & Billing — AI Generation Context

## Core Business Logic

An invoice and billing platform enables businesses to create professional invoices, track payments, manage expenses, and maintain financial records. The core workflow is: create an invoice with line items, send it to a client, record payments against it, and track the outstanding balance. Recurring invoices automate this cycle for retainer clients or subscription-style services. The platform also tracks business expenses to give a complete picture of revenue vs. costs.

## Model Relationships

- A **Tenant** is the business issuing invoices. It stores company details, tax number, logo, and invoice numbering configuration (prefix + auto-increment).
- **Clients** are the customers who receive invoices. Each client belongs to a tenant and has default currency and payment terms that pre-fill when creating invoices for them.
- **Invoices** are the central entity. Each invoice belongs to a client, contains line items, and tracks its lifecycle via status (draft, sent, viewed, partially_paid, paid, overdue, cancelled). Financial totals (subtotal, tax, discount, total, amount_paid) are stored denormalized for query performance.
- **InvoiceItems** are ordered line items on an invoice. Each item has quantity, unit price, optional tax rate, optional discount, and a computed line total. Position determines display order.
- **Payments** record money received against an invoice. An invoice can have multiple partial payments. When sum of payments equals the invoice total, status transitions to paid. Payments reference the payment method and optionally a bank account and Stripe payment ID.
- **Expenses** track business costs. Each expense belongs to an **ExpenseCategory** and optionally a client (for billable expenses that can be added to the next invoice). Receipt file paths allow document attachment.
- **ExpenseCategories** organize expenses (e.g., office supplies, travel, software, utilities) with colors for dashboard visualizations.
- **TaxRates** define reusable tax rates (e.g., "VAT 20%", "GST 10%"). A tax rate can be compound (calculated on top of other taxes). Items reference a tax rate to auto-calculate tax amounts.
- **RecurringInvoices** are templates that automatically generate invoices on a schedule (weekly, monthly, quarterly, yearly). They store the same line item structure and generate a new Invoice record on each cycle date. The auto_send flag controls whether generated invoices are sent immediately or saved as drafts.
- **BankAccounts** store the business's banking details displayed on invoices for wire transfer payments. Multiple accounts support multi-currency operations.

## Migration Ordering

1. Tenant (no dependencies)
2. Client (belongs to tenant)
3. ExpenseCategory (belongs to tenant)
4. TaxRate (belongs to tenant)
5. BankAccount (belongs to tenant)
6. RecurringInvoice (belongs to tenant, client)
7. Invoice (belongs to tenant, client, optional recurring_invoice)
8. InvoiceItem (belongs to invoice, optional tax_rate)
9. Payment (belongs to invoice, optional bank_account)
10. Expense (belongs to tenant, expense_category, optional client)

## Key Patterns

**Invoice Numbering:** Each tenant has a configurable prefix (e.g., "INV-") and auto-incrementing number. The invoice_number field stores the formatted string (e.g., "INV-2026-0042"). Numbering must be sequential with no gaps within a tenant.

**Status Lifecycle:** Invoices follow a strict status flow: draft -> sent -> viewed -> partially_paid -> paid. Overdue is computed (sent/partially_paid + past due_date). Cancelled is a terminal state. Status transitions should be event-driven to trigger notifications.

**Financial Calculations:** Line totals are computed server-side: (quantity * unit_price) - discount + tax. Invoice totals aggregate line items. Amount paid is the sum of associated payments. Balance due = total - amount_paid. All monetary values use decimal type with 2 decimal places.

**Recurring Invoice Generation:** A scheduled job runs daily, checks recurring invoices where next_generate_date <= today and is_active = true, generates the invoice with items cloned from the template, advances next_generate_date based on frequency, and optionally sends the invoice automatically.

**Client Portal (Guest Access):** Clients access their invoices via a unique signed URL or portal login. They can view invoice PDFs, see payment history, and pay online via Stripe. Portal access is controlled per-client via the portal_enabled flag.

**PDF Generation:** Invoices must render as professional PDF documents with the tenant's logo, address, and bank details. The PDF is generated on-demand or cached when the invoice is finalized.
