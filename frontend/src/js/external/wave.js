/**
 * NSC Wave - Interactive Wireframe Terrain for the hero section.
 *
 * Renders a canvas-based FBM noise-driven wave grid with mouse
 * interaction and a localhost-only debug panel for real-time tuning.
 *
 * No external dependencies — pure Canvas 2D.
 *
 * @module NSCWave
 */
(function () {
  "use strict";

  // ---------------------------------------------------------------------------
  // Configuration
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

  // ---------------------------------------------------------------------------
  // Presets
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

  var canvas = null;
  var ctx = null;
  var W = 0;
  var H = 0;
  var dpr = 1;
  var sectionEl = null;
  var animFrameId = null;
  var mouse = { x: -9999, y: -9999, active: false };
  var time = 0;
  var lastTime = 0;
  var frameCount = 0;
  var fpsTimer = 0;
  var fpsVal = 0;
  var isInitialized = false;
  var isDisposed = false;

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

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  function permute(x) {
    return ((x * 34.0 + 1.0) * x) % 289.0;
  }

  function noise2D(x, y) {
    var ix = Math.floor(x);
    var iy = Math.floor(y);
    var fx = x - ix;
    var fy = y - iy;
    var ux = fx * fx * (3 - 2 * fx);
    var uy = fy * fy * (3 - 2 * fy);
    var a = permute(permute(ix) + iy) / 289.0;
    var b = permute(permute(ix + 1) + iy) / 289.0;
    var c = permute(permute(ix) + iy + 1) / 289.0;
    var d = permute(permute(ix + 1) + iy + 1) / 289.0;
    return a + (b - a) * ux + (c - a) * uy + (d - b - c + a) * ux * uy - 0.5;
  }

  function fbm(x, y, octaves) {
    var val = 0;
    var amp = 1;
    var freq = 1;
    var max = 0;
    for (var i = 0; i < octaves; i++) {
      val += noise2D(x * freq, y * freq) * amp;
      max += amp;
      amp *= 0.5;
      freq *= 2.0;
    }

    return val / max;
  }

  function falloff(dist, radius) {
    var t = Math.min(dist / radius, 1);
    if (cfg.cursorFalloff === "linear") return 1 - t;
    if (cfg.cursorFalloff === "sharp") return Math.pow(1 - t, 3);
    // smooth (cosine)
    return 0.5 * (1 + Math.cos(Math.PI * t));
  }

  function hexToRGB(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  // ---------------------------------------------------------------------------
  // Debug UI Primitives
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
    arrow.textContent = collapsed ? "\u25B6 " : "\u25BC ";
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
      arrow.textContent = isHidden ? "\u25BC " : "\u25B6 ";
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
  // Canvas Setup
  // ---------------------------------------------------------------------------

  function createCanvas() {
    if (!sectionEl) return;

    canvas = document.createElement("canvas");
    canvas.style.cssText =
      "position:absolute;bottom:0;left:0;width:100%;height:45%;z-index:5;pointer-events:auto;" +
      "-webkit-mask-image:linear-gradient(to bottom, transparent 0%, black 30%);" +
      "mask-image:linear-gradient(to bottom, transparent 0%, black 30%);";
    sectionEl.appendChild(canvas);
    ctx = canvas.getContext("2d");
    resizeCanvas();
  }

  function resizeCanvas() {
    if (!canvas) return;
    dpr = window.devicePixelRatio || 1;
    W = canvas.offsetWidth;
    H = canvas.offsetHeight;
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  // ---------------------------------------------------------------------------
  // Mouse Handling
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
  // Responsive Grid
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
  // Render
  // ---------------------------------------------------------------------------

  function render(now) {
    if (!ctx || !canvas) return;

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

    var cols = cfg.gridCols;
    var rows = cfg.gridRows;
    var sp = cfg.gridSpacing;

    // Center offset — relative to canvas dimensions
    var ox = W / 2 + cfg.cameraPosX;
    var oy = H / 2 + cfg.cameraHeight + cfg.cameraPosY;
    var zoom = cfg.cameraZoom;
    var pitch = cfg.cameraPitch;
    var rot = cfg.cameraRotation * (Math.PI / 180);
    var cosR = Math.cos(rot);
    var sinR = Math.sin(rot);

    // Compute grid heights and screen positions
    var total = cols * rows;
    var heights = new Float32Array(total);
    var screenX = new Float32Array(total);
    var screenY = new Float32Array(total);

    var r, c, idx, wx, wy, h, px, py, dx, dy, dist, f, rwx, rwy;

    for (r = 0; r < rows; r++) {
      for (c = 0; c < cols; c++) {
        idx = r * cols + c;
        wx = (c - (cols - 1) / 2) * sp;
        wy = (r - (rows - 1) / 2) * sp;

        // Wave height via FBM noise
        h = fbm(
          c * cfg.frequency + time * 0.3,
          r * cfg.frequency + time * 0.15,
          cfg.octaves
        ) * cfg.amplitude;

        // Turbulence
        if (cfg.turbulence > 0) {
          h += Math.abs(noise2D(c * cfg.frequency * 2.7 + time * 0.4, r * cfg.frequency * 2.7)) *
            cfg.amplitude * cfg.turbulence;
        }

        // Rotate grid around Y axis then project
        rwx = wx * cosR - wy * sinR;
        rwy = wx * sinR + wy * cosR;

        // Project 3D to 2D (orthographic with pitch)
        px = ox + rwx * zoom;
        py = oy + rwy * pitch * zoom - h * zoom;

        // Cursor interaction (in screen space)
        if (mouse.active) {
          dx = px - mouse.x;
          dy = py - mouse.y;
          dist = Math.sqrt(dx * dx + dy * dy);

          if (dist < cfg.cursorRadius) {
            f = falloff(dist, cfg.cursorRadius) * cfg.cursorStrength;

            if (cfg.cursorMode === "pull") {
              h += f * 0.8;
            } else if (cfg.cursorMode === "push") {
              h -= f * 0.8;
            } else if (cfg.cursorMode === "swirl") {
              var angle = Math.atan2(dy, dx) + Math.PI * 0.5;
              h += Math.sin(angle * 3 + time * 2) * f * 0.5;
            } else if (cfg.cursorMode === "flatten") {
              h *= (1 - falloff(dist, cfg.cursorRadius));
            }
          }
        }

        heights[idx] = h;
        screenX[idx] = ox + rwx * zoom;
        screenY[idx] = oy + rwy * pitch * zoom - h * zoom;
      }
    }

    // Compute per-quad facing (screen-space winding)
    var quadFacing;
    if (cfg.backfaceCull) {
      quadFacing = new Float32Array((rows - 1) * (cols - 1));
      for (r = 0; r < rows - 1; r++) {
        for (c = 0; c < cols - 1; c++) {
          idx = r * cols + c;
          var ex1 = screenX[idx + 1] - screenX[idx];
          var ey1 = screenY[idx + 1] - screenY[idx];
          var ex2 = screenX[idx + cols] - screenX[idx];
          var ey2 = screenY[idx + cols] - screenY[idx];
          quadFacing[r * (cols - 1) + c] = ex1 * ey2 - ey1 * ex2;
        }
      }
    }

    // Clear (transparent — hero background shows through)
    ctx.clearRect(0, 0, W, H);

    var rgb = hexToRGB(cfg.lineColor);
    var lr = rgb[0];
    var lg = rgb[1];
    var lb = rgb[2];
    ctx.lineWidth = cfg.lineWidth;
    ctx.lineJoin = "round";
    ctx.lineCap = "round";

    var lineCount = 0;
    var depthT, alpha, hNorm, bright, sx, sy, pidx;

    function isRowSegmentVisible(row, col) {
      // Row segment from (row, col-1) to (row, col)
      // Adjacent quads: (row-1, col-1) above, (row, col-1) below
      var above = row > 0 ? quadFacing[(row - 1) * (cols - 1) + (col - 1)] : -1;
      var below = row < rows - 1 ? quadFacing[row * (cols - 1) + (col - 1)] : -1;
      return above >= 0 || below >= 0;
    }

    function isColSegmentVisible(row, col) {
      // Col segment from (row-1, col) to (row, col)
      // Adjacent quads: (row-1, col-1) left, (row-1, col) right
      var left = col > 0 ? quadFacing[(row - 1) * (cols - 1) + (col - 1)] : -1;
      var right = col < cols - 1 ? quadFacing[(row - 1) * (cols - 1) + col] : -1;
      return left >= 0 || right >= 0;
    }

    // Hoist fill setup variables for unified loop
    var frgb, fr, fg, fb, ldx, ldy, ldz, ldLen;
    if (cfg.showFill) {
      frgb = hexToRGB(cfg.fillColor);
      fr = frgb[0];
      fg = frgb[1];
      fb = frgb[2];

      // Normalize light direction
      ldx = cfg.lightDirX;
      ldy = cfg.lightDirY;
      ldz = cfg.lightDirZ;
      ldLen = Math.sqrt(ldx * ldx + ldy * ldy + ldz * ldz) || 1;
      ldx /= ldLen;
      ldy /= ldLen;
      ldz /= ldLen;
    }

    // Unified back-to-front row loop — fills occlude lines from farther rows
    for (r = 0; r < rows; r++) {
      depthT = r / (rows - 1);

      // (a) Fill quads for row r → r+1
      if (cfg.showFill && r < rows - 1) {
        alpha = cfg.fillOpacity;
        if (cfg.depthFade) alpha *= (0.15 + 0.85 * (1 - depthT * 0.7));
        for (c = 0; c < cols - 1; c++) {
          var i00 = r * cols + c;
          var i10 = r * cols + c + 1;
          var i01 = (r + 1) * cols + c;
          var i11 = (r + 1) * cols + c + 1;

          if (cfg.backfaceCull && quadFacing[r * (cols - 1) + c] <= 0) continue;

          var litR = fr;
          var litG = fg;
          var litB = fb;

          if (cfg.lightEnabled) {
            var e1x = sp;
            var e1y = 0;
            var e1z = heights[i10] - heights[i00];
            var e2x = 0;
            var e2y = sp;
            var e2z = heights[i01] - heights[i00];

            var nx = e1y * e2z - e1z * e2y;
            var ny = e1z * e2x - e1x * e2z;
            var nz = e1x * e2y - e1y * e2x;
            var nLen = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
            nx /= nLen;
            ny /= nLen;
            nz /= nLen;

            var diff = -(nx * ldx + ny * ldy + nz * ldz);
            if (diff < 0) diff = 0;

            var dotLN = ldx * nx + ldy * ny + ldz * nz;
            var refZ = ldz - 2 * dotLN * nz;
            var spec = -refZ;
            if (spec < 0) spec = 0;
            spec = Math.pow(spec, cfg.lightSpecPower);

            var shade = cfg.lightAmbient + cfg.lightDiffuse * diff + cfg.lightSpecular * spec;
            if (shade > 1.5) shade = 1.5;

            litR = Math.round(Math.min(fr * shade, 255));
            litG = Math.round(Math.min(fg * shade, 255));
            litB = Math.round(Math.min(fb * shade, 255));
          }

          ctx.fillStyle = "rgba(" + litR + "," + litG + "," + litB + "," + alpha + ")";
          ctx.beginPath();
          ctx.moveTo(screenX[i00], screenY[i00]);
          ctx.lineTo(screenX[i10], screenY[i10]);
          ctx.lineTo(screenX[i11], screenY[i11]);
          ctx.lineTo(screenX[i01], screenY[i01]);
          ctx.closePath();
          ctx.fill();
        }
      }

      // (b) Row line at row r
      if (cfg.showRows) {
        alpha = cfg.lineOpacity;
        if (cfg.depthFade) alpha *= (0.15 + 0.85 * (1 - depthT * 0.7));

        if (!cfg.heightColor) {
          ctx.strokeStyle = "rgba(" + lr + "," + lg + "," + lb + "," + alpha + ")";
        }

        var rowPathActive = false;

        for (c = 1; c < cols; c++) {
          idx = r * cols + c;
          pidx = r * cols + (c - 1);
          sx = screenX[idx];
          sy = screenY[idx];

          if (cfg.backfaceCull && !isRowSegmentVisible(r, c)) {
            if (rowPathActive) {
              ctx.stroke();
              rowPathActive = false;
            }

            continue;
          }

          if (cfg.heightColor) {
            hNorm = Math.min(Math.abs(heights[pidx]) / (cfg.amplitude || 1), 1);
            bright = 0.4 + 0.6 * hNorm;
            ctx.strokeStyle = "rgba(" + Math.round(lr * bright) + "," + Math.round(lg * bright) + "," + Math.round(lb * bright) + "," + alpha + ")";
            if (rowPathActive) ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(screenX[pidx], screenY[pidx]);
            rowPathActive = true;
          }

          if (!rowPathActive) {
            ctx.beginPath();
            ctx.moveTo(screenX[pidx], screenY[pidx]);
            rowPathActive = true;
          }

          if (c < cols - 1) {
            var nidx = r * cols + (c + 1);
            var nextRowVis = !cfg.backfaceCull || isRowSegmentVisible(r, c + 1);
            if (nextRowVis) {
              var mx = (sx + screenX[nidx]) * 0.5;
              var my = (sy + screenY[nidx]) * 0.5;
              ctx.quadraticCurveTo(sx, sy, mx, my);
            } else {
              ctx.lineTo(sx, sy);
            }
          } else {
            ctx.quadraticCurveTo(
              (screenX[pidx] + sx) * 0.5, (screenY[pidx] + sy) * 0.5,
              sx, sy
            );
          }
        }

        if (rowPathActive) ctx.stroke();
        lineCount += cols - 1;
      }

      // (c) Column segments from r-1 → r
      if (cfg.showCols && r > 0) {
        alpha = cfg.lineOpacity;
        if (cfg.depthFade) alpha *= (0.15 + 0.85 * (1 - depthT * 0.7));

        if (!cfg.heightColor) {
          ctx.strokeStyle = "rgba(" + lr + "," + lg + "," + lb + "," + alpha + ")";
          ctx.beginPath();
          for (c = 0; c < cols; c++) {
            idx = r * cols + c;
            pidx = (r - 1) * cols + c;
            if (cfg.backfaceCull && !isColSegmentVisible(r, c)) continue;
            ctx.moveTo(screenX[pidx], screenY[pidx]);
            ctx.lineTo(screenX[idx], screenY[idx]);
          }

          ctx.stroke();
        } else {
          for (c = 0; c < cols; c++) {
            idx = r * cols + c;
            pidx = (r - 1) * cols + c;
            if (cfg.backfaceCull && !isColSegmentVisible(r, c)) continue;
            hNorm = Math.min(Math.abs(heights[pidx]) / (cfg.amplitude || 1), 1);
            bright = 0.4 + 0.6 * hNorm;
            ctx.strokeStyle = "rgba(" + Math.round(lr * bright) + "," + Math.round(lg * bright) + "," + Math.round(lb * bright) + "," + alpha + ")";
            ctx.beginPath();
            ctx.moveTo(screenX[pidx], screenY[pidx]);
            ctx.lineTo(screenX[idx], screenY[idx]);
            ctx.stroke();
          }
        }

        lineCount += cols;
      }
    }

    // Update stats
    if (statsFpsEl) statsFpsEl.textContent = fpsVal;
    if (statsPointsEl) statsPointsEl.textContent = (cols * rows).toLocaleString();
    if (statsLinesEl) statsLinesEl.textContent = lineCount.toLocaleString();
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
    resizeCanvas();
    cfg.gridCols = responsiveCols();
    cfg.cameraZoom = responsiveZoom();
    cfg.cameraHeight = responsiveHeight();
  }

  // ---------------------------------------------------------------------------
  // Debug Panel Sections
  // ---------------------------------------------------------------------------

  function appendRendererSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Renderer", false, function (body) {
      var info = document.createElement("div");
      info.textContent = "Active: Canvas 2D";
      info.style.cssText = "font-size:10px;color:rgba(255,255,255,0.35);margin:3px 0;";
      body.appendChild(info);
      body.appendChild(makeToggle("three.js (GPU)", useThreeWave(), function (v) {
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
    btn.textContent = "\u2699";
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
    closeBtn.textContent = "\u00D7";
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

  function disposeCanvas() {
    detachMouseListeners();
    if (canvas && canvas.parentNode) {
      canvas.parentNode.removeChild(canvas);
    }

    canvas = null;
    ctx = null;
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

    if (boundResize) {
      window.removeEventListener("resize", boundResize);
      boundResize = null;
    }
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  function useThreeWave() {
    if (window.NSC_WAVE_THREE_FAILED) return false;
    var v = null;
    try { v = window.localStorage.getItem("NSC_WAVE_USE_THREE"); } catch (e) {}
    return v !== "0" && v !== "false";
  }

  function dispose() {
    if (isDisposed) return;
    isDisposed = true;
    isInitialized = false;

    disposeTimers();
    disposeDebugUI();
    disposeCanvas();
  }

  function init() {
    if (isInitialized) return;
    if (useThreeWave()) return; // three.js variant (wave-three.js) owns the hero

    sectionEl = document.querySelector(".hero.home");
    if (!sectionEl) return;

    isDisposed = false;

    // Responsive defaults
    cfg.gridCols = responsiveCols();
    cfg.cameraZoom = responsiveZoom();
    cfg.cameraHeight = responsiveHeight();

    createCanvas();
    attachMouseListeners();

    boundResize = onResize;
    window.addEventListener("resize", boundResize);

    isInitialized = true;
    time = 0;
    lastTime = performance.now();
    frameCount = 0;
    fpsTimer = 0;

    createDebugPanel();
    animate(performance.now());
  }

  // ---------------------------------------------------------------------------
  // Lazy Loading with IntersectionObserver
  // ---------------------------------------------------------------------------

  function setupLazyLoad() {
    if (useThreeWave()) return; // three.js variant (wave-three.js) owns the hero
    var section = document.querySelector(".hero.home");
    if (!section) return;

    // Fallback: no IntersectionObserver support
    if (typeof IntersectionObserver === "undefined") {
      init();
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            if (!isInitialized) {
              init();
            }
          } else {
            if (isInitialized) {
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupLazyLoad);
  } else {
    setupLazyLoad();
  }

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  window.NSCWave = {
    instance: {
      dispose: dispose,
      init: init,
      applyPreset: applyPreset
    }
  };
})();
