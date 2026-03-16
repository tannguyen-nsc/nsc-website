/**
 * NSC Globe - 3D Interactive Globe for "Why NSC Software?" section.
 *
 * Renders a hex-polygon globe with city points, wireframe overlay,
 * dynamic SVG connector lines, and hover-driven rotation.
 *
 * Dependencies (loaded via CDN in HTML):
 *   - THREE        (three.js r160 + examples/js/lines/*)
 *   - ThreeGlobe   (three-globe v2.31 standalone)
 *   - TWEEN        (@tweenjs/tween.js v23)
 *
 * @module NSCGlobe
 */
(function () {
  "use strict";

  // ---------------------------------------------------------------------------
  // Configuration
  // ---------------------------------------------------------------------------

  var BLOOM_STRENGTH = 0;
  var BLOOM_RADIUS = 0.1;
  var BLOOM_THRESHOLD = 0;
  var EMISSIVE_INTENSITY = 0.17;

  var GLOBE_RADIUS = 100;
  var WIREFRAME_RADIUS = 107;
  var HEX_RESOLUTION = 3;
  var GLOBE_BASE_COLOR = "#36C3DC";
  var HEX_COLOR = "#fff";
  var VIETNAM_HEX_COLOR = "#fff";
  var WIREFRAME_COLOR = "#ffffff";
  var WIREFRAME_OPACITY = 1;
  var GRID_DOT_COLOR = "#ffffff";
  var GRID_DOT_SIZE = 2;
  var ATMOSPHERE_COLOR = "#36C3DC";
  var ROTATION_DURATION = 1200;
  var AUTO_ROTATE_SPEED = 0.15 * (Math.PI / 180); // deg/frame -> radians
  var BREAKPOINT_LARGE = 1280;
  var GEOJSON_URL =
    "https://cdn.jsdelivr.net/npm/three-globe@2.45.0/example/hexed-polygons/ne_110m_admin_0_countries.geojson";

  var POINTS_DATA = [
    { lat: 22.00, lng: 100.00, label: "Senior-Led, AI-Driven Expertise" },
    { lat: 23.00, lng: 125.50, label: "Time-Zone Aligned Collaboration" },
    { lat: 15.00, lng: 99.50, label: "Vietnam's Top 7% IT Talents" },
    { lat: 14.00, lng: 122.00, label: "100% English-Proficient Team" },
    { lat: 7.50, lng: 100.00, label: "100% Senior-Level Engineers" },
    { lat: 5.50, lng: 122.50, label: "High-Quality, Cost-Efficient Delivery" },
    { lat: 1.35, lng: 100.50, label: "6+ Years Minimum Experience" }
  ];

  // Left-column card indices connect from right edge; right-column from left edge.
  var LEFT_COLUMN_INDICES = [0, 2, 4, 6];

  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------

  var scene, camera, renderer, globe, wireframeMesh, gridDotsMesh, globeGroup;
  var composer, bloomPass;
  var ambientLight, keyLight, fillLight, backLight;
  var spotLights = [null, null, null, null];
  var debugPanel = null;
  var debugGearBtn = null;
  var debugWrapper = null;
  var containerEl, sectionEl, svgOverlay;
  var animFrameId = null;
  var autoRotate = false;
  var autoRotateResumeTimer = null;
  var isInitialized = false;
  var isDisposed = false;
  var resizeTimer = null;
  var debugInfoEl = null;
  var sliderRegistry = [];
  var toggleRegistry = [];
  var colorRegistry = [];
  var cameraTarget = { x: 0, y: 0, z: 0 };
  var connectorColor = "rgba(54, 195, 220, 0.35)";
  var connectorWidth = 3;
  var connectorAltitude = 0.015;
  var wireBaseColor = new THREE.Color(WIREFRAME_COLOR);
  var wireGlowIntensity = 1;
  var dotBaseColor = new THREE.Color(GRID_DOT_COLOR);
  var dotGlowIntensity = 1;
  var connectorDashed = false;
  var connectorDashSize = 4;
  var connectorGapSize = 3;
  var connectorDotsMesh = null;
  var CONNECTOR_DOT_COLOR = "#FFFFFF";
  var CONNECTOR_DOT_SIZE = 3;
  var connDotBaseColor = new THREE.Color(CONNECTOR_DOT_COLOR);
  var connDotGlowIntensity = 2.6;
  var rimShellMesh = null;
  var RIM_SHELL_COLOR = "#00b3ff";
  var RIM_SHELL_INTENSITY = 4.5;
  var RIM_SHELL_POWER = 7.2;
  var RIM_SHELL_OPACITY = 0.5;
  var RIM_SHELL_RADIUS = 100.5;
  var vietnamTileMesh = null;
  var VIETNAM_TILE_LAT = 16;
  var VIETNAM_TILE_LNG = 107;
  var VIETNAM_TILE_SIZE = 88;
  var VIETNAM_TILE_ALTITUDE = 0.05;
  var VIETNAM_TILE_ROTATE_X = 0;    // degrees
  var VIETNAM_TILE_ROTATE_Y = -180; // degrees
  var VIETNAM_TILE_ROTATE_Z = 4;    // degrees
  var VIETNAM_TILE_OFFSET_X = 16;
  var VIETNAM_TILE_OFFSET_Y = 4.5;
  var VIETNAM_TILE_OFFSET_Z = 0;

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Check whether a GeoJSON feature represents Vietnam.
   * @param {object} feat - GeoJSON feature
   * @returns {boolean}
   */
  function isVietnam(feat) {
    if (!feat || !feat.properties) return false;
    return feat.properties.NAME === "Vietnam" || feat.properties.ISO_A3 === "VNM";
  }

  /**
   * Convert lat/lng (degrees) to globe rotation euler angles.
   * The globe model has Y-up; we rotate the group so the target point faces camera.
   * @param {number} lat
   * @param {number} lng
   * @returns {{ x: number, y: number }}
   */
  function latLngToRotation(lat, lng) {
    var phi = lat * (Math.PI / 180);
    var theta = lng * (Math.PI / 180);
    return {
      x: phi,
      y: -theta,
    };
  }

  /**
   * Convert a 3D world position to 2D pixel coordinates relative to a container.
   * @param {THREE.Vector3} worldPos
   * @param {THREE.PerspectiveCamera} cam
   * @param {HTMLElement} container
   * @returns {{ x: number, y: number, visible: boolean }}
   */
  function projectToScreen(worldPos, cam, container) {
    var vec = worldPos.clone().project(cam);
    var rect = container.getBoundingClientRect();

    // Check if point is in front of camera (z in NDC < 1)
    var visible = vec.z < 1;

    // Also verify the point is not on the far side of the globe by checking
    // the dot product of its direction with the camera forward direction.
    var camDir = new THREE.Vector3();
    cam.getWorldDirection(camDir);
    var toPoint = worldPos.clone().sub(cam.position).normalize();
    var dot = camDir.dot(toPoint);
    // If dot < 0 the point is behind the camera entirely
    if (dot < 0) visible = false;

    // Extra check: if the point is occluded by the globe sphere
    // Use distance from camera to determine if the projected point is on
    // the visible hemisphere relative to the camera.
    var camPos = cam.position.clone();
    var pointDir = worldPos.clone().sub(camPos);
    var distToCenter = camPos.length(); // globe is at origin
    var distToPoint = pointDir.length();
    // If point is farther than center along camera->globe line, check hemisphere
    var globeCenter = new THREE.Vector3(0, 0, 0);
    globeGroup.localToWorld(globeCenter);
    var centerToPoint = worldPos.clone().sub(globeCenter);
    var centerToCam = camPos.clone().sub(globeCenter).normalize();
    if (centerToPoint.dot(centerToCam) < 0) {
      visible = false;
    }

    var halfW = rect.width / 2;
    var halfH = rect.height / 2;

    return {
      x: vec.x * halfW + halfW,
      y: -(vec.y * halfH) + halfH,
      visible: visible,
    };
  }

  /**
   * Convert lat/lng to a 3D position on the globe surface, accounting for
   * the globe group's current rotation.
   * @param {number} lat
   * @param {number} lng
   * @param {number} altitude - fractional altitude above radius (0 = surface)
   * @returns {THREE.Vector3}
   */
  function latLngToWorld(lat, lng, altitude) {
    var phi = (90 - lat) * (Math.PI / 180);
    var theta = (90 - lng) * (Math.PI / 180);
    var r = GLOBE_RADIUS * (1 + (altitude || 0));

    var pos = new THREE.Vector3(
      r * Math.sin(phi) * Math.cos(theta),
      r * Math.cos(phi),
      r * Math.sin(phi) * Math.sin(theta)
    );

    // Transform through globe group's world matrix
    globeGroup.updateMatrixWorld(true);
    pos.applyMatrix4(globeGroup.matrixWorld);

    return pos;
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
    var label = document.createElement("span");
    label.textContent = title;
    header.appendChild(arrow);
    header.appendChild(label);
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

  // ---------------------------------------------------------------------------
  // SVG Connector Lines
  // ---------------------------------------------------------------------------

  function createSVGOverlay() {
    if (svgOverlay) svgOverlay.remove();

    var contentEl = document.querySelector(".why-us-content");
    if (!contentEl) return null;

    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.classList.add("globe-connectors-svg");
    svg.setAttribute("xmlns", "http://www.w3.org/2000/svg");
    svg.style.cssText =
      "position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:5;overflow:visible;";

    var contentPos = window.getComputedStyle(contentEl).position;
    if (contentPos === "static") contentEl.style.position = "relative";
    contentEl.appendChild(svg);

    return svg;
  }

  function updateConnectorLines() {
    if (window.innerWidth < BREAKPOINT_LARGE || !renderer) return;

    // Self-heal: if svgOverlay was removed from DOM (e.g. by Slick unslick), recreate it
    if (svgOverlay && !document.contains(svgOverlay)) {
      svgOverlay = null;
    }
    if (!svgOverlay) {
      svgOverlay = createSVGOverlay();
    }
    if (!svgOverlay) return;

    var contentEl = document.querySelector(".why-us-content");
    if (!contentEl) return;

    var contentRect = contentEl.getBoundingClientRect();
    var canvasRect = renderer.domElement.getBoundingClientRect();

    // Offset of the canvas within the content container
    var offsetX = canvasRect.left - contentRect.left;
    var offsetY = canvasRect.top - contentRect.top;

    // Clear previous lines
    while (svgOverlay.firstChild) {
      svgOverlay.removeChild(svgOverlay.firstChild);
    }

    svgOverlay.setAttribute(
      "viewBox",
      "0 0 " + contentRect.width + " " + contentRect.height
    );

    POINTS_DATA.forEach(function (point, index) {
      var card = document.querySelector('[data-globe-index="' + index + '"]');
      if (!card) return;

      var worldPos = latLngToWorld(point.lat, point.lng, connectorAltitude);
      var screen = projectToScreen(worldPos, camera, renderer.domElement);

      if (!screen.visible) return;

      // Globe point position relative to content container
      var globeX = screen.x + offsetX;
      var globeY = screen.y + offsetY;

      // Card position relative to content container
      var cardRect = card.getBoundingClientRect();
      var isLeftColumn = LEFT_COLUMN_INDICES.indexOf(index) !== -1;

      var cardX, cardY;
      if (isLeftColumn) {
        cardX = cardRect.right - contentRect.left;
        cardY = cardRect.top + cardRect.height / 2 - contentRect.top;
      } else {
        cardX = cardRect.left - contentRect.left;
        cardY = cardRect.top + cardRect.height / 2 - contentRect.top;
      }

      var line = document.createElementNS("http://www.w3.org/2000/svg", "line");
      line.setAttribute("x1", String(globeX));
      line.setAttribute("y1", String(globeY));
      line.setAttribute("x2", String(cardX));
      line.setAttribute("y2", String(cardY));
      line.setAttribute("stroke", connectorColor);
      line.setAttribute("stroke-width", String(connectorWidth));
      if (connectorDashed) {
        line.setAttribute("stroke-dasharray", connectorDashSize + " " + connectorGapSize);
      } else {
        line.removeAttribute("stroke-dasharray");
      }
      svgOverlay.appendChild(line);
    });
  }

  // ---------------------------------------------------------------------------
  // Wireframe helper (LineSegments2 for variable line width)
  // ---------------------------------------------------------------------------

  function createWireframe(radius) {
    var icoGeo = new THREE.IcosahedronGeometry(radius, 5);

    if (THREE.LineSegmentsGeometry && THREE.LineMaterial && THREE.LineSegments2) {
      var wireGeo = new THREE.WireframeGeometry(icoGeo);
      var positions = wireGeo.attributes.position.array;
      var lineGeo = new THREE.LineSegmentsGeometry();
      lineGeo.setPositions(positions);
      var w = containerEl ? containerEl.clientWidth : window.innerWidth;
      var h = containerEl ? containerEl.clientHeight : window.innerHeight;
      var wireMat = new THREE.LineMaterial({
        color: new THREE.Color(WIREFRAME_COLOR).getHex(),
        transparent: true,
        opacity: WIREFRAME_OPACITY,
        linewidth: 0.5,
        dashed: true,
        dashSize: 0.7,
        gapSize: 1.5,
        resolution: new THREE.Vector2(w, h),
      });
      wireMat.toneMapped = false;
      var mesh = new THREE.LineSegments2(lineGeo, wireMat);
      mesh.computeLineDistances();
      icoGeo.dispose();
      wireGeo.dispose();
      return mesh;
    }

    // Fallback: standard wireframe (line width always 1px)
    return new THREE.Mesh(icoGeo, new THREE.MeshBasicMaterial({
      wireframe: true,
      color: WIREFRAME_COLOR,
      transparent: true,
      opacity: WIREFRAME_OPACITY,
      toneMapped: false,
    }));
  }

  // ---------------------------------------------------------------------------
  // Grid Dots helper (intersection points on wireframe)
  // ---------------------------------------------------------------------------

  function createGridDots(radius) {
    var icoGeo = new THREE.IcosahedronGeometry(radius, 5);
    var positions = icoGeo.attributes.position.array;
    var dotGeo = new THREE.BufferGeometry();
    dotGeo.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
    // Draw a circle on a small canvas → use as texture for round dots
    var canvas = document.createElement("canvas");
    canvas.width = 64;
    canvas.height = 64;
    var ctx = canvas.getContext("2d");
    ctx.beginPath();
    ctx.arc(32, 32, 30, 0, Math.PI * 2);
    ctx.fillStyle = "#ffffff";
    ctx.fill();
    var circleTexture = new THREE.CanvasTexture(canvas);

    var dotMat = new THREE.PointsMaterial({
      color: new THREE.Color(GRID_DOT_COLOR),
      size: GRID_DOT_SIZE,
      map: circleTexture,
      transparent: true,
      alphaTest: 0.5,
      sizeAttenuation: true,
      depthWrite: false,
    });
    dotMat.toneMapped = false;
    var points = new THREE.Points(dotGeo, dotMat);
    icoGeo.dispose();
    return points;
  }

  // ---------------------------------------------------------------------------
  // Connector Dots (3D dots at city points on the globe surface)
  // ---------------------------------------------------------------------------

  function createConnectorDots() {
    var positions = [];
    POINTS_DATA.forEach(function (point) {
      var phi = (90 - point.lat) * (Math.PI / 180);
      var theta = (90 - point.lng) * (Math.PI / 180);
      var r = GLOBE_RADIUS * (1 + connectorAltitude);
      positions.push(
        r * Math.sin(phi) * Math.cos(theta),
        r * Math.cos(phi),
        r * Math.sin(phi) * Math.sin(theta)
      );
    });
    var geo = new THREE.BufferGeometry();
    geo.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));

    var canvas = document.createElement("canvas");
    canvas.width = 64;
    canvas.height = 64;
    var ctx = canvas.getContext("2d");
    ctx.beginPath();
    ctx.arc(32, 32, 30, 0, Math.PI * 2);
    ctx.fillStyle = "#ffffff";
    ctx.fill();

    var mat = new THREE.PointsMaterial({
      color: new THREE.Color(CONNECTOR_DOT_COLOR),
      size: CONNECTOR_DOT_SIZE,
      map: new THREE.CanvasTexture(canvas),
      transparent: true,
      alphaTest: 0.5,
      sizeAttenuation: true,
      depthWrite: false,
    });
    mat.toneMapped = false;
    return new THREE.Points(geo, mat);
  }

  function updateConnectorDotPosition(index) {
    if (!connectorDotsMesh) return;
    var point = POINTS_DATA[index];
    var phi = (90 - point.lat) * (Math.PI / 180);
    var theta = (90 - point.lng) * (Math.PI / 180);
    var r = GLOBE_RADIUS * (1 + connectorAltitude);
    var positions = connectorDotsMesh.geometry.attributes.position.array;
    positions[index * 3] = r * Math.sin(phi) * Math.cos(theta);
    positions[index * 3 + 1] = r * Math.cos(phi);
    positions[index * 3 + 2] = r * Math.sin(phi) * Math.sin(theta);
    connectorDotsMesh.geometry.attributes.position.needsUpdate = true;
  }

  // ---------------------------------------------------------------------------
  // Fresnel Rim Shell
  // ---------------------------------------------------------------------------

  function createRimShell(radius) {
    var geo = new THREE.SphereGeometry(radius, 64, 64);
    var mat = new THREE.ShaderMaterial({
      uniforms: {
        rimColor: { value: new THREE.Color(RIM_SHELL_COLOR) },
        rimIntensity: { value: RIM_SHELL_INTENSITY },
        rimPower: { value: RIM_SHELL_POWER },
        rimOpacity: { value: RIM_SHELL_OPACITY },
      },
      vertexShader: [
        "varying vec3 vNormal;",
        "varying vec3 vViewDir;",
        "void main() {",
        "  vNormal = normalize(normalMatrix * normal);",
        "  vec4 mvPos = modelViewMatrix * vec4(position, 1.0);",
        "  vViewDir = normalize(-mvPos.xyz);",
        "  gl_Position = projectionMatrix * mvPos;",
        "}",
      ].join("\n"),
      fragmentShader: [
        "uniform vec3 rimColor;",
        "uniform float rimIntensity;",
        "uniform float rimPower;",
        "uniform float rimOpacity;",
        "varying vec3 vNormal;",
        "varying vec3 vViewDir;",
        "void main() {",
        "  float fresnel = 1.0 - abs(dot(vNormal, vViewDir));",
        "  fresnel = pow(fresnel, rimPower) * rimIntensity;",
        "  gl_FragColor = vec4(rimColor * fresnel, fresnel * rimOpacity);",
        "}",
      ].join("\n"),
      transparent: true,
      side: THREE.FrontSide,
      depthWrite: false,
      depthTest: false,
      blending: THREE.AdditiveBlending,
    });
    mat.toneMapped = false;
    return new THREE.Mesh(geo, mat);
  }

  // ---------------------------------------------------------------------------
  // Bloom Setup (deferred until Three.js addons are loaded)
  // ---------------------------------------------------------------------------

  function setupBloom(width, height) {
    if (composer) return; // already set up
    if (!renderer || !scene || !camera) return;
    if (!THREE.EffectComposer || !THREE.RenderPass || !THREE.UnrealBloomPass) {
      console.warn("[NSCGlobe] Bloom addons not yet available — will retry on three-addons-ready");
      return;
    }
    console.log("[NSCGlobe] Setting up bloom pipeline (OutputPass: " + !!THREE.OutputPass + ")");
    var rt = new THREE.WebGLRenderTarget(width, height, {
      format: THREE.RGBAFormat,
      type: THREE.HalfFloatType,
      stencilBuffer: false,
    });
    composer = new THREE.EffectComposer(renderer, rt);
    var renderPass = new THREE.RenderPass(scene, camera);
    renderPass.clearAlpha = 0;
    composer.addPass(renderPass);
    bloomPass = new THREE.UnrealBloomPass(
      new THREE.Vector2(width, height),
      BLOOM_STRENGTH,
      BLOOM_RADIUS,
      BLOOM_THRESHOLD
    );
    composer.addPass(bloomPass);
    if (THREE.OutputPass) {
      composer.addPass(new THREE.OutputPass());
    }
  }

  // Listen for deferred addon loading
  window.addEventListener("three-addons-ready", function () {
    if (!composer && renderer && containerEl) {
      setupBloom(containerEl.clientWidth, containerEl.clientHeight);
      // Rebuild debug panel so bloom sliders become active
      if (debugPanel && debugPanel.parentNode) {
        debugPanel.parentNode.removeChild(debugPanel);
        debugPanel = null;
        createDebugPanel();
      }
    }
  });

  // ---------------------------------------------------------------------------
  // Renderer & Lights Factories
  // ---------------------------------------------------------------------------

  function createRenderer(width, height) {
    try {
      renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    } catch (e) {
      console.warn("[NSCGlobe] WebGL renderer creation failed:", e);
      return null;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(width, height);
    renderer.setClearColor(0x000000, 0);
    containerEl.appendChild(renderer.domElement);
    return renderer;
  }

  function createLights() {
    ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);

    keyLight = new THREE.DirectionalLight(0xffffff, 0.8);
    keyLight.position.set(100, 200, 100);
    scene.add(keyLight);

    fillLight = new THREE.DirectionalLight(0xffffff, 0.3);
    fillLight.position.set(-100, -50, 200);
    scene.add(fillLight);

    backLight = new THREE.DirectionalLight(0xffffff, 1.6);
    backLight.position.set(-135, -300, 300);
    scene.add(backLight);

    for (var i = 0; i < 4; i++) {
      spotLights[i] = new THREE.DirectionalLight(0xffffff, 1.6);
      spotLights[i].position.set(-135, -300, 300);
      spotLights[i].visible = false;
      scene.add(spotLights[i]);
    }
  }

  // ---------------------------------------------------------------------------
  // Globe Initialization
  // ---------------------------------------------------------------------------

  function initScene() {
    containerEl = document.getElementById("globe-container");
    sectionEl = document.getElementById("why-us");
    if (!containerEl || !sectionEl) return false;

    var width = containerEl.clientWidth;
    var height = containerEl.clientHeight;

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(50, width / height, 1, 1000);
    camera.position.set(0, 0, 270);

    if (!createRenderer(width, height)) return false;

    createLights();

    // Tone mapping
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;

    // Post-processing bloom
    setupBloom(width, height);

    // Globe group (holds globe mesh + wireframe)
    globeGroup = new THREE.Group();
    globeGroup.rotation.order = "ZXY";
    scene.add(globeGroup);

    return true;
  }

  // ---------------------------------------------------------------------------
  // Vietnam Tile (image on globe surface)
  // ---------------------------------------------------------------------------

  function createVietnamTile() {
    var loader = new THREE.TextureLoader();
    var texture = loader.load("./img/s.png");
    texture.colorSpace = THREE.SRGBColorSpace;

    var geo = new THREE.PlaneGeometry(1, 1);
    // Flip UVs horizontally so the image isn't mirrored
    var uvs = geo.attributes.uv;
    for (var i = 0; i < uvs.count; i++) {
      uvs.setX(i, 1 - uvs.getX(i));
    }
    var mat = new THREE.MeshBasicMaterial({
      map: texture,
      transparent: true,
      depthWrite: false,
      side: THREE.DoubleSide,
      toneMapped: false,
    });

    var mesh = new THREE.Mesh(geo, mat);

    // Position on globe surface
    updateVietnamTilePosition(mesh);

    return mesh;
  }

  function updateVietnamTilePosition(mesh) {
    if (!mesh) return;
    var lat = VIETNAM_TILE_LAT;
    var lng = VIETNAM_TILE_LNG;
    var size = VIETNAM_TILE_SIZE;
    var phi = (90 - lat) * (Math.PI / 180);
    var theta = (90 - lng) * (Math.PI / 180);
    var r = GLOBE_RADIUS + VIETNAM_TILE_ALTITUDE;

    // Position on sphere surface
    mesh.position.set(
      r * Math.sin(phi) * Math.cos(theta),
      r * Math.cos(phi),
      r * Math.sin(phi) * Math.sin(theta)
    );

    // Scale
    mesh.scale.set(size, size, 1);

    // Orient to face outward from globe center (in parent's local space).
    // Use Matrix4.lookAt directly instead of mesh.lookAt to avoid
    // world-space conversion that breaks when globeGroup is rotated.
    var normal = mesh.position.clone().normalize();
    var lookTarget = mesh.position.clone().add(normal);
    var rotM = new THREE.Matrix4();
    rotM.lookAt(lookTarget, mesh.position, mesh.up);
    mesh.quaternion.setFromRotationMatrix(rotM);

    // Apply rotations in local frame (after lookAt sets the base orientation)
    mesh.rotateX(VIETNAM_TILE_ROTATE_X * Math.PI / 180);
    mesh.rotateY(VIETNAM_TILE_ROTATE_Y * Math.PI / 180);
    mesh.rotateZ(VIETNAM_TILE_ROTATE_Z * Math.PI / 180);

    // Apply offsets in world space
    mesh.position.x += VIETNAM_TILE_OFFSET_X;
    mesh.position.y += VIETNAM_TILE_OFFSET_Y;
    mesh.position.z += VIETNAM_TILE_OFFSET_Z;
  }

  function buildGlobe(geoFeatures) {
    // Three-globe instance
    globe = new ThreeGlobe()
      .hexPolygonsData(geoFeatures)
      .hexPolygonResolution(HEX_RESOLUTION)
      .hexPolygonMargin(0.4)
      .hexPolygonUseDots(true)
      .hexPolygonColor(function (feat) {
        return isVietnam(feat) ? VIETNAM_HEX_COLOR : HEX_COLOR;
      })
      .showAtmosphere(true)
      .atmosphereColor(ATMOSPHERE_COLOR)
      .atmosphereAltitude(0.0);

    // Globe material
    var mat = globe.globeMaterial();
    mat.color = new THREE.Color(GLOBE_BASE_COLOR);
    mat.transparent = true;
    mat.opacity = 0.95;
    mat.emissive = new THREE.Color(GLOBE_BASE_COLOR);
    mat.emissiveIntensity = EMISSIVE_INTENSITY;

    globeGroup.add(globe);

    // Force all three-globe internal meshes to render before the rim shell
    globe.traverse(function (child) {
      child.renderOrder = 0;
    });

    // Vietnam tile (custom image on globe surface)
    vietnamTileMesh = createVietnamTile();
    vietnamTileMesh.renderOrder = 3;
    globeGroup.add(vietnamTileMesh);

    // Wireframe overlay (using LineSegments2 for variable line width)
    wireframeMesh = createWireframe(WIREFRAME_RADIUS);
    wireframeMesh.material.color.copy(wireBaseColor).multiplyScalar(wireGlowIntensity);
    wireframeMesh.renderOrder = 1;
    globeGroup.add(wireframeMesh);

    // Grid dots at wireframe vertices
    gridDotsMesh = createGridDots(WIREFRAME_RADIUS);
    gridDotsMesh.material.color.copy(dotBaseColor).multiplyScalar(dotGlowIntensity);
    gridDotsMesh.renderOrder = 2;
    globeGroup.add(gridDotsMesh);

    // SVG overlay for connector lines (desktop only)
    if (window.innerWidth >= BREAKPOINT_LARGE) {
      svgOverlay = createSVGOverlay();
    }

    // Connector dots (3D dots at each city point)
    connectorDotsMesh = createConnectorDots();
    connectorDotsMesh.material.color.copy(connDotBaseColor).multiplyScalar(connDotGlowIntensity);
    globeGroup.add(connectorDotsMesh);

    // Fresnel rim shell (rendered last for correct additive blending)
    rimShellMesh = createRimShell(RIM_SHELL_RADIUS);
    rimShellMesh.renderOrder = 999;
    globeGroup.add(rimShellMesh);

    // Set initial rotation to face Hanoi
    var initial = latLngToRotation(POINTS_DATA[0].lat, POINTS_DATA[0].lng);
    globeGroup.rotation.x = initial.x;
    globeGroup.rotation.y = initial.y;
    globeGroup.rotation.z = -12 * (Math.PI / 180);
  }

  // ---------------------------------------------------------------------------
  // Rotation
  // ---------------------------------------------------------------------------

  /**
   * Smoothly rotate the globe to center on a specific point.
   * @param {number} index - Index into POINTS_DATA
   */
  function rotateToPoint(index) {
    if (index < 0 || index >= POINTS_DATA.length || !globeGroup) return;

    var target = latLngToRotation(
      POINTS_DATA[index].lat,
      POINTS_DATA[index].lng
    );

    // Normalize Y rotation to avoid >180-degree spins
    var currentY = globeGroup.rotation.y;
    var deltaY = target.y - currentY;
    while (deltaY > Math.PI) deltaY -= 2 * Math.PI;
    while (deltaY < -Math.PI) deltaY += 2 * Math.PI;
    target.y = currentY + deltaY;

    var from = { x: globeGroup.rotation.x, y: globeGroup.rotation.y };
    new TWEEN.Tween(from)
      .to({ x: target.x, y: target.y }, ROTATION_DURATION)
      .easing(TWEEN.Easing.Cubic.InOut)
      .onUpdate(function () {
        globeGroup.rotation.x = from.x;
        globeGroup.rotation.y = from.y;
      })
      .start();
  }

  // ---------------------------------------------------------------------------
  // Animation Loop
  // ---------------------------------------------------------------------------

  function syncDebugPanel() {
    if (debugInfoEl && globeGroup) {
      var rx = ((globeGroup.rotation.x * 180) / Math.PI).toFixed(2);
      var ry = ((globeGroup.rotation.y * 180) / Math.PI).toFixed(2);
      var lat = ((globeGroup.rotation.x * 180) / Math.PI).toFixed(2);
      var lng = ((-globeGroup.rotation.y * 180) / Math.PI).toFixed(2);
      debugInfoEl.textContent =
        "rot.x: " + rx + "  rot.y: " + ry + "\nlat: " + lat + "  lng: " + lng;
    }
    for (var si = 0; si < sliderRegistry.length; si++) {
      var entry = sliderRegistry[si];
      var current = entry.getter();
      if (current !== null && current !== undefined) {
        entry.inp.value = current;
        entry.val.textContent = Number(current).toFixed(2);
      }
    }
    for (var ti = 0; ti < toggleRegistry.length; ti++) {
      var tEntry = toggleRegistry[ti];
      var tCurrent = tEntry.getter();
      if (tCurrent !== null && tCurrent !== undefined) {
        tEntry.checkbox.checked = tCurrent;
      }
    }
    for (var ci = 0; ci < colorRegistry.length; ci++) {
      var cEntry = colorRegistry[ci];
      var cCurrent = cEntry.getter();
      if (cCurrent !== null && cCurrent !== undefined) {
        cEntry.input.value = cCurrent;
      }
    }
  }

  function animate() {
    animFrameId = requestAnimationFrame(animate);

    TWEEN.update();

    if (autoRotate && globeGroup) {
      globeGroup.rotation.y += AUTO_ROTATE_SPEED;
    }

    updateConnectorLines();

    if (debugPanel && debugPanel.style.display !== "none") {
      syncDebugPanel();
    }

    if (composer) {
      composer.render();
    } else if (renderer && scene && camera) {
      renderer.render(scene, camera);
    }
  }

  // ---------------------------------------------------------------------------
  // Event Handlers
  // ---------------------------------------------------------------------------

  function attachHoverListeners() {
    var items = document.querySelectorAll(".why-us-item[data-globe-index]");

    items.forEach(function (item) {
      item.addEventListener("mouseenter", function () {
        var idx = parseInt(item.getAttribute("data-globe-index"), 10);
        if (isNaN(idx)) return;

        clearTimeout(autoRotateResumeTimer);
        autoRotate = false;
        rotateToPoint(idx);
      });

      item.addEventListener("mouseleave", function () {
        clearTimeout(autoRotateResumeTimer);
      });
    });
  }

  function onResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      if (!containerEl || !camera || !renderer) return;

      var width = containerEl.clientWidth;
      var height = containerEl.clientHeight;

      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height);
      if (composer) composer.setSize(width, height);
      if (wireframeMesh && wireframeMesh.material.resolution) {
        wireframeMesh.material.resolution.set(width, height);
      }
    }, 150);
  }

  // ---------------------------------------------------------------------------
  // Debug Panel Sections
  // ---------------------------------------------------------------------------

  function appendLightingSection(panel) {
    panel.appendChild(makeHeading("Lighting"));
    panel.appendChild(
      makeSlider("Ambient", 0, 3, 0.05,
        ambientLight ? ambientLight.intensity : 1.4,
        function (v) { if (ambientLight) ambientLight.intensity = v; },
        function () { return ambientLight ? ambientLight.intensity : null; })
    );
    panel.appendChild(
      makeSlider("Key", 0, 3, 0.05,
        keyLight ? keyLight.intensity : 1.8,
        function (v) { if (keyLight) keyLight.intensity = v; },
        function () { return keyLight ? keyLight.intensity : null; })
    );
    panel.appendChild(
      makeSlider("Fill", 0, 3, 0.05,
        fillLight ? fillLight.intensity : 0.9,
        function (v) { if (fillLight) fillLight.intensity = v; },
        function () { return fillLight ? fillLight.intensity : null; })
    );
  }

  function appendBackLightAndSpotsSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Lights (Back + Spots)", true, function (container) {
      container.appendChild(makeHeading("Back Light"));

      container.appendChild(makeToggle("Enabled", true,
        function (v) { if (backLight) backLight.visible = v; },
        function () { return backLight ? backLight.visible : null; }
      ));

      container.appendChild(
        makeSlider("Intensity", 0, 20, 0.1,
          backLight ? backLight.intensity : 1.5,
          function (v) { if (backLight) backLight.intensity = v; },
          function () { return backLight ? backLight.intensity : null; })
      );

      container.appendChild(makeColorPicker("Color", "#ffffff",
        function (v) { if (backLight) backLight.color = new THREE.Color(v); },
        function () { return backLight ? "#" + backLight.color.getHexString() : null; }
      ));

      container.appendChild(
        makeSlider("Pos X", -300, 300, 5,
          backLight ? backLight.position.x : 0,
          function (v) { if (backLight) backLight.position.x = v; },
          function () { return backLight ? backLight.position.x : null; })
      );
      container.appendChild(
        makeSlider("Pos Y", -300, 300, 5,
          backLight ? backLight.position.y : 50,
          function (v) { if (backLight) backLight.position.y = v; },
          function () { return backLight ? backLight.position.y : null; })
      );
      container.appendChild(
        makeSlider("Pos Z", -300, 300, 5,
          backLight ? backLight.position.z : -200,
          function (v) { if (backLight) backLight.position.z = v; },
          function () { return backLight ? backLight.position.z : null; })
      );

      for (var si = 0; si < 4; si++) {
        (function (idx) {
          var light = spotLights[idx];
          container.appendChild(makeHeading("Light " + (idx + 1)));

          container.appendChild(makeToggle("Enabled", light ? light.visible : false,
            function (v) { if (spotLights[idx]) spotLights[idx].visible = v; },
            function () { return spotLights[idx] ? spotLights[idx].visible : null; }
          ));

          container.appendChild(
            makeSlider("Intensity", 0, 20, 0.1, light ? light.intensity : 1.6, function (v) {
              if (spotLights[idx]) spotLights[idx].intensity = v;
            }, function () { return spotLights[idx] ? spotLights[idx].intensity : null; })
          );

          container.appendChild(makeColorPicker("Color", light ? "#" + light.color.getHexString() : "#ffffff",
            function (v) { if (spotLights[idx]) spotLights[idx].color = new THREE.Color(v); },
            function () { return spotLights[idx] ? "#" + spotLights[idx].color.getHexString() : null; }
          ));

          container.appendChild(
            makeSlider("Pos X", -300, 300, 5, light ? light.position.x : -135, function (v) {
              if (spotLights[idx]) spotLights[idx].position.x = v;
            }, function () { return spotLights[idx] ? spotLights[idx].position.x : null; })
          );
          container.appendChild(
            makeSlider("Pos Y", -300, 300, 5, light ? light.position.y : -300, function (v) {
              if (spotLights[idx]) spotLights[idx].position.y = v;
            }, function () { return spotLights[idx] ? spotLights[idx].position.y : null; })
          );
          container.appendChild(
            makeSlider("Pos Z", -300, 300, 5, light ? light.position.z : 300, function (v) {
              if (spotLights[idx]) spotLights[idx].position.z = v;
            }, function () { return spotLights[idx] ? spotLights[idx].position.z : null; })
          );
        })(si);
      }
    }));
  }

  function appendCameraSection(panel) {
    panel.appendChild(makeHeading("Camera"));
    panel.appendChild(
      makeSlider("Distance", 100, 500, 5, camera ? camera.position.z : 260, function (v) {
        if (camera) camera.position.setZ(v);
      }, function () { return camera ? camera.position.z : null; })
    );
    panel.appendChild(
      makeSlider("Position X", -300, 300, 5, camera ? camera.position.x : 0, function (v) {
        if (camera) camera.position.setX(v);
      }, function () { return camera ? camera.position.x : null; })
    );
    panel.appendChild(
      makeSlider("Position Y", -300, 300, 5, camera ? camera.position.y : 0, function (v) {
        if (camera) camera.position.setY(v);
      }, function () { return camera ? camera.position.y : null; })
    );
    panel.appendChild(
      makeSlider("FOV", 10, 120, 1, camera ? camera.fov : 50, function (v) {
        if (camera) {
          camera.fov = v;
          camera.updateProjectionMatrix();
        }
      }, function () { return camera ? camera.fov : null; })
    );
    panel.appendChild(
      makeSlider("Look At X", -200, 200, 5, 0, function (v) {
        cameraTarget.x = v;
        if (camera) camera.lookAt(cameraTarget.x, cameraTarget.y, cameraTarget.z);
      }, function () { return cameraTarget.x; })
    );
    panel.appendChild(
      makeSlider("Look At Y", -200, 200, 5, 0, function (v) {
        cameraTarget.y = v;
        if (camera) camera.lookAt(cameraTarget.x, cameraTarget.y, cameraTarget.z);
      }, function () { return cameraTarget.y; })
    );
    panel.appendChild(
      makeSlider("Look At Z", -200, 200, 5, 0, function (v) {
        cameraTarget.z = v;
        if (camera) camera.lookAt(cameraTarget.x, cameraTarget.y, cameraTarget.z);
      }, function () { return cameraTarget.z; })
    );
  }

  function appendGlobeMaterialSection(panel) {
    panel.appendChild(makeHeading("Globe"));

    panel.appendChild(makeColorPicker("Color", GLOBE_BASE_COLOR,
      function (v) {
        if (globe) {
          var mat = globe.globeMaterial();
          mat.color = new THREE.Color(v);
          mat.emissive = new THREE.Color(v);
        }
      },
      function () { return globe ? "#" + globe.globeMaterial().color.getHexString() : null; }
    ));

    panel.appendChild(
      makeSlider("Emissive Int.", 0, 1, 0.01, EMISSIVE_INTENSITY, function (v) {
        if (globe) globe.globeMaterial().emissiveIntensity = v;
      }, function () { return globe ? globe.globeMaterial().emissiveIntensity : null; })
    );
  }

  function appendVietnamTileSection(panel) {
    panel.appendChild(makeHeading("Vietnam Tile"));

    panel.appendChild(makeToggle("Enabled", vietnamTileMesh ? vietnamTileMesh.visible : true,
      function (v) { if (vietnamTileMesh) vietnamTileMesh.visible = v; },
      function () { return vietnamTileMesh ? vietnamTileMesh.visible : null; }
    ));

    panel.appendChild(
      makeSlider("Size", 5, 200, 1, VIETNAM_TILE_SIZE, function (v) {
        VIETNAM_TILE_SIZE = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_SIZE; })
    );
    panel.appendChild(
      makeSlider("Altitude", 0, 10, 0.1, VIETNAM_TILE_ALTITUDE, function (v) {
        VIETNAM_TILE_ALTITUDE = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_ALTITUDE; })
    );
    panel.appendChild(
      makeSlider("Lat", -90, 90, 0.5, VIETNAM_TILE_LAT, function (v) {
        VIETNAM_TILE_LAT = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_LAT; })
    );
    panel.appendChild(
      makeSlider("Lng", -180, 180, 0.5, VIETNAM_TILE_LNG, function (v) {
        VIETNAM_TILE_LNG = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_LNG; })
    );
    panel.appendChild(
      makeSlider("Rotate X", -180, 180, 1, VIETNAM_TILE_ROTATE_X, function (v) {
        VIETNAM_TILE_ROTATE_X = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_ROTATE_X; })
    );
    panel.appendChild(
      makeSlider("Rotate Y", -180, 180, 1, VIETNAM_TILE_ROTATE_Y, function (v) {
        VIETNAM_TILE_ROTATE_Y = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_ROTATE_Y; })
    );
    panel.appendChild(
      makeSlider("Rotate Z", -180, 180, 1, VIETNAM_TILE_ROTATE_Z, function (v) {
        VIETNAM_TILE_ROTATE_Z = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_ROTATE_Z; })
    );
    panel.appendChild(
      makeSlider("Offset X", -50, 50, 0.5, VIETNAM_TILE_OFFSET_X, function (v) {
        VIETNAM_TILE_OFFSET_X = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_OFFSET_X; })
    );
    panel.appendChild(
      makeSlider("Offset Y", -50, 50, 0.5, VIETNAM_TILE_OFFSET_Y, function (v) {
        VIETNAM_TILE_OFFSET_Y = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_OFFSET_Y; })
    );
    panel.appendChild(
      makeSlider("Offset Z", -50, 50, 0.5, VIETNAM_TILE_OFFSET_Z, function (v) {
        VIETNAM_TILE_OFFSET_Z = v;
        updateVietnamTilePosition(vietnamTileMesh);
      }, function () { return VIETNAM_TILE_OFFSET_Z; })
    );
  }

  function appendRimGlowSection(panel) {
    panel.appendChild(makeHeading("Rim Glow"));

    panel.appendChild(makeToggle("Enabled", rimShellMesh ? rimShellMesh.visible : true,
      function (v) { if (rimShellMesh) rimShellMesh.visible = v; },
      function () { return rimShellMesh ? rimShellMesh.visible : null; }
    ));

    panel.appendChild(makeColorPicker("Color", RIM_SHELL_COLOR,
      function (v) { if (rimShellMesh) rimShellMesh.material.uniforms.rimColor.value = new THREE.Color(v); },
      function () { return rimShellMesh ? "#" + rimShellMesh.material.uniforms.rimColor.value.getHexString() : null; }
    ));

    panel.appendChild(
      makeSlider("Intensity", 0, 5, 0.1, RIM_SHELL_INTENSITY, function (v) {
        if (rimShellMesh) rimShellMesh.material.uniforms.rimIntensity.value = v;
      }, function () { return rimShellMesh ? rimShellMesh.material.uniforms.rimIntensity.value : null; })
    );
    panel.appendChild(
      makeSlider("Power", 0.5, 10, 0.1, RIM_SHELL_POWER, function (v) {
        if (rimShellMesh) rimShellMesh.material.uniforms.rimPower.value = v;
      }, function () { return rimShellMesh ? rimShellMesh.material.uniforms.rimPower.value : null; })
    );
    panel.appendChild(
      makeSlider("Opacity", 0, 1, 0.05, RIM_SHELL_OPACITY, function (v) {
        if (rimShellMesh) rimShellMesh.material.uniforms.rimOpacity.value = v;
      }, function () { return rimShellMesh ? rimShellMesh.material.uniforms.rimOpacity.value : null; })
    );
    panel.appendChild(
      makeSlider("Radius", 100, 115, 0.5, RIM_SHELL_RADIUS, function (v) {
        RIM_SHELL_RADIUS = v;
        if (rimShellMesh) {
          rimShellMesh.geometry.dispose();
          rimShellMesh.geometry = new THREE.SphereGeometry(v, 64, 64);
        }
      }, function () { return RIM_SHELL_RADIUS; })
    );
  }

  function appendGlobeTransformSection(panel) {
    panel.appendChild(makeHeading("Globe Transform"));
    panel.appendChild(
      makeSlider("Rotation X", -180, 180, 1, globeGroup ? globeGroup.rotation.x * 180 / Math.PI : 0, function (v) {
        if (globeGroup) globeGroup.rotation.x = v * Math.PI / 180;
      }, function () { return globeGroup ? globeGroup.rotation.x * 180 / Math.PI : null; })
    );
    panel.appendChild(
      makeSlider("Rotation Y", -180, 180, 1, globeGroup ? globeGroup.rotation.y * 180 / Math.PI : 0, function (v) {
        if (globeGroup) globeGroup.rotation.y = v * Math.PI / 180;
      }, function () { return globeGroup ? globeGroup.rotation.y * 180 / Math.PI : null; })
    );
    panel.appendChild(
      makeSlider("Rotation Z", -180, 180, 1, globeGroup ? globeGroup.rotation.z * 180 / Math.PI : 0, function (v) {
        if (globeGroup) globeGroup.rotation.z = v * Math.PI / 180;
      }, function () { return globeGroup ? globeGroup.rotation.z * 180 / Math.PI : null; })
    );

    panel.appendChild(makeToggle("Auto-rotate", autoRotate,
      function (v) { autoRotate = v; },
      function () { return autoRotate; }
    ));
  }

  function appendBloomSection(panel) {
    panel.appendChild(makeHeading("Bloom" + (bloomPass ? "" : " (unavailable)")));
    if (!bloomPass) {
      var bloomNote = document.createElement("div");
      bloomNote.style.cssText = "color:#f88;font-size:10px;margin:3px 0;";
      bloomNote.textContent = "Bloom not active — check console for missing deps";
      panel.appendChild(bloomNote);
    }
    panel.appendChild(
      makeSlider("Strength", 0, 5, 0.05, bloomPass ? bloomPass.strength : BLOOM_STRENGTH, function (v) {
        if (bloomPass) bloomPass.strength = v;
      }, function () { return bloomPass ? bloomPass.strength : null; })
    );
    panel.appendChild(
      makeSlider("Radius", 0, 1, 0.01, bloomPass ? bloomPass.radius : BLOOM_RADIUS, function (v) {
        if (bloomPass) bloomPass.radius = v;
      }, function () { return bloomPass ? bloomPass.radius : null; })
    );
    panel.appendChild(
      makeSlider("Threshold", 0, 1, 0.01, bloomPass ? bloomPass.threshold : BLOOM_THRESHOLD, function (v) {
        if (bloomPass) bloomPass.threshold = v;
      }, function () { return bloomPass ? bloomPass.threshold : null; })
    );
    panel.appendChild(
      makeSlider("Exposure", 0.1, 5, 0.1, renderer ? renderer.toneMappingExposure : 1.5, function (v) {
        if (renderer) renderer.toneMappingExposure = v;
      }, function () { return renderer ? renderer.toneMappingExposure : null; })
    );
  }

  function appendOverlayGridSection(panel) {
    panel.appendChild(makeHeading("Overlay Grid"));

    panel.appendChild(makeColorPicker("Grid Color", "#ffffff",
      function (v) {
        wireBaseColor.set(v);
        if (wireframeMesh) {
          wireframeMesh.material.color.copy(wireBaseColor).multiplyScalar(wireGlowIntensity);
        }
      },
      function () { return "#" + wireBaseColor.getHexString(); }
    ));

    panel.appendChild(
      makeSlider("Grid Glow", 0, 5, 0.1, wireGlowIntensity, function (v) {
        wireGlowIntensity = v;
        if (wireframeMesh) {
          wireframeMesh.material.color.copy(wireBaseColor).multiplyScalar(v);
        }
      }, function () { return wireGlowIntensity; })
    );

    panel.appendChild(
      makeSlider("Line Width", 0.5, 5, 0.5, wireframeMesh ? wireframeMesh.material.linewidth || 1 : 1, function (v) {
        if (wireframeMesh) {
          wireframeMesh.material.linewidth = v;
          wireframeMesh.material.needsUpdate = true;
        }
      }, function () { return wireframeMesh ? wireframeMesh.material.linewidth : null; })
    );
    panel.appendChild(
      makeSlider("Grid Opacity", 0, 1, 0.05, wireframeMesh ? wireframeMesh.material.opacity : WIREFRAME_OPACITY, function (v) {
        if (wireframeMesh) {
          wireframeMesh.material.opacity = v;
          wireframeMesh.material.transparent = v < 1;
          wireframeMesh.material.needsUpdate = true;
        }
      }, function () { return wireframeMesh ? wireframeMesh.material.opacity : null; })
    );

    panel.appendChild(makeToggle("Dashed", true,
      function (v) {
        if (!wireframeMesh) return;
        wireframeMesh.material.dashed = v;
        wireframeMesh.material.needsUpdate = true;
      },
      function () { return wireframeMesh ? !!wireframeMesh.material.dashed : null; }
    ));

    panel.appendChild(
      makeSlider("Dash Size", 0.05, 2, 0.05, 0.7, function (v) {
        if (wireframeMesh) {
          wireframeMesh.material.dashSize = v;
        }
      }, function () { return wireframeMesh ? wireframeMesh.material.dashSize : null; })
    );
    panel.appendChild(
      makeSlider("Gap Size", 0.05, 2, 0.05, 1.5, function (v) {
        if (wireframeMesh) {
          wireframeMesh.material.gapSize = v;
        }
      }, function () { return wireframeMesh ? wireframeMesh.material.gapSize : null; })
    );

    panel.appendChild(
      makeSlider("Line Offset", 0, 10, 0.5, WIREFRAME_RADIUS - GLOBE_RADIUS, function (v) {
        if (wireframeMesh && globeGroup) {
          var newRadius = GLOBE_RADIUS + v;
          var oldMat = wireframeMesh.material;
          wireframeMesh.geometry.dispose();
          globeGroup.remove(wireframeMesh);

          if (THREE.LineSegmentsGeometry && THREE.LineMaterial && THREE.LineSegments2) {
            var icoGeo = new THREE.IcosahedronGeometry(newRadius, 5);
            var wireGeo = new THREE.WireframeGeometry(icoGeo);
            var lineGeo = new THREE.LineSegmentsGeometry();
            lineGeo.setPositions(wireGeo.attributes.position.array);
            wireframeMesh = new THREE.LineSegments2(lineGeo, oldMat);
            wireframeMesh.computeLineDistances();
            icoGeo.dispose();
            wireGeo.dispose();
          } else {
            wireframeMesh = new THREE.Mesh(
              new THREE.IcosahedronGeometry(newRadius, 5),
              oldMat
            );
          }

          globeGroup.add(wireframeMesh);

          if (gridDotsMesh) {
            gridDotsMesh.geometry.dispose();
            gridDotsMesh.material.dispose();
            globeGroup.remove(gridDotsMesh);
          }
          gridDotsMesh = createGridDots(newRadius);
          globeGroup.add(gridDotsMesh);
        }
      })
    );
  }

  function appendDotGridSection(panel) {
    panel.appendChild(makeColorPicker("Dot Color", GRID_DOT_COLOR,
      function (v) {
        GRID_DOT_COLOR = v;
        dotBaseColor.set(v);
        if (gridDotsMesh) {
          gridDotsMesh.material.color.copy(dotBaseColor).multiplyScalar(dotGlowIntensity);
        }
      },
      function () { return "#" + dotBaseColor.getHexString(); }
    ));

    panel.appendChild(
      makeSlider("Dot Size", 1, 10, 0.5, GRID_DOT_SIZE, function (v) {
        GRID_DOT_SIZE = v;
        if (gridDotsMesh) {
          gridDotsMesh.material.size = v;
        }
      }, function () { return GRID_DOT_SIZE; })
    );

    panel.appendChild(
      makeSlider("Dot Glow", 0, 5, 0.1, dotGlowIntensity, function (v) {
        dotGlowIntensity = v;
        if (gridDotsMesh) {
          gridDotsMesh.material.color.copy(dotBaseColor).multiplyScalar(v);
        }
      }, function () { return dotGlowIntensity; })
    );
  }

  function appendConnectorSection(panel) {
    panel.appendChild(makeHeading("Connectors"));

    panel.appendChild(makeToggle("Dashed", connectorDashed,
      function (v) { connectorDashed = v; },
      function () { return connectorDashed; }
    ));

    panel.appendChild(
      makeSlider("Line Width", 0.5, 5, 0.5, connectorWidth, function (v) {
        connectorWidth = v;
      }, function () { return connectorWidth; })
    );

    panel.appendChild(
      makeSlider("Dash Size", 0.5, 10, 0.5, connectorDashSize, function (v) {
        connectorDashSize = v;
      }, function () { return connectorDashSize; })
    );
    panel.appendChild(
      makeSlider("Gap Size", 0.5, 10, 0.5, connectorGapSize, function (v) {
        connectorGapSize = v;
      }, function () { return connectorGapSize; })
    );

    panel.appendChild(makeColorPicker("Conn Dot Color", CONNECTOR_DOT_COLOR,
      function (v) {
        CONNECTOR_DOT_COLOR = v;
        connDotBaseColor.set(v);
        if (connectorDotsMesh) {
          connectorDotsMesh.material.color.copy(connDotBaseColor).multiplyScalar(connDotGlowIntensity);
        }
      },
      function () { return "#" + connDotBaseColor.getHexString(); }
    ));

    panel.appendChild(
      makeSlider("Conn Dot Size", 1, 15, 0.5, CONNECTOR_DOT_SIZE, function (v) {
        CONNECTOR_DOT_SIZE = v;
        if (connectorDotsMesh) connectorDotsMesh.material.size = v;
      }, function () { return CONNECTOR_DOT_SIZE; })
    );

    panel.appendChild(
      makeSlider("Conn Dot Glow", 0, 5, 0.1, connDotGlowIntensity, function (v) {
        connDotGlowIntensity = v;
        if (connectorDotsMesh) {
          connectorDotsMesh.material.color.copy(connDotBaseColor).multiplyScalar(v);
        }
      }, function () { return connDotGlowIntensity; })
    );
  }

  function appendCityControlsSection(panel) {
    panel.appendChild(makeCollapsibleGroup("Why-Us Points", true, function (container) {
      for (var ci = 0; ci < POINTS_DATA.length; ci++) {
        (function (i) {
          container.appendChild(makeHeading(POINTS_DATA[i].label));
          container.appendChild(
            makeSlider("Lat", -90, 90, 0.5, POINTS_DATA[i].lat, function (v) {
              POINTS_DATA[i].lat = v;
              updateConnectorDotPosition(i);
            }, function () { return POINTS_DATA[i].lat; })
          );
          container.appendChild(
            makeSlider("Lng", -180, 180, 0.5, POINTS_DATA[i].lng, function (v) {
              POINTS_DATA[i].lng = v;
              updateConnectorDotPosition(i);
            }, function () { return POINTS_DATA[i].lng; })
          );
        })(ci);
      }
    }));
  }

  function appendDebugInfoSection(panel) {
    panel.appendChild(makeHeading("Debug Info"));
    debugInfoEl = document.createElement("div");
    debugInfoEl.style.cssText =
      "font-size:9px;font-family:monospace;color:#aaa;line-height:1.4;";
    debugInfoEl.textContent = "rot.x: 0  rot.y: 0\nlat: 0  lng: 0";
    panel.appendChild(debugInfoEl);
  }

  function appendSnapshotSection(panel) {
    var captureBtn = document.createElement("button");
    captureBtn.textContent = "Capture";
    captureBtn.style.cssText =
      "margin:8px 0 4px;padding:4px 12px;background:#1a6;color:#fff;border:none;" +
      "border-radius:3px;font-size:10px;cursor:pointer;width:100%;";
    captureBtn.addEventListener("click", function () {
      var snapshot = {
        lighting: {
          ambient: ambientLight ? ambientLight.intensity : null,
          key: keyLight ? keyLight.intensity : null,
          fill: fillLight ? fillLight.intensity : null,
          backLight: {
            enabled: backLight ? backLight.visible : null,
            intensity: backLight ? backLight.intensity : null,
            color: backLight ? "#" + backLight.color.getHexString() : null,
            position: backLight ? { x: backLight.position.x, y: backLight.position.y, z: backLight.position.z } : null,
          },
          spotLights: spotLights.map(function (l, i) {
            return {
              index: i + 1,
              enabled: l ? l.visible : null,
              intensity: l ? l.intensity : null,
              color: l ? "#" + l.color.getHexString() : null,
              position: l ? { x: l.position.x, y: l.position.y, z: l.position.z } : null,
            };
          }),
        },
        bloom: {
          active: !!bloomPass,
          strength: bloomPass ? bloomPass.strength : BLOOM_STRENGTH,
          radius: bloomPass ? bloomPass.radius : BLOOM_RADIUS,
          threshold: bloomPass ? bloomPass.threshold : BLOOM_THRESHOLD,
        },
        toneMapping: {
          type: renderer ? renderer.toneMapping : null,
          exposure: renderer ? renderer.toneMappingExposure : null,
        },
        camera: {
          fov: camera ? camera.fov : null,
          position: camera ? { x: camera.position.x, y: camera.position.y, z: camera.position.z } : null,
          lookAt: { x: cameraTarget.x, y: cameraTarget.y, z: cameraTarget.z },
        },
        globe: {
          baseColor: GLOBE_BASE_COLOR,
          color: globe ? "#" + globe.globeMaterial().color.getHexString() : null,
          opacity: globe ? globe.globeMaterial().opacity : null,
          emissiveIntensity: EMISSIVE_INTENSITY,
          rotation: globeGroup ? {
            x: +(globeGroup.rotation.x * 180 / Math.PI).toFixed(2),
            y: +(globeGroup.rotation.y * 180 / Math.PI).toFixed(2),
            z: +(globeGroup.rotation.z * 180 / Math.PI).toFixed(2),
          } : null,
          autoRotate: autoRotate,
        },
        vietnamTile: {
          enabled: vietnamTileMesh ? vietnamTileMesh.visible : null,
          size: VIETNAM_TILE_SIZE,
          altitude: VIETNAM_TILE_ALTITUDE,
          lat: VIETNAM_TILE_LAT,
          lng: VIETNAM_TILE_LNG,
          rotation: {
            x: VIETNAM_TILE_ROTATE_X,
            y: VIETNAM_TILE_ROTATE_Y,
            z: VIETNAM_TILE_ROTATE_Z,
          },
          offset: {
            x: VIETNAM_TILE_OFFSET_X,
            y: VIETNAM_TILE_OFFSET_Y,
            z: VIETNAM_TILE_OFFSET_Z,
          },
          position: vietnamTileMesh ? {
            x: +vietnamTileMesh.position.x.toFixed(2),
            y: +vietnamTileMesh.position.y.toFixed(2),
            z: +vietnamTileMesh.position.z.toFixed(2),
          } : null,
        },
        rimGlow: {
          enabled: rimShellMesh ? rimShellMesh.visible : null,
          color: rimShellMesh ? "#" + rimShellMesh.material.uniforms.rimColor.value.getHexString() : RIM_SHELL_COLOR,
          intensity: rimShellMesh ? rimShellMesh.material.uniforms.rimIntensity.value : RIM_SHELL_INTENSITY,
          power: rimShellMesh ? rimShellMesh.material.uniforms.rimPower.value : RIM_SHELL_POWER,
          opacity: rimShellMesh ? rimShellMesh.material.uniforms.rimOpacity.value : RIM_SHELL_OPACITY,
          radius: RIM_SHELL_RADIUS,
        },
        overlayGrid: {
          wireframeColor: "#" + wireBaseColor.getHexString(),
          wireGlowIntensity: wireGlowIntensity,
          lineWidth: wireframeMesh ? wireframeMesh.material.linewidth || 1 : null,
          opacity: wireframeMesh ? wireframeMesh.material.opacity : WIREFRAME_OPACITY,
          wireframeRadius: WIREFRAME_RADIUS,
          dashed: wireframeMesh ? !!wireframeMesh.material.dashed : false,
          dashSize: wireframeMesh ? wireframeMesh.material.dashSize : 0.7,
          gapSize: wireframeMesh ? wireframeMesh.material.gapSize : 1.5,
          lineOffset: WIREFRAME_RADIUS - GLOBE_RADIUS,
        },
        dotGrid: {
          color: "#" + dotBaseColor.getHexString(),
          size: GRID_DOT_SIZE,
          glowIntensity: dotGlowIntensity,
        },
        connectors: {
          color: connectorColor,
          width: connectorWidth,
          dashed: connectorDashed,
          dashSize: connectorDashSize,
          gapSize: connectorGapSize,
          dotColor: "#" + connDotBaseColor.getHexString(),
          dotSize: CONNECTOR_DOT_SIZE,
          dotGlow: connDotGlowIntensity,
        },
      };
      console.log("[NSCGlobe] Capture:", JSON.stringify(snapshot, null, 2));
      console.log("[NSCGlobe] Capture (object):", snapshot);
    });
    panel.appendChild(captureBtn);
  }

  // ---------------------------------------------------------------------------
  // Debug Panel (localhost only)
  // ---------------------------------------------------------------------------

  function createDebugPanel() {
    var host = location.hostname;
    if (host !== "localhost" && host !== "127.0.0.1") return;

    sliderRegistry = [];
    toggleRegistry = [];
    colorRegistry = [];

    // Overlay wrapper — sits outside .why-us so overflow:hidden cannot clip it
    var wrapper = document.createElement("div");
    wrapper.style.cssText =
      "position:fixed;z-index:10001;pointer-events:none;top:0;left:0;width:0;height:0;";
    document.body.appendChild(wrapper);
    debugWrapper = wrapper;

    // Gear toggle button
    var btn = document.createElement("button");
    btn.textContent = "\u2699";
    btn.style.cssText =
      "position:fixed;bottom:16px;right:16px;z-index:10000;width:32px;height:32px;" +
      "border:none;border-radius:50%;background:rgba(0,0,0,0.5);color:#36C3DC;" +
      "font-size:18px;cursor:pointer;line-height:32px;text-align:center;padding:0;" +
      "pointer-events:auto;";
    wrapper.appendChild(btn);
    debugGearBtn = btn;

    // Panel
    var panel = document.createElement("div");
    panel.style.cssText =
      "position:fixed;bottom:56px;right:16px;z-index:10000;background:rgba(0,0,0,0.85);" +
      "color:#ccc;font:11px/1.6 monospace;padding:10px 12px;border-radius:6px;" +
      "max-height:70vh;overflow-y:auto;display:none;min-width:220px;pointer-events:auto;";
    wrapper.appendChild(panel);
    debugPanel = panel;

    btn.addEventListener("click", function () {
      panel.style.display = panel.style.display === "none" ? "block" : "none";
    });

    // Append all sections
    appendLightingSection(panel);
    appendBackLightAndSpotsSection(panel);
    appendCameraSection(panel);
    appendGlobeMaterialSection(panel);
    appendVietnamTileSection(panel);
    appendRimGlowSection(panel);
    appendGlobeTransformSection(panel);
    appendBloomSection(panel);
    appendOverlayGridSection(panel);
    appendDotGridSection(panel);
    appendConnectorSection(panel);
    appendCityControlsSection(panel);
    appendDebugInfoSection(panel);
    appendSnapshotSection(panel);
  }

  function toggleDebug() {
    if (debugPanel) {
      debugPanel.style.display =
        debugPanel.style.display === "none" ? "block" : "none";
    }
  }

  // ---------------------------------------------------------------------------
  // Dispose Helpers
  // ---------------------------------------------------------------------------

  function disposeTimers() {
    if (animFrameId) {
      cancelAnimationFrame(animFrameId);
      animFrameId = null;
    }
    clearTimeout(autoRotateResumeTimer);
    clearTimeout(resizeTimer);
    window.removeEventListener("resize", onResize);
  }

  function disposeSVGOverlay() {
    if (svgOverlay) {
      svgOverlay.remove();
      svgOverlay = null;
    }
  }

  function disposePostProcessing() {
    if (composer) {
      composer.renderTarget1.dispose();
      composer.renderTarget2.dispose();
      composer = null;
      bloomPass = null;
    }
  }

  function disposeDebugUI() {
    debugInfoEl = null;
    if (debugWrapper) {
      debugWrapper.remove();
      debugWrapper = null;
    }
    debugGearBtn = null;
    debugPanel = null;
    sliderRegistry = [];
    toggleRegistry = [];
    colorRegistry = [];
    ambientLight = null;
    keyLight = null;
    fillLight = null;
    backLight = null;
    spotLights = [null, null, null, null];
  }

  function disposeRenderer() {
    if (renderer) {
      renderer.forceContextLoss();
      renderer.dispose();
      if (renderer.domElement && renderer.domElement.parentNode) {
        renderer.domElement.parentNode.removeChild(renderer.domElement);
      }
      renderer = null;
    }
  }

  function disposeSceneGraph() {
    if (scene) {
      scene.traverse(function (obj) {
        if (obj.geometry) obj.geometry.dispose();
        if (obj.material) {
          if (Array.isArray(obj.material)) {
            obj.material.forEach(function (m) {
              m.dispose();
            });
          } else {
            obj.material.dispose();
          }
        }
      });
      scene = null;
    }
  }

  function disposeMeshes() {
    globe = null;
    if (vietnamTileMesh) {
      vietnamTileMesh.geometry.dispose();
      vietnamTileMesh.material.map.dispose();
      vietnamTileMesh.material.dispose();
      vietnamTileMesh = null;
    }
    if (rimShellMesh) {
      rimShellMesh.geometry.dispose();
      rimShellMesh.material.dispose();
      rimShellMesh = null;
    }
    wireframeMesh = null;
    gridDotsMesh = null;
    globeGroup = null;
    camera = null;
    autoRotate = false;
  }

  // ---------------------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------------------

  function dispose() {
    if (isDisposed) return;
    isDisposed = true;
    isInitialized = false;

    disposeTimers();
    disposeSVGOverlay();
    disposePostProcessing();
    disposeDebugUI();
    disposeRenderer();
    disposeSceneGraph();
    disposeMeshes();
  }

  /**
   * Main initialization function.
   * Fetches world topology, builds the globe, and starts animation.
   */
  function init() {
    // Guard: WebGL support
    if (typeof WebGLRenderingContext === "undefined") return;

    // Guard: required globals
    if (
      typeof THREE === "undefined" ||
      typeof ThreeGlobe === "undefined" ||
      typeof TWEEN === "undefined"
    ) {
      console.warn(
        "[NSCGlobe] Missing required dependencies. Globe will not render."
      );
      return;
    }

    // Prevent duplicate initialization
    if (isInitialized) return;

    isDisposed = false;

    if (!initScene()) return;

    fetch(GEOJSON_URL)
      .then(function (res) {
        if (!res.ok)
          throw new Error("Failed to fetch country data: " + res.status);
        return res.json();
      })
      .then(function (geoData) {
        // Guard against disposal during fetch
        if (isDisposed) return;

        buildGlobe(geoData.features);
        attachHoverListeners();
        window.addEventListener("resize", onResize);

        isInitialized = true;
        createDebugPanel();
        animate();
      })
      .catch(function (err) {
        console.error("[NSCGlobe] Initialization failed:", err);
      });
  }

  // ---------------------------------------------------------------------------
  // Lazy Loading with IntersectionObserver
  // ---------------------------------------------------------------------------

  function setupLazyLoad() {
    var section = document.getElementById("why-us");
    if (!section) return;

    // Fallback: no IntersectionObserver support
    if (typeof IntersectionObserver === "undefined") {
      init();
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            if (!isInitialized) {
              init();
            }
          } else {
            if (isInitialized) {
              dispose();
            }
          }
        });
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

  window.NSCGlobe = {
    instance: {
      rotateToPoint: rotateToPoint,
      dispose: dispose,
      init: init,
      toggleDebug: toggleDebug,
    },
  };
})();
