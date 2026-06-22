<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>STEM CRM · Earth, Sun & Moon | Dynamic Time</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            background-color: #000;
            color: #fff;
        }
        /* Three.js Canvas Background */
        #canvas-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        /* Main Layout: Left Clock + Right Login */
        .dashboard-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem;
            gap: 2rem;
            flex-wrap: wrap;
        }
        /* Left Panel: Advanced Digital Clock & Stats */
        .left-panel {
            flex: 1;
            max-width: 420px;
            background: rgba(5, 10, 25, 0.55);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            border: 1px solid rgba(0, 255, 255, 0.2);
            padding: 1.8rem 1.6rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            transition: transform 0.3s ease;
            animation: fadeInLeft 0.8s ease forwards;
        }
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        /* Quantum Flip Clock */
        .quantum-clock {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .clock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px dashed rgba(0,255,255,0.3);
            padding-bottom: 0.5rem;
        }
        .clock-icon {
            font-size: 1.3rem;
            color: #ff5e1a;
            filter: drop-shadow(0 0 5px #ff5e1a);
        }
        .clock-label {
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            background: rgba(255,94,26,0.2);
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            color: #ffaa77;
        }
        .time-flip-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem 0 0.5rem;
        }
        .time-segment {
            display: flex;
            gap: 0.1rem;
            background: rgba(0,0,0,0.5);
            padding: 0.2rem 0.3rem;
            border-radius: 1rem;
        }
        .digit-box {
            width: 2.4rem;
            height: 3rem;
            background: linear-gradient(145deg, #0a0f1e, #01030a);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Mono', monospace;
            font-size: 2.2rem;
            font-weight: 700;
            color: #ffaa66;
            text-shadow: 0 0 8px #ff5e1a;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.1), 0 4px 8px rgba(0,0,0,0.3);
            transition: all 0.1s;
        }
        .digit-flip {
            animation: flipAnim 0.25s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
        }
        @keyframes flipAnim {
            0% { transform: rotateX(0deg); color: #ffaa66; }
            45% { transform: rotateX(90deg); color: #00ffff; text-shadow: 0 0 12px cyan; }
            55% { transform: rotateX(90deg); }
            100% { transform: rotateX(0deg); color: #ffaa66; }
        }
        .sep-colon {
            font-size: 2rem;
            font-weight: 800;
            color: #ff5e1a;
            text-shadow: 0 0 8px #ff5e1a;
            animation: blinkPulse 1.2s step-end infinite;
        }
        @keyframes blinkPulse {
            0%,100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .clock-date-modern {
            display: flex;
            justify-content: space-between;
            background: rgba(0,0,0,0.4);
            border-radius: 40px;
            padding: 0.5rem 1rem;
            margin: 0.8rem 0;
            font-size: 0.75rem;
        }
        .date-day { color: #00ffff; font-weight: 600; }
        .second-ring {
            width: 100%;
            height: 2px;
            background: rgba(255,94,26,0.3);
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }
        .second-progress {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #ff5e1a, #00ffff);
            transition: width 1s linear;
        }
        /* Stats */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 1.5rem 0 1rem;
        }
        .stat-pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(0,255,255,0.2);
            border-radius: 40px;
            padding: 6px 14px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .stat-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ff5e1a;
            box-shadow: 0 0 6px #ff5e1a;
        }
        /* Right Panel: Login Card */
        .login-card {
            background: rgba(10, 20, 40, 0.65);
            backdrop-filter: blur(15px);
            border-radius: 2rem;
            border: 1px solid rgba(0, 255, 255, 0.25);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 255, 255, 0.2);
            width: 100%;
            max-width: 440px;
            padding: 2rem 2rem 2.2rem;
            transition: all 0.3s;
            animation: fadeSlideUp 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-icon {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .hex-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #ff5e1a, #e8192c);
            clip-path: polygon(50% 0%, 93% 25%, 93% 75%, 50% 100%, 7% 75%, 7% 25%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.5rem;
            font-family: 'Space Mono', monospace;
            box-shadow: 0 0 15px rgba(255, 94, 26, 0.6);
        }
        .brand-text {
            font-weight: 800;
            font-size: 1.5rem;
            background: linear-gradient(90deg, #fff, #00ffff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .brand-text span { color: #ff5e1a; background: none; -webkit-background-clip: unset; }
        .welcome-title { font-size: 2rem; font-weight: 800; margin-bottom: 0.3rem; }
        .welcome-sub { color: rgba(255,255,255,0.6); margin-bottom: 2rem; border-left: 3px solid #00ffff; padding-left: 12px; font-size: 0.85rem; }
        .input-group-custom { margin-bottom: 1.5rem; }
        .input-group-custom label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: #a0c0ff; margin-bottom: 6px; display: block; }
        .input-field {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(0,255,255,0.3);
            border-radius: 16px;
            padding: 12px 18px;
            color: white;
            transition: 0.3s;
        }
        .input-field:focus {
            border-color: #ff5e1a;
            box-shadow: 0 0 12px rgba(255,94,26,0.3);
            outline: none;
        }
        .password-wrapper { position: relative; }
        .toggle-pw {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #ccc;
            cursor: pointer;
        }
        .options {
            display: flex;
            justify-content: space-between;
            margin: 1rem 0 1.8rem;
        }
        .checkbox-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .checkbox-custom input { display: none; }
        .check-box {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(0,255,255,0.5);
            border-radius: 5px;
            background: rgba(0,0,0,0.4);
        }
        .checkbox-custom input:checked + .check-box {
            background: #ff5e1a;
            border-color: #ff5e1a;
        }
        .checkbox-custom input:checked + .check-box::after {
            content: "✓";
            display: block;
            text-align: center;
            color: white;
            font-size: 12px;
            line-height: 14px;
        }
        .forgot-link { color: #00ffff; text-decoration: none; font-size: 0.8rem; }
        .login-btn {
            width: 100%;
            background: linear-gradient(95deg, #ff5e1a, #e8192c);
            border: none;
            border-radius: 40px;
            padding: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            color: white;
            transition: 0.3s;
            margin-bottom: 1rem;
        }
        .login-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(255,94,26,0.5); }
        .custom-alert {
            background: rgba(0,255,255,0.15);
            border-left: 5px solid #00ffff;
            border-radius: 12px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            display: flex;
            justify-content: space-between;
        }
        .custom-alert.error { border-left-color: #ff5e1a; background: rgba(255,94,26,0.15); color: #ffbaba; }
        .close-alert { background: none; border: none; color: inherit; cursor: pointer; }
        @media (max-width: 900px) {
            .dashboard-wrapper { flex-direction: column; align-items: stretch; }
            .left-panel { max-width: 100%; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <div id="canvas-container"></div>

    <div class="dashboard-wrapper">
        <!-- LEFT PANEL: ADVANCED DIGITAL CLOCK + STATS -->
        <div class="left-panel">
            <div class="quantum-clock">
                <div class="clock-header">
                    <span class="clock-icon">⌚</span>
                    <span class="clock-label">LIVE SESSION · UTC+5:30</span>
                </div>
                <div class="time-flip-container">
                    <div class="time-segment">
                        <div class="digit-box" id="hrTens">0</div>
                        <div class="digit-box" id="hrUnits">0</div>
                    </div>
                    <span class="sep-colon">:</span>
                    <div class="time-segment">
                        <div class="digit-box" id="minTens">0</div>
                        <div class="digit-box" id="minUnits">0</div>
                    </div>
                    <span class="sep-colon">:</span>
                    <div class="time-segment">
                        <div class="digit-box" id="secTens">0</div>
                        <div class="digit-box" id="secUnits">0</div>
                    </div>
                </div>
                <div class="clock-date-modern">
                    <span class="date-day" id="clockDayName">MONDAY</span>
                    <span class="date-full" id="clockFullDate">-- --- ----</span>
                </div>
                <div class="second-ring">
                    <div class="second-progress" id="secondProgress"></div>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-pill"><span class="stat-dot"></span> 20L+ Students</div>
                <div class="stat-pill"><span class="stat-dot"></span> 30K+ Teachers</div>
                <div class="stat-pill"><span class="stat-dot"></span> 5.3K+ Schools</div>
                <div class="stat-pill"><span class="stat-dot"></span> 26 States</div>
                <div class="stat-pill"><span class="stat-dot"></span> 350+ Corporates</div>
            </div>
            <div style="font-size:0.65rem; opacity:0.6; text-align:center;">STEM Learning Pvt Ltd · Earth Observation Portal</div>
        </div>

        <!-- RIGHT PANEL: LOGIN CARD -->
        <div class="login-card">
            <div class="brand-icon">
                <div class="hex-icon">S</div>
                <div class="brand-text"><span>STEM</span> CRM</div>
            </div>
            <div class="welcome-title">Welcome back</div>
            <div class="welcome-sub">Real‑time sunlight & moon phases</div>

            <?php if ($this->session->flashdata('success_message')): ?>
                <div class="custom-alert success"><span>✓ <?= htmlspecialchars($this->session->flashdata('success_message')); ?></span><button class="close-alert">&times;</button></div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('error_message')): ?>
                <div class="custom-alert error"><span>⚠️ <?= htmlspecialchars($this->session->flashdata('error_message')); ?></span><button class="close-alert">&times;</button></div>
            <?php endif; ?>

            <form id="loginForm" action="<?= base_url('Menu/login'); ?>" method="post">
                <div class="input-group-custom">
                    <label>Username</label>
                    <input type="text" class="input-field" name="user" id="username" placeholder="astronaut@stem">
                </div>
                <div class="input-group-custom">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="input-field" name="password" id="password" placeholder="••••••••">
                        <button type="button" class="toggle-pw" id="togglePassword">👁️</button>
                    </div>
                </div>
                <div class="options">
                    <label class="checkbox-custom">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="check-box"></span>
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot Orbit?</a>
                </div>
                <button type="submit" class="login-btn" id="signinBtn">LAUNCH SESSION</button>
                <div style="text-align:center; font-size:0.7rem; opacity:0.5;">© 2025 STEM Learning Pvt Ltd</div>
            </form>
        </div>
    </div>

    <!-- Three.js Module with Earth, Dynamic Sun, and Moon -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.128.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.128.0/examples/jsm/"
            }
        }
    </script>

    <script type="module">
        import * as THREE from 'three';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

        // Setup Scene
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x010210);
        scene.fog = new THREE.FogExp2(0x010210, 0.0004);

        const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 0.2, 3.8);
        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(window.devicePixelRatio);
        container.appendChild(renderer.domElement);

        // Controls
        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 0.6;
        controls.enableZoom = true;
        controls.enablePan = false;
        controls.target.set(0, 0, 0);

        // Starfields
        const starGeo = new THREE.BufferGeometry();
        const starCount = 2500;
        const starPos = new Float32Array(starCount * 3);
        for (let i = 0; i < starCount; i++) {
            const r = 350 + Math.random() * 200;
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos(2 * Math.random() - 1);
            starPos[i*3] = r * Math.sin(phi) * Math.cos(theta);
            starPos[i*3+1] = r * Math.sin(phi) * Math.sin(theta);
            starPos[i*3+2] = r * Math.cos(phi);
        }
        starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
        const starsMat = new THREE.PointsMaterial({ color: 0xffffff, size: 0.2, transparent: true, blending: THREE.AdditiveBlending });
        const stars = new THREE.Points(starGeo, starsMat);
        scene.add(stars);

        // Earth Group
        const earthGroup = new THREE.Group();
        scene.add(earthGroup);

        // Textures
        const loader = new THREE.TextureLoader();
        const earthMap = loader.load('https://threejs.org/examples/textures/planets/earth_atmos_2048.jpg');
        const cloudMap = loader.load('https://threejs.org/examples/textures/planets/earth_clouds_1024.png');
        const specMap = loader.load('https://threejs.org/examples/textures/planets/earth_specular_2048.jpg');
        const normalMap = loader.load('https://threejs.org/examples/textures/planets/earth_normal_2048.jpg');
        const moonMap = loader.load('https://threejs.org/examples/textures/planets/moon_1024.jpg');

        // Earth
        const earthMat = new THREE.MeshPhongMaterial({ map: earthMap, specularMap: specMap, specular: new THREE.Color('grey'), shininess: 5, normalMap: normalMap });
        const earthMesh = new THREE.Mesh(new THREE.SphereGeometry(1.0, 128, 128), earthMat);
        earthGroup.add(earthMesh);

        // Clouds
        const cloudMat = new THREE.MeshPhongMaterial({ map: cloudMap, transparent: true, opacity: 0.12, blending: THREE.AdditiveBlending });
        const cloudMesh = new THREE.Mesh(new THREE.SphereGeometry(1.01, 128, 128), cloudMat);
        earthGroup.add(cloudMesh);

        // Atmosphere
        const atmosMat = new THREE.MeshPhongMaterial({ color: 0x4488ff, transparent: true, opacity: 0.07, side: THREE.BackSide });
        const atmosMesh = new THREE.Mesh(new THREE.SphereGeometry(1.04, 64, 64), atmosMat);
        earthGroup.add(atmosMesh);

        // ---- MOON ----
        const moonGeometry = new THREE.SphereGeometry(0.28, 128, 128);
        const moonMaterial = new THREE.MeshStandardMaterial({ map: moonMap, roughness: 0.8, metalness: 0.1, color: 0xccccdd });
        const moon = new THREE.Mesh(moonGeometry, moonMaterial);
        scene.add(moon);
        let moonAngle = 0;

        // ---- SUN (Glowing sphere + directional light) ----
        const sunGeometry = new THREE.SphereGeometry(0.32, 64, 64);
        const sunMaterial = new THREE.MeshStandardMaterial({ color: 0xffaa66, emissive: 0xff4411, emissiveIntensity: 1.2 });
        const sunSphere = new THREE.Mesh(sunGeometry, sunMaterial);
        scene.add(sunSphere);
        const sunLight = new THREE.DirectionalLight(0xffeedd, 1.2);
        sunLight.target = earthMesh;
        scene.add(sunLight);
        const ambientLight = new THREE.AmbientLight(0x222222);
        scene.add(ambientLight);
        const fillLight = new THREE.PointLight(0x2266aa, 0.2);
        scene.add(fillLight);
        
        // Sun glow point light
        const sunGlow = new THREE.PointLight(0xff8844, 0.7);
        scene.add(sunGlow);

        // Dynamic Sunlight & Moon Orbiting (based on real time)
        const orbitRadius = 7.2;
        const maxHeight = 3.4;
        
        function updateSunAndMoon() {
            const now = new Date();
            let decimalHour = now.getHours() + now.getMinutes() / 60 + now.getSeconds() / 3600;
            // Sun angle: 0 at 6am (east), PI/2 at noon, PI at 6pm (west), 3PI/2 at midnight
            let sunAngle = ((decimalHour - 6) / 24) * Math.PI * 2;
            const x = Math.cos(sunAngle) * orbitRadius;
            const z = Math.sin(sunAngle) * orbitRadius * 1.1;
            const y = Math.sin(sunAngle) * maxHeight;
            
            sunSphere.position.set(x, y, z);
            sunLight.position.set(x, y, z);
            sunGlow.position.set(x, y, z);
            
            // Intensity based on sun height
            let intensity = Math.max(0.08, Math.sin(sunAngle) * 1.3);
            if (y < 0) intensity = 0.08;
            sunLight.intensity = Math.min(1.5, intensity);
            sunLight.color.setHSL(0.12, 1.0, 0.5 + intensity * 0.3);
            
            // Sun material glow
            const emissIntensity = y < 0 ? 0.1 : 0.8 + Math.sin(sunAngle) * 0.8;
            sunMaterial.emissiveIntensity = emissIntensity;
            sunGlow.intensity = intensity * 0.8;
            
            // Ambient light adaptation
            ambientLight.intensity = 0.12 + intensity * 0.25;
            fillLight.intensity = y < 0 ? 0.2 : 0.1;
            
            // ---- MOON ORBIT (independent, opposite side sometimes) ----
            // Moon completes orbit in about 27.3 days (simplified)
            moonAngle += 0.0028; // smooth motion
            const moonDist = 2.4;
            const moonX = Math.cos(moonAngle) * moonDist;
            const moonZ = Math.sin(moonAngle) * moonDist;
            const moonY = Math.sin(moonAngle * 0.8) * 0.5;
            moon.position.set(moonX, moonY, moonZ);
            // Moon light reflection (subtle)
            const moonLight = new THREE.PointLight(0x88aaff, 0.12);
            if (!window.moonLightAdded) {
                window.moonLightAdded = true;
                scene.add(moonLight);
            } else {
                const ml = scene.getObjectByName('moonLight');
                if(ml) ml.intensity = 0.12;
            }
        }

        setInterval(updateSunAndMoon, 200);
        updateSunAndMoon();

        // Dust particles around Earth
        const dustCount = 1200;
        const dustGeo = new THREE.BufferGeometry();
        const dustPos = new Float32Array(dustCount * 3);
        for (let i = 0; i < dustCount; i++) {
            const rad = 1.15 + Math.random() * 0.25;
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos(2 * Math.random() - 1);
            dustPos[i*3] = rad * Math.sin(phi) * Math.cos(theta);
            dustPos[i*3+1] = rad * Math.sin(phi) * Math.sin(theta) * 0.6;
            dustPos[i*3+2] = rad * Math.cos(phi);
        }
        dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
        const dustMat = new THREE.PointsMaterial({ color: 0x88aaff, size: 0.008, transparent: true });
        const dust = new THREE.Points(dustGeo, dustMat);
        earthGroup.add(dust);
        
        // Animation Loop
        let time = 0;
        function animate() {
            requestAnimationFrame(animate);
            time += 0.005;
            earthGroup.rotation.y += 0.002;
            cloudMesh.rotation.y += 0.0025;
            stars.rotation.y += 0.0001;
            controls.update();
            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        console.log('3D Scene Active: Earth + Dynamic Sun + Moon');
    </script>

    <!-- Digital Clock & UI Interactions -->
    <script>
        // Flip Clock Logic
        const hrTens = document.getElementById('hrTens');
        const hrUnits = document.getElementById('hrUnits');
        const minTens = document.getElementById('minTens');
        const minUnits = document.getElementById('minUnits');
        const secTens = document.getElementById('secTens');
        const secUnits = document.getElementById('secUnits');
        const daySpan = document.getElementById('clockDayName');
        const dateSpan = document.getElementById('clockFullDate');
        const progressBar = document.getElementById('secondProgress');

        let prevDigits = { h1:'0', h2:'0', m1:'0', m2:'0', s1:'0', s2:'0' };
        function applyFlip(el, newVal, oldVal) {
            if (oldVal !== newVal && el) {
                el.classList.remove('digit-flip');
                void el.offsetHeight;
                el.classList.add('digit-flip');
                setTimeout(() => el.classList.remove('digit-flip'), 280);
            }
            if (el) el.textContent = newVal;
        }

        function updateClock() {
            const now = new Date();
            const hh = now.getHours().toString().padStart(2,'0');
            const mm = now.getMinutes().toString().padStart(2,'0');
            const ss = now.getSeconds().toString().padStart(2,'0');
            applyFlip(hrTens, hh[0], prevDigits.h1);
            applyFlip(hrUnits, hh[1], prevDigits.h2);
            applyFlip(minTens, mm[0], prevDigits.m1);
            applyFlip(minUnits, mm[1], prevDigits.m2);
            applyFlip(secTens, ss[0], prevDigits.s1);
            applyFlip(secUnits, ss[1], prevDigits.s2);
            prevDigits = { h1:hh[0], h2:hh[1], m1:mm[0], m2:mm[1], s1:ss[0], s2:ss[1] };
            
            const days = ['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'];
            const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
            daySpan.textContent = days[now.getDay()];
            dateSpan.textContent = `${now.getDate().toString().padStart(2,'0')} ${months[now.getMonth()]} ${now.getFullYear()}`;
            const percent = ((now.getSeconds() + now.getMilliseconds()/1000) / 60) * 100;
            progressBar.style.width = `${percent}%`;
        }
        updateClock();
        setInterval(updateClock, 50);

        // Password Toggle
        const togglePw = document.getElementById('togglePassword');
        const pwInput = document.getElementById('password');
        if (togglePw) {
            togglePw.addEventListener('click', () => {
                const type = pwInput.type === 'password' ? 'text' : 'password';
                pwInput.type = type;
                togglePw.textContent = type === 'password' ? '👁️' : '🙈';
            });
        }

        // Flash message close
        document.querySelectorAll('.close-alert').forEach(btn => {
            btn.addEventListener('click', (e) => {
                btn.closest('.custom-alert')?.remove();
            });
        });
        setTimeout(() => {
            document.querySelectorAll('.custom-alert').forEach(el => el.remove());
        }, 5000);

        // Form loader
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('signinBtn');
        if (form) {
            form.addEventListener('submit', (e) => {
                const user = document.getElementById('username')?.value.trim();
                const pwd = pwInput?.value;
                if (!user || !pwd) {
                    e.preventDefault();
                    alert('Please enter username and password');
                }
            });
        }
    </script>
</body>
</html>