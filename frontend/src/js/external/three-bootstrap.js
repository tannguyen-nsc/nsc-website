/**
 * three-bootstrap.js
 *
 * Single-source three.js bootstrap (r177, classic WebGL build).
 * Loaded as <script type="module"> AFTER the importmap and BEFORE every
 * global-THREE consumer (three-globe UMD, tween, globe.js) — module scripts
 * without `async` share the in-order post-parse queue with `defer` scripts,
 * so window.THREE is guaranteed to exist before those evaluate.
 *
 * Responsibilities:
 *  - Import the one classic three core ('three' -> three.module.js 0.177).
 *  - Attach the post-processing + fat-lines addons onto a MUTABLE plain-object
 *    copy of the namespace (module namespace objects are frozen; assigning
 *    onto them throws in strict mode).
 *  - Publish it as window.THREE and dispatch 'three-addons-ready'. Note:
 *    because this script always evaluates (and dispatches) before globe.js
 *    registers its listener, that listener never actually fires — bloom
 *    setup relies on the direct THREE.EffectComposer check at init() time.
 *
 * NOTE: never import 'three/webgpu' / 'three/tsl' here — those belong to
 * wave-three.js and must stay a separate graph.
 */
import * as THREE_NS from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';
import { LineSegmentsGeometry } from 'three/addons/lines/LineSegmentsGeometry.js';
import { LineMaterial } from 'three/addons/lines/LineMaterial.js';
import { LineSegments2 } from 'three/addons/lines/LineSegments2.js';

var THREE = Object.assign({}, THREE_NS); // namespace objects are frozen; copy before extending
THREE.EffectComposer = EffectComposer;
THREE.RenderPass = RenderPass;
THREE.ShaderPass = ShaderPass;
THREE.UnrealBloomPass = UnrealBloomPass;
THREE.OutputPass = OutputPass;
THREE.LineSegmentsGeometry = LineSegmentsGeometry;
THREE.LineMaterial = LineMaterial;
THREE.LineSegments2 = LineSegments2;
window.THREE = THREE;
window.dispatchEvent(new Event('three-addons-ready'));
