# Race Planner

A collaborative race event planning tool that lets organisers draw routes, place markers, manage infrastructure, and export plans — all on an interactive map. Inspired by [oneplan.io](https://www.oneplan.io/features/).

---

## Features

### Events & Collaboration

- Create, edit, and delete race events
- Duplicate an event (copies all plans, elements, and overlay files)
- Invite collaborators by email with one of three roles:
  - **Owner** — full control including event settings
  - **Editor** — can create, edit, and delete map content
  - **Viewer** — read-only access to the event and its plans

### Plans

- Every event starts with a default plan ("Plan 1")
- Create multiple plans per event (e.g. Plan A / Plan B course options)
- Rename, duplicate, or delete plans
- Plan-level **equipment list** — track items needed for that scenario

### Interactive Map Editor

- Full-screen map editor per event
- **Two base layers** (switchable mid-session):
  - OpenStreetMap vector (via [OpenFreeMap](https://openfreemap.org))
  - Vlaanderen high-resolution satellite imagery (Agentschap Informatie Vlaanderen)
- **Undo** support for draw operations
- Map elements are either **plan-scoped** (only visible in one plan) or **shared** across all plans

### Map Elements

Draw and label all race logistics directly on the map:

| Category | Types |
|---|---|
| **Groups** | Group boundary (collapsible container for child elements) |
| **Routes** | Course, Pedestrian Route, Vehicle Route, Barrier, Fence |
| **Markers** | Start, Finish, Checkpoint, Aid Station, Medical, Buoy, Hazard, Electricity, Transition, Timing Mat, Spectator Area, Bag Drop, Feed Zone |
| **Zones** | Restricted Area, Parking, Transition Zone, Start Zone, Finish Area, Spectator Zone, Media Zone, Staging Area, Race Village, Exclusion Zone |
| **Infrastructure** | Tent, Generator, Toilet Block, Stage, Podium, Timing Gantry |

Each element supports:
- Custom name and notes
- Colour and opacity styling
- Lock (prevent accidental edits) and hide (declutter the view)
- Assignment to a specific plan or shared across all plans
- **Equipment list** — quantities, units, and notes per item
- **Level** tag (for child elements inside a group, e.g. "Ground floor")

### Groups (Hierarchical Elements)

- Draw a group boundary polygon to define a zone of interest (e.g. a transition area or race village)
- Child elements can be nested inside a group — drawing while a group is selected auto-assigns the parent
- Expand/collapse groups in the sidebar to show or hide children on the map and in the list
- Groups cannot be nested inside other groups

### Overlays

- Upload georeferenced image overlays (PNG, JPG, etc.) on top of the map
- Adjust opacity per overlay
- Pin overlays to a specific plan or share them across all plans
- Delete overlays (removes the file from storage)

### Export

- **Export PNG** — snapshot of the current map viewport at screen resolution
- **Print PNG** — high-DPI snapshot suitable for printing
- **Export CSV** — download all map elements for the active plan (including shared elements) as a spreadsheet

### Measurements

Selected elements automatically show calculated measurements in the properties panel:
- Routes: total length (m or km)
- Zones and polygons: area (m² or ha) and perimeter (m)
- Infrastructure rectangles: width, length, and rotation

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Frontend bridge | Inertia.js (Vue adapter) |
| Frontend | Vue 3 Composition API |
| CSS | Tailwind CSS 4 |
| Build | Vite |
| Map | MapLibre GL JS, @mapbox/mapbox-gl-draw, Turf.js |
| Auth | Laravel Breeze |
| Database | MariaDB (dev/prod), SQLite (tests) |

---

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link

npm run build
php artisan serve
```

Set your database credentials in `.env`. The dev database is MariaDB; tests run against SQLite in-memory automatically.

```bash
php artisan test   # run the test suite (~80 tests, ~2s)
```
