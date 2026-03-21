# Design System Documentation: The Monolithic Architect

## 1. Overview & Creative North Star
The Creative North Star for this design system is **"The Monolithic Architect."** 

This system moves away from the "web-page-as-a-document" mental model and toward "web-page-as-a-precision-tool." We aim for a high-end editorial feel that mirrors the complexity and beauty of high-level code. By utilizing intentional asymmetry, we break the rigid "template" look. Elements should feel like they are floating in a vast, dark void, held together by gravity and logic rather than visible boxes. 

The aesthetic is characterized by **Technical Brutalism**: high-contrast typography scales, vast negative space, and a rejection of traditional UI crutches like heavy borders and generic drop shadows.

---

## 2. Colors & Tonal Depth
Our color palette is rooted in the deep shadows of a developer’s environment. We do not use color for decoration; we use it for **functional signal**.

### The "No-Line" Rule
Standard 1px solid borders for sectioning are strictly prohibited. Layout boundaries must be defined through:
1.  **Background Color Shifts:** Use `surface_container_low` vs. `surface_container_highest` to define sections.
2.  **Negative Space:** Use the Spacing Scale (`16` or `20`) to create conceptual divides.
3.  **Tonal Transitions:** Subtle linear gradients between surface tiers to guide the eye.

### Surface Hierarchy & Nesting
Treat the UI as a physical stack of semi-translucent plates. 
- **Foundation:** `background` (#121316) or `surface_container_lowest`.
- **Primary Content Area:** `surface_container`.
- **Interactive Elements/Cards:** `surface_container_highest`.

### The "Glass & Gradient" Rule
To elevate the UI from "flat" to "premium," main CTAs and floating panels (like command palettes) should utilize **Glassmorphism**. Use a semi-transparent `surface_variant` with a 20px-40px backdrop blur. 
**Signature Texture:** For primary actions, use a 45-degree gradient from `primary` (#c0c1ff) to `primary_container` (#8083ff). This provides a "glow" that feels engineered, not just painted.

---

## 3. Typography: The Editorial Voice
We pair the human-centric **Inter** with the technical precision of **Berkeley Mono**.

- **Display & Headlines:** Use `display-lg` through `headline-sm`. These should be set with tight tracking (-0.02em) and high contrast against the dark background. 
- **The Technical Soul:** All numbers, code snippets, and metadata (labels) must use **Berkeley Mono**. This anchors the product in the developer's world.
- **Visual Hierarchy:** Use `label-sm` in `on_surface_variant` for "eyebrow" text above `headline-lg` titles to create an architectural, tiered information structure.

---

## 4. Elevation & Depth
Depth in this system is achieved through **Tonal Layering** rather than structural lines.

- **The Layering Principle:** Stacking surface tiers creates a natural lift. A `surface_container_high` card sitting on a `surface_container_low` background creates a "soft lift" that is easier on the eyes than a harsh shadow.
- **Ambient Shadows:** Shadows are reserved for high-level floating elements (modals). They must be ultra-diffused: `blur: 60px`, `y: 20px`, and use the `surface_tint` at 5% opacity. This mimics a soft glow from the screen rather than a shadow cast by a sun.
- **The "Ghost Border" Fallback:** If containment is required for accessibility, use a "Ghost Border." This is a 1px stroke using the `outline_variant` token at **15% opacity**. It should be barely visible, acting as a hint rather than a wall.

---

## 5. Components

### Buttons
- **Primary:** Gradient fill (`primary` to `primary_container`), `on_primary` text. No border.
- **Secondary:** `surface_container_highest` background with a Ghost Border.
- **Tertiary/Ghost:** No background. `primary` text. Use for low-emphasis actions.
- **Shape:** Use `rounded-md` (0.375rem) for a precise, "tooled" look.

### Chips
- Used for tags and status. Use `surface_container_high` with `label-md` typography.
- **Status Chips:** Use a 4px solid circle of `success`, `warning`, or `tertiary` (red) to the left of the text.

### Input Fields
- Background: `surface_container_lowest`. 
- Border: 1px Ghost Border. 
- Focus State: Transition the border to `primary` at 50% opacity and add a 2px `primary_container` outer glow (4% opacity).
- **Font:** Use Monospace for the input text to emphasize data precision.

### Cards & Lists
- **Forbid Dividers:** Do not use lines between list items. Use a 1px vertical gap that reveals the `surface_container_low` background, or simply use `spacing-4` to separate items.
- **Interaction:** On hover, a card should shift from `surface_container` to `surface_container_high`.

### Specialized: The "Terminal" Component
Since this is an AI Architect for developers, use a specific "Terminal Card" style:
- Background: `#08090C` (the Primary BG).
- Typography: `Berkeley Mono`.
- Border: Ghost Border using `secondary_container`.
- Use this for AI logs, architectural specs, or CLI outputs.

---

## 6. Do's and Don'ts

### Do:
- **Use Monospace for Data:** All timestamps, IDs, and coordinates must be in `Berkeley Mono`.
- **Embrace Asymmetry:** Align headings to the left while keeping specific data points shifted to the right to create an editorial "magazine" flow.
- **Layer Surfaces:** Always place a lighter surface on a darker one to indicate "upward" movement in the Z-axis.

### Don't:
- **Don't use #000000:** It is too heavy. Stick to the `background` token (#121316).
- **Don't use 100% Opaque Borders:** This shatters the "Monolithic" feel and makes the UI look like a legacy bootstrap app.
- **Don't center-align everything:** Center alignment is for marketing landing pages. This is a tool; keep it left-aligned or grid-anchored for speed of scanning.
- **Don't use standard Tooltip shadows:** Use the Ambient Shadow rule with a `surface_variant` background and 10% `outline` stroke.