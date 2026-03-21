# UI Regression Checklist

Use this quick checklist whenever table/list UI styling is updated.

## Scope
- Resident Applications
- Residents
- Households
- Blotter
- Complaints
- Announcements
- Certificate Applications
- Users
- Officials (tile actions)

## Visual Consistency
- Action icon buttons are the same size (32x32) and radius.
- Action icon button hover states are consistent.
- Status/role pills have consistent height and typography.
- Search bar controls (Search, Filter, Reset) share height and alignment.
- Actions column width uses utility classes only:
  - actions-col-compact
  - actions-col-wide

## Table Behavior
- Main table in each module is scrollable.
- Header row remains sticky while scrolling.
- Actions column does not wrap unexpectedly.
- Empty state row looks intentional and readable.

## Filtering and Search
- Search input works by button click.
- Search input works with Enter key.
- Search input debounced typing triggers results.
- Selected status tab is persisted per module/page.
- Search query is persisted per module/page.

## Accessibility
- Icon-only action buttons have aria-label values.
- Focus state is visible for buttons and action icons.
- Pill contrast remains readable against background.

## Responsive Checks
- Desktop (>= 992px): no clipped controls or badges.
- Tablet (~768px): columns remain readable and scroll works.
- Mobile (<= 576px): filters and actions remain usable.

## Quick Smoke Tests
1. Open each module and confirm table loads with no JS errors.
2. Click each status tab and verify list refreshes.
3. Type in search, wait, and verify debounce search works.
4. Refresh page and verify tab/search persistence.
5. Hover and keyboard-focus action icons in each module.
