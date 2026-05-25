# OnePlan vs RacePlanner — Feature Comparison

**Date:** 2026-05-25  
**OnePlan demo:** https://app.oneplan.io/events/d050ea59-294f-4f78-bdc6-ca27affbdfbf  
**OnePlan plan tier:** Free (No Subscription)

---

## What OnePlan Has That RacePlanner Is Missing

### 1. Generic Shape Drawing (Rectangle, Circle, Line, Triangle)
A **shape tool** separate from the element type system allows drawing freehand geometry: rectangle, circle, polyline, triangle. These are general-purpose drawing primitives not attached to any element type, useful for rough outlines, annotations, or scratch planning.

RacePlanner now has `annotation_line`, `annotation_rect`, and `annotation_circle` element subtypes with dedicated draw modes. However, these are still tied to the element type system (they create persisted elements with properties). OnePlan's shape tool is a more lightweight, scratch-drawing primitive with no metadata/properties overhead.

**Priority: Low-Medium — Annotation shapes are now covered for most use cases. True freehand/scratch shapes are a UX refinement.**

---

### 2. Rich Basemap Options
OnePlan offers **8 Esri-powered basemaps**: Streets 1–4 (different cartographic styles) and Satellite 1–3 + Satellite Streets. Each is a thumbnail-selectable tile. The "Powered by Esri" attribution is shown.

RacePlanner has 2 options: OSM vector style + Vlaanderen 2025 satellite WMTS. While the Flanders satellite is a good local option, OSM is less polished than Esri Streets.

**Priority: Medium — Adding more OSM-based or Esri-lite styles would improve appeal for non-Belgian events.**

---

### 3. Infrastructure Subcategories (Audio Visual, Electrical, Broadcast, etc.)
OnePlan's Infrastructure category has 17 subcategories: Audio Visual, Barriers, Branding and Signage, Broadcast, Electrical, Food and Beverage, Furniture, Ground and Trackway, Points of Interest, Safety and Security, Structures, Toilets, Traffic Management, Transport, Vehicles, Waste, Water and Sanitation. Each has its own icon set.

RacePlanner's infrastructure now has 13 subtypes: tent, generator, toilet_block, stage, podium, timing_gantry, food_stall, bar_drinks, water_point, banner_arch, info_board, bike_parking, shuttle_stop. This covers the most common race event needs but still lacks some of OnePlan's more specialised categories (AV/broadcast, electrical, furniture, waste).

**Priority: Low — Good coverage for typical race events. Remaining gaps are niche.**

---

### 4. Object Library / Community Packs
OnePlan has an **"Object library"** button that opens a catalogue of sport-specific element packs (Race pack, Biathlon pack, Cricket pack, etc.), each contributed by the community and containing 38–42 pre-styled elements. Adding a pack to an event (premium) pre-populates the sidebar with relevant types.

This is essentially a **template marketplace**. Users can discover what elements are typically needed for their sport.

RacePlanner has no template library or sport-specific presets.

**Priority: Low (infrastructure complexity) — High long-term value for viral adoption.**

---

### 5. Public View-Only Share Link
OnePlan's Share dialog has a **toggle** to generate a public read-only URL anyone can open without an account.

RacePlanner only supports collaboration via named invitations with roles. There is no way to share a plan as a "view link" without adding the recipient as a collaborator.

**Priority: Medium — Important for sharing plans with external stakeholders (venue managers, officials) who shouldn't need an account.**

---

### 6. Password-Protected Share Link *(OnePlan Premium)*
Building on the view-only share link above: OnePlan lets event owners **set a password** on the public share URL so only people with the password can view it.

RacePlanner has no share link at all yet. Once #5 is implemented, password protection is a natural next step.

**Priority: Low — Depends on #5 being built first.**

---

### 7. Levels System (Multi-Floor / Multi-Phase)
OnePlan uses **Levels** (Level 0, Level 1, etc.) to model different floors of a venue or distinct phases of an event. Levels can be renamed, duplicated, reordered, and deleted. Only Level 0 is free; adding more is premium.

RacePlanner has **Plans** which serve a similar purpose (different versions/days of the event, each with their own element set). Plans are arguably more powerful for multi-day race events. However, the naming is less intuitive for indoor/multi-floor venues.

**Gap: Conceptual overlap — Plans serve a similar role. Not a critical missing feature.**

---

### 8. Legend Generator
OnePlan links to an external **Legend Generator** tool (https://legend.oneplan.io/) that auto-generates a printable map legend based on the placed objects and their icons. This is useful for printing and distributing to event staff.

RacePlanner has no legend generation.

**Priority: Low initially — High value once the element icon set is fully mature.**

---

## What RacePlanner Does Well (Advantages vs OnePlan Free Tier)

| Feature | RacePlanner | OnePlan (Free) |
|---|---|---|
| Multiple events | Unlimited free | 1 event (more = premium) |
| Plans per event | Multiple (rename/duplicate/delete) | Plans not clearly separate from levels |
| Overlay import | ✓ Free | ✗ Premium |
| Event duplication | ✓ Free | ✗ Premium |
| Collaboration (roles) | Owner/Editor/Viewer with invitations | Limited (team = premium) |
| Belgium satellite imagery | Vlaanderen 2025 WMTS (high quality) | Generic Esri satellite only |
| Shared elements across plans | ✓ (event_plan_id = NULL) | Not explicit |
| Measurements (length/area) | ✓ Auto-calculated via Turf.js | ✓ |
| Per-element notes | ✓ | ✓ |
| SVG element icons (sidebar + map) | ✓ Custom icon library | ✓ |
| Coordinates display + copy | ✓ In properties panel | ✓ |
| Search in placed objects | ✓ | ✓ |
| Workforce placement types | ✓ (Steward, Security, Police, Fire, Volunteer, Supervisor) | ✓ |
| Entry & access types | ✓ (Entry Gate, Exit Gate, Ticket Check, Wristband, Accreditation) | ✓ |
| Undo/Redo | ✓ With buttons + Ctrl-Z/Y keyboard shortcuts | ✓ |
| Stroke type (solid/dashed/dotted) | ✓ PropertiesPanel dropdown + 3 MapLibre line layers | ✓ |
| Text label tool | ✓ `text_label` element subtype | ✓ |
| Annotation shapes | ✓ annotation_line, annotation_rect, annotation_circle | Partial |
| Self-hosted / open codebase | Yes | SaaS only |

---

## Priority-Ordered Improvement Backlog

| # | Feature | Effort | Impact |
|---|---|---|---|
| 1 | Public view-only share link | Medium | High |
| 2 | More basemap styles | Low | Low-Med |
| 3 | Generic shape tools (scratch drawing, no element overhead) | Medium | Low-Med |
| 4 | Password-protected share link (after #1) | Low | Medium |
| 5 | Expanded infrastructure element types (AV, electrical, waste…) | Med | Low |
| 6 | Object library / sport packs (free import = competitive advantage) | High | High (viral) |
| 7 | Legend generator | High | Medium |
