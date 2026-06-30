# Technical Implementation Plan: Resilient Background Processing and Design/Delight Overhaul

This document details the technical modifications for overhauling the plugin's CSS/JS assets and HTML structures to deliver a modern, premium light-mode experience with HSL-tailored slate/indigo palettes, gradient accents, and spring-like micro-animations.

## 1. System Architecture Updates
1. **Settings Navigation Overhaul**: Redesign the tab headers in `includes/class-virastai-acr-admin.php` and `assets/css/admin.css` to use a modern, custom pill-shaped tab system instead of the stock WordPress `.nav-tab` styling.
2. **Dynamic UI Delights**:
   - Add spring-like hover transforms and active states to settings forms, switches, buttons, and grid checkboxes.
   - Upgrade the basic skeleton loader in `assets/css/metabox.css` to a shimmering linear gradient sweep.
   - Animate transition states (e.g. settings saved confirmation notices, log details modal pop-ins) using custom CSS easing curves.
3. **Robust Backend Integration**: Keep retry limits, skipped states, timeout monitoring, and logging optimization fully functional while updating visual styles.

## 2. Asset Modifications & Design Palette

### Curated Color Tokens (HSL Slate/Indigo/Teal/Pink)
- **Primary Indigo Accent**: `hsla(238, 80%, 58%, 1)` -> `#4f46e5`
- **Secondary Slate Slate**: `hsla(215, 15%, 45%, 1)` -> `#64748b`
- **Success Emerald/Teal**: `hsla(162, 75%, 42%, 1)` -> `#10b981`
- **Danger Rose/Pink**: `hsla(340, 82%, 52%, 1)` -> `#ec4899`
- **Backgrounds**: Slate-grey tints `hsla(210, 40%, 98%, 1)` (`#f8fafc`) and transparent overlays.

---

### Component Styling Changes

#### 1. `assets/css/admin.css`
- **Main Container**: Increase border-radius to `12px` and apply a soft, elegant box-shadow (`0 4px 20px -2px rgba(148, 163, 184, 0.12), 0 2px 8px -1px rgba(148, 163, 184, 0.08)`).
- **Modern Tab Bar**: Override `.nav-tab-wrapper` and `.nav-tab`. Eliminate default borders, borders between tabs, and white backgrounds. Render as a unified card track with pill-shaped links that highlight with a gradient backdrop and slide/scale active states.
- **Form Switch Toggles**: Refine toggle sliders with custom scale transitions and spring easing (`cubic-bezier(0.34, 1.56, 0.64, 1)`).
- **Checkbox Grid Cards**: Add a hover lift (`transform: translateY(-3px)`) and a transition to scale the checkbox card when selected. Add a subtle radial gradient hover reflection.
- **Floating Sidebar widget**: Revamp the statistics sidebar widget with a smooth gradient progress bar (indigo-to-violet) and animated reset buttons (rotation + scale on hover).
- **Logs Viewer details modal**: Modernize with `backdrop-filter: blur(12px)` overlay, elastic scale animation, Consolas code panel with subtle inner glow, and animated copy status actions.

#### 2. `assets/css/metabox.css`
- **Metabox Container**: Style the sandbox panel to align with the new card aesthetic.
- **Regenerate & Action Buttons**: Implement primary (emerald-teal), secondary (slate), and danger (rose) gradient buttons with a subtle springy press transition.
- **Shimmering Skeleton Loader**: Define keyframe `@keyframes virastaiShimmer` that sweeps a linear gradient highlighting across the skeleton lines at a `1.5s` loop:
  ```css
  background: linear-gradient(90deg, #e2e8f0 25%, #cbd5e1 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: virastaiShimmer 1.5s infinite linear;
  ```
- **Copy Status Indication**: Add a green checkmark check animation when copy succeeds.

---

#### 3. `assets/js/admin.js` & `assets/js/metabox.js`
- **Transitions**: Handle transition triggers for modal opening (adding/removing class `.active` with appropriate JS timeouts).
- **Copy Success Actions**: Enhance the navigator clipboard triggers to dynamically adjust inner HTML with CSS-animated success checkboxes.

---

## 3. Verification Plan
- **PHP Syntax & WordPress Standards Check**: Run `php -l` on all PHP files.
- **Visual Inspections**: Verify responsiveness, tab transitions, hover elevations, modal bounce animations, and copy button state toggles.

