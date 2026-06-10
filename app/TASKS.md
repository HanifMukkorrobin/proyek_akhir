# Tasks

## Done
- [x] Initial full-stack repository structuring.
- [x] Baseline CI/CD setup.
- [x] Nuxt 4 & Lumen baseline setup.
- [x] Setup `.env` parsing via dotenv in Nuxt.
- [x] Setup Nuxt landing page initial structure with custom colors & dark mode block.
- [x] Simplify header UI (FE-006).
- [x] Integrate DaisyUI for components (FE-007).
- [x] Fix layout collision on the `.hero` class (FE-008).
- [x] Refactor to utility-only classes without `<style>` tags (FE-009).
- [x] Migrate all standard icons to `@iconify/vue` (FE-010).
- [x] Update landing page to precise visual layout spec matching attached HTML design (FE-011).
- [x] Replace app UI with GeoVisit PJJ IT reference design across landing, login, admin dashboard, logs, mahasiswa/import modal, and users/reset modal (FE-013).
- [x] Align User Management display with usergroup-based user data shape (FE-014).
- [x] Connect User Management page to `/users` CRUD and reset password API (FE-015).
- [x] Connect Mahasiswa directory to `/mahasiswa` CRUD and two-phase import API (FE-016).
- [x] Convert Mahasiswa import template/download/upload flow to Excel without `dibuat_oleh_user_id` column (FE-017).
- [x] Connect Dashboard to `/dashboard/summary`, `/dashboard/chart`, and `/dashboard/wilayah-tree` with Highcharts and expandable table tree (FE-018).
- [x] Connect Log Aktivitas to `/activity-logs` list/summary/detail API with filters, pagination, detail modal, recent events, top modules, and CSV export (FE-019).
- [x] Add `/dashboard/chart` as the non-admin chart dashboard entry with login redirect support (FE-020).
- [x] Add `/dashbord/map` as the non-admin CesiumJS 3D map entry with `/dashboard/map` alias (FE-021).
- [x] Connect route simulation calculations to OSRM API and render returned geometry as 3D map polyline (FE-035).
- [x] Rework route simulation into create modal and saved history sidebar (FE-036).
- [x] Add delete action for owned simulations in history sidebar (FE-040).
- [x] Send return-to-departure route simulation payload and label final waypoint as Kembali (FE-041).
- [x] Show manual vs optimized route comparison metrics in simulation detail (FE-042).
- [x] Fix simulation route camera focus so detailed OSRM polylines zoom in to route bounds (FE-043).
- [x] Auto-redraw simulation route when switching detail and clear route when exiting sidebar detail (FE-044).
- [x] Hide wilayah distribution markers, labels, and detail panels while a simulation route is active (FE-045).

## Doing
- No active task.

## Blocked
- None.

## Todo
- Setup `useFetch` composables mapped to Lumen API endpoints.
- Wire remaining visual-first admin mock logs screen to live API when available.
