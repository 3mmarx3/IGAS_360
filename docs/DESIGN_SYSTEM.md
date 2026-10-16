# I-GAS Enterprise Design System
## Concept: "Pressure & Purity" — Industrial Gas Plant Visual Identity

The interface of I-GAS Enterprise is modeled on the control room of an industrial gas plant: precise, technical, calm, and trustworthy. It combines P&ID engineering diagrams, cryogenic frost aesthetics, and strict plant safety signage. Within 2 seconds, an operator or executive understands that this is an industrial gas product, avoiding generic SaaS tropes, purple gradients, and standard Tailwind blues.

---

## 1. Identity & Brand Palette

### Core Palette
- **Liquid Nitrogen Ink (`--ink`)**: `#0A1B26`
  Deep, dense navy-black drawn from compressed cryogenic gas storage. Serves as base typography color in light mode and sidebar backdrop.
- **Cryo Teal (`--primary`)**: `#0B6E8A`
  Technical instrument teal for structural framing, table headers, secondary buttons, and active tabs.
- **Frost Cyan (`--primary-bright`)**: `#19B5C9`
  High-visibility cyan for interactive links, charts, and sidebar active item typography.
- **Safety Valve Orange (`--accent`)**: `#FF6B1A`
  High-priority safety signage orange reserved for primary CTAs, critical indicators, and active sidebar indicator tabs.
- **Tonal Surface Scale (Light Mode)**:
  - Surface 0 (Canvas): `#F3F7F9` (Cool lab white)
  - Surface 1 (Cards/Elevations): `#FFFFFF` (Clean sterile card background)
  - Surface 2 (Instruments/Panels): `#E7EEF2` (Inputs, table headers, sub-cards)
  - Surface 3 (Hover/Active): `#DDE6EC`
- **Tonal Surface Scale (Dark Control-Room Mode)**:
  - Surface 0 (Canvas): `#07131B` (Deep control room navy, NOT pure black)
  - Surface 1 (Cards/Elevations): `#0D1F2B`
  - Surface 2 (Instruments/Panels): `#14303F`
  - Surface 3 (Hover/Active): `#1B3F54`
- **Hairlines (`--line`)**:
  - Light mode: `#D3DEE5` (Hairline border), `#E2EAF0` (Soft line), `#B8C7D1` (Hard line)
  - Dark mode: `#1F3A4A` (Hairline border), `#182F3D` (Soft line), `#2D5065` (Hard line)

### Status Scale (Safety & Compliance)
- **Purged / Nominal / OK**: `#1FA971` (Gas line purged, safe operation)
- **Caution / Warning**: `#F2B01E` (Inspection due, low tank level)
- **Hazard / Critical Alert**: `#D93636` (Leak, pressure threshold breached, overdue)
- **Technical Notice**: `#2F80ED` (Fleet dispatch, customer confirmation)

---

## 2. Absolute Zero-Shadow Rule

### Philosophy
In an industrial gas facility, safety gauges, valves, and manifold pipes do not have fuzzy dropshadows. Depth is created strictly through:
1. **1px hairline borders** (`border border-[var(--line)]`).
2. **Tonal surface layering** (3 engineered background levels: `surface-0`, `surface-1`, `surface-2`).
3. **2px colored top or start accent rules** (e.g. 3px orange active tab).
4. **Solid color blocks** and the **24px blueprint engineering grid**.
5. **Modals and dropdowns** separate via a 1px border plus a semi-opaque scrim (`rgba(7, 19, 27, 0.7)`).
6. **Focus indicators** use `outline: 2px solid var(--focus); outline-offset: 2px;` (never box-shadow rings).

### Safety Net
Enforced globally in `assets/css/theme.css` and `assets/css/main.css`:
```css
*, *::before, *::after {
    box-shadow: none !important;
    text-shadow: none !important;
    filter: none;
    -webkit-box-shadow: none !important;
}
```

---

## 3. Gas Color Coding & Cylinder Standards

Every gas product and cylinder displays a chemical formula chip with standard industrial cylinder shoulder/body color coding:

| Gas Name | Formula | Cylinder Color | Cap/Band | Hazard Class | Purity Std |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Oxygen** | O₂ | Blue (`#0066CC`) | White | Oxidizer | 99.995% |
| **Nitrogen** | N₂ | Black / Dark Slate (`#1F2937`) | Black | Compressed Gas | 99.999% |
| **Argon** | Ar | Dark Green (`#047857`) | Dark Green | Compressed Gas | 99.998% |
| **Carbon Dioxide** | CO₂ | Medium Gray (`#6B7280`) | Gray | Compressed Gas | 99.9% |
| **Acetylene** | C₂H₂ | Maroon (`#881337`) | Maroon | Flammable Gas | 98.5% |
| **Hydrogen** | H₂ | Red (`#DC2626`) | Red | Flammable Gas | 99.999% |
| **Helium** | He | Brown (`#78350F`) | Brown | Compressed Gas | 99.999% |
| **Compressed Air**| Air | Dark Gray (`#374151`) | White Split | Compressed Gas | Medical/Ind |
| **LPG** | LPG | Safety Orange (`#EA580C`) | Orange/Red | Flammable Gas | Commercial |
| **Nitrous Oxide** | N₂O | Blue (`#2563EB`) | Blue | Oxidizer | 99.5% |

### Cylinder Lifecycle Status Matrix
- **Full (Purged)**: Green `#1FA971` with `check-circle-2` icon.
- **In Plant**: Teal `#0B6E8A` with `gauge` icon.
- **In Transit**: Cyan `#19B5C9` with `truck` icon.
- **At Customer**: Blue `#2F80ED` with `building-2` icon.
- **Empty / Depleted**: Gray `#6B7280` with `circle` icon.
- **Under Test**: Yellow `#F2B01E` with `activity` icon.
- **Maintenance**: Red `#D93636` with `wrench` icon.

---

## 4. Technical Typography & Bilingual System

- **Display & Headings (Gauge Panel Feel)**:
  - English: **Oxanium** (squared technical letterforms)
  - Arabic: **Reem Kufi** (geometric industrial Kufi)
- **Body & Controls**:
  - English: **IBM Plex Sans**
  - Arabic: **IBM Plex Sans Arabic**
- **Data & Numerics**:
  - **IBM Plex Mono** with `tabular-nums` for quantities, pressures, weights, serial numbers, invoice numbers, prices, and numeric columns.
- **Section Overlines**:
  - Monospace uppercase with letter-spacing `0.16em` for an instrument-labeled look:
  ```css
  .section-overline {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--text-muted);
  }
  ```

---

## 5. Signature Industrial Gas Components

### 1. Blueprint / P&ID 24px Engineering Grid
Subtle 24px grid overlay (1px hairlines at 4-6% opacity) providing technical structure to the app canvas and brand surfaces:
```css
.blueprint-grid {
    background-image: 
        linear-gradient(to right, rgba(11, 110, 138, 0.05) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(11, 110, 138, 0.05) 1px, transparent 1px);
    background-size: 24px 24px;
}
```

### 2. Engineering Pipeline Dividers
Section separators rendered as technical pipelines with SVG valve and joint nodes:
```php
echo pipe_divider('MANIFOLD STAGE A — PURITY CHECKS', 'VALVE-04');
```

### 3. Semicircular Pressure Gauge KPI Cards
Key operational metrics rendered as semicircular pressure/capacity gauges with technical markings and needles in flat solid colors:
```php
echo gauge_kpi('CYLINDER STOCK', '842', 'CYL', 842, 0, 1200, 'nominal');
```

### 4. GHS Safety Hazard Diamond Badges
Standardized 45-degree diamond badges with thick hazard borders and industrial SVG pictograms:
- `flammable` (Flame)
- `oxidizer` (Flame over circle)
- `compressed_gas` (Cylinder under pressure)
- `toxic` (Hazard toxicity)
- `cryogenic` (Cryogenic extreme cold)
- `corrosive` (Corrosive liquid)

### 5. Tank-Fill Progress Level Bars
Horizontal level gauges with tick marks every 10% (0% to 100%) indicating volume, stock, or credit consumption.

### 6. Serial & Instrument Label Plates
Tags styled like engraved metal instrument identification plates:
```php
echo tag_plate('CYL-O2-9902', 'SERIAL');
```

---

## 6. Layout & Controls Specification

- **Sidebar**: `--ink` background, module groups with overline labels, active item indicated by a **3px start-side orange bar** (`#FF6B1A`), **frost-cyan text** (`#19B5C9`), and slightly elevated surface.
- **Buttons**:
  - `btn-cta-orange`: Safety Valve Orange fill (`#FF6B1A`) for the single primary call to action.
  - `btn-teal-outline`: Technical Cryo Teal outline (`#0B6E8A` / `#19B5C9` in dark mode).
- **Inputs & Forms**: 1px hairline border, 2px Cryo Teal border and outline on focus (`outline-offset: 2px`).
- **Tables**: Dense engineered layout with sticky header, 1px row borders, tabular-num monospace figures, and automatic horizontal scrolling (`overflow-x-auto`).
- **Bilingual & Responsive**: 100% mobile-first (320px–2560px), full RTL/LTR bi-directional mirroring via CSS logical properties.
