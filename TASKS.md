# TASKS

## Todo
| ID | Title | Priority | Owner/Agent | Dependencies | Notes |
| --- | --- | --- | --- | --- | --- |
| API-009 | Add endpoint test cases for public wilayah | P1 | Unassigned | API-007 | Cover no-param, wilayah_id, cari, and combined filter behavior. |
| API-014 | Add automated tests for classifier endpoint | P1 | Unassigned | API-013 | Cover normalization, tokenization, exact and fuzzy matching paths. |
| API-016 | Tune external geocoding merge heuristics | P1 | Unassigned | API-017 | Refine when Nominatim hint should override internal mapping. |
| API-018 | Add connectivity monitor for Nominatim | P2 | Unassigned | API-017 | Optional: detect timeout/rate-limit and expose health telemetry. |
| API-020 | Improve token normalization quality | P1 | Unassigned | API-012 | Optional: collapse repeated terms in token chunks after numeric removal. |
| API-049 | Persist Nominatim cache across requests | P1 | Unassigned | API-017 | Cache per-request dibuang setelah response. Tambah cache persistent (Cache store / tabel) berkunci md5 query, TTL 30–90 hari untuk mempercepat bulk import. |
| API-051 | Add data-driven kabupaten/kota alias dictionary | P2 | Unassigned | API-023 | Tambah tabel `wilayah_aliases (wilayah_id, alias)` dan merge alias ke `buildSearchKeys` supaya shortform umum (JKT/BDG/SBY/MLG) ter-resolve. |
| API-052 | Add phonetic fallback matcher (Metaphone) | P2 | Unassigned | API-012 | Tambah pass Metaphone untuk token ≥6 karakter ketika fuzzy Levenshtein gagal lolos threshold adaptif. |
| API-053 | Move stop-word and estate-word list to config | P2 | Unassigned | API-012 | Pindahkan `STOP_WORDS_PATTERN` ke `config/classifier.php` / env sehingga tuning tidak perlu edit class. |
| API-057 | Add automated tests for OSRM route simulation | P1 | Unassigned | API-056 | Use approved dummy coordinates or mocked OSRM responses; do not send private mahasiswa locations to public OSRM. |
| API-058 | Decide production OSRM provider/deployment | P1 | Unassigned | API-056 | Public OSRM demo server is not a production/privacy boundary. Decide self-hosted OSRM or approved provider. |
| API-059 | Extend visitasi constraints | P2 | Unassigned | API-056 | Add time windows, max visits per route, priority weighting, and explicit end-point exceptions if required. |

## Doing
| ID | Title | Priority | Owner/Agent | Dependencies | Notes |
| --- | --- | --- | --- | --- | --- |
| - | - | - | - | - | No active task. |

## Blocked
| ID | Title | Priority | Owner/Agent | Dependencies | Notes |
| --- | --- | --- | --- | --- | --- |
| - | - | - | - | - | No blocked task logged. |

## Done
| ID | Title | Priority | Owner/Agent | Dependencies | Notes |
| --- | --- | --- | --- | --- | --- |
| CTX-000 | Normalize shared project context baseline | P0 | Copilot | None | Added required context files and initial entries. |
| API-000 | Bootstrap Lumen API with PostgreSQL defaults | P0 | Copilot | None | Created `api/`, enabled Eloquent/facades, set pgsql env defaults. |
| API-001 | Confirm local PostgreSQL target database | P0 | Copilot | None | Connected to DB `project_ta` from `api/.env`. |
| API-002 | Read tables and columns from PostgreSQL | P0 | Copilot | API-001 | Found `public.mahasiswa` and `public.wilayah` with full column metadata. |
| API-004 | Generate model per existing table | P0 | Copilot | API-002 | Added `Mahasiswa` and `Wilayah` models mapped to DB schema. |
| API-003 | Define API contract for map sebaran 3D | P0 | Copilot | API-004 | Contract embedded in response fields from `/public/get-wilayah`. |
| API-007 | Implement endpoint read wilayah | P1 | Copilot | API-003 | Added route and controller with level/child/search-parent logic. |
| API-011 | Refactor wilayah endpoint to repository pattern | P1 | Copilot | API-007 | Moved API processing from controller into `app/Repositories/PublicWilayahRepository.php`. |
| API-012 | Build address-to-wilayah classification engine | P0 | Copilot | API-011 | Added normalization, stop-word removal, tokenization, matching, fuzzy fallback, and internal geocoding resolver in repository layer. |
| API-013 | Create API testing endpoint for classifier | P0 | Copilot | API-012 | Added `POST /public/test-klasifikasi-alamat` using provided sample addresses as default test dataset. |
| API-015 | Tune classifier to reach desa level | P0 | Copilot | API-013 | Enabled desa-level dictionary coverage and depth-aware anchor selection with confirmation fallback. |
| API-017 | Integrate optional Nominatim fallback geocoding | P0 | Copilot | API-015 | Added external geocoding repository + classifier fallback flow with request option `use_external_geocoding`. |
| API-019 | Query Nominatim from manual mapping output | P0 | Copilot | API-017 | External query now built from internal geocoding result (desa/kec/kab/prov), not raw address input. |
| API-021 | Remove numeric tokens from classifier output | P0 | Copilot | API-012 | Tokenizer now excludes all tokens/ngrams containing digits to improve match accuracy. |
| API-022 | Optimize classifier with hierarchy consistency and admin hints | P0 | Copilot | API-021 | Added administrative hint extraction/scoring, hierarchy completeness scoring, related regions diagnostics, and consistency validation in classifier mapping output. |
| API-023 | Implement classifier accuracy quick wins | P0 | Copilot | API-022 | Added wilayah alias dictionary support, adaptive fuzzy threshold by token length, calibrated confidence scoring, and top-3 review candidates for ambiguous cases. |
| DOC-001 | Document geocoding processing flow | P0 | Copilot | API-023 | Added technical flow documentation covering internal mapping, confidence gating, and Nominatim fallback behavior. |
| API-006 | Implement endpoint read mahasiswa | P1 | Copilot | API-003 | Added `GET /mahasiswa` with search and pagination metadata response. |
| API-010 | Move next API logic to repository layer | P1 | Copilot | None | Added `MahasiswaRepository` and thin `MahasiswaController` for CRUD flow. |
| API-024 | Add reusable global pagination helper | P0 | Copilot | API-006 | Added global `paginate_builder` helper for reusable paginated query response. |
| API-025 | Implement mahasiswa CRUD with auto geocoding | P0 | Copilot | API-024 | Added create/read/update/delete endpoints and auto-populate `wilayah_id`, `latitude`, `longitude` from alamat classifier pipeline. |
| API-026 | Enforce UUID mahasiswa_id on create | P0 | Copilot | API-025 | Create flow now always generates `mahasiswa_id` using UUID and ignores manual input ID payload. |
| API-027 | Embed wilayah object in mahasiswa GET response | P0 | Copilot | API-025 | Added mahasiswa->wilayah relation and return `wilayah: {}` style object payload on list/detail response. |
| API-028 | Standardize API response envelope | P0 | Copilot | API-027 | Added unified `code/data/message/errors` response format via base controller helpers and applied to active endpoints. |
| API-029 | Apply custom pagination response shape | P0 | Copilot | API-028 | Updated paginated mahasiswa response to format `code,data,error,message` with data keys `data,halaman_sekarang,per_halaman,total_data,total_halaman`. |
| API-030 | Implement import scan tahap 1 | P0 | Copilot | API-025 | Added `POST /mahasiswa/import/scan` for CSV scanning + alamat classification to mark rows importable/non-importable. |
| API-031 | Implement import konfirmasi tahap 2 | P0 | Copilot | API-030 | Added `POST /mahasiswa/import/confirm` to insert only selected/importable rows from scan draft. |
| API-032 | Add download template import mahasiswa | P0 | Copilot | API-030 | Added `GET /mahasiswa/import/template`; template now downloads as Excel with `nama` and `alamat` columns. |
| API-033 | Consolidate import logic to MahasiswaRepository | P1 | Copilot | API-032 | Moved all import scan/confirm/template logic into `MahasiswaRepository` and removed dedicated import repository file. |
| API-034 | Create auth tables for multilevel login | P0 | Copilot | None | Added migrations for `users` and `auth_tokens` tables with role support (`admin`,`dosen`,`mahasiswa`). |
| API-035 | Implement login API with token | P0 | Copilot | API-034 | Added `POST /auth/login` that validates credentials and returns bearer token plus user role payload. |
| API-036 | Seed default auth accounts | P1 | Copilot | API-034 | Added auth seeder for demo accounts `admin`, `dosen`, `mahasiswa` (password default `P@ssw0rd`). |
| API-037 | Enforce token middleware on non-public APIs | P0 | Copilot | API-035 | Added `auth.token` middleware to require login token for non-public routes (`/mahasiswa` and import routes). |
| API-038 | Add dedicated token validation helper | P0 | Copilot | API-037 | Added separate helper for token extraction and validation, used by middleware for Authorization/token header checks. |
| FE-001 | Bootstrap Nuxt 4 app in app folder | P0 | Copilot | None | Initialized Nuxt 4 minimal template in `app/` with npm scripts and base structure. |
| FE-002 | Install Tailwind, Axios, and Pinia setup | P0 | Copilot | FE-001 | Installed `@nuxtjs/tailwindcss`, `@pinia/nuxt`, `pinia`, `axios`, configured modules and Axios plugin baseline. |
| FE-003 | Stabilize Nuxt production build | P0 | Copilot | FE-002 | Resolved build crash by disabling `cssnano` in Nuxt PostCSS config and validated `npm run build` completes successfully. |
| FE-004 | Configure dotenv base URL frontend | P0 | Copilot | FE-002 | Added `dotenv`, wired `NUXT_PUBLIC_API_BASE` into Nuxt runtime config, and added `.env`/`.env.example` baseline. |
| FE-005 | Build themed landing page | P0 | Copilot | FE-002 | Replaced default app screen with responsive landing page using base colors `#20893A` and `#FFFFFF`, including persistent light/dark mode toggle. |
| FE-006 | Simplify landing header controls | P1 | Copilot | FE-005 | Removed `NuklirVisit3D` text and changed theme switcher to icon-only button while preserving accessibility label. |
| FE-007 | Integrate DaisyUI design system | P1 | Copilot | FE-005 | Installed `daisyui`, added Tailwind plugin + custom light/dark themes, and migrated landing core controls to DaisyUI component classes. |
| FE-008 | Fix DaisyUI class collision layout | P0 | Copilot | FE-007 | Resolved overlapping landing elements by renaming local `hero` class to `hero-section` to avoid DaisyUI `hero` grid behavior. |
| FE-009 | Refactor landing to utility-only | P1 | Copilot | FE-008 | Removed custom CSS block from `app/app.vue` and rebuilt layout fully with Tailwind + DaisyUI classes for consistency. |
| FE-010 | Standardize icons with Iconify | P1 | Copilot | FE-009 | Installed `@iconify/vue` and replaced inline theme icons with Iconify components as frontend icon baseline. |
| FE-011 | Group admin routes under one folder | P1 | Copilot | FE-010 | Moved admin area pages to `pages/admin/**`, placed auth login in `pages/auth/login`, and updated redirects to `/auth/login` and `/admin/dashboard`. |
| FE-012 | Implement reusable admin dashboard with Highcharts | P0 | Copilot | FE-011 | Added reusable admin layout/components, implemented dashboard UI from design reference, and migrated chart visuals to Highcharts. |
| FE-013 | Replace app UI with GeoVisit PJJ IT reference design | P0 | Codex | FE-011 | Replaced landing/login/admin shell/dashboard/logs/mahasiswa/users visuals; added import/reset modals and visual-first mock data while preserving auth flow. |
| API-039 | Normalize user roles into usergroups table | P0 | Codex | API-034 | Added `usergroups` migration, migrated `users.role` into `users.usergroup_id`, and preserved login role alias from usergroup code. |
| API-040 | Implement user CRUD API with reset password | P0 | Codex | API-039 | Added protected `/users` CRUD routes with search/filter pagination and `/users/{userId}/reset-password`. |
| API-041 | Implement activity log API and request logging | P0 | Codex | API-037 | Added `activity_logs` table, list/detail/summary endpoints, global request logging middleware, and explicit login activity recording. |
| FE-014 | Align User Management display with usergroup schema | P1 | Codex | FE-013, API-039 | Updated visual-first user table data shape to show username, usergroup, linked mahasiswa, status, and reset action context. |
| FE-015 | Wire User Management to user CRUD API | P0 | Codex | FE-014, API-040 | Connected `/admin/users` to `/users` list/search/filter/create/update/delete/reset endpoints with modal actions and minimal table columns. |
| FE-016 | Wire Mahasiswa directory to CRUD and import APIs | P0 | Codex | API-025, API-030, API-031, API-032 | Connected `/admin/mahasiswa` to `/mahasiswa` list/search/create/update/delete and two-phase import scan/confirm/template endpoints. |
| FE-017 | Convert mahasiswa import template to Excel | P0 | Codex | FE-016, API-032 | Updated import template/download/upload flow to XLSX and removed `dibuat_oleh_user_id` from the template. |
| FE-018 | Wire Dashboard to dashboard analytics API | P0 | Codex | FE-013 | Connected `/admin/dashboard` to `/dashboard/summary`, `/dashboard/chart`, and `/dashboard/wilayah-tree` with Highcharts and expandable table tree. |
| FE-019 | Wire Log Aktivitas to activity log API | P0 | Codex | API-041, FE-013 | Connected `/admin/log` to `/activity-logs` list/summary/detail with filters, pagination, detail modal, recent events, top modules, and CSV export. |
| FE-020 | Add non-admin chart dashboard route | P0 | Codex | FE-018 | Added `/dashboard/chart` with reference chart layout, protected dashboard API data, and non-admin login redirect. |
| FE-021 | Add non-admin Cesium 3D map route | P0 | Codex | FE-020 | Added `/dashbord/map` with `/dashboard/map` alias, CesiumJS viewer, simulation overlays, dashboard wilayah markers/arcs, and mode switch link. |
| API-042 | Extend dashboard analytics API for non-admin chart | P0 | Codex | FE-020 | Added summary coverage/top-region metadata, chart rows/bar percentages, chart limit, and wilayah-tree `root_level` support while preserving existing admin fields. |
| API-043 | Add 3D map dashboard APIs | P0 | Codex | FE-021 | Added protected wilayah point clustering, region mahasiswa list, and mahasiswa map search endpoints for Cesium map mode. |
| FE-022 | Wire non-admin Cesium map to map APIs | P0 | Codex | FE-021, API-043 | `/dashbord/map` now loads zoom-driven wilayah points, region mahasiswa lists, and mahasiswa search markers from `/dashboard/map/*`. |
| FE-023 | Optimize Cesium map rendering performance | P0 | Codex | FE-022 | Reduced marker/API limits per level, disabled heavy terrain/effects, switched Cesium to on-demand render, and reduced labels/arcs/search markers. |
| API-044 | Optimize dashboard map query path and DB indexes | P0 | Codex | API-043 | Removed extra `has_child` query pass, pushed parent filter into aggregation, and added local DB indexes for active `mahasiswa.wilayah_id`. |
| API-045 | Filter soft-deleted wilayah from classifier candidates | P0 | Codex | API-022 | Classifier candidates now skip `dihapus_pada` rows while retaining dictionary rows for hierarchy lookup. |
| API-046 | Remove silent PENS default fallback in mahasiswa create/update/import | P0 | Codex | API-025, API-030 | Weak/empty classifications now keep the original address, clear wilayah/coordinates, and mark `needs_review` in scan/reference output instead of substituting PENS. |
| API-047 | Restrict desa hint bucket to level 5 only | P0 | Codex | API-022 | `resolveHintBucketForLevel` now maps only level 5 to `desa`, preventing dusun/deeper rows from receiving desa hint bonuses. |
| API-048 | Gate Nominatim fallback on confidence and hint coverage | P1 | Codex | API-017, API-023 | External fallback is now opt-in by default and only triggers when confidence `<0.55` and hint coverage `<0.5`. |
| API-050 | Tighten classifier contains-match floor for short tokens | P1 | Codex | API-012 | Contains matches are skipped when exact token matches exist and require stricter length ratio for tokens up to 6 chars. |
| API-054 | Improve internal-only import classification accuracy | P0 | Codex | API-045, API-046, API-050 | Added jammed administrative keyword parsing, level-locked admin hints, hint-aligned anchor selection, distinct-token hierarchy support, high-gap exact acceptance, and `MEDAYU UTARA` alias; latest 223-row draft improved 79 -> 105 importable. |
| FE-024 | Reduce kecamatan render churn in Cesium map | P0 | Codex | FE-023, API-044 | Switched bulk wilayah markers to Cesium primitives, cached map payloads, and stopped bounds-based refetch when parent drilldown is already fixed. |
| FE-025 | Place map markers relative to terrain height | P0 | Codex | FE-024 | Loaded Cesium world terrain asynchronously, sampled marker ground heights with cache, and positioned region/search points slightly above terrain. |
| FE-026 | Scope map drilldown fetches to selected parent and harden flyToIndonesia | P0 | Codex | FE-024, FE-025 | Prevented global fetch for kabupaten/kecamatan/desa, resolved parent from current region selection, and made `flyToIndonesia` reset via cached province payload before camera animation. |
| FE-027 | Switch map drilldown to click-first navigation | P0 | Codex | FE-026 | Region click now drills into child wilayah, camera movement no longer changes data level, and breadcrumb/back navigation handles returning to previous levels. |
| FE-028 | Center selection camera in top-down map view | P0 | Codex | FE-025, FE-027 | Region and mahasiswa selections now fly to a terrain-relative center position with near-vertical pitch, including direct marker clicks on the map. |
| FE-029 | Restore scoped labels for kabupaten and deeper levels | P1 | Codex | FE-023, FE-027 | Re-enabled region labels below provinsi with per-level limits, smaller typography, and distance-based fade to keep deeper drilldown readable without restoring the earlier render cost. |
| FE-030 | Increase province label readability on 3D map | P1 | Codex | FE-029 | Increased province label font size, reduced far-distance fade aggressiveness, and strengthened label background/outline opacity for the default national view. |
| FE-031 | Normalize dark/light mode coverage across admin views | P0 | Codex | FE-018, FE-019 | Completed admin theme coverage by re-enabling the admin topbar toggle, converting remaining hard-coded light surfaces/modals/tables to theme tokens, and making the legacy admin Highcharts view react to theme changes. |
| FE-032 | Guard landing dashboard CTA by role | P1 | Copilot | FE-011 | Landing "Buka Dashboard" CTA now checks authenticated role (mahasiswa/dosen) before routing and redirects unauthenticated users to login. |
| FE-034 | Show mahasiswa count labels on all 3D map wilayah levels | P1 | Codex | FE-029 | Kecamatan and desa/kelurahan labels now use the same two-line `Jumlah Mahasiswa` format as provinsi and kabupaten/kota. |
| API-055 | Implement geocoding fallback logic to default PENS coordinates | P0 | Antigravity | API-025, API-030 | Mahasiswa without valid address fallback to Lat: -7.275612, Lon: 112.793910 with `is_valid_address=false` and logged reason. |
| DB-001 | Add is_valid_address and geocoding_status to mahasiswa table | P0 | Antigravity | None | Added migration for boolean validity flag and string status for fallback reasons. |
| FE-033 | Implement RBAC address visibility filtering on dashboard and map | P0 | Antigravity | API-055, FE-021 | Exclude invalid addresses from non-admin 3D map, filter main admin charts/tree, and add Data Lokasi Bermasalah card. |
| API-005 | Define route simulation rule set | P0 | Codex | API-004 | MVP rule: selected valid mahasiswa coordinates, default PENS start point, OSRM `trip` for optimized order, OSRM `route` for fixed order, GeoJSON geometry and step-level route details. |
| DB-002 | Add visitasi simulation schema | P0 | Codex | API-005 | Added `visitasi_rencana`, `visitasi_peserta`, `visitasi_rute`, and `visitasi_rute_detail`; local migration ran successfully. |
| DB-003 | Normalize legacy visitasi simulation columns | P0 | Codex | DB-002 | Added compatibility migration for existing local `visitasi_*` tables with legacy column names, preserving data while enabling the new saved simulation contract. |
| API-008 | Implement route simulation service skeleton | P1 | Codex | API-005, DB-002 | Added protected `POST /visitasi/simulasi-rute` using OSRM with `steps=true`, `geometries=geojson`, `overview=full`, and `annotations=duration,distance`. |
| API-056 | Implement OSRM route simulation MVP | P0 | Codex | API-008 | Supports OSRM `trip` and `route`, returns ordered waypoints, ordered mahasiswa, route geometry, legs, leg summaries, raw OSRM response, and optional persistence. |
| FE-035 | Wire 3D map to OSRM route simulation | P0 | Codex | API-056, FE-021 | Added mahasiswa destination selection, OSRM run action, distance/duration/legs/steps summary, and Cesium polyline rendering from OSRM GeoJSON geometry. |
| API-060 | Add saved simulation list/detail contract | P0 | Codex | API-056 | Added `GET /visitasi/simulasi-rute`, `GET /visitasi/simulasi-rute/{simulationId}`, vehicle metadata, and persisted detail transformation with geometry, legs, steps, and ordered waypoints. |
| FE-036 | Rework simulation UI to modal and history sidebar | P0 | Codex | API-060, FE-035 | Added create modal for title, description, mahasiswa, departure point, and vehicle option; saved simulations can be listed, opened, and rendered from a dedicated sidebar. |
| API-061 | Fix undefined function now() error in route simulation | P0 | Antigravity | API-056 | Replaced now() helper calls with Carbon::now() and imported Carbon in RouteSimulationRepository. |
| API-062 | Add missing dibuat_pada column to visitasi_rute_detail | P0 | Antigravity | DB-003 | Updated normalization migration to add dibuat_pada column to visitasi_rute_detail and migrated. |
| FE-037 | Hide route simulation buttons and sidebar access for mahasiswa | P0 | Antigravity | FE-036, API-062 | Hide route simulation UI elements and history sidebar from student (mahasiswa) user role on 3D map dashboard, and block corresponding endpoints in backend. |
| FE-038 | Filter route simulations by creator user ID | P0 | Antigravity | FE-037 | Filter route simulation list and detail endpoints by the authenticated user ID from token header, so users only see their own simulations. |
| FE-039 | Configure frontend development server port to 3001 | P0 | Antigravity | None | Set the devServer port to 3001 in nuxt.config.ts so the app always starts on port 3001 by default. |
| API-063 | Add owner-scoped delete for route simulations | P0 | Codex | API-060, FE-038 | Added `DELETE /visitasi/simulasi-rute/{simulationId}` restricted to the authenticated owner/creator across `dibuat_oleh_user_id`, `dosen_user_id`, and legacy `dosen_id`. |
| FE-040 | Add delete action in simulation history sidebar | P0 | Codex | API-063, FE-036 | Added detail-level `Hapus Simulasi` action with confirmation modal, API delete call, list refresh, and active route cleanup. |
| API-064 | Close route simulations back to departure point | P0 | Codex | API-056 | Default simulation end point now returns to `titik_awal` when no explicit `titik_akhir` is provided, producing final leg back to start in OSRM geometry/legs. |
| FE-041 | Mark return waypoint in simulation detail | P1 | Codex | API-064, FE-036 | Frontend now sends `kembali_ke_titik_awal=true` on create and labels the final `end` waypoint as `Kembali`. |
| API-065 | Compare manual and optimized route candidates | P0 | Codex | API-064 | Simulation now computes OSRM `route` for input order and OSRM `trip` for optimized order, compares distance/duration with weighted score, and stores comparison metadata. |
| FE-042 | Show route comparison metrics in simulation detail | P1 | Codex | API-065, FE-036 | Simulation detail now shows manual vs optimized distance, duration, score, saving, and recommended route label. |
| FE-043 | Fix simulation route camera focus | P0 | Codex | FE-036 | Replaced route focus range based on OSRM geometry vertex count with route-bounds camera framing to avoid zooming out after create/detail load. |
| FE-044 | Auto-redraw route when switching simulation detail | P0 | Codex | FE-036 | Detail switching now clears stale route entities before loading the next simulation, ignores stale detail responses, redraws the latest route automatically, and clears the active route when the sidebar closes. |
| FE-045 | Hide wilayah distribution while simulation route is active | P1 | Codex | FE-036 | Active route mode now hides wilayah distribution markers/labels/arcs and region detail panels; clearing the route restores the distribution layer. |
