/**
 * Three.js 3D Scaffold Hero Visualization
 *
 * Creates an animated 3D scaffold representing a SaaS architecture,
 * with assembly animation, breathing oscillation, and mouse-driven camera.
 * Renders on the #hero-canvas element in the landing page hero section.
 */

import * as THREE from 'three';

// ──────────────────────────────────────────────
// 1. Guard clauses — bail early if not needed
// ──────────────────────────────────────────────

const canvas = document.getElementById('hero-canvas');
if (!canvas || canvas.dataset.enabled === 'false') {
    // Canvas not present or explicitly disabled via feature flag
    // CSS gradient fallback handles the hero background
    throw new Error('[threejs-hero] Canvas not found or disabled. Skipping initialization.');
}

// Performance gate: skip on low-end devices or mobile
const isMobile = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile/i.test(navigator.userAgent);
const isLowEnd = navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4;

if (isMobile || isLowEnd) {
    canvas.style.display = 'none';
    throw new Error('[threejs-hero] Low-end device detected. Skipping 3D visualization.');
}

// ──────────────────────────────────────────────
// 2. Color palette (from design system)
// ──────────────────────────────────────────────

const COLORS = {
    primary:          0xc0c1ff,
    primaryContainer: 0x8083ff,
    secondary:        0x4cd7f6,
    ambient:          0x1b1b1f,
};

// ──────────────────────────────────────────────
// 3. Scene, camera, renderer setup
// ──────────────────────────────────────────────

const scene = new THREE.Scene();
scene.background = null; // Transparent — landing page bg shows through

const camera = new THREE.PerspectiveCamera(
    60,
    canvas.clientWidth / canvas.clientHeight,
    0.1,
    100
);
camera.position.set(0, 2, 8);
camera.lookAt(0, 0, 0);

const renderer = new THREE.WebGLRenderer({
    canvas,
    alpha: true,
    antialias: true,
});
renderer.setSize(canvas.clientWidth, canvas.clientHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

// ──────────────────────────────────────────────
// 4. Lighting
// ──────────────────────────────────────────────

const ambientLight = new THREE.AmbientLight(COLORS.ambient, 0.5);
scene.add(ambientLight);

const primaryLight = new THREE.PointLight(COLORS.primary, 0.8);
primaryLight.position.set(5, 5, 5);
scene.add(primaryLight);

const secondaryLight = new THREE.PointLight(COLORS.secondary, 0.3);
secondaryLight.position.set(-3, 2, -3);
scene.add(secondaryLight);

// ──────────────────────────────────────────────
// 5. Scaffold block definitions
// ──────────────────────────────────────────────

/**
 * Each block has:
 *   size     — [w, h, d] for BoxGeometry
 *   target   — final assembled position [x, y, z]
 *   label    — descriptive name (for readability)
 */
const blockDefs = [
    // Foundation layer
    { size: [4, 0.2, 3],     target: [0, -1.5, 0],      label: 'foundation' },
    // Service blocks (medium)
    { size: [2, 0.3, 1.5],   target: [-0.8, -0.9, 0.5], label: 'service-auth' },
    { size: [2, 0.3, 1.5],   target: [0.8, -0.9, -0.5], label: 'service-billing' },
    { size: [1.8, 0.3, 1.2], target: [0, -0.3, 0],      label: 'service-core' },
    // Model cubes (small)
    { size: [0.8, 0.8, 0.8], target: [-1.2, 0.6, 0.8],  label: 'model-user' },
    { size: [0.8, 0.8, 0.8], target: [0, 0.6, 0],       label: 'model-project' },
    { size: [0.8, 0.8, 0.8], target: [1.2, 0.6, -0.8],  label: 'model-team' },
    { size: [0.7, 0.7, 0.7], target: [-0.5, 0.6, -0.9], label: 'model-subscription' },
    // API endpoint towers (tall thin)
    { size: [0.5, 1.5, 0.5], target: [-1.8, 0.5, -0.5], label: 'api-users' },
    { size: [0.5, 1.5, 0.5], target: [1.8, 0.5, 0.5],   label: 'api-projects' },
    { size: [0.4, 1.2, 0.4], target: [-1.8, 0.5, 1.2],  label: 'api-auth' },
    { size: [0.4, 1.2, 0.4], target: [1.8, 0.5, -1.2],  label: 'api-billing' },
];

// ──────────────────────────────────────────────
// 6. Create meshes + edge wireframes
// ──────────────────────────────────────────────

const blocks = []; // { mesh, edges, target, startPos, phase }

blockDefs.forEach((def, i) => {
    const geometry = new THREE.BoxGeometry(...def.size);

    // Semi-transparent fill
    const material = new THREE.MeshStandardMaterial({
        color: COLORS.primaryContainer,
        emissive: COLORS.primaryContainer,
        emissiveIntensity: 0.15,
        transparent: true,
        opacity: 0.15,
    });
    const mesh = new THREE.Mesh(geometry, material);

    // Random start position (scattered)
    const startPos = new THREE.Vector3(
        (Math.random() - 0.5) * 12,
        (Math.random() - 0.5) * 10,
        (Math.random() - 0.5) * 8
    );
    mesh.position.copy(startPos);

    // Edge wireframe
    const edgesGeo = new THREE.EdgesGeometry(geometry);
    const edgesMat = new THREE.LineBasicMaterial({
        color: COLORS.primary,
        transparent: true,
        opacity: 0.6,
    });
    const edgeLines = new THREE.LineSegments(edgesGeo, edgesMat);
    mesh.add(edgeLines);

    scene.add(mesh);

    blocks.push({
        mesh,
        edges: edgeLines,
        target: new THREE.Vector3(...def.target),
        startPos: startPos.clone(),
        phase: Math.random() * Math.PI * 2, // random phase offset for breathing
        rotationSpeed: 0.001 + Math.random() * 0.002,
    });
});

// ──────────────────────────────────────────────
// 7. Connecting lines between adjacent blocks
// ──────────────────────────────────────────────

const connectionPairs = [
    [0, 1], [0, 2], [1, 3], [2, 3],  // foundation → services → core
    [3, 4], [3, 5], [3, 6], [3, 7],  // core → models
    [1, 8], [2, 9], [1, 10], [2, 11], // services → API towers
];

const connectionLines = [];

connectionPairs.forEach(([a, b]) => {
    const geometry = new THREE.BufferGeometry();
    // Positions will be updated each frame
    const positions = new Float32Array(6); // 2 vertices * 3 components
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    const material = new THREE.LineBasicMaterial({
        color: COLORS.secondary,
        transparent: true,
        opacity: 0,  // start invisible, fade in after assembly
    });
    const line = new THREE.Line(geometry, material);
    scene.add(line);

    connectionLines.push({ line, a, b, geometry });
});

// ──────────────────────────────────────────────
// 8. Mouse tracking for camera offset
// ──────────────────────────────────────────────

const mouse = { x: 0, y: 0 };
const cameraBase = new THREE.Vector3(0, 2, 8);
const cameraTarget = new THREE.Vector3(0, 2, 8);

window.addEventListener('mousemove', (e) => {
    mouse.x = (e.clientX / window.innerWidth) * 2 - 1;  // -1..1
    mouse.y = (e.clientY / window.innerHeight) * 2 - 1;  // -1..1
});

// ──────────────────────────────────────────────
// 9. Resize handling
// ──────────────────────────────────────────────

function onResize() {
    const width = canvas.clientWidth;
    const height = canvas.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
}
window.addEventListener('resize', onResize);

// ──────────────────────────────────────────────
// 10. Animation state
// ──────────────────────────────────────────────

const ASSEMBLY_DURATION = 3.0; // seconds
let startTime = null;
let assembled = false;

// ──────────────────────────────────────────────
// 11. Animation loop
// ──────────────────────────────────────────────

function animate(time) {
    requestAnimationFrame(animate);

    if (startTime === null) startTime = time;
    const elapsed = (time - startTime) / 1000; // seconds
    const assemblyProgress = Math.min(elapsed / ASSEMBLY_DURATION, 1);

    // Smooth ease-out for assembly
    const eased = 1 - Math.pow(1 - assemblyProgress, 3);

    blocks.forEach((block) => {
        if (assemblyProgress < 1) {
            // Assembly phase: lerp from start to target
            block.mesh.position.lerpVectors(block.startPos, block.target, eased);
        } else {
            // Breathing phase: gentle Y oscillation
            if (!assembled) {
                // Snap to exact target on first frame after assembly completes
                block.mesh.position.copy(block.target);
            }
            const breathOffset = Math.sin(elapsed * 1.5 + block.phase) * 0.1;
            block.mesh.position.y = block.target.y + breathOffset;
        }

        // Slow Y rotation
        block.mesh.rotation.y += block.rotationSpeed;
    });

    if (assemblyProgress >= 1 && !assembled) {
        assembled = true;
    }

    // Update connecting lines
    connectionLines.forEach((conn) => {
        const posA = blocks[conn.a].mesh.position;
        const posB = blocks[conn.b].mesh.position;
        const positions = conn.geometry.attributes.position.array;
        positions[0] = posA.x; positions[1] = posA.y; positions[2] = posA.z;
        positions[3] = posB.x; positions[4] = posB.y; positions[5] = posB.z;
        conn.geometry.attributes.position.needsUpdate = true;

        if (assembled) {
            // Fade in + pulse opacity
            const pulse = 0.15 + Math.sin(elapsed * 2 + conn.a) * 0.15;
            conn.line.material.opacity = pulse;
        } else {
            // Fade in gradually during assembly
            conn.line.material.opacity = eased * 0.1;
        }
    });

    // Camera: smooth lerp toward mouse-offset position
    cameraTarget.set(
        cameraBase.x + mouse.x * 0.5,
        cameraBase.y + mouse.y * 0.3,
        cameraBase.z
    );
    camera.position.lerp(cameraTarget, 0.05);
    camera.lookAt(0, 0, 0);

    renderer.render(scene, camera);
}

requestAnimationFrame(animate);
