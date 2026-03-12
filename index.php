<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Portfolio Generator - Showcase Your Journey</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="dashboard-nav" style="background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);">
        <h2 style="font-family: 'Playfair Display', serif; color: var(--primary);">Portfolio Builder</h2>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#about">About</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="dashboard.php?logout=1" class="btn btn-secondary" style="padding: 0.5rem 1.2rem;">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="login.php?signup=1" class="btn btn-primary" style="padding: 0.5rem 1.2rem; color: white;">Get Started</a>
            <?php endif; ?>
        </div>
    </nav>

    <header class="landing-hero animate-up">
        <h1 style="font-family: 'Playfair Display', serif;">Craft Your Professional Story</h1>
        <p>Transform your achievements into a stunning, interactive portfolio in minutes. Powered by AI, designed for professionals.</p>
        <div style="display: flex; gap: 1rem;">
            <a href="login.php?signup=1" class="btn btn-primary" style="padding: 1.2rem 2.5rem; font-size: 1.1rem; border-radius: 50px;">Start Building for Free</a>
            <a href="#about" class="btn btn-secondary" style="padding: 1.2rem 2.5rem; font-size: 1.1rem; border-radius: 50px;">Learn More</a>
        </div>
    </header>

    <section id="about" class="section-padding container">
        <h2 class="section-title animate-up" style="font-family: 'Playfair Display', serif;">Your Identity, Elevated</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;" class="animate-up">
            <div>
                <p style="font-size: 1.2rem; color: #475569; margin-bottom: 1.5rem;">
                    In today's digital world, a plain resume isn't enough. Our Dynamic Portfolio Generator helps you bridge the gap between "what you do" and "how you present it."
                </p>
                <p style="font-size: 1.1rem; color: #64748b;">
                    Whether you're a developer, designer, or academic, our platform provides the tools to create a living profile that showcases your skills, projects, and professional milestones with elegance and precision.
                </p>
            </div>
            <div style="background: #f1f5f9; padding: 2rem; border-radius: 20px; border: 1px solid var(--border);">
                <img src="hero_bg.png" alt="Portfolio Mockup" style="width: 100%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            </div>
        </div>
    </section>

    <section class="section-padding" style="background: white;">
        <div class="container">
            <h2 class="section-title animate-up" style="font-family: 'Playfair Display', serif;">Why Choose Us?</h2>
            <div class="grid-3">
                <div class="feature-card animate-up">
                    <span class="feature-icon">✨</span>
                    <h3>Magic OCR Scan</h3>
                    <p>Simply upload your PDF resume, and our AI will automatically extract your details, projects, and skills to build your profile instantly.</p>
                </div>
                <div class="feature-card animate-up" style="animation-delay: 0.1s;">
                    <span class="feature-icon">🎨</span>
                    <h3>Premium Themes</h3>
                    <p>Choose from a curated selection of professional themes—from Minimalist Light to Modern Glassmorphism—that make your work pop.</p>
                </div>
                <div class="feature-card animate-up" style="animation-delay: 0.2s;">
                    <span class="feature-icon">🚀</span>
                    <h3>Live Hosting</h3>
                    <p>Get a dedicated link for each of your portfolios to share with recruiters, clients, and collaborators across the globe.</p>
                </div>
            </div>
        </div>
    </section>

    <footer style="background: #1e293b; color: white; padding: 4rem 0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: white; margin-bottom: 1rem;">Portfolio Builder</h3>
                <p style="color: #94a3b8; font-size: 0.9rem;">Empowering professionals to showcase their journey.</p>
            </div>
            <div class="nav-links" style="gap: 2rem;">
                <a href="login.php" style="color: #cbd5e1;">Login</a>
                <a href="login.php?signup=1" style="color: #cbd5e1;">Sign Up</a>
                <a href="#" style="color: #cbd5e1;">Privacy Policy</a>
            </div>
        </div>
        <div class="container" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #334155; text-align: center; color: #64748b; font-size: 0.8rem;">
            &copy; <?php echo date('Y'); ?> Dynamic Portfolio Generator. All rights reserved.
        </div>
    </footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
