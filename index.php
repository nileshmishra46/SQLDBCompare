<?php
require_once __DIR__ . '/config/layout.php';

renderHeader('Home');
?>

<div class="row justify-content-center text-center mb-5 animate-fade-in">
    <div class="col-lg-8">
        <span class="badge bg-primary-glow text-primary px-3 py-2 rounded-pill mb-3" style="color: var(--primary-color) !important; background: var(--primary-glow) !important; font-weight: 600;">Secure & Offline-First</span>
        <h1 class="display-5 fw-bold mb-3">Compare SQL Server Schemas</h1>
        <p class="lead text-secondary">
            Instantly detect differences in tables, columns, indexes, keys, triggers, and constraints. Generate remediation scripts to bring your databases in sync.
        </p>
    </div>
</div>

<div class="row justify-content-center g-4 animate-fade-in" style="animation-delay: 0.1s;">
    <!-- Live Database connection mode -->
    <div class="col-md-5">
        <a href="connect.php" class="card h-100 glass-card p-4 mode-card border-0">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mode-icon bg-brand-gradient text-white">
                    <i class="bi bi-hdd-network"></i>
                </div>
                <h3 class="card-title fw-bold mb-3">Live Database Connections</h3>
                <p class="card-text text-secondary mb-4">
                    Connect directly to two online or local SQL Server instances via security-compliant credentials and inspect schemas in real-time.
                </p>
                <span class="btn btn-primary mt-auto w-100">
                    Connect & Compare <i class="bi bi-arrow-right ms-1"></i>
                </span>
            </div>
        </a>
    </div>

    <!-- DDL File upload mode -->
    <div class="col-md-5">
        <a href="upload.php" class="card h-100 glass-card p-4 mode-card border-0">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mode-icon bg-brand-gradient text-white" style="background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%) !important;">
                    <i class="bi bi-file-earmark-code"></i>
                </div>
                <h3 class="card-title fw-bold mb-3">Upload SQL DDL Files</h3>
                <p class="card-text text-secondary mb-4">
                    Compare two `.sql` schema exports generated from SSMS or schema generation scripts. No active DB connection required.
                </p>
                <span class="btn btn-primary mt-auto w-100" style="background: var(--info-color); border-color: var(--info-color); box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);">
                    Upload Files <i class="bi bi-arrow-right ms-1"></i>
                </span>
            </div>
        </a>
    </div>
</div>

<div class="row justify-content-center mt-5 pt-4 animate-fade-in" style="animation-delay: 0.2s;">
    <div class="col-lg-10">
        <div class="glass-card p-4 border-0 text-center">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-2"></i>Security & Privacy Assured</h5>
            <p class="text-secondary small mb-0">
                This schema comparator runs completely within your own server context. Database passwords and connection details are stored only in PHP session memory, never cached to disk, and are destroyed when your session expires or is closed. Uploaded SQL files are held in temporary storage and cleaned up automatically.
            </p>
        </div>
    </div>
</div>

<?php
renderFooter();
?>
