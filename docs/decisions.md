# Decisions Log

## DEC-001
- Date: 2026-04-11
- Context: Repository had no shared project context files.
- Decision: Use a standardized 5-file context baseline (AGENTS.md, PROJECT_STATUS.md, TASKS.md, docs/decisions.md, docs/handoffs/HANDOFF_TEMPLATE.md).
- Rationale: Reduce ambiguity and keep all agents aligned on one operational context source.
- Impact: Future work should update these files as the primary coordination mechanism.
- Follow-up: Revalidate structure after first real implementation milestone.

## DEC-002
- Date: 2026-04-11
- Context: Need to start backend project from scratch and target PostgreSQL database.
- Decision: Initialize Laravel Lumen project in `api/` and set default DB environment to PostgreSQL.
- Rationale: Follow requested stack/directory and make local setup immediately database-ready.
- Impact: New backend work should be implemented under `api/` with PostgreSQL as default connection.
- Follow-up: Validate connection against a real PostgreSQL instance and add first migration.

## DEC-003
- Date: 2026-04-11
- Context: Existing PostgreSQL schema already contains tables `mahasiswa` and `wilayah`.
- Decision: Generate explicit Eloquent models per existing table and map custom keys/timestamp/deleted columns directly.
- Rationale: Keep app layer aligned with live schema without guessing conventions.
- Impact: Model files are ready for CRUD use; relationships remain manual until foreign keys are defined.
- Follow-up: Add relationship methods after FK constraints/business rules are confirmed.

## DEC-004
- Date: 2026-04-11
- Context: Stakeholder provided official final project title and case study scope.
- Decision: Use the official scope as primary reference for all next prompts and implementation planning.
- Rationale: Keep all agent outputs aligned to one domain objective and avoid scope drift.
- Impact: Backlog and API planning should prioritize 3D domicile distribution visualization and efficient lecturer visit route simulation.
- Follow-up: Translate scope into concrete endpoint contracts and simulation rules.

## DEC-005
- Date: 2026-04-11
- Context: Need a public wilayah API that can power tree rendering and filtered navigation.
- Decision: Implement `GET /public/get-wilayah` returning ordered flat array with `level` and `parent_wilayah_id`, plus optional filters `wilayah_id` (direct children) and `cari` (parent-to-match path by name).
- Rationale: Keep frontend tree construction flexible while preserving hierarchical semantics in payload.
- Impact: Frontend can build tree UI without extra transformation endpoint.
- Follow-up: Add endpoint tests and align response shape with frontend integration contract.

## DEC-006
- Date: 2026-04-11
- Context: API controller started accumulating query/filter/transform logic.
- Decision: Move API processing logic into repository classes under `api/app/Repositories` and keep controllers thin.
- Rationale: Improve code organization, readability, and consistency for next endpoint implementation.
- Impact: New API endpoints should place business/query processing in repository layer.
- Follow-up: Apply the same pattern to upcoming mahasiswa and route simulation endpoints.

## DEC-007
- Date: 2026-04-11
- Context: Need engine/service to classify raw addresses into wilayah hierarchy and produce internal geocoding output.
- Decision: Implement repository-based classification pipeline with uppercase normalization, special-char cleaning, stop-word removal, comma/dot tokenization, exact+contains+fuzzy matching, hierarchy-context ranking, and internal coordinate fallback from matched wilayah chain.
- Rationale: Meet requested processing steps while keeping API logic consistent with repository-first architecture.
- Impact: Address parsing can now be tested via `POST /public/test-klasifikasi-alamat` using provided sample dataset.
- Follow-up: Add automated tests and tune confidence thresholds from real user feedback.

## DEC-008
- Date: 2026-04-11
- Context: Classifier previously never reached desa level because village entries are soft-deleted in source table and anchor ranking favored higher nodes.
- Decision: Include soft-deleted wilayah rows in classifier dictionary via memory-safe DB cursor loading and add village-priority anchor selection with confidence guard.
- Rationale: Improve classification granularity to desa while keeping ambiguity visible through `needs_confirmation`.
- Impact: Sample dataset now yields village-level anchors with fallback confirmation flags for uncertain cases.
- Follow-up: Validate village accuracy with additional real addresses and add automated regression tests.

## DEC-009
- Date: 2026-04-11
- Context: Need higher classification robustness by combining internal mapping with external geocoding source.
- Decision: Add optional OpenStreetMap Nominatim fallback that is triggered only when internal result is uncertain or lacks coordinates, with graceful no-network failure handling.
- Rationale: Increase accuracy and coordinate coverage while keeping internal dictionary as primary source.
- Impact: API can combine internal + external hints via `use_external_geocoding` option without breaking existing flow.
- Follow-up: Tune merge heuristics from real traffic and enforce Nominatim policy settings in environment.

## DEC-010
- Date: 2026-04-11
- Context: External geocoding was previously queried from raw address input.
- Decision: Query Nominatim only using address string composed from internal manual geocoding result (desa/kecamatan/kabupaten/provinsi chain), never directly from raw address.
- Rationale: Keep external lookup aligned with validated internal mapping context and reduce noisy query variance.
- Impact: External enrichment now acts as refinement of manual result, not replacement of raw parsing stage.
- Follow-up: Monitor query success rate and adjust manual query composition when needed.

## DEC-011
- Date: 2026-04-11
- Context: Normalization tokens still contained numeric fragments (RT/RW/nomor), reducing matching precision.
- Decision: Remove all tokens and n-grams containing digits during tokenization stage.
- Rationale: Numeric fragments are not location names and add noise to dictionary matching.
- Impact: Cleaner token output and better signal quality for wilayah matching.
- Follow-up: Add optional duplicate-term compaction on tokens for further cleanup.

## DEC-012
- Date: 2026-04-11
- Context: External reference matcher shows stronger explicit hierarchy validation and sequential level-awareness that can improve explainability and anchor stability.
- Decision: Extend classifier with administrative hint extraction (prov/kab/kec/desa), hint-aware candidate scoring, hierarchy-completeness weighting, related region candidates per level, and parent-child consistency validation in mapping output.
- Rationale: Keep current high-recall matching while adding stronger hierarchy checks and clearer diagnostics for ambiguous address cases.
- Impact: Better confidence handling for partial/inconsistent mappings and richer response metadata for downstream review.
- Follow-up: Add automated regression tests for hierarchy validation outcomes and score-threshold tuning.

## DEC-013
- Date: 2026-04-11
- Context: User requested direct implementation of practical accuracy improvements after recommendation phase.
- Decision: Implement three quick wins in classifier flow: dictionary alias expansion for common regional abbreviations, adaptive fuzzy threshold by token length, and confidence-based ambiguity handling with top-3 review candidates.
- Rationale: Improve recall for shorthand location inputs while reducing short-token false positives and making uncertain matches explicitly reviewable.
- Impact: Service can better parse abbreviated regional inputs (e.g. JATIM/JABAR), produce more stable fuzzy behavior, and return actionable alternatives on ambiguous results.
- Follow-up: Build automated benchmark tests from labeled address dataset to tune confidence threshold and candidate-gap normalization.

## DEC-014
- Date: 2026-04-11
- Context: Mahasiswa data API needs CRUD capability, paginated listing reuse, and automatic location enrichment from existing address engine.
- Decision: Implement mahasiswa CRUD endpoints using repository-first architecture, add global reusable helper `paginate_builder`, and enforce `wilayah_id`/`latitude`/`longitude` assignment from address classification pipeline during create/update.
- Rationale: Keep endpoint logic consistent with existing code structure, avoid duplicated pagination code, and ensure saved mahasiswa records always use standardized geocoding process.
- Impact: `GET /mahasiswa` now supports reusable pagination response metadata and save/update paths automatically enrich location fields from alamat.
- Follow-up: Add endpoint-level automated tests for CRUD validation, pagination parameters, and geocoding-derived field assertions.

## DEC-015
- Date: 2026-04-11
- Context: Requirement update states `mahasiswa_id` must be generated by system using UUID during save.
- Decision: Force UUID generation for `mahasiswa_id` in create flow and stop accepting manual input ID in store validation.
- Rationale: Keep primary key format consistent, prevent client-side ID collisions, and simplify write-path constraints.
- Impact: New mahasiswa records always use UUID regardless of payload values.
- Follow-up: Add API contract note and automated tests that assert manual `mahasiswa_id` payload is ignored.

## DEC-016
- Date: 2026-04-11
- Context: GET mahasiswa response needs relational wilayah payload for easier frontend consumption.
- Decision: Add Eloquent relation `mahasiswa -> wilayah` and embed `wilayah` object in mahasiswa list/detail response transformation.
- Rationale: Avoid extra API calls on frontend and keep wilayah context directly attached to each mahasiswa record.
- Impact: `GET /mahasiswa` and `GET /mahasiswa/{id}` now return `wilayah` field as object (or empty object when not available).
- Follow-up: Add endpoint contract tests asserting `wilayah` presence and shape.

## DEC-017
- Date: 2026-04-11
- Context: Need consistent response contract across endpoints to simplify frontend/error handling integration.
- Decision: Standardize active API controller responses to envelope format: `code`, `data`, `message`, `errors` using shared helper in base controller.
- Rationale: Remove per-endpoint response variance and centralize success/error serialization logic.
- Impact: Public wilayah, classifier, and mahasiswa endpoints now return uniform payload shape for success and failure responses.
- Follow-up: Apply the same response helper to any new controller endpoints and add contract tests for response schema.

## DEC-018
- Date: 2026-04-11
- Context: Pagination response requires a specific frontend contract distinct from generic endpoint envelope.
- Decision: Implement dedicated pagination response format for mahasiswa list: top-level keys `code`, `data`, `error`, `message`; pagination payload keys `data`, `halaman_sekarang`, `per_halaman`, `total_data`, `total_halaman`.
- Rationale: Match requested payload shape exactly while keeping pagination helper reusable for similar list endpoints.
- Impact: `GET /mahasiswa` now returns the custom pagination contract with empty string defaults for `error` and `message`.
- Follow-up: Reuse the same pagination response helper for future list endpoints that need this exact format.

## DEC-019
- Date: 2026-04-12
- Context: Need bulk import mahasiswa with review gate before insertion and downloadable template for input standardization.
- Decision: Implement two-step import workflow: step-1 scan (`POST /mahasiswa/import/scan`) performs CSV parsing + address classification and returns importable/non-importable rows with draft ID; step-2 confirm (`POST /mahasiswa/import/confirm`) inserts selected importable rows from stored draft; add template download endpoint (`GET /mahasiswa/import/template`).
- Rationale: Prevent blind bulk insert, expose classification quality before commit, and standardize source file format for users.
- Impact: Import now supports validation/preview and explicit confirmation phase with deterministic insertion payload.
- Follow-up: Add scheduler/retention policy to clean old draft JSON files and add automated tests for scan/confirm contract.

## DEC-020
- Date: 2026-04-12
- Context: User requested mahasiswa import repository logic to be centralized in the main mahasiswa repository.
- Decision: Merge all import scan/confirm/template logic from `MahasiswaImportRepository` into `MahasiswaRepository` and update `MahasiswaImportController` to depend on `MahasiswaRepository` only.
- Rationale: Keep mahasiswa domain logic in one repository entry point and reduce repository fragmentation.
- Impact: Import endpoints still behave the same, but maintenance now happens in a single repository file.
- Follow-up: Add unit tests for consolidated repository methods to keep file growth manageable and prevent regressions.

## DEC-021
- Date: 2026-04-12
- Context: Need initial login API with token output and multilevel role support for admin, dosen, and mahasiswa.
- Decision: Add dedicated auth schema (`users`, `auth_tokens`), implement `POST /auth/login` credential flow in `AuthRepository`, and issue hashed persisted bearer tokens with configurable expiry.
- Rationale: Provide a simple, framework-neutral token authentication base without introducing external auth packages at this stage.
- Impact: System can authenticate three role levels and return role-aware token payload for downstream authorization integration.
- Follow-up: Add auth middleware to protect private endpoints and implement logout/token revocation endpoint.

## DEC-022
- Date: 2026-04-12
- Context: Non-public APIs must require token from login and token checking should be centralized in a helper.
- Decision: Register route middleware `auth.token` for non-public routes and implement dedicated helper functions for token extraction (`Authorization: Bearer` / `token` header) and token validation.
- Rationale: Keep authorization enforcement consistent at routing layer while avoiding duplicate token-check logic inside controllers.
- Impact: `/mahasiswa` and import routes now reject requests without valid token; public routes remain open.
- Follow-up: Add role-based access middleware (admin/dosen/mahasiswa permission matrix) on top of current token-auth middleware.

## DEC-023
- Date: 2026-04-12
- Context: User requested frontend project initialization with Nuxt 4 in `app/` plus Tailwind, Axios, and Pinia dependencies.
- Decision: Bootstrap Nuxt 4 minimal app in `app/`, enable `@nuxtjs/tailwindcss` and `@pinia/nuxt` modules in Nuxt config, install `axios`, and add a runtime-configured Axios plugin baseline.
- Rationale: Provide ready-to-use frontend foundation aligned with current backend API and state management needs.
- Impact: Frontend workspace now has standard Nuxt scripts, Tailwind styling pipeline, Pinia store integration, and shared API client injection for next feature development.
- Follow-up: Investigate and resolve local production build issue (`MediaQueryList` structure error) before release packaging.

## DEC-024
- Date: 2026-04-12
- Context: Nuxt production build failed in local environment due `csso/css-tree` compatibility error (`Missed \`structure\` field...`) triggered in css optimization path.
- Decision: Keep Tailwind + Pinia + Axios setup unchanged and disable `cssnano` in Nuxt PostCSS plugin config as build compatibility workaround.
- Rationale: Restore deterministic production build without blocking frontend bootstrap deliverable while keeping runtime behavior intact.
- Impact: `npm run build` now completes successfully; CSS minification from `cssnano` is skipped until upstream compatibility is re-evaluated.
- Follow-up: Revisit and re-enable CSS minification once dependency chain is stable in target runtime.

## DEC-025
- Date: 2026-04-12
- Context: Frontend base URL needs to be configurable from environment file and user requested explicit `dotenv` package usage.
- Decision: Add `dotenv` dependency in Nuxt app, load `.env` in `nuxt.config.ts`, and map runtime `public.apiBase` to `NUXT_PUBLIC_API_BASE` with fallback `http://localhost:8080`.
- Rationale: Keep API endpoint configuration environment-driven and easy to switch across local/staging/production without code edits.
- Impact: Axios plugin now consumes `useRuntimeConfig().public.apiBase` sourced from `.env`; project includes `app/.env.example` and local `app/.env` baseline.
- Follow-up: Ensure CI/CD and deployment environments set `NUXT_PUBLIC_API_BASE` explicitly.

## DEC-026
- Date: 2026-04-12
- Context: Need an initial landing page with dominant brand colors `#20893A` and `#FFFFFF`, while supporting both light and dark mode.
- Decision: Replace default Nuxt welcome view with a custom single-page hero layout in `app/app.vue`, using brand-led gradients, responsive metric cards, and persistent theme toggle (localStorage + system preference fallback).
- Rationale: Establish a distinctive first impression aligned with project identity and ensure immediate usability in both light and dark viewing conditions.
- Impact: App now opens directly into a branded landing page that is mobile-friendly, animated, and theme-aware.
- Follow-up: Link CTA actions to real routes/features as frontend modules become available.

## DEC-027
- Date: 2026-04-12
- Context: UI consistency needs to improve as frontend grows beyond one landing page.
- Decision: Introduce `daisyui` on top of Tailwind, define custom `light`/`dark` themes aligned with brand colors, and start using DaisyUI component classes on landing page controls.
- Rationale: Use a consistent component design system to reduce ad-hoc styling drift while keeping existing Tailwind workflow.
- Impact: Buttons, cards, badges, and theme toggle now follow shared DaisyUI component styling with centralized theme tokens.
- Follow-up: Continue migrating future pages/components to DaisyUI primitives to maintain visual consistency.

## DEC-028
- Date: 2026-04-12
- Context: After DaisyUI integration, landing elements overlapped because local class name `hero` collided with DaisyUI `hero` component behavior (grid overlay).
- Decision: Avoid DaisyUI reserved/component class names for custom layout wrappers; rename local wrapper to `hero-section`.
- Rationale: Prevent unintended style inheritance and layout regressions from framework component selectors.
- Impact: Landing content returns to normal flow (no stacked overlap) while DaisyUI components remain enabled.
- Follow-up: Audit future custom class names to avoid collisions with DaisyUI component names.

## DEC-029
- Date: 2026-04-12
- Context: UI stack has Tailwind + DaisyUI, but landing page still carried a large custom scoped CSS block, reducing consistency and maintainability.
- Decision: Refactor landing page to utility-first approach using Tailwind and DaisyUI classes only; remove custom `<style>` section from `app/app.vue`.
- Rationale: Keep styling source-of-truth in design system primitives and avoid duplicated per-page CSS tokens.
- Impact: Landing page now follows framework conventions end-to-end, making future UI changes faster and more consistent.
- Follow-up: Apply the same approach for future pages unless there is a specific need for isolated custom CSS.

## DEC-030
- Date: 2026-04-12
- Context: Need a consistent frontend implementation guideline for styling and icons in upcoming pages.
- Decision: Standardize frontend UI implementation to Tailwind + DaisyUI for styling/components and Iconify (`@iconify/vue`) for icon assets.
- Rationale: Minimize style drift, simplify maintenance, and keep icon usage consistent without per-component SVG duplication.
- Impact: New frontend work should prefer utility/component classes from Tailwind/DaisyUI and use Iconify icons by default.
- Follow-up: Gradually replace legacy inline SVG icons in future touched components.

## DEC-031
- Date: 2026-04-12
- Context: Admin portal now needs a richer dashboard implementation based on design reference, with reusable structure and chart standardization.
- Decision: Organize admin routes under `pages/admin/**`, introduce reusable admin UI components/layout, and adopt Highcharts as the chart engine for dashboard visualizations.
- Rationale: Keep admin area scalable through component reuse while meeting requested charting requirement and preserving consistent visual language.
- Impact: Dashboard UI assembly is now modular and future admin pages can reuse the same shell/components without duplicating layout code.
- Follow-up: Connect dashboard cards/charts/tables to live backend analytics endpoints once data contracts are finalized.

## DEC-032
- Date: 2026-04-12
- Context: Auth pages should not be nested under the admin folder route structure.
- Decision: Place login route in `pages/auth/login` and keep admin-protected area routes in `pages/admin/**`.
- Rationale: Separate authentication entry flow from admin shell routing to keep URL semantics clearer and easier to maintain.
- Impact: Unauthorized redirects now point to `/auth/login`, while authenticated users continue into `/admin/dashboard`.
- Follow-up: Apply the same separation pattern if additional auth pages are added (forgot password, reset, etc.).

## DEC-033
- Date: 2026-04-25
- Context: User provided a GeoVisit PJJ IT portal reference design and requested the old `app` design be replaced before backend data wiring.
- Decision: Standardize visible frontend branding on `GeoVisit PJJ IT`, replace landing/login/admin screens with the reference visual system, keep new mahasiswa/users/logs screens visual-first with static mock data, and route `/admin/log-simulasi` into the unified `/admin/log` logs page.
- Rationale: Deliver the requested design replacement without prematurely coupling unfinished UI surfaces to backend contracts.
- Impact: Frontend now has complete visual routes for the referenced portal screens while existing auth/login behavior remains intact.
- Follow-up: Wire visual-first screens to live APIs once data contracts and interaction requirements are finalized.

## DEC-034
- Date: 2026-05-03
- Context: User requested user CRUD API and asked for `usergroup` to become its own database table instead of being stored directly as `users.role`.
- Decision: Create `usergroups` as the role/group master table, migrate existing role values into required `users.usergroup_id`, remove `users.role`, and keep `role` only as a response alias derived from `usergroups.kode`.
- Rationale: Normalizing user roles reduces duplicated role strings while preserving compatibility for current login/frontend consumers.
- Impact: New user write paths must provide `usergroup_id`, `usergroup_kode`, or legacy-compatible `role`; API responses include both `usergroup` and `role`.
- Follow-up: Add role-based authorization middleware if admin/dosen/mahasiswa permissions need enforcement beyond token authentication.

## DEC-035
- Date: 2026-05-03
- Context: User requested the User Management UI to remove unnecessary displayed data and fully use the existing `/users` CRUD/reset API.
- Decision: Wire `/admin/users` directly to protected `/users` endpoints, keep the table minimal (`nama`, `username`, `email`, `usergroup`, `status`, actions), and use modal flows for create/edit/delete/reset password.
- Rationale: Align the UI with the normalized usergroup schema and make CRUD actions operational without adding new backend contracts.
- Impact: User Management is no longer mock/static; authenticated frontend users can manage users through the existing API.
- Follow-up: Add role-based UI permissions after backend authorization rules are defined.

## DEC-036
- Date: 2026-05-03
- Context: User requested the Mahasiswa admin screen to use available APIs, including CRUD and the two-phase import flow.
- Decision: Wire `/admin/mahasiswa` to protected `/mahasiswa` endpoints, display API output fields (`mahasiswa_id`, `nama`, `alamat`, `wilayah`, coordinates, update timestamp), and implement import as scan/preview/confirm using existing import endpoints.
- Rationale: Keep the frontend aligned to the actual Lumen API contract and avoid mock-only admin flows.
- Impact: Admin users can manage mahasiswa records and perform import from the frontend.
- Follow-up: Add dedicated frontend regression tests when a browser test harness is available.

## DEC-037
- Date: 2026-05-03
- Context: User requested the mahasiswa import template to become Excel and remove `dibuat_oleh_user_id` from the template.
- Decision: Make `/mahasiswa/import/template` return `.xlsx`, keep template columns limited to `nama` and `alamat`, and support `.xlsx` parsing in the existing scan/confirm import flow.
- Rationale: Excel is easier for admin users while preserving the two-phase import validation gate.
- Impact: Frontend downloads/uploads Excel by default; imported records still enter through the existing scan and confirm API process.
- Follow-up: Keep CSV support only as backward-compatible fallback unless explicitly removed later.

## DEC-038
- Date: 2026-05-04
- Context: User requested Dashboard API analysis and frontend implementation for summary, chart, and wilayah tree.
- Decision: Wire `/admin/dashboard` to existing protected dashboard endpoints: `/dashboard/summary` for cards, `/dashboard/chart` for Highcharts column chart, and `/dashboard/wilayah-tree` for lazy-loaded expandable table tree.
- Rationale: Use the backend contract already present instead of keeping mock visual data, while preserving the GeoVisit PJJ IT admin visual system.
- Impact: Dashboard now reflects live API-shaped data and supports expand/collapse wilayah hierarchy without adding new backend endpoints.
- Follow-up: Add browser regression smoke tests after a standard frontend test harness is defined.

## DEC-039
- Date: 2026-05-04
- Context: User requested an API log aktivitas and asked that every API process be recorded.
- Decision: Add database-backed `activity_logs`, expose protected list/detail/summary endpoints, record all non-skipped HTTP API requests through global middleware, and record `/auth/login` manually so token/password data is never stored.
- Rationale: Middleware gives broad coverage across current and future API endpoints without duplicating logging calls in every controller, while manual login handling protects sensitive auth response data.
- Impact: API activity can now be searched, filtered, paginated, summarized, and audited by actor, module, action, target, method, status, and time.
- Follow-up: Wire the admin Logs UI to `/activity-logs` when frontend log screen is updated.

## DEC-040
- Date: 2026-05-04
- Context: User requested the admin log screen to use the newly implemented activity log API.
- Decision: Replace the visual-first `/admin/log` mock screen with API-backed Log Aktivitas UI using `/activity-logs`, `/activity-logs/summary`, and `/activity-logs/{logId}`.
- Rationale: The log screen should reflect the actual audit data contract instead of simulation placeholder data.
- Impact: Admin users can search, filter, paginate, inspect detail payload/metadata, view summary cards/top modules/recent events, and export the current page as CSV.
- Follow-up: Add automated browser smoke tests when the frontend test harness is defined.

## DEC-041
- Date: 2026-05-07
- Context: User requested non-admin 3D map mode and specified CesiumJS plus a Cesium ion token.
- Decision: Use CesiumJS for the frontend 3D map route, configure Cesium ion through Nuxt public runtime config, generate Cesium static assets from `node_modules` into `public/cesium`, and load Cesium through `/cesium/Cesium.js`.
- Rationale: CesiumJS is the requested and appropriate geospatial 3D engine; generated static assets avoid committing vendor build output and avoid Vite optimized dependency loading failures in dev.
- Impact: `/dashbord/map` and alias `/dashboard/map` render a full-bleed Cesium viewer with GeoVisit overlays and dashboard wilayah markers/arcs.
- Follow-up: Define final route simulation API contract and add browser regression tests when test harness is available.

## DEC-042
- Date: 2026-05-07
- Context: 3D map mode needs zoom-driven wilayah points and mahasiswa search/detail data that differ from admin dashboard chart/tree needs.
- Decision: Add protected map-specific dashboard endpoints under `/dashboard/map/*` for wilayah points, mahasiswa by wilayah, and mahasiswa search; keep chart API changes additive for admin compatibility.
- Rationale: Separate map interaction contracts from admin dashboard contracts while reusing the existing mahasiswa/wilayah data model.
- Impact: Frontend can load province points first, request deeper levels by zoom/parent, show mahasiswa lists on region click, and show student markers from search results.
- Follow-up: Add endpoint tests for level, bounds, search, and pagination behavior.

## DEC-043
- Date: 2026-05-07
- Context: User requested implementation of the new 3D map APIs into the dashboard map page.
- Decision: Wire `/dashbord/map` to map-specific endpoints, use Cesium camera height and visible bounds to select wilayah level, show mahasiswa lists on wilayah click, and render search results as student markers.
- Rationale: The map needs interaction-oriented data loading instead of the chart/tree dashboard contract.
- Impact: Non-admin map users can start at province points, zoom into deeper wilayah levels, click a wilayah to inspect students, and search students directly on the map.
- Follow-up: Tune level thresholds from real Cesium visibility testing and add browser regression tests when test harness is available.

## DEC-044
- Date: 2026-05-07
- Context: User reported the Cesium map page felt heavy and slow.
- Decision: Default the map to performance mode by limiting API/render points per level, reducing labels/arcs/search markers, disabling terrain/fog/bloom/lighting, lowering render quality on high-DPI screens, and using Cesium request-render mode.
- Rationale: The current map interaction needs fast exploration more than high-cost terrain and decorative effects.
- Impact: `/dashbord/map` should load and pan more smoothly with live API data, while preserving wilayah markers, student search markers, click detail, and drilldown.
- Follow-up: Tune marker limits after browser performance testing on target devices.

## DEC-045
- Date: 2026-05-07
- Context: Kecamatan-level map interaction still felt slow after the first rendering pass optimizations.
- Decision: Remove the extra backend `has_child` query, push `parent_id` into the main aggregate, add DB indexes for active `mahasiswa.wilayah_id`, render bulk wilayah points through Cesium primitive collections, cache map payloads, and stop bounds-based refetch while staying inside a fixed drilldown parent.
- Rationale: The remaining bottleneck was repeated work on both sides: redundant query passes in the API and repeated entity rebuild/refetch in the frontend.
- Impact: Drilldown map interaction should do less DB work per request and much less Cesium object churn at kecamatan level.
- Follow-up: Validate frame-time and interaction smoothness in the browser on target hardware and consider pre-aggregated summaries if the dataset grows substantially.

## DEC-046
- Date: 2026-05-07
- Context: User requested map points to follow terrain elevation so marker placement feels more specific geographically.
- Decision: Load Cesium world terrain asynchronously, sample terrain height for visible region and search markers, cache sampled heights client-side, and place markers with a small level-based offset above the ground surface.
- Rationale: Terrain-aware placement improves spatial specificity without blocking first render, while caching keeps repeated sampling under control.
- Impact: Map markers no longer sit on a flat synthetic altitude and should visually align better with the terrain shape.
- Follow-up: Check browser performance and terrain accuracy on target connectivity, because terrain sampling depends on Cesium terrain requests.

## DEC-047
- Date: 2026-05-07
- Context: User reported `flyToIndonesia` caused crashes and requested deeper wilayah fetches to load only for the currently selected zoomed region.
- Decision: Make `flyToIndonesia` reset the map using cached province payloads before the camera animation, suppress refresh logic while the camera is transitioning, and disallow kabupaten/kecamatan/desa fetches unless a matching parent wilayah is actively selected.
- Rationale: Reset crashes were likely caused by heavy work during camera animation, while global child-level fetches created unnecessary load and broke the intended drilldown model.
- Impact: Reset to Indonesia should be lighter, and deeper map levels now behave as parent-scoped drilldown instead of global zoom buckets.
- Follow-up: Validate the new selection-first drilldown flow in browser and refine the interaction if users need clearer affordances before zooming deeper.

## DEC-048
- Date: 2026-05-07
- Context: User requested the drilldown concept to change so kabupaten and deeper levels load by clicking a province/region point, and asked for a good way to return to the previous level.
- Decision: Make region point clicks the only trigger for entering child wilayah levels, stop camera zoom/pan from changing data level, and use breadcrumb plus one-step back navigation to return to earlier scopes.
- Rationale: Click-first drilldown is more predictable, lighter on the API, and easier to understand than implicit level changes tied to camera position.
- Impact: Map data scope now follows an explicit navigation path instead of zoom heuristics, and users can move back up the hierarchy without resetting the whole scene.
- Follow-up: Validate whether parent region clicks should stay navigation-only or also expose region-level mahasiswa detail through a separate action.

## DEC-049
- Date: 2026-05-07
- Context: User requested region clicks and mahasiswa search-result selections to center the view like the reference image, with a straight top-down angle.
- Decision: Standardize selection focus in `/dashbord/map` to a terrain-relative top-down camera flight, use level-based focus heights, and route direct mahasiswa marker clicks through the same focus helper as list selections.
- Rationale: Centered top-down framing makes region drilldown and mahasiswa inspection easier to read, and consistent focus behavior reduces interaction ambiguity between list clicks and map clicks.
- Impact: Region and mahasiswa selections now fly to a near-vertical centered view above the selected coordinates instead of keeping the previous oblique angle.
- Follow-up: Validate focus heights in browser with live data and tune per level if some scopes need a wider or tighter framing.

## DEC-050
- Date: 2026-05-07
- Context: User requested labels to appear again at kabupaten level and deeper, not only at provinsi.
- Decision: Re-enable deeper-level region labels in `/dashbord/map`, but constrain them with smaller per-level label limits, lighter typography, and distance-based fade/visibility rules.
- Rationale: The map needs readable geographic context during drilldown, but unrestricted labels at kecamatan/desa would bring back clutter and render cost.
- Impact: Kabupaten, kecamatan, and desa can now show labels again within controlled visibility budgets instead of being completely label-free.
- Follow-up: Validate readability and performance in browser and tune label counts per level on target devices if needed.

## DEC-051
- Date: 2026-05-07
- Context: User reported province labels in the 3D national view were still too small and too transparent against the imagery base layer.
- Decision: Increase province-only label emphasis by raising font weight/size, easing far-distance fade, and strengthening label background plus outline opacity.
- Rationale: Province labels are the primary context in the default Indonesia-wide map state, so they need stronger contrast than deeper-level labels.
- Impact: Province names and counts should remain more legible in the initial map overview without changing the denser drilldown label strategy.
- Follow-up: Validate final readability in browser and increase background padding only if the larger type still feels cramped on wide screens.

## DEC-052
- Date: 2026-05-07
- Context: A full frontend audit showed dark/light mode was not yet complete across admin views: several visible admin pages, modal dialogs, and the legacy `/admin/dashboard` chart still relied on hard-coded light surfaces or light-only chart styling.
- Decision: Re-enable the admin topbar theme toggle, migrate the remaining admin surfaces to shared theme-aware color tokens, and make the legacy Highcharts dashboard view re-render from the active `data-theme` palette.
- Rationale: Theme support is only operationally complete when users can switch modes from the admin area itself and all rendered surfaces, dialogs, and charts follow the same active theme.
- Impact: Admin pages now have direct theme switching again, remaining light-only panels are normalized, and the old admin chart no longer stays in a light palette after switching to dark mode.
- Follow-up: Run a browser pass across admin pages and modal flows in both modes to catch any residual contrast issues.

## DEC-053
- Date: 2026-05-10
- Context: User requested faster and higher-quality mahasiswa import without external geocoding after 200-row scan took several minutes.
- Decision: Make import scan internal-only by default, keep Nominatim opt-in, stop silent PENS fallback for weak classifications, and tighten internal classifier candidate selection.
- Rationale: Public Nominatim has a 1.1s/request throttle and is not suitable for bulk import speed; internal-only classification is fast enough but must avoid false-valid defaults.
- Impact: 200-row scans can run under the target time when internal-only, while ambiguous rows are returned for review instead of imported with fake coordinates.
- Follow-up: Validate against the real import file and tune aliases/stop-words for rows marked `needs_review`.

## DEC-054
- Date: 2026-05-10
- Context: Latest internal-only import scan produced 79 importable and 144 review rows from 223 total.
- Decision: Improve internal-only classifier by parsing jammed administrative markers, locking explicit admin hints to their level, aligning anchors with explicit admin hints, requiring distinct-token hierarchy support, and adding the `MEDAYU UTARA` alias for `Medokan Ayu`.
- Rationale: The real failures were mostly ambiguous single-token matches and malformed address markers, not missing external coordinates.
- Impact: Same 223-row draft now scans to 105 importable, 90 empty-address rejects, and 28 non-empty review rows in under 1 second locally.
- Follow-up: Fill missing addresses in the import file and add a managed alias table for remaining recurring local abbreviations.

## Entry Template
- Date: YYYY-MM-DD
- Context: <what triggered the decision>
- Decision: <what was decided>
- Rationale: <why this option>
- Impact: <expected consequence>
- Follow-up: <next verification or action>
