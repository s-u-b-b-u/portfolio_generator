<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/**
 * Handle File Uploads
 */
function handleUpload($fileKey, $currentValue) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $error_code = $_FILES[$fileKey]['error'];
            $error_msg = "Upload error (Code: $error_code)";
            if ($error_code === UPLOAD_ERR_INI_SIZE) $error_msg = "File too large (exceeds php.ini limit)";
            if ($error_code === UPLOAD_ERR_FORM_SIZE) $error_msg = "File too large (exceeds form limit)";
            $_SESSION['error'] = $error_msg;
            return $currentValue;
        }

        $fileTmpPath = $_FILES[$fileKey]['tmp_name'];
        $fileName = $_FILES[$fileKey]['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = ['jpg', 'png', 'jpeg', 'webp'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = './uploads/' . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                return $newFileName;
            } else {
                $_SESSION['error'] = "Failed to move uploaded file to destination.";
            }
        } else {
            $_SESSION['error'] = "Invalid file extension '$fileExtension'. Allowed: jpg, png, jpeg, webp";
        }
    }
    return $currentValue;
}

// -------------------------------------------------------------------------------------------------
// Portfolio Logic
// -------------------------------------------------------------------------------------------------
$portfolio_id = $_GET['id'] ?? null;
$editing = !!$portfolio_id;

// Handle Delete Portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_portfolio'])) {
    $del_id = (int)$_POST['delete_portfolio'];
    // Ensure ownership before deleting
    $del_check = $pdo->prepare("SELECT id FROM portfolios WHERE id = ? AND user_id = ?");
    $del_check->execute([$del_id, $user_id]);
    if ($del_check->fetch()) {
        $pdo->prepare("DELETE FROM projects WHERE portfolio_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM portfolios WHERE id = ?")->execute([$del_id]);
        $_SESSION['success'] = "Portfolio deleted successfully.";
    } else {
        $_SESSION['error'] = "Could not delete portfolio.";
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch list of portfolios for sidebar/list
$portfolios_stmt = $pdo->prepare("SELECT * FROM portfolios WHERE user_id = ? ORDER BY created_at DESC");
$portfolios_stmt->execute([$user_id]);
$all_portfolios = $portfolios_stmt->fetchAll();

// Fetch current portfolio if editing
$current_portfolio = null;
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM portfolios WHERE id = ? AND user_id = ?");
    $stmt->execute([$portfolio_id, $user_id]);
    $current_portfolio = $stmt->fetch();
    
    if (!$current_portfolio) {
        $_SESSION['error'] = "Portfolio not found or access denied.";
        header("Location: dashboard.php");
        exit();
    }
}

// Handle Form Submission (Unified Manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
    try {
        $pdo->beginTransaction();

        $target_id = $_POST['portfolio_id'] ?? null;
        
        // Handle image uploads
        $existing_pic = $target_id ? $current_portfolio['profile_pic'] : 'default_avatar.png';
        $existing_bg = $target_id ? $current_portfolio['hero_bg'] : 'default_bg.jpg';
        
        $profile_pic = handleUpload('profile_pic', $existing_pic);
        $hero_bg = handleUpload('hero_bg', $existing_bg);

        if ($target_id) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE portfolios SET 
                name = ?, email = ?, phone = ?, 
                github_url = ?, linkedin_url = ?, twitter_url = ?,
                job_title = ?, location = ?,
                bio = ?, skills = ?, education = ?, experience = ?,
                theme_choice = ?, profile_pic = ?, hero_bg = ? 
                WHERE id = ? AND user_id = ?");
            
            $stmt->execute([
                $_POST['name'], $_POST['email'], $_POST['phone'] ?? '',
                $_POST['github_url'] ?? '', $_POST['linkedin_url'] ?? '', $_POST['twitter_url'] ?? '',
                $_POST['job_title'] ?? '', $_POST['location'] ?? '',
                $_POST['bio'] ?? '', $_POST['skills'] ?? '', $_POST['education'] ?? '', $_POST['experience'] ?? '',
                $_POST['theme_choice'] ?? 'Minimalist Light',
                $profile_pic, $hero_bg, $target_id, $user_id
            ]);
        } else {
            // Create New
            $stmt = $pdo->prepare("INSERT INTO portfolios 
                (user_id, name, email, phone, github_url, linkedin_url, twitter_url, job_title, location, bio, skills, education, experience, theme_choice, profile_pic, hero_bg)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $_POST['name'], $_POST['email'], $_POST['phone'] ?? '',
                $_POST['github_url'] ?? '', $_POST['linkedin_url'] ?? '', $_POST['twitter_url'] ?? '',
                $_POST['job_title'] ?? '', $_POST['location'] ?? '',
                $_POST['bio'] ?? '', $_POST['skills'] ?? '', $_POST['education'] ?? '', $_POST['experience'] ?? '',
                $_POST['theme_choice'] ?? 'Minimalist Light',
                $profile_pic, $hero_bg
            ]);
            $target_id = $pdo->lastInsertId();
        }

        // Projects Table Sync
        $pdo->prepare("DELETE FROM projects WHERE portfolio_id = ?")->execute([$target_id]);
        if (isset($_POST['project_titles'])) {
            $titles = $_POST['project_titles'];
            $descriptions = $_POST['project_descriptions'];
            $links = $_POST['project_links'];
            $stmt = $pdo->prepare("INSERT INTO projects (portfolio_id, user_id, title, description, link) VALUES (?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($titles); $i++) {
                if (!empty(trim($titles[$i]))) {
                    $stmt->execute([$target_id, $user_id, trim($titles[$i]), trim($descriptions[$i]), trim($links[$i])]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Portfolio saved successfully!";
        header("Location: dashboard.php?id=" . $target_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error saving: " . $e->getMessage();
        header("Location: dashboard.php" . ($editing ? "?id=$portfolio_id" : ""));
        exit();
    }
}

// Handle Magic Autofill Submission (Resume Path)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['magic_save'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO portfolios 
            (user_id, name, job_title, location, email, phone, github_url, linkedin_url, twitter_url, bio, skills, education, experience)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user_id, $_POST['name'], $_POST['job_title'], $_POST['location'],
            $_POST['email'], $_POST['phone'],
            $_POST['github_url'], $_POST['linkedin_url'], $_POST['twitter_url'],
            $_POST['bio'], $_POST['skills'], $_POST['education'], $_POST['experience']
        ]);
        $new_id = $pdo->lastInsertId();

        // Save scanned projects into projects table
        if (isset($_POST['project_titles'])) {
            $titles = $_POST['project_titles'];
            $descriptions = $_POST['project_descriptions'];
            $links = $_POST['project_links'];
            $pstmt = $pdo->prepare("INSERT INTO projects (portfolio_id, user_id, title, description, link) VALUES (?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($titles); $i++) {
                if (!empty(trim($titles[$i]))) {
                    $pstmt->execute([$new_id, $user_id, trim($titles[$i]), trim($descriptions[$i] ?? ''), trim($links[$i] ?? '')]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Magic Portfolio generated successfully!";
        header("Location: dashboard.php?id=" . $new_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error saving magic profile: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}

// Fetch current projects
$projects = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE portfolio_id = ?");
    $stmt->execute([$portfolio_id]);
    $projects = $stmt->fetchAll();
}

if (isset($_GET['logout'])) {
    logout();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Alumni Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Tesseract.js for Client-side OCR -->
    <script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>
    <!-- pdf.js for PDF support -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>
<body>
    <nav class="dashboard-nav" style="background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);">
        <h2 style="font-family: 'Playfair Display', serif; color: var(--primary); cursor: pointer;" onclick="window.location.href='index.php'">Portfolio Builder</h2>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="?logout=1" class="btn btn-secondary" style="padding: 0.5rem 1.2rem;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($success): ?>
            <div id="alert-success" class="alert alert-success" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2.5rem; transition: opacity 0.5s;">
                <span>✨ <?php echo $success; ?></span>
                <a href="view.php?id=<?php echo $portfolio_id; ?>" target="_blank" class="btn btn-primary" style="background: #2d5a27; border: none; padding: 0.8rem 1.5rem; border-radius: 8px;">View Live Portfolio 🚀</a>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div id="alert-error" class="alert alert-error" style="transition: opacity 0.5s;"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Portfolio Listing View -->
        <?php if (!$editing && !isset($_GET['new'])): ?>
            <div id="portfolio-list-view" style="padding: 2rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;">
                    <h1 style="font-family: 'Playfair Display', serif; color: var(--primary); margin: 0;">My Portfolios</h1>
                    <button onclick="window.location.href='?new=1'" class="btn btn-primary" style="padding: 0.8rem 2rem; border-radius: 50px;">+ Create New Portfolio</button>
                </div>

                <?php if (empty($all_portfolios)): ?>
                    <div class="card" style="text-align: center; padding: 5rem 2rem; background: #fafafa; border: 2px dashed #ddd;">
                        <div style="font-size: 4rem; opacity: 0.3; margin-bottom: 1.5rem;">📂</div>
                        <h3 style="color: #888;">No portfolios yet</h3>
                        <p style="color: #aaa; margin-bottom: 2rem;">Start by creating a portfolio manually or using Magic OCR scan.</p>
                        <button onclick="window.location.href='?new=1'" class="btn btn-primary">Get Started</button>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                        <?php foreach ($all_portfolios as $p): ?>
                            <div class="card portfolio-item-card" style="padding: 2rem; transition: transform 0.2s; position: relative; border-left: 5px solid var(--primary);">
                                <!-- Delete button at top-right corner -->
                                <form method="POST" style="position: absolute; top: 0.8rem; right: 0.8rem; margin: 0;" onsubmit="return confirm('Delete this portfolio? This cannot be undone.');">
                                    <input type="hidden" name="delete_portfolio" value="<?php echo $p['id']; ?>">
                                    <button type="submit" title="Delete Portfolio" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 8px; padding: 0.35rem 0.6rem; cursor: pointer; font-size: 0.8rem; line-height: 1; transition: background 0.2s;" onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'">🗑</button>
                                </form>

                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                                    <img src="uploads/<?php echo $p['profile_pic']; ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.1rem;"><?php echo htmlspecialchars($p['name']); ?></h3>
                                        <p style="margin: 0; font-size: 0.8rem; color: #888;"><?php echo htmlspecialchars($p['job_title']); ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="dashboard.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary" style="font-size: 0.8rem; flex: 1; text-align: center;">Edit Details</a>
                                    <a href="view.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-primary" style="font-size: 0.8rem; flex: 1; text-align: center;">View Live</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Creation/Editing Gateway -->
        <?php if ($editing || isset($_GET['new'])): ?>
            <div style="margin-bottom: 2rem;">
                <a href="dashboard.php" class="btn btn-secondary" style="font-size: 0.8rem; text-decoration: none;">← Back to My Portfolios</a>
            </div>
        <?php endif; ?>

        <?php if ($editing || isset($_GET['new'])): ?>

        <!-- Choice Gateway Screen (Only if creating new) -->
        <div id="choice-screen" style="display: <?php echo (isset($_GET['new']) && !$editing) ? 'block' : 'none'; ?>; text-align: center; padding: 2rem 0;">
            <h1 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 3rem; color: var(--primary);">How would you like to generate your portfolio?</h1>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 900px; margin: 0 auto;">
                <div class="card choice-card" onclick="showWorkflow('resume')" style="cursor: pointer; padding: 3rem; transition: transform 0.3s; border: 2px solid #eee;">
                    <div style="font-size: 3rem; margin-bottom: 1.5rem;">✨</div>
                    <h3 style="color: var(--primary); margin-bottom: 1rem;">Magic Generation</h3>
                    <p style="color: #666; font-size: 0.9rem;">Upload your Resume/CV and let our AI extract all your professional details automatically.</p>
                </div>
                <div class="card choice-card" onclick="showWorkflow('manual')" style="cursor: pointer; padding: 3rem; transition: transform 0.3s; border: 2px solid #eee;">
                    <div style="font-size: 3rem; margin-bottom: 1.5rem;">⌨️</div>
                    <h3 style="color: var(--primary); margin-bottom: 1rem;">Manual Generation</h3>
                    <p style="color: #666; font-size: 0.9rem;">Fill in your details manually using our structured form builder to have full control.</p>
                </div>
            </div>
        </div>

        <!-- Resume Workflow Block -->
        <div id="resume-workflow" style="display: none;">
            <div style="margin-bottom: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="resetWorkflow()" style="font-size: 0.8rem;">← Back to Options</button>
            </div>

            <!-- Step 1: Upload Zone -->
            <div id="resume-upload-zone">
                <div class="card" style="background: linear-gradient(135deg, #fdf2f2 0%, #fff 100%); border: 1px solid var(--primary); border-style: dashed; padding: 4rem 2rem; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1.5rem;">📄</div>
                    <h2 style="color: var(--primary); margin-bottom: 1rem;">Upload your Resume/CV</h2>
                    <p style="margin-bottom: 2.5rem; color: #666; max-width: 500px; margin: 0 auto 2.5rem;">Our AI will analyze your document and build your entire portfolio structure in seconds.</p>
                    <div style="max-width: 400px; margin: 0 auto; border: 2px dashed #ccc; padding: 3rem; border-radius: 12px; background: #fafafa;" id="drop-zone">
                        <input type="file" id="resume_upload" accept="image/*,.pdf" style="display: none;">
                        <label for="resume_upload" class="add-project-btn" style="margin: 0 auto; width: fit-content; padding: 1rem 2rem; font-size: 1rem; cursor: pointer;">Choose File</label>
                        <div id="file-name-display" style="margin-top: 1.5rem; font-size: 0.9rem; color: #888;">No file selected</div>
                    </div>
                    <button type="button" id="scan_resume_btn" class="btn btn-primary" style="margin-top: 2rem; width: 100%; max-width: 300px; padding: 1rem; font-weight: 600;" disabled>
                        Scan &amp; Build Portfolio
                    </button>
                    <div id="autofill-status" style="margin-top: 1.5rem; font-size: 0.9rem; min-height: 24px;"></div>
                </div>
            </div>

            <!-- Step 2: Preview & Submit — Always in DOM so form fields submit correctly -->
            <div id="resume-preview" style="display: none;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="background: var(--primary); color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-weight: 700;">✓</div>
                    <h2 style="font-family: 'Playfair Display', serif; color: var(--primary); margin: 0;">Review Extracted Identity</h2>
                    <button type="button" onclick="document.getElementById('resume-upload-zone').style.display='block'; document.getElementById('resume-preview').style.display='none';" class="btn btn-secondary" style="font-size: 0.75rem; margin-left: auto;">← Re-upload</button>
                </div>
                <form method="POST" action="dashboard.php" id="magic-form">
                    <?php if($editing): ?>
                        <input type="hidden" name="portfolio_id" value="<?php echo $portfolio_id; ?>">
                    <?php endif; ?>
                    <!-- Identity Card -->
                    <div class="card" style="margin-bottom: 2rem; border-left: 5px solid var(--primary);">
                        <h3 style="margin-top: 0; margin-bottom: 1.5rem; color: var(--primary); font-size: 1.1rem;">Professional Identity</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Full Name</label>
                                <input type="text" name="name" id="magic-name" value="<?php echo htmlspecialchars($current_portfolio['name'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Job Title</label>
                                <input type="text" name="job_title" id="magic-job-title" value="<?php echo htmlspecialchars($current_portfolio['job_title'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Location</label>
                                <input type="text" name="location" id="magic-location" value="<?php echo htmlspecialchars($current_portfolio['location'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Email</label>
                                <input type="email" name="email" id="magic-email" value="<?php echo htmlspecialchars($current_portfolio['email'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Phone</label>
                                <input type="text" name="phone" id="magic-phone" value="<?php echo htmlspecialchars($current_portfolio['phone'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">LinkedIn URL</label>
                                <input type="text" name="linkedin_url" id="magic-linkedin" value="<?php echo htmlspecialchars($current_portfolio['linkedin_url'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">GitHub URL</label>
                                <input type="text" name="github_url" id="magic-github" value="<?php echo htmlspecialchars($current_portfolio['github_url'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 0.8rem; color: #666;">Twitter URL</label>
                                <input type="text" name="twitter_url" id="magic-twitter" value="<?php echo htmlspecialchars($current_portfolio['twitter_url'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 0.8rem; box-sizing: border-box;">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Bio & Details Card -->
                    <div class="card" style="margin-bottom: 2rem; border-left: 5px solid var(--primary);">
                        <h3 style="margin-top: 0; margin-bottom: 1.5rem; color: var(--primary); font-size: 1.1rem;">Bio & Professional Story</h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                            <div>
                                <label style="font-weight: 700; color: var(--primary); display: block; margin-bottom: 0.5rem;">Bio / Introduction</label>
                                <textarea name="bio" id="magic-bio" rows="4" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;"><?php echo htmlspecialchars($current_portfolio['bio'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label style="font-weight: 700; color: var(--primary); display: block; margin-bottom: 0.5rem;">Skills &amp; Expertise <span style="font-weight:400; color:#888;">(comma-separated)</span></label>
                                <input type="text" name="skills" id="magic-skills" value="<?php echo htmlspecialchars($current_portfolio['skills'] ?? ''); ?>" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; font-family: inherit; font-size: 0.95rem; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-weight: 700; color: var(--primary); display: block; margin-bottom: 0.5rem;">Education Details</label>
                                <textarea name="education" id="magic-education" rows="3" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;"><?php echo htmlspecialchars($current_portfolio['education'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label style="font-weight: 700; color: var(--primary); display: block; margin-bottom: 0.5rem;">Professional Experience</label>
                                <textarea name="experience" id="magic-experience" rows="4" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;"><?php echo htmlspecialchars($current_portfolio['experience'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label style="font-weight: 700; color: var(--primary); display: block; margin-bottom: 0.5rem;">Projects</label>
                                <!-- Scanned project cards from OCR -->
                                <div id="magic-projects-container" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 0.5rem;"></div>
                                <button type="button" onclick="addMagicProjectCard()" style="background: none; border: 2px dashed var(--primary); color: var(--primary); border-radius: 8px; padding: 0.6rem 1.2rem; cursor: pointer; font-size: 0.85rem; width: 100%;">+ Add Project</button>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                        <button type="submit" name="magic_save" value="1" class="btn btn-primary" style="padding: 1.2rem 3rem; font-size: 1.1rem; border-radius: 50px; width: 100%; max-width: 400px;">
                            🚀 <?php echo $editing ? "Update Portfolio" : "Build My Portfolio!"; ?>
                        </button>
                        <?php if ($success && strpos($success, 'resume') !== false): ?>
                            <a href="view.php?id=<?php echo $portfolio_id; ?>" target="_blank" class="btn btn-primary" style="background: #2d5a27; padding: 1.2rem 3rem; font-size: 1.1rem; border-radius: 50px; width: 100%; max-width: 400px; text-decoration: none; color: white;">
                                LAUNCH LIVE PORTFOLIO 🚀
                            </a>
                        <?php endif; ?>
                        <p style="font-size: 0.8rem; color: #888;">This will update your Bio, Skills, Education, and Experience.</p>
                    </div>
                </form>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="manual-workflow" style="display: <?php echo $editing ? 'block' : 'none'; ?>;">    
            <?php if($editing): ?>
                <input type="hidden" name="portfolio_id" value="<?php echo $portfolio_id; ?>">
            <?php endif; ?>
            
            <div style="display: flex; gap: 2rem; align-items: flex-end; margin-bottom: 2.5rem; background: white; padding: 2.5rem; border-radius: 16px; border: 1px solid var(--border); shadow: var(--shadow);">
                <div style="text-align: center;">
                    <img src="uploads/<?php echo $current_portfolio['profile_pic'] ?? 'default_avatar.png'; ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); margin-bottom: 1rem;">
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <label for="profile_pic_input" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">Profile Pic</label>
                        <input type="file" id="profile_pic_input" name="profile_pic" style="display: none;" accept="image/*">
                        
                        <label for="hero_bg_input" class="btn btn-secondary" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">Hero BG</label>
                        <input type="file" id="hero_bg_input" name="hero_bg" style="display: none;" accept="image/*">
                    </div>
                </div>
                <div style="flex: 1;">
                    <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($current_portfolio['name'] ?? 'New Portfolio'); ?></h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($current_portfolio['job_title'] ?? 'Professional Title'); ?></p>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button type="submit" name="save_all" class="btn btn-primary" style="margin-top: 1rem; background: var(--primary);">Save Portfolio Changes</button>
                        <?php if ($editing): ?>
                            <a href="view.php?id=<?php echo $portfolio_id; ?>" target="_blank" class="btn btn-primary" style="margin-top: 1rem; background: #2d5a27; text-decoration: none; color: white;">Launch Portfolio 🚀</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-grid">
                <!-- Name -->
                <div class="card">
                    <label>Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($current_portfolio['name'] ?? ''); ?>" required>
                </div>
                <!-- Email -->
                <div class="card">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($current_portfolio['email'] ?? ''); ?>" required>
                </div>
                <!-- Phone -->
                <div class="card">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($current_portfolio['phone'] ?? ''); ?>" placeholder="+91 XXXX XXXX XX">
                </div>
                <!-- Github -->
                <div class="card">
                    <label>GitHub</label>
                    <input type="url" name="github_url" value="<?php echo htmlspecialchars($current_portfolio['github_url'] ?? ''); ?>" placeholder="github.com/username">
                </div>
                <!-- Location -->
                <div class="card">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($current_portfolio['location'] ?? ''); ?>" placeholder="City, Country">
                </div>
                <!-- Job Title -->
                <div class="card">
                    <label>Professional Title</label>
                    <input type="text" name="job_title" value="<?php echo htmlspecialchars($current_portfolio['job_title'] ?? ''); ?>" placeholder="e.g. Software Developer">
                </div>
                <!-- LinkedIn -->
                <div class="card">
                    <label>LinkedIn URL</label>
                    <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($current_portfolio['linkedin_url'] ?? ''); ?>" placeholder="linkedin.com/in/username">
                </div>
                <!-- Twitter -->
                <div class="card">
                    <label>Twitter/X URL</label>
                    <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($current_portfolio['twitter_url'] ?? ''); ?>" placeholder="twitter.com/username">
                </div>
                
                <div class="card" style="grid-column: span 1;">
                    <label>About Bio</label>
                    <textarea name="bio" rows="3"><?php echo htmlspecialchars($current_portfolio['bio'] ?? ''); ?></textarea>
                </div>

                <div class="card" style="grid-column: span 1;">
                    <label>Education</label>
                    <textarea name="education" rows="3" placeholder="College, Degree, Year..."><?php echo htmlspecialchars($current_portfolio['education'] ?? ''); ?></textarea>
                </div>

                <div class="card" style="grid-column: span 1;">
                    <label>Professional Experience</label>
                    <textarea name="experience" rows="3" placeholder="Company, Role, Duration..."><?php echo htmlspecialchars($current_portfolio['experience'] ?? ''); ?></textarea>
                </div>

                <div class="card">
                    <label>Theme Preference</label>
                    <select name="theme_choice">
                        <option value="Minimalist Light" <?php echo ($current_portfolio['theme_choice'] ?? '') == 'Minimalist Light' ? 'selected' : ''; ?>>Minimalist Light</option>
                        <option value="Professional Dark" <?php echo ($current_portfolio['theme_choice'] ?? '') == 'Professional Dark' ? 'selected' : ''; ?>>Professional Dark</option>
                        <option value="Academic Blue" <?php echo ($current_portfolio['theme_choice'] ?? '') == 'Academic Blue' ? 'selected' : ''; ?>>Academic Blue</option>
                        <option value="Modern Glass" <?php echo ($current_portfolio['theme_choice'] ?? '') == 'Modern Glass' ? 'selected' : ''; ?>>Modern Glass</option>
                    </select>
                </div>
            </div>

            <div style="margin: 3rem 0 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: var(--primary);">Projects</h2>
                <button type="button" id="add-project-btn" class="add-project-btn"><span>+</span> Add Project</button>
            </div>

            <div id="projects-container" class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <button type="button" class="delete-project-btn" onclick="this.parentElement.remove()">🗑</button>
                        <div class="project-icon">📂</div>
                        <div style="width: 100%;">
                            <div class="form-group">
                                <label>Project Title</label>
                                <input type="text" name="project_titles[]" value="<?php echo htmlspecialchars($project['title']); ?>" placeholder="Project Name">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="project_descriptions[]" rows="2" placeholder="Brief summary..."><?php echo htmlspecialchars($project['description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Live Link / GitHub</label>
                                <input type="url" name="project_links[]" value="<?php echo htmlspecialchars($project['link']); ?>" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($projects)): ?>
                    <div class="project-card">
                        <button type="button" class="delete-project-btn" onclick="this.parentElement.remove()">🗑</button>
                        <div class="project-icon">📂</div>
                        <div style="width: 100%;">
                            <div class="form-group">
                                <label>Project Title</label>
                                <input type="text" name="project_titles[]" placeholder="My First Project">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="project_descriptions[]" rows="2" placeholder="Tell us about it..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Live Link / GitHub</label>
                                <input type="url" name="project_links[]" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <label>Skills</label>
                <input type="text" name="skills" value="<?php echo htmlspecialchars($current_portfolio['skills'] ?? ''); ?>" placeholder="Python, React, Node.js, SQL (Separate by comma)">
            </div>

            <div style="margin-top: 3rem; text-align: center; padding-bottom: 5rem;">
                <button type="submit" name="save_all" class="btn btn-primary" style="width: 100%; max-width: 400px; font-size: 1.1rem; padding: 1rem;">Finalize & Save All Changes</button>
            </div>
        </form>
        <?php endif; // end ($editing || isset($_GET['new'])) ?>
    </div>

    <script>
        function showWorkflow(type) {
            document.getElementById('choice-screen').style.display = 'none';
            if (type === 'resume') {
                document.getElementById('resume-workflow').style.display = 'block';
                // Always start with the upload zone visible and preview hidden
                document.getElementById('resume-upload-zone').style.display = 'block';
                document.getElementById('resume-preview').style.display = 'none';
            } else {
                document.getElementById('manual-workflow').style.display = 'block';
            }
        }

        function resetWorkflow() {
            document.getElementById('choice-screen').style.display = 'block';
            document.getElementById('resume-workflow').style.display = 'none';
            document.getElementById('manual-workflow').style.display = 'none';
            // Also reset internal resume states
            document.getElementById('resume-upload-zone').style.display = 'block';
            document.getElementById('resume-preview').style.display = 'none';
            document.getElementById('autofill-status').innerHTML = '';
        }

        // Helper: add a project card to the Magic Preview form
        function addMagicProjectCard(title = '', desc = '', link = '') {
            const container = document.getElementById('magic-projects-container');
            const card = document.createElement('div');
            card.style.cssText = 'background:#f9f9f9; border:1px solid #ddd; border-radius:10px; padding:1rem; position:relative;';
            card.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:0.5rem;right:0.5rem;background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;border-radius:6px;padding:0.2rem 0.5rem;cursor:pointer;font-size:0.75rem;">✕</button>
                <div style="margin-bottom:0.6rem;">
                    <label style="font-size:0.75rem;font-weight:700;color:#666;display:block;margin-bottom:0.3rem;">PROJECT TITLE</label>
                    <input type="text" name="project_titles[]" value="${title.replace(/"/g, '&quot;')}" placeholder="Project Name" style="width:100%;border:1px solid #ddd;border-radius:6px;padding:0.6rem;box-sizing:border-box;font-size:0.9rem;">
                </div>
                <div style="margin-bottom:0.6rem;">
                    <label style="font-size:0.75rem;font-weight:700;color:#666;display:block;margin-bottom:0.3rem;">DESCRIPTION</label>
                    <textarea name="project_descriptions[]" rows="2" placeholder="Brief description..." style="width:100%;border:1px solid #ddd;border-radius:6px;padding:0.6rem;box-sizing:border-box;font-size:0.9rem;resize:vertical;">${desc}</textarea>
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:700;color:#666;display:block;margin-bottom:0.3rem;">LIVE LINK / GITHUB</label>
                    <input type="url" name="project_links[]" value="${link}" placeholder="https://..." style="width:100%;border:1px solid #ddd;border-radius:6px;padding:0.6rem;box-sizing:border-box;font-size:0.9rem;">
                </div>
            `;
            container.appendChild(card);
        }

        document.getElementById('add-project-btn').addEventListener('click', function() {
            const container = document.getElementById('projects-container');
            const newRow = document.createElement('div');
            newRow.className = 'project-card';
            newRow.innerHTML = `
                <button type="button" class="delete-project-btn" onclick="this.parentElement.remove()">🗑</button>
                <div class="project-icon">📂</div>
                <div style="width: 100%;">
                    <div class="form-group">
                        <label>Project Title</label>
                        <input type="text" name="project_titles[]" placeholder="New Project Name">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="project_descriptions[]" rows="2" placeholder="Brief summary..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Live Link / GitHub</label>
                        <input type="url" name="project_links[]" placeholder="https://...">
                    </div>
                </div>
            `;
            container.appendChild(newRow);
        });
        // Resume Autofill Logic
        const resumeInput = document.getElementById('resume_upload');
        const scanBtn = document.getElementById('scan_resume_btn');
        const statusDiv = document.getElementById('autofill-status');

        const fileNameDisplay = document.getElementById('file-name-display');

        resumeInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                scanBtn.disabled = false;
                scanBtn.style.opacity = '1';
                fileNameDisplay.innerHTML = `<span style="color: green; font-weight: 600;">✓ ${this.files[0].name}</span>`;
                statusDiv.innerHTML = `<span style="color: #666;">Document ready. Click scan to extract details.</span>`;
            }
        });

        // Configure pdf.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        scanBtn.addEventListener('click', async function() {
            const file = resumeInput.files[0];
            if (!file) return;

            scanBtn.disabled = true;
            statusDiv.innerHTML = `
                <div class="loading-spinner" style="display: inline-block; width: 12px; height: 12px; border: 2px solid #ccc; border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <span style="margin-left: 0.5rem; color: var(--primary);" id="ocr-status">Initializing Magic OCR...</span>
            `;

            try {
                let imageSource = file;

                // Handle PDF conversion
                if (file.type === 'application/pdf') {
                    document.getElementById('ocr-status').innerText = 'Converting PDF to image...';
                    const arrayBuffer = await file.arrayBuffer();
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    const page = await pdf.getPage(1); // Scan first page
                    const viewport = page.getViewport({ scale: 2.0 }); // Higher scale = better OCR
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    await page.render({ canvasContext: context, viewport: viewport }).promise;
                    imageSource = canvas;
                }

                const worker = await Tesseract.createWorker('eng', 1, {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            document.getElementById('ocr-status').innerText = `Analyzing: ${Math.round(m.progress * 100)}%`;
                        }
                    }
                });

                const { data: { text } } = await worker.recognize(imageSource);
                await worker.terminate();

                // Improved OCR Parser
                const parseResume = (text) => {
                    const lines = text.split('\n');
                    let data = {
                        name: "", job_title: "", location: "",
                        email: "", phone: "", linkedin: "", github: "", twitter: "",
                        bio: "", skills: "", education: "", experience: "", projects: ""
                    };

                    // Name is usually one of the first non-empty lines
                    data.name = lines.find(l => l.trim().length > 3 && !l.includes('@')) || "New User";
                    
                    // Basic Email/Phone extraction
                    const emailMatch = text.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
                    if (emailMatch) data.email = emailMatch[0];

                    const phoneMatch = text.match(/[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}/);
                    if (phoneMatch) data.phone = phoneMatch[0];

                    // Extract sections by keywords
                    const sections = {
                        skills: ['SKILLS', 'EXPERTISE', 'CORE COMPETENCIES', 'TECHNOLOGIES', 'TECHNICAL SKILLS'],
                        education: ['EDUCATION', 'ACADEMIC', 'UNIVERSITY', 'DEGREE'],
                        experience: ['EXPERIENCE', 'WORK HISTORY', 'EMPLOYMENT', 'PROFESSIONAL EXPERIENCE'],
                        projects: ['PROJECTS', 'ACADEMIC PROJECTS', 'PERSONAL PROJECTS'],
                        bio: ['SUMMARY', 'BIO', 'OBJECTIVE', 'ABOUT ME', 'PROFILE']
                    };

                    let currentSection = null;
                    lines.forEach(line => {
                        const cleanLine = line.trim().toUpperCase();
                        if (!cleanLine) return;
                        
                        // Check if line is a header
                        let foundHeader = false;
                        for (const [key, keywords] of Object.entries(sections)) {
                            // If line is short and contains keyword, or matches keyword exactly
                            if (keywords.some(kw => {
                                // Exact match or the line starts with the keyword and is short
                                return cleanLine === kw || (cleanLine.includes(kw) && cleanLine.length < 35);
                            })) {
                                currentSection = key;
                                foundHeader = true;
                                break;
                            }
                        }

                        if (foundHeader) return;

                        if (currentSection) {
                            // Preserve some line breaks for readability in large blobs
                            data[currentSection] += line.trim() + "\n";
                        }
                    });

                    // Cleanup
                    Object.keys(data).forEach(key => {
                        data[key] = data[key].trim();
                    });

                    // Fallback for bio if empty
                    if (!data.bio && text.length > 0) {
                        data.bio = text.substring(0, 300).trim() + "...";
                    }

                    return data;
                };

                const extracted = parseResume(text);

                // Populate Magic Preview Fields
                document.getElementById('magic-name').value = extracted.name || "";
                document.getElementById('magic-job-title').value = extracted.job_title || "Professional";
                document.getElementById('magic-location').value = extracted.location || "";
                document.getElementById('magic-email').value = extracted.email || "";
                document.getElementById('magic-phone').value = extracted.phone || "";
                document.getElementById('magic-linkedin').value = extracted.linkedin || "";
                document.getElementById('magic-github').value = extracted.github || "";
                document.getElementById('magic-twitter').value = extracted.twitter || "";
                
                document.getElementById('magic-bio').value = extracted.bio || "";
                document.getElementById('magic-skills').value = extracted.skills || "";
                document.getElementById('magic-education').value = extracted.education || "";
                document.getElementById('magic-experience').value = extracted.experience || "";
                // Populate project cards from extracted projects text
                const container = document.getElementById('magic-projects-container');
                container.innerHTML = '';
                if (extracted.projects) {
                    // Split projects by numbered items (1. Project, 2. Project) or double newlines
                    const rawProjects = extracted.projects
                        .split(/\n(?=\d+[\.\)]\s)|\n{2,}/)
                        .map(p => p.replace(/^\d+[\.\)]\s*/, '').trim())
                        .filter(p => p.length > 2);

                    const toShow = rawProjects.length > 0 ? rawProjects : [extracted.projects.trim()];
                    toShow.forEach(proj => {
                        // First line of project block is usually the title
                        const projLines = proj.split('\n');
                        const title = projLines[0].trim();
                        const desc = projLines.slice(1).join(' ').trim();
                        addMagicProjectCard(title, desc, '');
                    });
                }
                
                // Switch to Preview Mode
                document.getElementById('resume-upload-zone').style.display = 'none';
                document.getElementById('resume-preview').style.display = 'block';

            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = `<span style="color: red;">Error processing resume: ${err.message}</span>`;
            } finally {
                scanBtn.disabled = false;
            }
        });

        // Workflow Persistence after Save
        <?php if ($success): ?>
            <?php if (strpos($success, 'resume') !== false): ?>
                showWorkflow('resume');
                document.getElementById('resume-upload-zone').style.display = 'none';
                document.getElementById('resume-preview').style.display = 'block';
            <?php else: ?>
                showWorkflow('manual');
            <?php endif; ?>
        <?php endif; ?>

        // Auto-dismiss alerts after 5 seconds
        const alerts = ['alert-success', 'alert-error'];
        alerts.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                setTimeout(function() {
                    el.style.opacity = '0';
                    setTimeout(function() { el.style.display = 'none'; }, 500);
                }, 5000);
            }
        });
    </script>
    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</body>
</html>
