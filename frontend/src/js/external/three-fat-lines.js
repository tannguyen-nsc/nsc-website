/**
 * Three.js Fat Lines (LineSegments2 / LineMaterial / LineSegmentsGeometry)
 *
 * Vendored from Three.js r160 examples/jsm/lines/ and adapted for global
 * THREE namespace (non-module script tag usage).
 *
 * Source:
 *   https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/lines/LineSegmentsGeometry.js
 *   https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/lines/LineMaterial.js
 *   https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/lines/LineSegments2.js
 *
 * License: MIT (Three.js)
 */
(function () {
  "use strict";

  if (typeof THREE === "undefined") {
    console.warn("[three-fat-lines] THREE is not defined. Skipping.");
    return;
  }

  // =========================================================================
  // LineSegmentsGeometry
  // =========================================================================

  var Box3 = THREE.Box3;
  var Float32BufferAttribute = THREE.Float32BufferAttribute;
  var InstancedBufferGeometry = THREE.InstancedBufferGeometry;
  var InstancedInterleavedBuffer = THREE.InstancedInterleavedBuffer;
  var InterleavedBufferAttribute = THREE.InterleavedBufferAttribute;
  var Sphere = THREE.Sphere;
  var Vector3 = THREE.Vector3;
  var WireframeGeometry = THREE.WireframeGeometry;

  var _box$1 = new Box3();
  var _vector = new Vector3();

  class LineSegmentsGeometry extends InstancedBufferGeometry {
    constructor() {
      super();

      this.isLineSegmentsGeometry = true;
      this.type = "LineSegmentsGeometry";

      var positions = [-1, 2, 0, 1, 2, 0, -1, 1, 0, 1, 1, 0, -1, 0, 0, 1, 0, 0, -1, -1, 0, 1, -1, 0];
      var uvs = [-1, 2, 1, 2, -1, 1, 1, 1, -1, -1, 1, -1, -1, -2, 1, -2];
      var index = [0, 2, 1, 2, 3, 1, 2, 4, 3, 4, 5, 3, 4, 6, 5, 6, 7, 5];

      this.setIndex(index);
      this.setAttribute("position", new Float32BufferAttribute(positions, 3));
      this.setAttribute("uv", new Float32BufferAttribute(uvs, 2));
    }

    applyMatrix4(matrix) {
      var start = this.attributes.instanceStart;
      var end = this.attributes.instanceEnd;

      if (start !== undefined) {
        start.applyMatrix4(matrix);
        end.applyMatrix4(matrix);
        start.needsUpdate = true;
      }

      if (this.boundingBox !== null) {
        this.computeBoundingBox();
      }

      if (this.boundingSphere !== null) {
        this.computeBoundingSphere();
      }

      return this;
    }

    setPositions(array) {
      var lineSegments;

      if (array instanceof Float32Array) {
        lineSegments = array;
      } else if (Array.isArray(array)) {
        lineSegments = new Float32Array(array);
      }

      var instanceBuffer = new InstancedInterleavedBuffer(lineSegments, 6, 1);

      this.setAttribute("instanceStart", new InterleavedBufferAttribute(instanceBuffer, 3, 0));
      this.setAttribute("instanceEnd", new InterleavedBufferAttribute(instanceBuffer, 3, 3));

      this.computeBoundingBox();
      this.computeBoundingSphere();

      return this;
    }

    setColors(array) {
      var colors;

      if (array instanceof Float32Array) {
        colors = array;
      } else if (Array.isArray(array)) {
        colors = new Float32Array(array);
      }

      var instanceColorBuffer = new InstancedInterleavedBuffer(colors, 6, 1);

      this.setAttribute("instanceColorStart", new InterleavedBufferAttribute(instanceColorBuffer, 3, 0));
      this.setAttribute("instanceColorEnd", new InterleavedBufferAttribute(instanceColorBuffer, 3, 3));

      return this;
    }

    fromWireframeGeometry(geometry) {
      this.setPositions(geometry.attributes.position.array);
      return this;
    }

    fromEdgesGeometry(geometry) {
      this.setPositions(geometry.attributes.position.array);
      return this;
    }

    fromMesh(mesh) {
      this.fromWireframeGeometry(new WireframeGeometry(mesh.geometry));
      return this;
    }

    fromLineSegments(lineSegments) {
      var geometry = lineSegments.geometry;
      this.setPositions(geometry.attributes.position.array);
      return this;
    }

    computeBoundingBox() {
      if (this.boundingBox === null) {
        this.boundingBox = new Box3();
      }

      var start = this.attributes.instanceStart;
      var end = this.attributes.instanceEnd;

      if (start !== undefined && end !== undefined) {
        this.boundingBox.setFromBufferAttribute(start);
        _box$1.setFromBufferAttribute(end);
        this.boundingBox.union(_box$1);
      }
    }

    computeBoundingSphere() {
      if (this.boundingSphere === null) {
        this.boundingSphere = new Sphere();
      }

      if (this.boundingBox === null) {
        this.computeBoundingBox();
      }

      var start = this.attributes.instanceStart;
      var end = this.attributes.instanceEnd;

      if (start !== undefined && end !== undefined) {
        var center = this.boundingSphere.center;

        this.boundingBox.getCenter(center);

        var maxRadiusSq = 0;

        for (var i = 0, il = start.count; i < il; i++) {
          _vector.fromBufferAttribute(start, i);
          maxRadiusSq = Math.max(maxRadiusSq, center.distanceToSquared(_vector));

          _vector.fromBufferAttribute(end, i);
          maxRadiusSq = Math.max(maxRadiusSq, center.distanceToSquared(_vector));
        }

        this.boundingSphere.radius = Math.sqrt(maxRadiusSq);

        if (isNaN(this.boundingSphere.radius)) {
          console.error(
            "THREE.LineSegmentsGeometry.computeBoundingSphere(): Computed radius is NaN. The instanced position data is likely to have NaN values.",
            this
          );
        }
      }
    }

    toJSON() {
      // todo
    }

    applyMatrix(matrix) {
      console.warn("THREE.LineSegmentsGeometry: applyMatrix() has been renamed to applyMatrix4().");
      return this.applyMatrix4(matrix);
    }
  }

  // =========================================================================
  // LineMaterial
  // =========================================================================

  var ShaderLib = THREE.ShaderLib;
  var ShaderMaterial = THREE.ShaderMaterial;
  var UniformsLib = THREE.UniformsLib;
  var UniformsUtils = THREE.UniformsUtils;
  var Vector2 = THREE.Vector2;

  UniformsLib.line = {
    worldUnits: { value: 1 },
    linewidth: { value: 1 },
    resolution: { value: new Vector2(1, 1) },
    dashOffset: { value: 0 },
    dashScale: { value: 1 },
    dashSize: { value: 1 },
    gapSize: { value: 1 },
  };

  ShaderLib["line"] = {
    uniforms: UniformsUtils.merge([
      UniformsLib.common,
      UniformsLib.fog,
      UniformsLib.line,
    ]),

    vertexShader: /* glsl */ [
      "#include <common>",
      "#include <color_pars_vertex>",
      "#include <fog_pars_vertex>",
      "#include <logdepthbuf_pars_vertex>",
      "#include <clipping_planes_pars_vertex>",
      "",
      "uniform float linewidth;",
      "uniform vec2 resolution;",
      "",
      "attribute vec3 instanceStart;",
      "attribute vec3 instanceEnd;",
      "",
      "attribute vec3 instanceColorStart;",
      "attribute vec3 instanceColorEnd;",
      "",
      "#ifdef WORLD_UNITS",
      "",
      "  varying vec4 worldPos;",
      "  varying vec3 worldStart;",
      "  varying vec3 worldEnd;",
      "",
      "  #ifdef USE_DASH",
      "",
      "    varying vec2 vUv;",
      "",
      "  #endif",
      "",
      "#else",
      "",
      "  varying vec2 vUv;",
      "",
      "#endif",
      "",
      "#ifdef USE_DASH",
      "",
      "  uniform float dashScale;",
      "  attribute float instanceDistanceStart;",
      "  attribute float instanceDistanceEnd;",
      "  varying float vLineDistance;",
      "",
      "#endif",
      "",
      "void trimSegment( const in vec4 start, inout vec4 end ) {",
      "",
      "  float a = projectionMatrix[ 2 ][ 2 ];",
      "  float b = projectionMatrix[ 3 ][ 2 ];",
      "  float nearEstimate = - 0.5 * b / a;",
      "",
      "  float alpha = ( nearEstimate - start.z ) / ( end.z - start.z );",
      "",
      "  end.xyz = mix( start.xyz, end.xyz, alpha );",
      "",
      "}",
      "",
      "void main() {",
      "",
      "  #ifdef USE_COLOR",
      "",
      "    vColor.xyz = ( position.y < 0.5 ) ? instanceColorStart : instanceColorEnd;",
      "",
      "  #endif",
      "",
      "  #ifdef USE_DASH",
      "",
      "    vLineDistance = ( position.y < 0.5 ) ? dashScale * instanceDistanceStart : dashScale * instanceDistanceEnd;",
      "    vUv = uv;",
      "",
      "  #endif",
      "",
      "  float aspect = resolution.x / resolution.y;",
      "",
      "  vec4 start = modelViewMatrix * vec4( instanceStart, 1.0 );",
      "  vec4 end = modelViewMatrix * vec4( instanceEnd, 1.0 );",
      "",
      "  #ifdef WORLD_UNITS",
      "",
      "    worldStart = start.xyz;",
      "    worldEnd = end.xyz;",
      "",
      "  #else",
      "",
      "    vUv = uv;",
      "",
      "  #endif",
      "",
      "  bool perspective = ( projectionMatrix[ 2 ][ 3 ] == - 1.0 );",
      "",
      "  if ( perspective ) {",
      "",
      "    if ( start.z < 0.0 && end.z >= 0.0 ) {",
      "",
      "      trimSegment( start, end );",
      "",
      "    } else if ( end.z < 0.0 && start.z >= 0.0 ) {",
      "",
      "      trimSegment( end, start );",
      "",
      "    }",
      "",
      "  }",
      "",
      "  vec4 clipStart = projectionMatrix * start;",
      "  vec4 clipEnd = projectionMatrix * end;",
      "",
      "  vec3 ndcStart = clipStart.xyz / clipStart.w;",
      "  vec3 ndcEnd = clipEnd.xyz / clipEnd.w;",
      "",
      "  vec2 dir = ndcEnd.xy - ndcStart.xy;",
      "",
      "  dir.x *= aspect;",
      "  dir = normalize( dir );",
      "",
      "  #ifdef WORLD_UNITS",
      "",
      "    vec3 worldDir = normalize( end.xyz - start.xyz );",
      "    vec3 tmpFwd = normalize( mix( start.xyz, end.xyz, 0.5 ) );",
      "    vec3 worldUp = normalize( cross( worldDir, tmpFwd ) );",
      "    vec3 worldFwd = cross( worldDir, worldUp );",
      "    worldPos = position.y < 0.5 ? start: end;",
      "",
      "    float hw = linewidth * 0.5;",
      "    worldPos.xyz += position.x < 0.0 ? hw * worldUp : - hw * worldUp;",
      "",
      "    #ifndef USE_DASH",
      "",
      "      worldPos.xyz += position.y < 0.5 ? - hw * worldDir : hw * worldDir;",
      "",
      "      worldPos.xyz += worldFwd * hw;",
      "",
      "      if ( position.y > 1.0 || position.y < 0.0 ) {",
      "",
      "        worldPos.xyz -= worldFwd * 2.0 * hw;",
      "",
      "      }",
      "",
      "    #endif",
      "",
      "    vec4 clip = projectionMatrix * worldPos;",
      "",
      "    vec3 clipPose = ( position.y < 0.5 ) ? ndcStart : ndcEnd;",
      "    clip.z = clipPose.z * clip.w;",
      "",
      "  #else",
      "",
      "    vec2 offset = vec2( dir.y, - dir.x );",
      "    dir.x /= aspect;",
      "    offset.x /= aspect;",
      "",
      "    if ( position.x < 0.0 ) offset *= - 1.0;",
      "",
      "    if ( position.y < 0.0 ) {",
      "",
      "      offset += - dir;",
      "",
      "    } else if ( position.y > 1.0 ) {",
      "",
      "      offset += dir;",
      "",
      "    }",
      "",
      "    offset *= linewidth;",
      "",
      "    offset /= resolution.y;",
      "",
      "    vec4 clip = ( position.y < 0.5 ) ? clipStart : clipEnd;",
      "",
      "    offset *= clip.w;",
      "",
      "    clip.xy += offset;",
      "",
      "  #endif",
      "",
      "  gl_Position = clip;",
      "",
      "  vec4 mvPosition = ( position.y < 0.5 ) ? start : end;",
      "",
      "  #include <logdepthbuf_vertex>",
      "  #include <clipping_planes_vertex>",
      "  #include <fog_vertex>",
      "",
      "}",
    ].join("\n"),

    fragmentShader: /* glsl */ [
      "uniform vec3 diffuse;",
      "uniform float opacity;",
      "uniform float linewidth;",
      "",
      "#ifdef USE_DASH",
      "",
      "  uniform float dashOffset;",
      "  uniform float dashSize;",
      "  uniform float gapSize;",
      "",
      "#endif",
      "",
      "varying float vLineDistance;",
      "",
      "#ifdef WORLD_UNITS",
      "",
      "  varying vec4 worldPos;",
      "  varying vec3 worldStart;",
      "  varying vec3 worldEnd;",
      "",
      "  #ifdef USE_DASH",
      "",
      "    varying vec2 vUv;",
      "",
      "  #endif",
      "",
      "#else",
      "",
      "  varying vec2 vUv;",
      "",
      "#endif",
      "",
      "#include <common>",
      "#include <color_pars_fragment>",
      "#include <fog_pars_fragment>",
      "#include <logdepthbuf_pars_fragment>",
      "#include <clipping_planes_pars_fragment>",
      "",
      "vec2 closestLineToLine(vec3 p1, vec3 p2, vec3 p3, vec3 p4) {",
      "",
      "  float mua;",
      "  float mub;",
      "",
      "  vec3 p13 = p1 - p3;",
      "  vec3 p43 = p4 - p3;",
      "",
      "  vec3 p21 = p2 - p1;",
      "",
      "  float d1343 = dot( p13, p43 );",
      "  float d4321 = dot( p43, p21 );",
      "  float d1321 = dot( p13, p21 );",
      "  float d4343 = dot( p43, p43 );",
      "  float d2121 = dot( p21, p21 );",
      "",
      "  float denom = d2121 * d4343 - d4321 * d4321;",
      "",
      "  float numer = d1343 * d4321 - d1321 * d4343;",
      "",
      "  mua = numer / denom;",
      "  mua = clamp( mua, 0.0, 1.0 );",
      "  mub = ( d1343 + d4321 * ( mua ) ) / d4343;",
      "  mub = clamp( mub, 0.0, 1.0 );",
      "",
      "  return vec2( mua, mub );",
      "",
      "}",
      "",
      "void main() {",
      "",
      "  #include <clipping_planes_fragment>",
      "",
      "  #ifdef USE_DASH",
      "",
      "    if ( vUv.y < - 1.0 || vUv.y > 1.0 ) discard;",
      "",
      "    if ( mod( vLineDistance + dashOffset, dashSize + gapSize ) > dashSize ) discard;",
      "",
      "  #endif",
      "",
      "  float alpha = opacity;",
      "",
      "  #ifdef WORLD_UNITS",
      "",
      "    vec3 rayEnd = normalize( worldPos.xyz ) * 1e5;",
      "    vec3 lineDir = worldEnd - worldStart;",
      "    vec2 params = closestLineToLine( worldStart, worldEnd, vec3( 0.0, 0.0, 0.0 ), rayEnd );",
      "",
      "    vec3 p1 = worldStart + lineDir * params.x;",
      "    vec3 p2 = rayEnd * params.y;",
      "    vec3 delta = p1 - p2;",
      "    float len = length( delta );",
      "    float norm = len / linewidth;",
      "",
      "    #ifndef USE_DASH",
      "",
      "      #ifdef USE_ALPHA_TO_COVERAGE",
      "",
      "        float dnorm = fwidth( norm );",
      "        alpha = 1.0 - smoothstep( 0.5 - dnorm, 0.5 + dnorm, norm );",
      "",
      "      #else",
      "",
      "        if ( norm > 0.5 ) {",
      "",
      "          discard;",
      "",
      "        }",
      "",
      "      #endif",
      "",
      "    #endif",
      "",
      "  #else",
      "",
      "    #ifdef USE_ALPHA_TO_COVERAGE",
      "",
      "      float a = vUv.x;",
      "      float b = ( vUv.y > 0.0 ) ? vUv.y - 1.0 : vUv.y + 1.0;",
      "      float len2 = a * a + b * b;",
      "      float dlen = fwidth( len2 );",
      "",
      "      if ( abs( vUv.y ) > 1.0 ) {",
      "",
      "        alpha = 1.0 - smoothstep( 1.0 - dlen, 1.0 + dlen, len2 );",
      "",
      "      }",
      "",
      "    #else",
      "",
      "      if ( abs( vUv.y ) > 1.0 ) {",
      "",
      "        float a = vUv.x;",
      "        float b = ( vUv.y > 0.0 ) ? vUv.y - 1.0 : vUv.y + 1.0;",
      "        float len2 = a * a + b * b;",
      "",
      "        if ( len2 > 1.0 ) discard;",
      "",
      "      }",
      "",
      "    #endif",
      "",
      "  #endif",
      "",
      "  vec4 diffuseColor = vec4( diffuse, alpha );",
      "",
      "  #include <logdepthbuf_fragment>",
      "  #include <color_fragment>",
      "",
      "  gl_FragColor = vec4( diffuseColor.rgb, alpha );",
      "",
      "  #include <tonemapping_fragment>",
      "  #include <colorspace_fragment>",
      "  #include <fog_fragment>",
      "  #include <premultiplied_alpha_fragment>",
      "",
      "}",
    ].join("\n"),
  };

  class LineMaterial extends ShaderMaterial {
    constructor(parameters) {
      super({
        type: "LineMaterial",
        uniforms: UniformsUtils.clone(ShaderLib["line"].uniforms),
        vertexShader: ShaderLib["line"].vertexShader,
        fragmentShader: ShaderLib["line"].fragmentShader,
        clipping: true,
      });

      this.isLineMaterial = true;

      this.setValues(parameters);
    }

    get color() {
      return this.uniforms.diffuse.value;
    }
    set color(value) {
      this.uniforms.diffuse.value = value;
    }

    get worldUnits() {
      return "WORLD_UNITS" in this.defines;
    }
    set worldUnits(value) {
      if (value === true) {
        this.defines.WORLD_UNITS = "";
      } else {
        delete this.defines.WORLD_UNITS;
      }
    }

    get linewidth() {
      return this.uniforms.linewidth.value;
    }
    set linewidth(value) {
      if (!this.uniforms.linewidth) return;
      this.uniforms.linewidth.value = value;
    }

    get dashed() {
      return "USE_DASH" in this.defines;
    }
    set dashed(value) {
      if (value === true !== this.dashed) {
        this.needsUpdate = true;
      }
      if (value === true) {
        this.defines.USE_DASH = "";
      } else {
        delete this.defines.USE_DASH;
      }
    }

    get dashScale() {
      return this.uniforms.dashScale.value;
    }
    set dashScale(value) {
      this.uniforms.dashScale.value = value;
    }

    get dashSize() {
      return this.uniforms.dashSize.value;
    }
    set dashSize(value) {
      this.uniforms.dashSize.value = value;
    }

    get dashOffset() {
      return this.uniforms.dashOffset.value;
    }
    set dashOffset(value) {
      this.uniforms.dashOffset.value = value;
    }

    get gapSize() {
      return this.uniforms.gapSize.value;
    }
    set gapSize(value) {
      this.uniforms.gapSize.value = value;
    }

    get opacity() {
      return this.uniforms.opacity.value;
    }
    set opacity(value) {
      if (!this.uniforms) return;
      this.uniforms.opacity.value = value;
    }

    get resolution() {
      return this.uniforms.resolution.value;
    }
    set resolution(value) {
      this.uniforms.resolution.value.copy(value);
    }

    get alphaToCoverage() {
      return "USE_ALPHA_TO_COVERAGE" in this.defines;
    }
    set alphaToCoverage(value) {
      if (!this.defines) return;
      if (value === true !== this.alphaToCoverage) {
        this.needsUpdate = true;
      }
      if (value === true) {
        this.defines.USE_ALPHA_TO_COVERAGE = "";
        this.extensions.derivatives = true;
      } else {
        delete this.defines.USE_ALPHA_TO_COVERAGE;
        this.extensions.derivatives = false;
      }
    }
  }

  // =========================================================================
  // LineSegments2
  // =========================================================================

  var Mesh = THREE.Mesh;
  var Line3 = THREE.Line3;
  var MathUtils = THREE.MathUtils;
  var Matrix4 = THREE.Matrix4;
  var Vector4 = THREE.Vector4;

  var _start = new Vector3();
  var _end = new Vector3();

  var _start4 = new Vector4();
  var _end4 = new Vector4();

  var _ssOrigin = new Vector4();
  var _ssOrigin3 = new Vector3();
  var _mvMatrix = new Matrix4();
  var _line = new Line3();
  var _closestPoint = new Vector3();

  var _box = new Box3();
  var _sphere = new Sphere();
  var _clipToWorldVector = new Vector4();

  var _ray, _lineWidth;

  function getWorldSpaceHalfWidth(camera, distance, resolution) {
    _clipToWorldVector.set(0, 0, -distance, 1.0).applyMatrix4(camera.projectionMatrix);
    _clipToWorldVector.multiplyScalar(1.0 / _clipToWorldVector.w);
    _clipToWorldVector.x = _lineWidth / resolution.width;
    _clipToWorldVector.y = _lineWidth / resolution.height;
    _clipToWorldVector.applyMatrix4(camera.projectionMatrixInverse);
    _clipToWorldVector.multiplyScalar(1.0 / _clipToWorldVector.w);

    return Math.abs(Math.max(_clipToWorldVector.x, _clipToWorldVector.y));
  }

  function raycastWorldUnits(lineSegments, intersects) {
    var matrixWorld = lineSegments.matrixWorld;
    var geometry = lineSegments.geometry;
    var instanceStart = geometry.attributes.instanceStart;
    var instanceEnd = geometry.attributes.instanceEnd;
    var segmentCount = Math.min(geometry.instanceCount, instanceStart.count);

    for (var i = 0, l = segmentCount; i < l; i++) {
      _line.start.fromBufferAttribute(instanceStart, i);
      _line.end.fromBufferAttribute(instanceEnd, i);

      _line.applyMatrix4(matrixWorld);

      var pointOnLine = new Vector3();
      var point = new Vector3();

      _ray.distanceSqToSegment(_line.start, _line.end, point, pointOnLine);
      var isInside = point.distanceTo(pointOnLine) < _lineWidth * 0.5;

      if (isInside) {
        intersects.push({
          point: point,
          pointOnLine: pointOnLine,
          distance: _ray.origin.distanceTo(point),
          object: lineSegments,
          face: null,
          faceIndex: i,
          uv: null,
          uv1: null,
        });
      }
    }
  }

  function raycastScreenSpace(lineSegments, camera, intersects) {
    var projectionMatrix = camera.projectionMatrix;
    var material = lineSegments.material;
    var resolution = material.resolution;
    var matrixWorld = lineSegments.matrixWorld;

    var geometry = lineSegments.geometry;
    var instanceStart = geometry.attributes.instanceStart;
    var instanceEnd = geometry.attributes.instanceEnd;
    var segmentCount = Math.min(geometry.instanceCount, instanceStart.count);

    var near = -camera.near;

    _ray.at(1, _ssOrigin);

    _ssOrigin.w = 1;
    _ssOrigin.applyMatrix4(camera.matrixWorldInverse);
    _ssOrigin.applyMatrix4(projectionMatrix);
    _ssOrigin.multiplyScalar(1 / _ssOrigin.w);

    _ssOrigin.x *= resolution.x / 2;
    _ssOrigin.y *= resolution.y / 2;
    _ssOrigin.z = 0;

    _ssOrigin3.copy(_ssOrigin);

    _mvMatrix.multiplyMatrices(camera.matrixWorldInverse, matrixWorld);

    for (var i = 0, l = segmentCount; i < l; i++) {
      _start4.fromBufferAttribute(instanceStart, i);
      _end4.fromBufferAttribute(instanceEnd, i);

      _start4.w = 1;
      _end4.w = 1;

      _start4.applyMatrix4(_mvMatrix);
      _end4.applyMatrix4(_mvMatrix);

      var isBehindCameraNear = _start4.z > near && _end4.z > near;
      if (isBehindCameraNear) {
        continue;
      }

      if (_start4.z > near) {
        var deltaDist = _start4.z - _end4.z;
        var t = (_start4.z - near) / deltaDist;
        _start4.lerp(_end4, t);
      } else if (_end4.z > near) {
        var deltaDist2 = _end4.z - _start4.z;
        var t2 = (_end4.z - near) / deltaDist2;
        _end4.lerp(_start4, t2);
      }

      _start4.applyMatrix4(projectionMatrix);
      _end4.applyMatrix4(projectionMatrix);

      _start4.multiplyScalar(1 / _start4.w);
      _end4.multiplyScalar(1 / _end4.w);

      _start4.x *= resolution.x / 2;
      _start4.y *= resolution.y / 2;

      _end4.x *= resolution.x / 2;
      _end4.y *= resolution.y / 2;

      _line.start.copy(_start4);
      _line.start.z = 0;

      _line.end.copy(_end4);
      _line.end.z = 0;

      var param = _line.closestPointToPointParameter(_ssOrigin3, true);
      _line.at(param, _closestPoint);

      var zPos = MathUtils.lerp(_start4.z, _end4.z, param);
      var isInClipSpace = zPos >= -1 && zPos <= 1;

      var isInside = _ssOrigin3.distanceTo(_closestPoint) < _lineWidth * 0.5;

      if (isInClipSpace && isInside) {
        _line.start.fromBufferAttribute(instanceStart, i);
        _line.end.fromBufferAttribute(instanceEnd, i);

        _line.start.applyMatrix4(matrixWorld);
        _line.end.applyMatrix4(matrixWorld);

        var pointOnLine = new Vector3();
        var point = new Vector3();

        _ray.distanceSqToSegment(_line.start, _line.end, point, pointOnLine);

        intersects.push({
          point: point,
          pointOnLine: pointOnLine,
          distance: _ray.origin.distanceTo(point),
          object: lineSegments,
          face: null,
          faceIndex: i,
          uv: null,
          uv1: null,
        });
      }
    }
  }

  class LineSegments2 extends Mesh {
    constructor(geometry, material) {
      if (geometry === undefined) geometry = new LineSegmentsGeometry();
      if (material === undefined) material = new LineMaterial({ color: Math.random() * 0xffffff });

      super(geometry, material);

      this.isLineSegments2 = true;
      this.type = "LineSegments2";
    }

    computeLineDistances() {
      var geometry = this.geometry;

      var instanceStart = geometry.attributes.instanceStart;
      var instanceEnd = geometry.attributes.instanceEnd;
      var lineDistances = new Float32Array(2 * instanceStart.count);

      for (var i = 0, j = 0, l = instanceStart.count; i < l; i++, j += 2) {
        _start.fromBufferAttribute(instanceStart, i);
        _end.fromBufferAttribute(instanceEnd, i);

        lineDistances[j] = j === 0 ? 0 : lineDistances[j - 1];
        lineDistances[j + 1] = lineDistances[j] + _start.distanceTo(_end);
      }

      var instanceDistanceBuffer = new InstancedInterleavedBuffer(lineDistances, 2, 1);

      geometry.setAttribute("instanceDistanceStart", new InterleavedBufferAttribute(instanceDistanceBuffer, 1, 0));
      geometry.setAttribute("instanceDistanceEnd", new InterleavedBufferAttribute(instanceDistanceBuffer, 1, 1));

      return this;
    }

    raycast(raycaster, intersects) {
      var worldUnits = this.material.worldUnits;
      var camera = raycaster.camera;

      if (camera === null && !worldUnits) {
        console.error(
          'LineSegments2: "Raycaster.camera" needs to be set in order to raycast against LineSegments2 while worldUnits is set to false.'
        );
      }

      var threshold = raycaster.params.Line2 !== undefined ? raycaster.params.Line2.threshold || 0 : 0;

      _ray = raycaster.ray;

      var matrixWorld = this.matrixWorld;
      var geometry = this.geometry;
      var material = this.material;

      _lineWidth = material.linewidth + threshold;

      if (geometry.boundingSphere === null) {
        geometry.computeBoundingSphere();
      }

      _sphere.copy(geometry.boundingSphere).applyMatrix4(matrixWorld);

      var sphereMargin;
      if (worldUnits) {
        sphereMargin = _lineWidth * 0.5;
      } else {
        var distanceToSphere = Math.max(camera.near, _sphere.distanceToPoint(_ray.origin));
        sphereMargin = getWorldSpaceHalfWidth(camera, distanceToSphere, material.resolution);
      }

      _sphere.radius += sphereMargin;

      if (_ray.intersectsSphere(_sphere) === false) {
        return;
      }

      if (geometry.boundingBox === null) {
        geometry.computeBoundingBox();
      }

      _box.copy(geometry.boundingBox).applyMatrix4(matrixWorld);

      var boxMargin;
      if (worldUnits) {
        boxMargin = _lineWidth * 0.5;
      } else {
        var distanceToBox = Math.max(camera.near, _box.distanceToPoint(_ray.origin));
        boxMargin = getWorldSpaceHalfWidth(camera, distanceToBox, material.resolution);
      }

      _box.expandByScalar(boxMargin);

      if (_ray.intersectsBox(_box) === false) {
        return;
      }

      if (worldUnits) {
        raycastWorldUnits(this, intersects);
      } else {
        raycastScreenSpace(this, camera, intersects);
      }
    }
  }

  // =========================================================================
  // Register on THREE namespace
  // =========================================================================

  THREE.LineSegmentsGeometry = LineSegmentsGeometry;
  THREE.LineMaterial = LineMaterial;
  THREE.LineSegments2 = LineSegments2;
})();
