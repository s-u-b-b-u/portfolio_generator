<?php
require_once 'db_connect.php';

if (!isset($_GET['id'])) {
    die("Portfolio ID not specified.");
}

$portfolio_id = (int)$_GET['id'];

// Fetch portfolio data from portfolios table
$stmt = $pdo->prepare("SELECT * FROM portfolios WHERE id = ?");
$stmt->execute([$portfolio_id]);
$user = $stmt->fetch(); // Keep variable name $user for template compatibility

if (!$user) {
    die("Portfolio not found.");
}

// Fetch projects linked to this portfolio
$stmt = $pdo->prepare("SELECT * FROM projects WHERE portfolio_id = ?");
$stmt->execute([$portfolio_id]);
$projects = $stmt->fetchAll();

// Skills processing
$skills = !empty($user['skills']) ? explode(',', $user['skills']) : [];

$theme_class = 'theme-' . strtolower(str_replace(' ', '-', $user['theme_choice'] ?: 'Minimalist Light'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> | Professional Portfolio</title>
    <link rel="stylesheet" href="css/themes.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body class="<?php echo $theme_class; ?>">

    <!-- Navigation -->
    <nav class="portfolio-nav">
        <div class="nav-brand"><?php echo htmlspecialchars($user['name']); ?></div>
        <div class="nav-links">
            <a href="view.php?id=<?php echo $portfolio_id; ?>" class="active">HOME</a>
            <a href="work.php?id=<?php echo $portfolio_id; ?>">WORK &amp; SKILLS</a>
        </div>
    </nav>

    <!-- Large Hero Section -->
    <header class="hero-full" style="background-image: url('uploads/<?php echo $user['hero_bg'] ?: 'default_bg.jpg'; ?>')">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <img src="uploads/<?php echo $user['profile_pic'] ?: 'default_avatar.png'; ?>" class="intro-avatar">
            <h1 class="hero-title"><?php echo htmlspecialchars($user['name']); ?></h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($user['job_title'] ?? ''); ?></p>
        </div>
    </header>

    <main class="story-container">
        <!-- Bio -->
        <section id="about" class="story-section animate-up visible">
            <h2 class="section-heading">Introduction</h2>
            <div class="section-content bio-text">
                <?php echo htmlspecialchars($user['bio']); ?>
            </div>
        </section>

        <!-- Personal Details -->
        <section id="details" class="story-section animate-up">
            <h2 class="section-heading">Personal Details</h2>
            <div class="contact-grid">
                <div class="contact-card">
                    <span class="contact-label">LOCATION</span>
                    <span class="contact-value"><?php echo htmlspecialchars($user['location'] ?: 'Not Specified'); ?></span>
                </div>
                <div class="contact-card">
                    <span class="contact-label">EMAIL</span>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="contact-value"><?php echo htmlspecialchars($user['email']); ?></a>
                </div>
                <?php if ($user['linkedin_url']): ?>
                    <div class="contact-card">
                        <span class="contact-label">LINKEDIN</span>
                        <a href="<?php echo htmlspecialchars($user['linkedin_url']); ?>" target="_blank" class="contact-value">Professional Profile</a>
                    </div>
                <?php endif; ?>
                <?php if ($user['github_url']): ?>
                    <div class="contact-card">
                        <span class="contact-label">GITHUB</span>
                        <a href="<?php echo htmlspecialchars($user['github_url']); ?>" target="_blank" class="contact-value">Source Code</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Skills -->
        <?php if (!empty($skills)): ?>
        <section id="skills" class="story-section animate-up">
            <h2 class="section-heading">Strategic Skills</h2>
             <div class="section-content">
                <div class="skills-grid">
                    <?php foreach ($skills as $skill): ?>
                        <div class="skill-pill"><?php echo htmlspecialchars(trim($skill)); ?></div>
                    <?php endforeach; ?>
                </div>
             </div>
        </section>
        <?php endif; ?>

        <!-- Education -->
        <section id="education" class="story-section animate-up">
            <h2 class="section-heading">Education</h2>
            <div class="section-content story-text">
                <?php echo htmlspecialchars($user['education'] ?: 'Education details not added yet.'); ?>
            </div>
        </section>

        <!-- Experience -->
        <section id="experience" class="story-section animate-up">
            <h2 class="section-heading">Professional Journey</h2>
            <div class="section-content story-text">
                <?php echo htmlspecialchars($user['experience'] ?: 'Professional experience not added yet.'); ?>
            </div>
        </section>

        <!-- Projects -->
        <?php if (!empty($user['projects_text'])): ?>
        <section id="projects" class="story-section animate-up">
            <h2 class="section-heading">Projects & Case Studies</h2>
            <div class="section-content story-text">
                <?php echo htmlspecialchars($user['projects_text']); ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer class="portfolio-final-footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($user['name']); ?>. Generated by Dynamic Portfolio Builder.</p>
        <p><a href="dashboard.php" style="color: inherit; text-decoration: none; opacity: 0.5;">Manage Profile</a></p>
    </footer>

    <script>
        // Simple Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-up').forEach(section => {
            observer.observe(section);
        });
    </script>
</body>
</html>
