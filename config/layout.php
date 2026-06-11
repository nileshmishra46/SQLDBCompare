<?php

function renderHeader(string $title = 'SQL Schema Comparator'): void {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | SQL Schema Comparator</title>
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <!-- Custom premium styling -->
        <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    </head>
    <body>
        
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg py-3 border-bottom border-color bg-white" style="background: var(--bg-secondary) !important; border-color: var(--border-color) !important;">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="index.php">
                    <span class="fs-4 brand-gradient"><i class="bi bi-database-fill-gear me-2"></i>SQL Schema Comparator</span>
                </a>
                
                <div class="d-flex align-items-center gap-3">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm border-0 d-none d-sm-inline-flex align-items-center gap-1" style="color: var(--text-secondary);">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                    
                    <button class="theme-switch-btn" id="theme-toggle-btn" title="Toggle Theme" aria-label="Toggle Theme">
                        <i class="bi bi-moon-fill"></i>
                    </button>
                </div>
            </div>
        </nav>

        <div class="container py-5">
    <?php
}

function renderFooter(): void {
    ?>
        </div> <!-- End container -->

        <footer class="footer mt-auto py-4 border-top border-color text-center" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
            <div class="container">
                <span class="text-muted small">&copy; <?php echo date('Y'); ?> SQL Schema Comparator. Developed by Nilesh Mishra. Built for secure database migrations.</span>
            </div>
        </footer>

        <!-- Bootstrap 5 JS Bundle -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Custom scripts -->
        <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    </body>
    </html>
    <?php
}
