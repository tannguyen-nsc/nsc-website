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

- **THREE** (three.js r160) — 3D rendering, loaded via CDN
- **ThreeGlobe** (three-globe v2.31) — hex-polygon globe, loaded via CDN
- **TWEEN** (@tweenjs/tween.js v23) — animation easing, loaded via CDN
- Three.js addons (EffectComposer, RenderPass, UnrealBloomPass, OutputPass, LineSegments2, LineMaterial) — loaded asynchronously

## Verification

1. Open localhost — gear icon appears bottom-right, debug panel opens with all sections
2. Test every slider/toggle/color picker — scene updates in real-time
3. Resize browser — globe adjusts, connector lines stay visible
4. Cross 1280px breakpoint — connectors appear/disappear correctly
5. Scroll away from "Why Us" section — dispose runs cleanly (no console errors)
6. Scroll back — globe re-initializes correctly
7. Run `npm run prod` — minified output works correctly
8. Non-localhost — debug panel does NOT appear
