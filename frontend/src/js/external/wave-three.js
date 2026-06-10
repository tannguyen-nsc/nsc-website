/**
 * NSC Wave (three.js variant) - Interactive Wireframe Terrain for the hero section.
 *
 * GPU re-creation of wave.js: same oblique-orthographic projection, FBM noise,
 * cursor force, lighting and debug panel — but every per-vertex computation
 * (noise, displacement, cursor, backface culling, lighting, depth fade) runs in
 * TSL node shaders on WebGPURenderer (auto-fallback to WebGL2).
 *
 * Active by default; set localStorage NSC_WAVE_USE_THREE to "0" or "false"
 * to opt out, in which case this file does nothing and wave.js (Canvas 2D)
 * runs instead. Loads three 0.177 lazily via dynamic import("three/webgpu") /
 * ("three/tsl") (importmap entries) so opted-out visitors download nothing extra.
 * Never imports bare "three" — that resolves to the page's legacy 0.160 build.
 *
 * @module NSCWaveThree
 */
(function () {
  "use strict";

  function useThreeWave() {
    var v = null;
    try { v = window.localStorage.getItem("NSC_WAVE_USE_THREE"); } catch (e) {}
    return v !== "0" && v !== "false";
  }

  if (!useThreeWave()) return;

  // Keep a handle on the Canvas-2D implementation (wave.js runs first and
  // always publishes window.NSCWave) so we can fall back if WebGPU/WebGL2 fail.
  var canvasWaveAPI = null;

  // ---------------------------------------------------------------------------
  // Configuration (identical keys/defaults to wave.js)
  // ---------------------------------------------------------------------------

  var cfg = {
    gridCols: 100,
    gridRows: 100,
    gridSpacing: 14,
    amplitude: 96,
    frequency: 0.057,
    speed: 0.5,
    octaves: 4,
    turbulence: 0.8,
    cursorStrength: 20,
    cursorRadius: 80,
    cursorMode: "push",
    cursorFalloff: "smooth",
    cameraPitch: 0.14,
    cameraHeight: 200,
    cameraZoom: 3,
    cameraPosX: 245,
    cameraPosY: -15,
    cameraRotation: -30,
    lineColor: "#ffffff",
    lineWidth: 1,
    lineOpacity: 0.8,
    showFill: true,
    fillColor: "#1fd5f9",
    fillOpacity: 1,
    lightEnabled: true,
    lightDirX: -0.4,
    lightDirY: -0.8,
    lightDirZ: 0.5,
    lightAmbient: 0.88,
    lightDiffuse: 1.3,
    lightSpecular: 0.45,
    lightSpecPower: 21,
    showRows: true,
    showCols: true,
    depthFade: true,
    heightColor: false,
    backfaceCull: true
  };

  var defaults = {};
  (function () {
    var keys = Object.keys(cfg);
    for (var i = 0; i < keys.length; i++) {
      defaults[keys[i]] = cfg[keys[i]];
    }
  })();

  var BREAKPOINTS = [
    { minWidth: 3840, cameraZoom: 4.0, cameraHeight: 300 },  // 4K
    { minWidth: 2560, cameraZoom: 3.0, cameraHeight: 200 }   // 2K
  ];

  // Sub-segments used to rasterize each quadratic-midpoint spline piece of the
  // row lines (canvas quadraticCurveTo equivalent).
  var ROW_SUBDIV = 4;

  // Noise lattice is periodic mod 289, so time can wrap at the smallest T with
  // 0.3T, 0.15T and 0.4T all integer multiples of 289 (T=5780) without any
  // visual discontinuity. Keeps float32 noise math exact forever (the original
  // relies on JS float64 instead).
  var TIME_WRAP = 5780;

  // Height storage sized for the debug-slider maxima (200 cols x 120 rows).
  // One extra workgroup (64) of padding absorbs dispatch-count rounding so a
  // padded compute invocation can never write out of bounds.
  var MAX_GRID_POINTS = 200 * 120;

  // ---------------------------------------------------------------------------
  // Presets (identical to wave.js)
  // ---------------------------------------------------------------------------

  var presets = {
    terrain: { amplitude: 55, frequency: 0.018, speed: 0.4, octaves: 5, turbulence: 0.6, cameraPitch: 0.55, cameraHeight: 80 },
    ocean: { amplitude: 25, frequency: 0.035, speed: 2.0, octaves: 3, turbulence: 0.1, cameraPitch: 0.7, cameraHeight: 40, lineColor: "#4488cc" },
    pulse: { amplitude: 80, frequency: 0.015, speed: 3.0, octaves: 1, turbulence: 0.0, cameraPitch: 0.5, cameraHeight: 20 },
    chaos: { amplitude: 100, frequency: 0.05, speed: 2.5, octaves: 6, turbulence: 0.9, cameraPitch: 0.6, cameraHeight: 50 },
    calm: { amplitude: 15, frequency: 0.02, speed: 0.5, octaves: 2, turbulence: 0.0, cameraPitch: 0.75, cameraHeight: 30 }
  };

  function applyPreset(name) {
    var base;
    var k;
    if (name === "reset") {
      base = defaults;
    } else {
      base = {};
      var dKeys = Object.keys(defaults);
      for (k = 0; k < dKeys.length; k++) {
        base[dKeys[k]] = defaults[dKeys[k]];
      }

      var pKeys = Object.keys(presets[name] || {});
      for (k = 0; k < pKeys.length; k++) {
        base[pKeys[k]] = presets[name][pKeys[k]];
      }
    }

    var allKeys = Object.keys(base);
    for (k = 0; k < allKeys.length; k++) {
      cfg[allKeys[k]] = base[allKeys[k]];
    }
  }

  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------

  var THREE = null;
  var TSL = null;
  var modulesPromise = null;

  var canvas = null;
  var renderer = null;
  var scene = null;
  var camera = null;
  var fillMesh = null;
  var rowMesh = null;
  var colMesh = null;
  var fillMat = null;
  var rowMat = null;
  var colMat = null;
  var uniforms = null;
  var builtCols = 0;
  var builtRows = 0;

  // GPU height-field compute pass (WebGPU backend only): finalHeight is
  // evaluated once per grid point per frame into this storage buffer instead
  // of redundantly in every vertex of every material (~144x fewer noise
  // evaluations at the default 100x100 grid).
  var gpuCompute = false;
  var heightsBuf = null;
  var heightsComputeFn = null;
  var heightsCompute = null;
  var computeBuiltCols = 0;
  var computeBuiltRows = 0;

  // Cache of the last uploaded color hex strings (avoids re-parsing/allocating
  // in the rAF loop — colors only change via the debug panel).
  var lastLineHex = "";
  var lastFillHex = "";

  var W = 0;
  var H = 0;
  var sectionEl = null;
  var animFrameId = null;
  var heroVisible = true; // tracked by the IntersectionObserver (if available)
  var mouse = { x: -9999, y: -9999, active: false };
  var time = 0;
  var lastTime = 0;
  var frameCount = 0;
  var fpsTimer = 0;
  var fpsVal = 0;
  var isInitialized = false;
  var isDisposed = false;
  var initToken = 0;
  var fallbackTriggered = false;

  var debugPanel = null;
  var debugWrapper = null;
  var debugGearBtn = null;
  var sliderRegistry = [];
  var toggleRegistry = [];
  var colorRegistry = [];
  var selectRegistry = [];
  var statsFpsEl = null;
  var statsPointsEl = null;
  var statsLinesEl = null;

  var boundMouseMove = null;
  var boundMouseLeave = null;
  var boundResize = null;
  var resizeTimer = null;

  var CURSOR_MODE_INDEX = { pull: 0, push: 1, swirl: 2, flatten: 3 };
  var CURSOR_FALLOFF_INDEX = { smooth: 0, linear: 1, sharp: 2 };

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  function hexToRGB(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  // ---------------------------------------------------------------------------
  // Debug UI Primitives (identical look/behavior to wave.js)
  // ---------------------------------------------------------------------------

  function makeSlider(label, min, max, step, value, onChange, getter) {
    var row = document.createElement("div");
    row.style.cssText =
      "margin:3px 0;display:flex;align-items:center;gap:6px;";
    var lbl = document.createElement("span");
    lbl.textContent = label;
    lbl.style.cssText = "min-width:90px;font-size:10px;";
    var inp = document.createElement("input");
    inp.type = "range";
    inp.min = min;
    inp.max = max;
    inp.step = step;
    inp.value = value;
    inp.style.cssText = "flex:1;height:14px;accent-color:#36C3DC;";
    var val = document.createElement("span");
    val.textContent = Number(value).toFixed(2);
    val.style.cssText = "min-width:32px;text-align:right;font-size:10px;";
    inp.addEventListener("input", function () {
      val.textContent = Number(inp.value).toFixed(2);
      onChange(Number(inp.value));
    });
    row.appendChild(lbl);
    row.appendChild(inp);
    row.appendChild(val);
    if (getter) {
      sliderRegistry.push({ inp: inp, val: val, getter: getter });
    }

    return row;
  }

  function makeHeading(text) {
    var h = document.createElement("div");
    h.textContent = text;
    h.style.cssText =
      "color:#36C3DC;font-weight:bold;font-size:11px;margin:8px 0 3px;border-bottom:1px solid #333;padding-bottom:2px;";
    return h;
  }

  function makeCollapsibleGroup(title, collapsed, buildContent) {
    var wrapper = document.createElement("div");
    var header = document.createElement("div");
    header.style.cssText =
      "color:#36C3DC;font-weight:bold;font-size:11px;margin:8px 0 3px;border-bottom:1px solid #333;padding-bottom:2px;cursor:pointer;user-select:none;";
    var arrow = document.createElement("span");
    arrow.textContent = collapsed ? "▶ " : "▼ ";
    arrow.style.cssText = "font-size:9px;";
    var labelEl = document.createElement("span");
    labelEl.textContent = title;
    header.appendChild(arrow);
    header.appendChild(labelEl);
    var body = document.createElement("div");
    body.style.display = collapsed ? "none" : "block";
    buildContent(body);
    header.addEventListener("click", function () {
      var isHidden = body.style.display === "none";
      body.style.display = isHidden ? "block" : "none";
      arrow.textContent = isHidden ? "▼ " : "▶ ";
    });
    wrapper.appendChild(header);
    wrapper.appendChild(body);
    return wrapper;
  }

  function makeToggle(label, checked, onChange, getter) {
    var row = document.createElement("div");
    row.style.cssText = "margin:3px 0;display:flex;align-items:center;gap:6px;";
    var lbl = document.createElement("span");
    lbl.textContent = label;
    lbl.style.cssText = "min-width:90px;font-size:10px;";
    var chk = document.createElement("input");
    chk.type = "checkbox";
    chk.checked = checked;
    chk.style.cssText = "accent-color:#36C3DC;cursor:pointer;";
    chk.addEventListener("change", function () {
      onChange(chk.checked);
    });
    if (getter) {
      toggleRegistry.push({ checkbox: chk, getter: getter });
    }

    row.appendChild(lbl);
    row.appendChild(chk);
    return row;
  }

  function makeColorPicker(label, value, onChange, getter) {
    var row = document.createElement("div");
    row.style.cssText = "margin:3px 0;display:flex;align-items:center;gap:6px;";
    var lbl = document.createElement("span");
    lbl.textContent = label;
    lbl.style.cssText = "min-width:90px;font-size:10px;";
    var inp = document.createElement("input");
    inp.type = "color";
    inp.value = value;
    inp.style.cssText = "width:40px;height:20px;border:none;padding:0;cursor:pointer;";
    inp.addEventListener("input", function () {
      onChange(inp.value);
    });
    if (getter) {
      colorRegistry.push({ input: inp, getter: getter });
    }

    row.appendChild(lbl);
    row.appendChild(inp);
    return row;
  }

  function makeSelect(label, options, value, onChange, getter) {
    var row = document.createElement("div");
    row.style.cssText = "margin:3px 0;display:flex;align-items:center;gap:6px;";
    var lbl = document.createElement("span");
    lbl.textContent = label;
    lbl.style.cssText = "min-width:90px;font-size:10px;";
    var sel = document.createElement("select");
    sel.style.cssText =
      "flex:1;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);" +
      "color:#fff;font:10px monospace;padding:3px 6px;border-radius:4px;cursor:pointer;";
    for (var i = 0; i < options.length; i++) {
      var opt = document.createElement("option");
      opt.value = options[i].value;
      opt.textContent = options[i].label;
      if (options[i].value === value) opt.selected = true;
      sel.appendChild(opt);
    }

    sel.addEventListener("change", function () {
      onChange(sel.value);
    });
    if (getter) {
      selectRegistry.push({ select: sel, getter: getter });
    }

    row.appendChild(lbl);
    row.appendChild(sel);
    return row;
  }

  // ---------------------------------------------------------------------------
  // Responsive Grid (identical to wave.js)
  // ---------------------------------------------------------------------------

  function responsiveCols() {
    var w = window.innerWidth;
    if (w < 768) return 40;
    if (w < 992) return 60;
    if (w < 1280) return 80;
    return 100;
  }

  function responsiveZoom() {
    var w = window.screen.width * (window.devicePixelRatio || 1);
    for (var i = 0; i < BREAKPOINTS.length; i++) {
      if (w >= BREAKPOINTS[i].minWidth) return BREAKPOINTS[i].cameraZoom;
    }

    return defaults.cameraZoom;
  }

  function responsiveHeight() {
    var w = window.screen.width * (window.devicePixelRatio || 1);
    for (var i = 0; i < BREAKPOINTS.length; i++) {
      if (w >= BREAKPOINTS[i].minWidth) return BREAKPOINTS[i].cameraHeight;
    }

    return defaults.cameraHeight;
  }

  // ---------------------------------------------------------------------------
  // Geometry Builders (static — attributes hold grid indices, never positions)
  // ---------------------------------------------------------------------------

  // Fill: de-indexed quads (4 unique verts each) so every vertex knows its quad
  // (aQuad) for shader-side backface culling + flat per-quad lighting.
  // Quads ordered back-to-front by row, matching the painter loop of wave.js.
  function buildFillGeometry(cols, rows) {
    var quads = (cols - 1) * (rows - 1);
    var pos = new Float32Array(quads * 4 * 3);
    var quad = new Float32Array(quads * 4 * 2);
    var idx = new Uint32Array(quads * 6);
    var v = 0;
    var q = 0;
    var ii = 0;
    var vi = 0;
    for (var qr = 0; qr < rows - 1; qr++) {
      for (var qc = 0; qc < cols - 1; qc++) {
        var corners = [qc, qr, qc + 1, qr, qc + 1, qr + 1, qc, qr + 1];
        for (var k = 0; k < 4; k++) {
          pos[v++] = corners[k * 2];
          pos[v++] = corners[k * 2 + 1];
          pos[v++] = 0;
          quad[q++] = qc;
          quad[q++] = qr;
        }

        idx[ii++] = vi; idx[ii++] = vi + 1; idx[ii++] = vi + 2;
        idx[ii++] = vi; idx[ii++] = vi + 2; idx[ii++] = vi + 3;
        vi += 4;
      }
    }

    var g = new THREE.BufferGeometry();
    g.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    g.setAttribute("aQuad", new THREE.BufferAttribute(quad, 2));
    g.setIndex(new THREE.BufferAttribute(idx, 1));
    return g;
  }

  // Row lines: one "piece" per (row, segment c-1..c), tessellated into
  // ROW_SUBDIV sub-quads along the quadratic spline; vertices are screen-space
  // ribbon sides (position = (c, r, side), aT = curve parameter).
  function buildRowLineGeometry(cols, rows) {
    var N = ROW_SUBDIV;
    var pieces = rows * (cols - 1);
    var vpp = (N + 1) * 2;
    var pos = new Float32Array(pieces * vpp * 3);
    var at = new Float32Array(pieces * vpp);
    var idx = new Uint32Array(pieces * N * 6);
    var v = 0;
    var a = 0;
    var ii = 0;
    var vi = 0;
    for (var r = 0; r < rows; r++) {
      for (var c = 1; c < cols; c++) {
        var j;
        for (j = 0; j <= N; j++) {
          var t = j / N;
          pos[v++] = c; pos[v++] = r; pos[v++] = -1; at[a++] = t;
          pos[v++] = c; pos[v++] = r; pos[v++] = 1; at[a++] = t;
        }

        for (j = 0; j < N; j++) {
          var b = vi + j * 2;
          idx[ii++] = b; idx[ii++] = b + 1; idx[ii++] = b + 2;
          idx[ii++] = b + 1; idx[ii++] = b + 3; idx[ii++] = b + 2;
        }

        vi += vpp;
      }
    }

    var g = new THREE.BufferGeometry();
    g.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    g.setAttribute("aT", new THREE.BufferAttribute(at, 1));
    g.setIndex(new THREE.BufferAttribute(idx, 1));
    return g;
  }

  // Column lines: straight ribbon per segment (r0 -> r0+1).
  // position = (c, r0, side), aT = end flag (0 = upper vertex, 1 = lower).
  function buildColLineGeometry(cols, rows) {
    var segs = (rows - 1) * cols;
    var pos = new Float32Array(segs * 4 * 3);
    var at = new Float32Array(segs * 4);
    var idx = new Uint32Array(segs * 6);
    var v = 0;
    var a = 0;
    var ii = 0;
    var vi = 0;
    for (var r0 = 0; r0 < rows - 1; r0++) {
      for (var c = 0; c < cols; c++) {
        pos[v++] = c; pos[v++] = r0; pos[v++] = -1; at[a++] = 0;
        pos[v++] = c; pos[v++] = r0; pos[v++] = 1; at[a++] = 0;
        pos[v++] = c; pos[v++] = r0; pos[v++] = -1; at[a++] = 1;
        pos[v++] = c; pos[v++] = r0; pos[v++] = 1; at[a++] = 1;
        idx[ii++] = vi; idx[ii++] = vi + 1; idx[ii++] = vi + 2;
        idx[ii++] = vi + 1; idx[ii++] = vi + 3; idx[ii++] = vi + 2;
        vi += 4;
      }
    }

    var g = new THREE.BufferGeometry();
    g.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    g.setAttribute("aT", new THREE.BufferAttribute(at, 1));
    g.setIndex(new THREE.BufferAttribute(idx, 1));
    return g;
  }

  function rebuildGeometries() {
    var cols = cfg.gridCols;
    var rows = cfg.gridRows;
    if (fillMesh.geometry) fillMesh.geometry.dispose();
    if (rowMesh.geometry) rowMesh.geometry.dispose();
    if (colMesh.geometry) colMesh.geometry.dispose();
    fillMesh.geometry = buildFillGeometry(cols, rows);
    rowMesh.geometry = buildRowLineGeometry(cols, rows);
    colMesh.geometry = buildColLineGeometry(cols, rows);
    builtCols = cols;
    builtRows = rows;
  }

  // ---------------------------------------------------------------------------
  // TSL Shader Construction
  // ---------------------------------------------------------------------------

  function createUniforms() {
    function f(v) { return TSL.uniform(v); }
    lastLineHex = "";
    lastFillHex = "";
    return {
      time: f(0),
      // Unwrapped time, used ONLY by the swirl cursor's sin(angle*3 + t*2):
      // the wrapped `time` would phase-jump at TIME_WRAP (2*5780 is not a
      // multiple of 2*pi). The noise itself must keep the wrapped value.
      timeRaw: f(0),
      ox: f(0),
      oy: f(0),
      zoom: f(cfg.cameraZoom),
      pitch: f(cfg.cameraPitch),
      cosR: f(1),
      sinR: f(0),
      sp: f(cfg.gridSpacing),
      halfC: f((cfg.gridCols - 1) / 2),
      halfR: f((cfg.gridRows - 1) / 2),
      cols: f(cfg.gridCols),
      rows: f(cfg.gridRows),
      invRowsM1: f(1 / Math.max(cfg.gridRows - 1, 1)),
      lineZBias: f(0.3 / Math.max(cfg.gridRows - 1, 1)),
      colDX: f(0),
      colDY: f(0),
      rowDX: f(0),
      rowDY: f(0),
      amplitude: f(cfg.amplitude),
      ampGuard: f(cfg.amplitude || 1),
      frequency: f(cfg.frequency),
      octaves: f(cfg.octaves),
      turbulence: f(cfg.turbulence),
      mouseX: f(-9999),
      mouseY: f(-9999),
      mouseActive: f(0),
      cursorStrength: f(cfg.cursorStrength),
      cursorRadius: f(cfg.cursorRadius),
      cursorMode: f(CURSOR_MODE_INDEX[cfg.cursorMode] || 0),
      cursorFalloff: f(CURSOR_FALLOFF_INDEX[cfg.cursorFalloff] || 0),
      lineColor: TSL.uniform(new THREE.Vector3(255, 255, 255)),  // 0..255 to mirror canvas rgba() math
      lineWidth: f(cfg.lineWidth),
      lineOpacity: f(cfg.lineOpacity),
      fillColor: TSL.uniform(new THREE.Vector3(31, 213, 249)),
      fillOpacity: f(cfg.fillOpacity),
      depthFade: f(1),
      heightColor: f(0),
      cull: f(1),
      lightEnabled: f(1),
      lightDir: TSL.uniform(new THREE.Vector3(0, 0, 1)),         // normalized on CPU each frame, like wave.js
      lightAmbient: f(cfg.lightAmbient),
      lightDiffuse: f(cfg.lightDiffuse),
      lightSpecular: f(cfg.lightSpecular),
      lightSpecPower: f(cfg.lightSpecPower)
    };
  }

  function buildMaterials() {
    var u = uniforms;

    // -- Noise (exact port of wave.js permute/noise2D/fbm) --------------------
    // permute inputs are wrapped mod 289 (the permutation polynomial is
    // mod-289-invariant), keeping float32 math exact where the original used
    // float64. Inputs are always >= 0 so GLSL mod == JS %.
    function permute(x) {
      return TSL.mod(x.mul(34.0).add(1.0).mul(x), 289.0);
    }

    // NB: Fn callbacks must destructure params iterably — layouted functions
    // receive a named-property object (not an array) at shader-build time.
    var noise2D = TSL.Fn(function ([p]) {
      var ix = TSL.mod(TSL.floor(p.x), 289.0).toVar();
      var iy = TSL.mod(TSL.floor(p.y), 289.0).toVar();
      var fx = TSL.fract(p.x).toVar();
      var fy = TSL.fract(p.y).toVar();
      var ux = fx.mul(fx).mul(TSL.float(3.0).sub(fx.mul(2.0))).toVar();
      var uy = fy.mul(fy).mul(TSL.float(3.0).sub(fy.mul(2.0))).toVar();
      var p0 = permute(ix).toVar();
      var p1 = permute(ix.add(1.0)).toVar();
      var a = permute(p0.add(iy)).div(289.0).toVar();
      var b = permute(p1.add(iy)).div(289.0).toVar();
      var c = permute(p0.add(iy).add(1.0)).div(289.0).toVar();
      var d = permute(p1.add(iy).add(1.0)).div(289.0).toVar();
      return a
        .add(b.sub(a).mul(ux))
        .add(c.sub(a).mul(uy))
        .add(d.sub(b).sub(c).add(a).mul(ux).mul(uy))
        .sub(0.5);
    }).setLayout({
      name: "nscNoise2D",
      type: "float",
      inputs: [{ name: "p", type: "vec2" }]
    });

    // FBM, unrolled to the slider max (6) with per-octave masks so `octaves`
    // stays a uniform (no dynamic loop bound in the shader).
    function fbm(p, octaves) {
      var val = TSL.float(0.0).toVar();
      var maxAmp = TSL.float(0.0).toVar();
      var amp = 1.0;
      var freq = 1.0;
      for (var i = 0; i < 6; i++) {
        var act = TSL.step(TSL.float(i + 0.5), octaves);
        val.addAssign(noise2D(p.mul(freq)).mul(amp).mul(act));
        maxAmp.addAssign(act.mul(amp));
        amp *= 0.5;
        freq *= 2.0;
      }

      return val.div(maxAmp);
    }

    // -- Projection (exact port of the wave.js oblique-orthographic math) -----
    var project = TSL.Fn(function ([cr, h]) {
      var wx = cr.x.sub(u.halfC).mul(u.sp);
      var wy = cr.y.sub(u.halfR).mul(u.sp);
      var rwx = wx.mul(u.cosR).sub(wy.mul(u.sinR));
      var rwy = wx.mul(u.sinR).add(wy.mul(u.cosR));
      return TSL.vec2(
        u.ox.add(rwx.mul(u.zoom)),
        u.oy.add(rwy.mul(u.pitch).mul(u.zoom)).sub(h.mul(u.zoom))
      );
    });

    function falloffNode(dist) {
      var t = dist.div(u.cursorRadius).min(1.0);
      var linear = TSL.float(1.0).sub(t);
      var sharp = TSL.pow(linear, 3.0);
      var smoothF = TSL.cos(t.mul(Math.PI)).add(1.0).mul(0.5);
      return TSL.select(
        u.cursorFalloff.equal(1.0), linear,
        TSL.select(u.cursorFalloff.equal(2.0), sharp, smoothF)
      );
    }

    // Final height at grid point (c, r): FBM + turbulence, then the cursor
    // force measured against the screen position projected with the
    // PRE-cursor height — exactly like wave.js.
    var finalHeight = TSL.Fn(function ([cr]) {
      var h0 = fbm(
        TSL.vec2(
          cr.x.mul(u.frequency).add(u.time.mul(0.3)),
          cr.y.mul(u.frequency).add(u.time.mul(0.15))
        ),
        u.octaves
      ).mul(u.amplitude).toVar();
      h0.addAssign(
        TSL.abs(noise2D(TSL.vec2(
          cr.x.mul(u.frequency).mul(2.7).add(u.time.mul(0.4)),
          cr.y.mul(u.frequency).mul(2.7)
        ))).mul(u.amplitude).mul(u.turbulence)
      );

      var pos = project(cr, h0);
      var dx = pos.x.sub(u.mouseX).toVar();
      var dy = pos.y.sub(u.mouseY).toVar();
      var dist = TSL.sqrt(dx.mul(dx).add(dy.mul(dy))).toVar();
      var fall = falloffNode(dist).toVar();
      var f = fall.mul(u.cursorStrength).toVar();
      var dxSafe = TSL.select(dist.lessThan(1e-6), TSL.float(1e-6), dx);
      var angle = TSL.atan(dy, dxSafe).add(Math.PI * 0.5);
      var hMode = TSL.select(
        u.cursorMode.equal(0.0), h0.add(f.mul(0.8)),
        TSL.select(
          u.cursorMode.equal(1.0), h0.sub(f.mul(0.8)),
          TSL.select(
            u.cursorMode.equal(2.0),
            h0.add(TSL.sin(angle.mul(3.0).add(u.timeRaw.mul(2.0))).mul(f).mul(0.5)),
            h0.mul(TSL.float(1.0).sub(fall))
          )
        )
      );
      var inside = u.mouseActive.mul(TSL.float(1.0).sub(TSL.step(u.cursorRadius, dist)));
      return TSL.mix(h0, hMode, inside);
    });

    // -- Height lookup ---------------------------------------------------------
    // WebGPU backend: a per-frame compute pass evaluates finalHeight exactly
    // once per grid point into a storage buffer and the materials fetch from
    // it (the vertex stages previously re-ran the full FBM + cursor chain up
    // to ~144x per grid point). The WebGL2 fallback backend keeps the inline
    // per-vertex evaluation (compute emulation there is unverified).
    gpuCompute = !!(renderer && renderer.backend && renderer.backend.isWebGPUBackend);
    var heightAt;
    if (gpuCompute) {
      heightsBuf = TSL.instancedArray(MAX_GRID_POINTS + 64, "float");
      heightsComputeFn = TSL.Fn(function () {
        var idx = TSL.float(TSL.instanceIndex).toVar();
        var gr = TSL.floor(idx.div(u.cols)).toVar();
        var gc = idx.sub(gr.mul(u.cols)).toVar();
        heightsBuf.element(TSL.instanceIndex).assign(finalHeight(TSL.vec2(gc, gr)));
      });
      heightAt = function (cNode, rNode) {
        // Clamped fetch: every use of an out-of-range neighbor height is
        // already discarded downstream (facingOrInvalid forces facing -1 /
        // the spline selects drop it), exactly as wave.js never evaluates
        // heights outside the grid — so clamping never changes a drawn pixel.
        var ci = TSL.clamp(cNode, 0.0, u.cols.sub(1.0));
        var ri = TSL.clamp(rNode, 0.0, u.rows.sub(1.0));
        return heightsBuf.element(ri.mul(u.cols).add(ci).toUint()).toVar();
      };
    } else {
      heightAt = function (cNode, rNode) {
        return finalHeight(TSL.vec2(cNode, rNode)).toVar();
      };
    }

    // Screen-space winding of quad (qc, qr) from the heights of three of its
    // corners. px deltas are height-independent, so they are uniforms.
    function facingNode(h00, h10, h01) {
      var ey1 = u.colDY.sub(h10.sub(h00).mul(u.zoom));
      var ey2 = u.rowDY.sub(h01.sub(h00).mul(u.zoom));
      return u.colDX.mul(ey2).sub(ey1.mul(u.rowDX));
    }

    // Out-of-range neighbor quads count as facing -1 (like wave.js).
    function facingOrInvalid(qc, qr, facing) {
      var valid = qc.greaterThanEqual(0.0)
        .and(qc.lessThanEqual(u.cols.sub(2.0)))
        .and(qr.greaterThanEqual(0.0))
        .and(qr.lessThanEqual(u.rows.sub(2.0)));
      return TSL.select(valid, facing, TSL.float(-1.0));
    }

    function fadeAlpha(baseOpacity, depthT) {
      var df = TSL.float(0.15).add(TSL.float(0.85).mul(TSL.float(1.0).sub(depthT.mul(0.7))));
      return baseOpacity.mul(TSL.select(u.depthFade.greaterThan(0.5), df, TSL.float(1.0)));
    }

    function lineRGB(heightAtStart) {
      var hNorm = TSL.abs(heightAtStart).div(u.ampGuard).min(1.0);
      var bright = TSL.float(0.4).add(hNorm.mul(0.6));
      return TSL.select(
        u.heightColor.greaterThan(0.5),
        TSL.round(u.lineColor.mul(bright)),
        u.lineColor
      ).div(255.0);
    }

    function mid(a, b) {
      return a.add(b).mul(0.5);
    }

    function makeBaseMaterial() {
      var m = new THREE.MeshBasicNodeMaterial();
      m.transparent = true;
      m.depthTest = true;
      m.depthWrite = true;
      m.side = THREE.DoubleSide;
      // Premultiplied source-over — identical compositing to Canvas 2D rgba().
      m.blending = THREE.CustomBlending;
      m.blendEquation = THREE.AddEquation;
      m.blendSrc = THREE.OneFactor;
      m.blendDst = THREE.OneMinusSrcAlphaFactor;
      m.blendSrcAlpha = THREE.OneFactor;
      m.blendDstAlpha = THREE.OneMinusSrcAlphaFactor;
      m.fog = false;
      return m;
    }

    var cullOff = u.cull.lessThan(0.5);
    var CLIPPED_Z = TSL.float(1000.0); // beyond the near plane -> primitive clipped

    // Each material computes position AND color in one vertex-stage Fn (so the
    // expensive height samples are evaluated once); the color leaves the
    // vertex stage through a varyingProperty.

    // ------------------------------- Fill mesh -------------------------------
    fillMat = makeBaseMaterial();
    (function () {
      var vColor = TSL.varyingProperty("vec4", "vWaveFill");
      fillMat.positionNode = TSL.Fn(function () {
        var cr = TSL.positionGeometry.xy;
        var quad = TSL.attribute("aQuad", "vec2");
        var qc = quad.x;
        var qr = quad.y;
        var h00 = heightAt(qc, qr);
        var h10 = heightAt(qc.add(1.0), qr);
        var h01 = heightAt(qc, qr.add(1.0));
        var hOwn = heightAt(cr.x, cr.y);
        var facing = facingNode(h00, h10, h01).toVar();
        var visible = facing.greaterThan(0.0).or(cullOff); // fill culls at <= 0
        var pos = project(cr, hOwn);
        var z = cr.y.mul(u.invRowsM1);

        // Per-quad lambert + specular against world-space edges (z = height axis)
        var e1z = h10.sub(h00);
        var e2z = h01.sub(h00);
        var nx = e1z.mul(u.sp).negate().toVar();
        var ny = e2z.mul(u.sp).negate().toVar();
        var nz = u.sp.mul(u.sp).toVar();
        var nLen = TSL.sqrt(nx.mul(nx).add(ny.mul(ny)).add(nz.mul(nz))).max(1e-9).toVar();
        nx.divAssign(nLen);
        ny.divAssign(nLen);
        nz.divAssign(nLen);
        var ld = u.lightDir;
        var dotLN = ld.x.mul(nx).add(ld.y.mul(ny)).add(ld.z.mul(nz)).toVar();
        var diff = dotLN.negate().max(0.0);
        var refZ = ld.z.sub(dotLN.mul(2.0).mul(nz));
        var spec = TSL.pow(refZ.negate().max(0.0), u.lightSpecPower);
        var shade = u.lightAmbient
          .add(u.lightDiffuse.mul(diff))
          .add(u.lightSpecular.mul(spec))
          .min(1.5);
        var lit = TSL.round(u.fillColor.mul(shade).min(255.0));
        var rgb = TSL.select(u.lightEnabled.greaterThan(0.5), lit, u.fillColor).div(255.0);
        var alpha = fadeAlpha(u.fillOpacity, qr.mul(u.invRowsM1));
        vColor.assign(TSL.vec4(rgb, alpha));

        return TSL.vec3(pos, TSL.select(visible, z, CLIPPED_Z));
      })();
      fillMat.colorNode = vColor.rgb.mul(vColor.a);
      fillMat.opacityNode = vColor.a;
    })();

    // ------------------------------- Row lines -------------------------------
    // Reproduces the canvas quadratic-midpoint spline: each piece (segment
    // c-1 -> c of row r) is a ribbon along the Bezier with control points
    // chosen exactly as ctx.quadraticCurveTo did, including path breaks from
    // backface culling and the per-segment restarts of heightColor mode.
    rowMat = makeBaseMaterial();
    (function () {
      var vColor = TSL.varyingProperty("vec4", "vWaveRow");
      rowMat.positionNode = TSL.Fn(function () {
        var pg = TSL.positionGeometry;
        var c = pg.x;
        var r = pg.y;
        var side = pg.z;
        var t = TSL.attribute("aT", "float");

        // Heights for rows r-1..r+1 x cols c-2..c+1 (11 points cover the three
        // segment-visibility tests and the three spline points).
        function hAt(dc, dr) {
          return heightAt(c.add(dc), r.add(dr));
        }

        var hm1m2 = hAt(-2, -1);
        var hm1m1 = hAt(-1, -1);
        var hm1c0 = hAt(0, -1);
        var hm1p1 = hAt(1, -1);
        var h0m2 = hAt(-2, 0);
        var h0m1 = hAt(-1, 0);
        var h0c0 = hAt(0, 0);
        var h0p1 = hAt(1, 0);
        var hp1m2 = hAt(-2, 1);
        var hp1m1 = hAt(-1, 1);
        var hp1c0 = hAt(0, 1);

        function quadF(qcOff, h00, h10, h01, qrOff) {
          return facingOrInvalid(
            c.add(qcOff),
            r.add(qrOff),
            facingNode(h00, h10, h01)
          ).toVar();
        }

        // Quads above (qr = r-1) and below (qr = r) for segments c-1, c, c+1
        var fAbovePrev = quadF(-2, hm1m2, hm1m1, h0m2, -1);
        var fBelowPrev = quadF(-2, h0m2, h0m1, hp1m2, 0);
        var fAboveThis = quadF(-1, hm1m1, hm1c0, h0m1, -1);
        var fBelowThis = quadF(-1, h0m1, h0c0, hp1m1, 0);
        var fAboveNext = quadF(0, hm1c0, hm1p1, h0c0, -1);
        var fBelowNext = quadF(0, h0c0, h0p1, hp1c0, 0);

        var visPrev = fAbovePrev.greaterThanEqual(0.0).or(fBelowPrev.greaterThanEqual(0.0)).or(cullOff);
        var visThis = fAboveThis.greaterThanEqual(0.0).or(fBelowThis.greaterThanEqual(0.0)).or(cullOff);
        var visNext = fAboveNext.greaterThanEqual(0.0).or(fBelowNext.greaterThanEqual(0.0)).or(cullOff);

        var Pa = project(TSL.vec2(c.sub(1.0), r), h0m1).toVar();
        var Pb = project(TSL.vec2(c, r), h0c0).toVar();
        var Pc = project(TSL.vec2(c.add(1.0), r), h0p1).toVar();

        // Path-start point: P[c-1] when the canvas path had just (re)started
        // (first segment, previous segment culled, or heightColor restarting
        // per segment); otherwise the midpoint the previous curve ended at.
        var fresh = c.lessThan(1.5).or(u.heightColor.greaterThan(0.5));
        var A = TSL.select(fresh, Pa, TSL.select(visPrev, mid(Pa, Pb), Pa)).toVar();

        var isLast = c.greaterThan(u.cols.sub(1.5));
        // last column: ctrl = midpoint, end = P[c] | next visible: ctrl = P[c],
        // end = midpoint | next culled: straight lineTo(P[c])
        var B = TSL.select(isLast, mid(Pa, Pb), TSL.select(visNext, Pb, mid(A, Pb))).toVar();
        var C = TSL.select(isLast, Pb, TSL.select(visNext, mid(Pb, Pc), Pb)).toVar();

        var omt = TSL.float(1.0).sub(t).toVar();
        var S = A.mul(omt.mul(omt))
          .add(B.mul(omt.mul(t).mul(2.0)))
          .add(C.mul(t.mul(t)));
        var tan = B.sub(A).mul(omt).add(C.sub(B).mul(t)).toVar();
        var tanLen = TSL.sqrt(tan.x.mul(tan.x).add(tan.y.mul(tan.y))).max(1e-6);
        var nrm = TSL.vec2(tan.y.negate(), tan.x).div(tanLen);
        var P = S.add(nrm.mul(side).mul(u.lineWidth).mul(0.5));
        var z = r.mul(u.invRowsM1).add(u.lineZBias);

        var alpha = fadeAlpha(u.lineOpacity, r.mul(u.invRowsM1));
        vColor.assign(TSL.vec4(lineRGB(h0m1), alpha)); // brightness from P[c-1], like heights[pidx]

        return TSL.vec3(P, TSL.select(visThis, z, CLIPPED_Z));
      })();
      rowMat.colorNode = vColor.rgb.mul(vColor.a);
      rowMat.opacityNode = vColor.a;
    })();

    // ------------------------------ Column lines -----------------------------
    colMat = makeBaseMaterial();
    (function () {
      var vColor = TSL.varyingProperty("vec4", "vWaveCol");
      colMat.positionNode = TSL.Fn(function () {
        var pg = TSL.positionGeometry;
        var c = pg.x;
        var r0 = pg.y;
        var side = pg.z;
        var e = TSL.attribute("aT", "float"); // 0 = top endpoint (r0), 1 = bottom (r0+1)

        var hLT = heightAt(c.sub(1.0), r0);
        var hCT = heightAt(c, r0);
        var hRT = heightAt(c.add(1.0), r0);
        var hLB = heightAt(c.sub(1.0), r0.add(1.0));
        var hCB = heightAt(c, r0.add(1.0));

        var fLeft = facingOrInvalid(c.sub(1.0), r0, facingNode(hLT, hCT, hLB)).toVar();
        var fRight = facingOrInvalid(c, r0, facingNode(hCT, hRT, hCB)).toVar();
        var visible = fLeft.greaterThanEqual(0.0).or(fRight.greaterThanEqual(0.0)).or(cullOff);

        var P0 = project(TSL.vec2(c, r0), hCT).toVar();
        var P1 = project(TSL.vec2(c, r0.add(1.0)), hCB).toVar();
        var dir = P1.sub(P0).toVar();
        var dirLen = TSL.sqrt(dir.x.mul(dir.x).add(dir.y.mul(dir.y))).max(1e-6);
        var nrm = TSL.vec2(dir.y.negate(), dir.x).div(dirLen);
        var P = TSL.mix(P0, P1, e).add(nrm.mul(side).mul(u.lineWidth).mul(0.5));
        var z = r0.add(e).mul(u.invRowsM1).add(u.lineZBias);

        // Drawn during iteration r = r0+1 in the canvas loop -> that row's fade.
        var alpha = fadeAlpha(u.lineOpacity, r0.add(1.0).mul(u.invRowsM1));
        vColor.assign(TSL.vec4(lineRGB(hCT), alpha)); // brightness from upper vertex

        return TSL.vec3(P, TSL.select(visible, z, CLIPPED_Z));
      })();
      colMat.colorNode = vColor.rgb.mul(vColor.a);
      colMat.opacityNode = vColor.a;
    })();
  }

  function buildScene() {
    scene = new THREE.Scene();
    // Screen-space ortho camera: world units == CSS px, +y down, z = row depth
    // (0 far .. 1 near) so the depth buffer enforces the painter's row order.
    camera = new THREE.OrthographicCamera(0, 1, 0, 1, 0.1, 10);
    camera.position.z = 2;

    uniforms = createUniforms();
    buildMaterials();

    fillMesh = new THREE.Mesh(new THREE.BufferGeometry(), fillMat);
    rowMesh = new THREE.Mesh(new THREE.BufferGeometry(), rowMat);
    colMesh = new THREE.Mesh(new THREE.BufferGeometry(), colMat);
    fillMesh.renderOrder = 0;
    rowMesh.renderOrder = 1;
    colMesh.renderOrder = 2;
    fillMesh.frustumCulled = false;
    rowMesh.frustumCulled = false;
    colMesh.frustumCulled = false;
    scene.add(fillMesh);
    scene.add(rowMesh);
    scene.add(colMesh);
    rebuildGeometries();
  }

  // ---------------------------------------------------------------------------
  // Canvas / Renderer Setup
  // ---------------------------------------------------------------------------

  function styleCanvas(el) {
    el.style.cssText =
      "position:absolute;bottom:0;left:0;width:100%;height:45%;z-index:5;pointer-events:auto;" +
      "-webkit-mask-image:linear-gradient(to bottom, transparent 0%, black 30%);" +
      "mask-image:linear-gradient(to bottom, transparent 0%, black 30%);";
  }

  function resizeCanvas() {
    if (!canvas || !renderer) return;
    W = canvas.offsetWidth;
    H = canvas.offsetHeight;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(W, H, false);
    camera.left = 0;
    camera.right = W;
    camera.top = 0;
    camera.bottom = H;
    camera.updateProjectionMatrix();
  }

  // ---------------------------------------------------------------------------
  // Mouse Handling (identical to wave.js — instantaneous, no smoothing)
  // ---------------------------------------------------------------------------

  function onMouseMove(e) {
    if (!canvas) return;
    var rect = canvas.getBoundingClientRect();
    mouse.x = e.clientX - rect.left;
    mouse.y = e.clientY - rect.top;
    mouse.active = true;
  }

  function onMouseLeave() {
    mouse.active = false;
  }

  function attachMouseListeners() {
    if (!canvas) return;
    boundMouseMove = onMouseMove;
    boundMouseLeave = onMouseLeave;
    canvas.addEventListener("mousemove", boundMouseMove);
    canvas.addEventListener("mouseleave", boundMouseLeave);
  }

  function detachMouseListeners() {
    if (!canvas) return;
    if (boundMouseMove) canvas.removeEventListener("mousemove", boundMouseMove);
    if (boundMouseLeave) canvas.removeEventListener("mouseleave", boundMouseLeave);
    boundMouseMove = null;
    boundMouseLeave = null;
  }

  // ---------------------------------------------------------------------------
  // Per-frame Uniform Sync (cfg is the single source of truth, like wave.js)
  // ---------------------------------------------------------------------------

  function syncUniforms() {
    var u = uniforms;
    var cols = cfg.gridCols;
    var rows = cfg.gridRows;
    var sp = cfg.gridSpacing;
    var zoom = cfg.cameraZoom;
    var pitch = cfg.cameraPitch;
    var rot = cfg.cameraRotation * (Math.PI / 180);
    var cosR = Math.cos(rot);
    var sinR = Math.sin(rot);

    u.time.value = time % TIME_WRAP;
    u.timeRaw.value = time;
    u.ox.value = W / 2 + cfg.cameraPosX;
    u.oy.value = H / 2 + cfg.cameraHeight + cfg.cameraPosY;
    u.zoom.value = zoom;
    u.pitch.value = pitch;
    u.cosR.value = cosR;
    u.sinR.value = sinR;
    u.sp.value = sp;
    u.halfC.value = (cols - 1) / 2;
    u.halfR.value = (rows - 1) / 2;
    u.cols.value = cols;
    u.rows.value = rows;
    u.invRowsM1.value = 1 / Math.max(rows - 1, 1);
    u.lineZBias.value = 0.3 / Math.max(rows - 1, 1);
    u.colDX.value = sp * cosR * zoom;
    u.colDY.value = sp * sinR * pitch * zoom;
    u.rowDX.value = -sp * sinR * zoom;
    u.rowDY.value = sp * cosR * pitch * zoom;
    u.amplitude.value = cfg.amplitude;
    u.ampGuard.value = cfg.amplitude || 1;
    u.frequency.value = cfg.frequency;
    u.octaves.value = cfg.octaves;
    u.turbulence.value = cfg.turbulence;
    u.mouseX.value = mouse.x;
    u.mouseY.value = mouse.y;
    u.mouseActive.value = mouse.active ? 1 : 0;
    u.cursorStrength.value = cfg.cursorStrength;
    u.cursorRadius.value = cfg.cursorRadius;
    u.cursorMode.value = CURSOR_MODE_INDEX[cfg.cursorMode] || 0;
    u.cursorFalloff.value = CURSOR_FALLOFF_INDEX[cfg.cursorFalloff] || 0;

    if (cfg.lineColor !== lastLineHex) {
      var lc = hexToRGB(cfg.lineColor);
      u.lineColor.value.set(lc[0], lc[1], lc[2]);
      lastLineHex = cfg.lineColor;
    }

    if (cfg.fillColor !== lastFillHex) {
      var fc = hexToRGB(cfg.fillColor);
      u.fillColor.value.set(fc[0], fc[1], fc[2]);
      lastFillHex = cfg.fillColor;
    }

    u.lineWidth.value = cfg.lineWidth;
    u.lineOpacity.value = cfg.lineOpacity;
    u.fillOpacity.value = cfg.fillOpacity;
    u.depthFade.value = cfg.depthFade ? 1 : 0;
    u.heightColor.value = cfg.heightColor ? 1 : 0;
    u.cull.value = cfg.backfaceCull ? 1 : 0;

    u.lightEnabled.value = cfg.lightEnabled ? 1 : 0;
    var ldx = cfg.lightDirX;
    var ldy = cfg.lightDirY;
    var ldz = cfg.lightDirZ;
    var ldLen = Math.sqrt(ldx * ldx + ldy * ldy + ldz * ldz) || 1;
    u.lightDir.value.set(ldx / ldLen, ldy / ldLen, ldz / ldLen);
    u.lightAmbient.value = cfg.lightAmbient;
    u.lightDiffuse.value = cfg.lightDiffuse;
    u.lightSpecular.value = cfg.lightSpecular;
    u.lightSpecPower.value = cfg.lightSpecPower;

    fillMesh.visible = !!cfg.showFill;
    rowMesh.visible = !!cfg.showRows;
    colMesh.visible = !!cfg.showCols;
  }

  // ---------------------------------------------------------------------------
  // Animation Loop
  // ---------------------------------------------------------------------------

  function syncDebugPanel() {
    var si, ti, ci, xi, entry, current;
    for (si = 0; si < sliderRegistry.length; si++) {
      entry = sliderRegistry[si];
      current = entry.getter();
      if (current !== null && current !== undefined) {
        entry.inp.value = current;
        entry.val.textContent = Number(current).toFixed(2);
      }
    }

    for (ti = 0; ti < toggleRegistry.length; ti++) {
      entry = toggleRegistry[ti];
      current = entry.getter();
      if (current !== null && current !== undefined) {
        entry.checkbox.checked = current;
      }
    }

    for (ci = 0; ci < colorRegistry.length; ci++) {
      entry = colorRegistry[ci];
      current = entry.getter();
      if (current !== null && current !== undefined) {
        entry.input.value = current;
      }
    }

    for (xi = 0; xi < selectRegistry.length; xi++) {
      entry = selectRegistry[xi];
      current = entry.getter();
      if (current !== null && current !== undefined) {
        entry.select.value = current;
      }
    }
  }

  function render(now) {
    if (!renderer) return;

    var dt = (now - lastTime) / 1000;
    if (dt > 0.1) dt = 0.1; // clamp large gaps
    lastTime = now;
    time += dt * cfg.speed;

    // FPS
    frameCount++;
    fpsTimer += dt;
    if (fpsTimer >= 0.5) {
      fpsVal = Math.round(frameCount / fpsTimer);
      frameCount = 0;
      fpsTimer = 0;
    }

    // Geometry only depends on cols/rows (attributes are grid indices) —
    // rebuild lazily when the debug panel / responsive overrides change them.
    if (cfg.gridCols !== builtCols || cfg.gridRows !== builtRows) {
      rebuildGeometries();
    }

    syncUniforms();

    // Height-field compute pass (WebGPU backend): one finalHeight per grid
    // point per frame; the dispatch is rebuilt only when the grid changes.
    if (gpuCompute) {
      if (!heightsCompute || computeBuiltCols !== cfg.gridCols || computeBuiltRows !== cfg.gridRows) {
        heightsCompute = heightsComputeFn().compute(
          Math.min(cfg.gridCols * cfg.gridRows, MAX_GRID_POINTS)
        );
        computeBuiltCols = cfg.gridCols;
        computeBuiltRows = cfg.gridRows;
      }

      renderer.compute(heightsCompute);
    }

    renderer.render(scene, camera);

    // Stats (same formulas as wave.js — counts ignore culling); skipped while
    // the panel is hidden so the rAF loop never touches the DOM in that case.
    if (debugPanel && debugPanel.style.display !== "none") {
      var lineCount = 0;
      if (cfg.showRows) lineCount += cfg.gridRows * (cfg.gridCols - 1);
      if (cfg.showCols) lineCount += (cfg.gridRows - 1) * cfg.gridCols;
      if (statsFpsEl) statsFpsEl.textContent = fpsVal;
      if (statsPointsEl) statsPointsEl.textContent = (cfg.gridCols * cfg.gridRows).toLocaleString();
      if (statsLinesEl) statsLinesEl.textContent = lineCount.toLocaleString();
    }
  }

  function animate(now) {
    animFrameId = requestAnimationFrame(animate);

    render(now);

    if (debugPanel && debugPanel.style.display !== "none") {
      syncDebugPanel();
    }
  }

  // ---------------------------------------------------------------------------
  // Event Handlers
  // ---------------------------------------------------------------------------

  function onResize() {
    // Debounced: renderer.setSize reallocates the swapchain + MSAA targets,
    // which is far too heavy to run on every event of a window drag. The
    // canvas keeps its CSS size meanwhile; only the backing store lags.
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      resizeTimer = null;
      resizeCanvas();
      cfg.gridCols = responsiveCols();
      cfg.cameraZoom = responsiveZoom();
      cfg.cameraHeight = responsiveHeight();
    }, 150);
  }

  // ---------------------------------------------------------------------------
  // Debug Panel Sections (identical to wave.js, plus Renderer toggle)
  // ---------------------------------------------------------------------------

  function appendRendererSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Renderer", false, function (body) {
      var info = document.createElement("div");
      var backendName = "three.js";
      try {
        backendName = renderer && renderer.backend && renderer.backend.isWebGPUBackend ?
          "three.js / WebGPU" : "three.js / WebGL2";
      } catch (e) {}
      info.textContent = "Active: " + backendName;
      info.style.cssText = "font-size:10px;color:rgba(255,255,255,0.35);margin:3px 0;";
      body.appendChild(info);
      body.appendChild(makeToggle("three.js (GPU)", true, function (v) {
        try {
          window.localStorage.setItem("NSC_WAVE_USE_THREE", v ? "1" : "0");
        } catch (e) {}
        window.location.reload();
      }, function () { return useThreeWave(); }));
    }));
  }

  function appendPresetsSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Presets", false, function (body) {
      var names = ["terrain", "ocean", "pulse", "chaos", "calm", "reset"];
      var row = document.createElement("div");
      row.style.cssText = "display:flex;flex-wrap:wrap;gap:4px;margin:4px 0;";
      for (var i = 0; i < names.length; i++) {
        (function (name) {
          var btn = document.createElement("button");
          btn.textContent = name.charAt(0).toUpperCase() + name.slice(1);
          btn.style.cssText =
            "background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);" +
            "color:rgba(255,255,255,0.6);font:9px monospace;padding:4px 8px;border-radius:4px;" +
            "cursor:pointer;";
          if (name === "reset") {
            btn.style.background = "rgba(255,80,80,0.08)";
            btn.style.borderColor = "rgba(255,80,80,0.15)";
            btn.style.color = "rgba(255,120,120,0.7)";
          }

          btn.addEventListener("click", function () {
            applyPreset(name);
          });
          row.appendChild(btn);
        })(names[i]);
      }

      body.appendChild(row);
    }));
  }

  function appendGridSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Grid", true, function (body) {
      body.appendChild(makeSlider("Columns", 20, 200, 1, cfg.gridCols, function (v) { cfg.gridCols = v; }, function () { return cfg.gridCols; }));
      body.appendChild(makeSlider("Rows", 15, 120, 1, cfg.gridRows, function (v) { cfg.gridRows = v; }, function () { return cfg.gridRows; }));
      body.appendChild(makeSlider("Spacing", 4, 30, 1, cfg.gridSpacing, function (v) { cfg.gridSpacing = v; }, function () { return cfg.gridSpacing; }));
    }));
  }

  function appendWaveSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Wave Pattern", false, function (body) {
      body.appendChild(makeSlider("Amplitude", 0, 120, 1, cfg.amplitude, function (v) { cfg.amplitude = v; }, function () { return cfg.amplitude; }));
      body.appendChild(makeSlider("Frequency", 0.005, 0.08, 0.001, cfg.frequency, function (v) { cfg.frequency = v; }, function () { return cfg.frequency; }));
      body.appendChild(makeSlider("Speed", 0, 5, 0.1, cfg.speed, function (v) { cfg.speed = v; }, function () { return cfg.speed; }));
      body.appendChild(makeSlider("Wave Layers", 1, 6, 1, cfg.octaves, function (v) { cfg.octaves = v; }, function () { return cfg.octaves; }));
      body.appendChild(makeSlider("Turbulence", 0, 1, 0.05, cfg.turbulence, function (v) { cfg.turbulence = v; }, function () { return cfg.turbulence; }));
    }));
  }

  function appendCursorSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Cursor Force", true, function (body) {
      body.appendChild(makeSlider("Strength", 0, 300, 5, cfg.cursorStrength, function (v) { cfg.cursorStrength = v; }, function () { return cfg.cursorStrength; }));
      body.appendChild(makeSlider("Radius", 30, 400, 5, cfg.cursorRadius, function (v) { cfg.cursorRadius = v; }, function () { return cfg.cursorRadius; }));
      body.appendChild(makeSelect("Mode", [
        { value: "pull", label: "Pull (Attract)" },
        { value: "push", label: "Push (Repel)" },
        { value: "swirl", label: "Swirl (Vortex)" },
        { value: "flatten", label: "Flatten" }
      ], cfg.cursorMode, function (v) { cfg.cursorMode = v; }, function () { return cfg.cursorMode; }));
      body.appendChild(makeSelect("Falloff", [
        { value: "smooth", label: "Smooth" },
        { value: "linear", label: "Linear" },
        { value: "sharp", label: "Sharp" }
      ], cfg.cursorFalloff, function (v) { cfg.cursorFalloff = v; }, function () { return cfg.cursorFalloff; }));
    }));
  }

  function appendCameraSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Camera", true, function (body) {
      body.appendChild(makeSlider("Pos X", -500, 500, 5, cfg.cameraPosX, function (v) { cfg.cameraPosX = v; }, function () { return cfg.cameraPosX; }));
      body.appendChild(makeSlider("Pos Y", -500, 500, 5, cfg.cameraPosY, function (v) { cfg.cameraPosY = v; }, function () { return cfg.cameraPosY; }));
      body.appendChild(makeSlider("Rotation", -180, 180, 1, cfg.cameraRotation, function (v) { cfg.cameraRotation = v; }, function () { return cfg.cameraRotation; }));
      body.appendChild(makeSlider("Pitch", 0.01, 1.2, 0.01, cfg.cameraPitch, function (v) { cfg.cameraPitch = v; }, function () { return cfg.cameraPitch; }));
      body.appendChild(makeSlider("Height", -200, 400, 5, cfg.cameraHeight, function (v) { cfg.cameraHeight = v; }, function () { return cfg.cameraHeight; }));
      body.appendChild(makeSlider("Zoom", 0.3, 5, 0.05, cfg.cameraZoom, function (v) { cfg.cameraZoom = v; }, function () { return cfg.cameraZoom; }));
    }));
  }

  function appendAppearanceSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Appearance", false, function (body) {
      body.appendChild(makeToggle("Show Fill", cfg.showFill, function (v) { cfg.showFill = v; }, function () { return cfg.showFill; }));
      body.appendChild(makeColorPicker("Fill Color", cfg.fillColor, function (v) { cfg.fillColor = v; }, function () { return cfg.fillColor; }));
      body.appendChild(makeSlider("Fill Opacity", 0, 1, 0.01, cfg.fillOpacity, function (v) { cfg.fillOpacity = v; }, function () { return cfg.fillOpacity; }));
      body.appendChild(makeColorPicker("Line Color", cfg.lineColor, function (v) { cfg.lineColor = v; }, function () { return cfg.lineColor; }));
      body.appendChild(makeSlider("Line Width", 0.2, 3, 0.1, cfg.lineWidth, function (v) { cfg.lineWidth = v; }, function () { return cfg.lineWidth; }));
      body.appendChild(makeSlider("Opacity", 0.05, 1, 0.05, cfg.lineOpacity, function (v) { cfg.lineOpacity = v; }, function () { return cfg.lineOpacity; }));
      body.appendChild(makeToggle("Show Rows", cfg.showRows, function (v) { cfg.showRows = v; }, function () { return cfg.showRows; }));
      body.appendChild(makeToggle("Show Cols", cfg.showCols, function (v) { cfg.showCols = v; }, function () { return cfg.showCols; }));
      body.appendChild(makeToggle("Depth Fade", cfg.depthFade, function (v) { cfg.depthFade = v; }, function () { return cfg.depthFade; }));
      body.appendChild(makeToggle("Height Brightness", cfg.heightColor, function (v) { cfg.heightColor = v; }, function () { return cfg.heightColor; }));
      body.appendChild(makeToggle("Backface Cull", cfg.backfaceCull, function (v) { cfg.backfaceCull = v; }, function () { return cfg.backfaceCull; }));
    }));
  }

  function appendLightingSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Lighting", false, function (body) {
      body.appendChild(makeToggle("Enabled", cfg.lightEnabled, function (v) { cfg.lightEnabled = v; }, function () { return cfg.lightEnabled; }));
      body.appendChild(makeSlider("Dir X", -1, 1, 0.05, cfg.lightDirX, function (v) { cfg.lightDirX = v; }, function () { return cfg.lightDirX; }));
      body.appendChild(makeSlider("Dir Y", -1, 1, 0.05, cfg.lightDirY, function (v) { cfg.lightDirY = v; }, function () { return cfg.lightDirY; }));
      body.appendChild(makeSlider("Dir Z", -1, 1, 0.05, cfg.lightDirZ, function (v) { cfg.lightDirZ = v; }, function () { return cfg.lightDirZ; }));
      body.appendChild(makeSlider("Ambient", 0, 1, 0.01, cfg.lightAmbient, function (v) { cfg.lightAmbient = v; }, function () { return cfg.lightAmbient; }));
      body.appendChild(makeSlider("Diffuse", 0, 2, 0.05, cfg.lightDiffuse, function (v) { cfg.lightDiffuse = v; }, function () { return cfg.lightDiffuse; }));
      body.appendChild(makeSlider("Specular", 0, 2, 0.05, cfg.lightSpecular, function (v) { cfg.lightSpecular = v; }, function () { return cfg.lightSpecular; }));
      body.appendChild(makeSlider("Spec Power", 1, 64, 1, cfg.lightSpecPower, function (v) { cfg.lightSpecPower = v; }, function () { return cfg.lightSpecPower; }));
    }));
  }

  function appendStatsSection(panel) {
    panel.appendChild(makeHeading("Stats"));
    var row = document.createElement("div");
    row.style.cssText = "display:flex;justify-content:space-between;font-size:10px;color:rgba(255,255,255,0.35);padding-top:4px;";

    var fpsSpan = document.createElement("span");
    fpsSpan.textContent = "FPS: ";
    statsFpsEl = document.createElement("span");
    statsFpsEl.textContent = "--";
    fpsSpan.appendChild(statsFpsEl);

    var ptsSpan = document.createElement("span");
    ptsSpan.textContent = "Pts: ";
    statsPointsEl = document.createElement("span");
    statsPointsEl.textContent = "--";
    ptsSpan.appendChild(statsPointsEl);

    var lnsSpan = document.createElement("span");
    lnsSpan.textContent = "Lines: ";
    statsLinesEl = document.createElement("span");
    statsLinesEl.textContent = "--";
    lnsSpan.appendChild(statsLinesEl);

    row.appendChild(fpsSpan);
    row.appendChild(ptsSpan);
    row.appendChild(lnsSpan);
    panel.appendChild(row);
  }

  function appendCaptureSection(panel) {
    var btn = document.createElement("button");
    btn.textContent = "Capture";
    btn.style.cssText =
      "display:block;width:100%;margin-top:8px;padding:6px 0;border:1px solid rgba(255,255,255,0.2);" +
      "border-radius:4px;background:rgba(54,195,220,0.15);color:#36C3DC;font:11px monospace;" +
      "cursor:pointer;";
    btn.addEventListener("click", function () {
      var snapshot = {};
      var keys = Object.keys(cfg);
      for (var i = 0; i < keys.length; i++) {
        snapshot[keys[i]] = cfg[keys[i]];
      }

      // Same prefix as wave.js so console grep/tooling sees both variants.
      console.log("[NSCWave] Capture:", snapshot);
    });
    panel.appendChild(btn);
  }

  // ---------------------------------------------------------------------------
  // Debug Panel (localhost only)
  // ---------------------------------------------------------------------------

  function createDebugPanel() {
    var host = location.hostname;
    var whitelist = window.NSC_DEBUG_WHITELIST_DOMAINS ||
      ["localhost", "127.0.0.1", "dev.nsc-software.com", "nsc.test"];
    if (whitelist.indexOf(host) === -1) return;

    sliderRegistry = [];
    toggleRegistry = [];
    colorRegistry = [];
    selectRegistry = [];

    // Overlay wrapper
    var wrapper = document.createElement("div");
    wrapper.style.cssText =
      "position:fixed;z-index:10001;pointer-events:none;top:0;left:0;width:0;height:0;";
    document.body.appendChild(wrapper);
    debugWrapper = wrapper;

    // Gear toggle button — absolute within the hero section
    var btn = document.createElement("button");
    btn.textContent = "⚙";
    btn.style.cssText =
      "position:absolute;bottom:16px;right:16px;z-index:10;width:32px;height:32px;" +
      "border:none;border-radius:50%;background:rgba(0,0,0,0.5);color:#36C3DC;" +
      "font-size:18px;cursor:pointer;line-height:32px;text-align:center;padding:0;" +
      "pointer-events:auto;";
    sectionEl.appendChild(btn);
    debugGearBtn = btn;

    // Panel
    var panel = document.createElement("div");
    panel.style.cssText =
      "position:fixed;bottom:56px;right:16px;z-index:10000;background:rgba(0,0,0,0.85);" +
      "color:#ccc;font:11px/1.6 monospace;padding:10px 12px;border-radius:6px;" +
      "max-height:70vh;overflow-y:auto;display:none;min-width:220px;pointer-events:auto;";
    wrapper.appendChild(panel);
    debugPanel = panel;

    // Close button
    var closeBtn = document.createElement("button");
    closeBtn.textContent = "×";
    closeBtn.style.cssText =
      "position:sticky;top:0;float:right;z-index:1;width:20px;height:20px;margin:0 0 4px 4px;" +
      "border:none;border-radius:3px;background:rgba(255,255,255,0.1);color:#ccc;" +
      "font-size:14px;line-height:20px;text-align:center;padding:0;cursor:pointer;";
    closeBtn.addEventListener("click", function () {
      panel.style.display = "none";
    });
    panel.appendChild(closeBtn);

    btn.addEventListener("click", function () {
      panel.style.display = panel.style.display === "none" ? "block" : "none";
    });

    // Append all sections
    appendStatsSection(panel);
    appendRendererSection(panel);
    appendPresetsSection(panel);
    appendGridSection(panel);
    appendWaveSection(panel);
    appendCursorSection(panel);
    appendCameraSection(panel);
    appendAppearanceSection(panel);
    appendLightingSection(panel);
    appendCaptureSection(panel);
  }

  // ---------------------------------------------------------------------------
  // Dispose Helpers
  // ---------------------------------------------------------------------------

  function disposeScene() {
    detachMouseListeners();
    // try/catch: three 0.177 can throw when disposing node materials that
    // never produced a successful render (e.g. dispose mid-init failure).
    try {
      if (fillMesh && fillMesh.geometry) fillMesh.geometry.dispose();
      if (rowMesh && rowMesh.geometry) rowMesh.geometry.dispose();
      if (colMesh && colMesh.geometry) colMesh.geometry.dispose();
    } catch (e) {}
    try { if (fillMat) fillMat.dispose(); } catch (e) {}
    try { if (rowMat) rowMat.dispose(); } catch (e) {}
    try { if (colMat) colMat.dispose(); } catch (e) {}
    fillMesh = null;
    rowMesh = null;
    colMesh = null;
    fillMat = null;
    rowMat = null;
    colMat = null;
    uniforms = null;
    scene = null;
    camera = null;
    builtCols = 0;
    builtRows = 0;
    gpuCompute = false;
    heightsBuf = null;
    heightsComputeFn = null;
    heightsCompute = null;
    computeBuiltCols = 0;
    computeBuiltRows = 0;

    if (renderer) {
      try { renderer.dispose(); } catch (e) {}
      renderer = null;
    }

    if (canvas && canvas.parentNode) {
      canvas.parentNode.removeChild(canvas);
    }

    canvas = null;
  }

  function disposeDebugUI() {
    statsFpsEl = null;
    statsPointsEl = null;
    statsLinesEl = null;
    if (debugGearBtn && debugGearBtn.parentNode) {
      debugGearBtn.parentNode.removeChild(debugGearBtn);
    }

    if (debugWrapper) {
      debugWrapper.remove();
      debugWrapper = null;
    }

    debugGearBtn = null;
    debugPanel = null;
    sliderRegistry = [];
    toggleRegistry = [];
    colorRegistry = [];
    selectRegistry = [];
  }

  function disposeTimers() {
    if (animFrameId) {
      cancelAnimationFrame(animFrameId);
      animFrameId = null;
    }

    if (resizeTimer) {
      clearTimeout(resizeTimer);
      resizeTimer = null;
    }

    if (boundResize) {
      window.removeEventListener("resize", boundResize);
      boundResize = null;
    }
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  function dispose() {
    initToken++; // abort any in-flight async init
    if (isDisposed) return;
    isDisposed = true;
    isInitialized = false;

    disposeTimers();
    disposeDebugUI();
    disposeScene();
  }

  function loadModules() {
    if (!modulesPromise) {
      modulesPromise = Promise.all([
        import("three/webgpu"),
        import("three/tsl")
      ]).then(function (mods) {
        THREE = mods[0];
        TSL = mods[1];
      });
    }

    return modulesPromise;
  }

  // WebGPU and WebGL2 both unavailable (or a CDN/compile failure): warn and
  // hand the hero back to the Canvas-2D implementation for this page view.
  function fallbackToCanvas(err) {
    if (fallbackTriggered) return;
    fallbackTriggered = true;
    console.warn("[NSCWaveThree] three.js renderer unavailable, falling back to Canvas 2D wave.", err);
    dispose();
    window.NSC_WAVE_THREE_FAILED = true;
    if (canvasWaveAPI && canvasWaveAPI.instance && canvasWaveAPI.instance.init) {
      window.NSCWave = canvasWaveAPI;
      // Only start the CPU wave if the hero is on screen; from here on the
      // IntersectionObserver in setupLazyLoad() drives its init/dispose.
      if (heroVisible) {
        canvasWaveAPI.instance.init();
      }
    }
  }

  function init() {
    if (isInitialized || fallbackTriggered) return;

    sectionEl = document.querySelector(".hero.home");
    if (!sectionEl) return;

    var token = ++initToken;
    isInitialized = true; // guards re-entry while modules/renderer load
    isDisposed = false;

    loadModules().then(function () {
      if (token !== initToken) return;

      var newRenderer = new THREE.WebGPURenderer({ antialias: true, alpha: true });
      renderer = newRenderer;
      return newRenderer.init().then(function () {
        if (token !== initToken) {
          // Dispose only THIS init's renderer — a newer init may already own
          // the shared `renderer` slot (fast scroll out/in during GPU init).
          try { newRenderer.dispose(); } catch (e) {}
          if (renderer === newRenderer) renderer = null;
          return;
        }

        // Responsive defaults (re-applied here and on every resize)
        cfg.gridCols = responsiveCols();
        cfg.cameraZoom = responsiveZoom();
        cfg.cameraHeight = responsiveHeight();

        // Raw value passthrough: colors are fed as 0-255/255 exactly like the
        // canvas rgba() strings, so skip the linear->sRGB output transform.
        renderer.outputColorSpace = THREE.LinearSRGBColorSpace;
        renderer.toneMapping = THREE.NoToneMapping;
        renderer.setClearColor(0x000000, 0);

        canvas = renderer.domElement;
        styleCanvas(canvas);
        sectionEl.appendChild(canvas);

        buildScene();
        resizeCanvas();
        attachMouseListeners();

        boundResize = onResize;
        window.addEventListener("resize", boundResize);

        time = 0;
        lastTime = performance.now();
        frameCount = 0;
        fpsTimer = 0;

        createDebugPanel();
        animate(performance.now());
      });
    }).catch(function (err) {
      if (token !== initToken) return;
      isInitialized = false;
      fallbackToCanvas(err);
    });
  }

  // ---------------------------------------------------------------------------
  // Lazy Loading with IntersectionObserver (identical behavior to wave.js)
  // ---------------------------------------------------------------------------

  function setupLazyLoad() {
    var section = document.querySelector(".hero.home");
    if (!section) return;

    canvasWaveAPI = window.NSCWave || null;

    // Fallback: no IntersectionObserver support
    if (typeof IntersectionObserver === "undefined") {
      init();
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          heroVisible = entries[i].isIntersecting;
          if (entries[i].isIntersecting) {
            if (fallbackTriggered) {
              // GPU init failed earlier: this observer drives the Canvas-2D
              // wave's lifecycle instead (wave.js never registered its own —
              // its setupLazyLoad bailed at boot while the flag was on).
              // wave.js init()/dispose() are idempotent.
              if (canvasWaveAPI && canvasWaveAPI.instance && canvasWaveAPI.instance.init) {
                canvasWaveAPI.instance.init();
              }
            } else if (!isInitialized) {
              init();
            }
          } else {
            if (fallbackTriggered) {
              if (canvasWaveAPI && canvasWaveAPI.instance && canvasWaveAPI.instance.dispose) {
                canvasWaveAPI.instance.dispose();
              }
            } else if (isInitialized) {
              dispose();
            }
          }
        }
      },
      { rootMargin: "200px" }
    );

    observer.observe(section);
  }

  // ---------------------------------------------------------------------------
  // Boot
  // ---------------------------------------------------------------------------

  function boot() {
    setupLazyLoad();

    // Public API (parity with wave.js) — only when this variant is active AND
    // the page actually has the hero. On hero-less pages wave.js keeps API
    // ownership (this file has no canvasWaveAPI handle to fall back to there).
    if (!fallbackTriggered && document.querySelector(".hero.home")) {
      window.NSCWave = {
        instance: {
          dispose: dispose,
          init: init,
          applyPreset: applyPreset
        }
      };
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
