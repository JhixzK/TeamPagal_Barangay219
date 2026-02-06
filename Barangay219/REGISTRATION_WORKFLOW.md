# E-Barangay Resident Registration Workflow

## Overview

The registration system follows an **approval-based workflow** and **Data Privacy Act of 2012** principles. Initial registration does **not** require a username or password.

## Registration Flow

1. **Resident submits application** via `register.php` (public, no login)
2. System creates a **PENDING** application in `resident_applications`
3. **Barangay staff** reviews applications in `applications.php` (Applications menu)
4. Upon **approval**:
   - Resident ID (e.g., `BR219-2026-00001`) is auto-generated
   - Resident record is created in `residents`
   - User account is created with Resident ID as username
   - Activation link is generated (valid 7 days)
5. **Resident** clicks activation link and sets password
6. Resident logs in with **Resident ID** and password

## Registration Sections (in order)

| Section | Fields |
|---------|--------|
| Personal Information | Full name, sex, DOB (age auto-calculated), place of birth, civil status, citizenship |
| Family & Household | Family Code / Head of Family ID, relationship to head |
| Address & Residency | House number, street, purok/sitio, barangay/city/province (auto-filled), length of residency |
| Contact & Emergency | Mobile, email (optional), emergency contact name/number/relationship |
| Education & Employment | Educational attainment, employment status, occupation |
| Special Categories | Senior citizen (auto-validated 60+), PWD, solo parent, IP member, 4Ps beneficiary |
| Identification | Valid ID type/number, upload valid ID, upload proof of residency, Data Privacy consent |

## Design Principles

- **Privacy-first**: ID-based referencing; no public exposure of sensitive data
- **Approval-driven**: No account until barangay approval
- **Audit-ready**: `application_audit_log` records submitted, approved, rejected actions
- **Data Privacy Act compliant**: Consent checkbox, minimal data collection

## Key Files

| File | Purpose |
|------|---------|
| `public/register.php` | Public registration form (no auth) |
| `public/activate-account.php` | Password setup after approval |
| `public/applications.php` | Barangay staff review/approve/reject |
| `api/register.php` | Public API – creates PENDING application |
| `api/applications.php` | Staff API – list, get, approve, reject |
| `api/activate-account.php` | Account activation (set password) |
| `database/migrations/001_resident_registration_workflow.sql` | Schema for workflow |

## Setup

1. Run migration: visit `run-migration.php` or import `database/migrations/001_resident_registration_workflow.sql`
2. Ensure `uploads/applications/` is writable (created automatically by API)

## Resident ID Format

`BR219-YYYY-NNNNN` (e.g., BR219-2026-00001)

## Administrative Fields

- `record_status` – active, inactive, deceased, transferred
- `remarks` – staff notes
- `last_updated_by` / `last_updated_at` – audit trail
