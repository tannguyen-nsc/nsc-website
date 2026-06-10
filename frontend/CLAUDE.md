# NSC Website Frontend

## globe.js Architecture

`src/js/external/globe.js` is a single ES5 IIFE that renders the interactive 3D globe in the "Why NSC Software?" section. All state is shared through closure variables at the top of the IIFE — there are no modules or classes.

### Coding Style

- **ES5 only**: `var`, `function` declarations, string concatenation (no `let`/`const`/arrow/template literals)
- Section banners use `// ---...--- //` comment blocks
- Functions are declared (not assigned to variables)
- No semicolon-less style — all statements end with `;`

### File Sections (in order)

1. **Configuration** — `var` constants (colors, sizes, URLs)
2. **State** — mutable `var` declarations (scene objects, meshes, DOM refs)
3. **Helpers** — pure utility functions (`isVietnam`, `latLngToRotation`, `projectToScreen`, `latLngToWorld`)
4. **Debug UI Primitives** — reusable DOM builders (`makeSlider`, `makeHeading`, `makeCollapsibleGroup`, `makeToggle`, `makeColorPicker`)
5. **SVG Connector Lines** — `createSVGOverlay()`, `updateConnectorLines()`
6. **Wireframe / Grid Dots / Connector Dots / Rim Shell** — mesh factory functions
7. **Bloom Setup** — `setupBloom()` + deferred addon listener
8. **Renderer & Lights Factories** — `createRenderer()`, `createLights()`
9. **Globe Initialization** — `initScene()` (orchestrator)
10. **Vietnam Tile** — `createVietnamTile()`, `updateVietnamTilePosition()`
11. **buildGlobe()** — assembles all meshes into `globeGroup`
12. **Rotation** — `rotateToPoint()` with TWEEN animation
13. **Animation Loop** — `syncDebugPanel()`, `animate()`
14. **Event Handlers** — `attachHoverListeners()`, `onResize()`
15. **Debug Panel Sections** — 14 `append*Section(panel)` functions
16. **Debug Panel** — `createDebugPanel()` orchestrator (~50 lines)
17. **Dispose Helpers** — 7 `dispose*()` functions
18. **Lifecycle** — `dispose()` orchestrator, `init()`
19. **Lazy Loading** — `setupLazyLoad()` with IntersectionObserver
20. **Boot** — DOMContentLoaded gate
21. **Public API** — `window.NSCGlobe`

### Debug Panel Conventions

- Each section is an `append*Section(panel)` function that receives the panel DOM element and appends controls
- Use `makeSlider(label, min, max, step, value, onChange, getter)` for range inputs
- Use `makeToggle(label, checked, onChange, getter)` for checkboxes
- Use `makeColorPicker(label, value, onChange, getter)` for color inputs
- Use `makeHeading(text)` for section titles
- Use `makeCollapsibleGroup(title, collapsed, buildContent)` for collapsible groups
- The `getter` callback enables live sync — registered controls update every frame via `syncDebugPanel()`
- Three registries (`sliderRegistry`, `toggleRegistry`, `colorRegistry`) track controls for frame sync
- **Real-time sync invariant:** Every `make*` control that reads mutable state **must** provide a `getter` callback. `syncDebugPanel()` (called every animation frame when the panel is visible) uses these getters to keep displayed values in sync with the actual runtime state. Never omit the getter — a slider without one will show stale values when the underlying state changes externally (e.g., via TWEEN animation or programmatic updates).

### Key Shared State

- **Scene objects**: `scene`, `camera`, `renderer`, `globeGroup`, `composer`, `bloomPass`
- **Meshes**: `globe`, `wireframeMesh`, `gridDotsMesh`, `connectorDotsMesh`, `rimShellMesh`, `vietnamTileMesh`
- **Lights**: `ambientLight`, `keyLight`, `fillLight`, `backLight`, `spotLights[4]`
- **DOM refs**: `containerEl`, `sectionEl`, `svgOverlay`, `debugPanel`, `debugWrapper`, `debugGearBtn`
- **Lifecycle flags**: `isInitialized`, `isDisposed`, `animFrameId`

## Dependencies

- **THREE** (three.js r177, classic `build/three.module.min.js`) — 3D rendering. There is **no UMD build** at r177; `src/js/external/three-bootstrap.js` (a `<script type="module">`) imports the core via the page importmap, spreads the frozen module namespace into a mutable plain object, attaches the addons, publishes it as `window.THREE`, and dispatches `three-addons-ready`. `three.module.min.js` pulls `three.core.min.js` via a relative import — no extra importmap entry needed.
- **Three.js addons** (EffectComposer, RenderPass, ShaderPass, UnrealBloomPass, OutputPass, LineSegmentsGeometry, LineMaterial, LineSegments2) — official `three/addons/` (examples/jsm) at r177, imported by three-bootstrap.js. The old vendored `three-fat-lines.js` (r160 copy of the lines addons) has been removed.
- **ThreeGlobe** (three-globe v2.45 UMD) — hex-polygon globe, loaded via CDN; reads `window.THREE` at evaluation time, so it must stay after the bootstrap in the script order.
- **TWEEN** (@tweenjs/tween.js v23) — animation easing, loaded via CDN
- **wave-three.js** uses `three/webgpu` + `three/tsl` (r177 webgpu build) via dynamic import only — it never touches bare `three` or `window.THREE`. Note: the importmaps currently point `three/webgpu` at the non-minified `three.webgpu.js`, which pulls `three.core.js` — a **different URL** from the classic chain's `three.core.min.js` — so pages that load both the globe stack and wave-three fetch **two** three cores. Known follow-up: switching the webgpu/tsl entries to `three.webgpu.min.js` would reuse `three.core.min.js` and reduce this to one core per page.

### Script-order contract

Non-async `<script type="module">` and classic `defer` scripts share one in-order post-parse queue. The required order on every page is: importmap → `three-bootstrap.js` (module) → three-globe UMD → tween UMD → `globe.js`. This block is **duplicated verbatim across 7 HTML pages** (index, 404, about, cookies-policy, privacy-policy, terms-of-use, master — about.html has no wave/webgpu entries); there is no shared partial, so edits must be repeated on all 7.

## Verification

1. Open localhost — gear icon appears bottom-right, debug panel opens with all sections
2. Test every slider/toggle/color picker — scene updates in real-time
3. Resize browser — globe adjusts, connector lines stay visible
4. Cross 1140px breakpoint — connectors appear/disappear correctly
5. Scroll away from "Why Us" section — dispose runs cleanly (no console errors)
6. Scroll back — globe re-initializes correctly
7. Run `npm run prod` — minified output works correctly
8. Non-localhost — debug panel does NOT appear
