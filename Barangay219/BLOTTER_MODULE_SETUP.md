# Resident Incident Reporting (Blotter) Setup Guide

This guide sets up the resident blotter reporting feature end-to-end.

## 1) Apply Migration

Run the SQL in:

- `database/migrations/016_resident_blotter_records.sql`

Recommended options:

- Import via phpMyAdmin SQL tab.
- Or append it to your migration/import routine.

The migration creates `blotter_records` with:

- Resident complainant linkage
- Resident/non-resident respondent fields
- Incident details and narrative
- Optional witness JSON and evidence path
- Status tracking (`pending`, `investigation`, `mediation`, `settled`, `dismissed`)

## 2) Confirm Folder Permissions

Ensure web server can write to:

- `uploads/blotter/`

This folder is used for resident evidence image uploads.

## 3) Resident Pages

New resident pages:

- `public/report_incident.php` - submit incident report
- `public/my_blotters.php` - view own blotter list and details

Resident sidebar now includes links for both pages.

## 4) APIs Used by Resident UI

- `api/blotter/create.php` - create report
- `api/blotter/list.php` - list own reports
- `api/blotter/get.php` - view own report details
- `api/blotter/resident-options.php` - respondent resident options

All endpoints are resident-auth scoped and return JSON.

## 5) Quick Verification Checklist

1. Log in as a resident account.
2. Open `Report Incident` and submit one report.
3. Verify redirect to `My Blotters` with a reference number.
4. Open report details from list.
5. Confirm evidence link opens uploaded file (if provided).

## Notes

- Respondent can be selected from residents or entered manually for non-residents.
- Confidential reports are flagged in the record.
- Residents can only view their own blotter records.
