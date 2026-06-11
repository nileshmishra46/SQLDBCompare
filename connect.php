<?php
require_once __DIR__ . '/config/layout.php';

session_start();

$sqlsrvLoaded = extension_loaded('sqlsrv') && extension_loaded('pdo_sqlsrv');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sqlsrvLoaded) {
        $errors[] = "The sqlsrv/pdo_sqlsrv PHP extensions are not loaded. Live connections are disabled.";
    } else {
        $sourceParams = [
            'host'                     => $_POST['src_host'] ?? '',
            'port'                     => $_POST['src_port'] ?? '1433',
            'dbname'                   => $_POST['src_dbname'] ?? '',
            'user'                     => $_POST['src_user'] ?? '',
            'password'                 => $_POST['src_password'] ?? '',
            'encrypt'                  => isset($_POST['src_encrypt']),
            'trust_server_certificate' => isset($_POST['src_trust']),
        ];

        $targetParams = [
            'host'                     => $_POST['tgt_host'] ?? '',
            'port'                     => $_POST['tgt_port'] ?? '1433',
            'dbname'                   => $_POST['tgt_dbname'] ?? '',
            'user'                     => $_POST['tgt_user'] ?? '',
            'password'                 => $_POST['tgt_password'] ?? '',
            'encrypt'                  => isset($_POST['tgt_encrypt']),
            'trust_server_certificate' => isset($_POST['tgt_trust']),
        ];

        // Basic validation
        if (empty($sourceParams['host']) || empty($sourceParams['dbname'])) {
            $errors[] = "Source Database Host and Name are required.";
        }
        if (empty($targetParams['host']) || empty($targetParams['dbname'])) {
            $errors[] = "Target Database Host and Name are required.";
        }

        if (empty($errors)) {
            // Save connection credentials in session (temporary, clean)
            $_SESSION['compare_mode'] = 'live';
            $_SESSION['source_db'] = $sourceParams;
            $_SESSION['target_db'] = $targetParams;

            header('Location: compare.php');
            exit;
        }
    }
}

renderHeader('Connect Live DB');
?>

<div class="row justify-content-center mb-4 animate-fade-in">
    <div class="col-lg-10">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary border-color" style="color: var(--text-secondary);"><i class="bi bi-chevron-left me-1"></i> Back</a>
            </div>
            <h2 class="fw-bold mb-0">Connect Live Databases</h2>
            <div></div> <!-- Spacer -->
        </div>

        <?php if (!$sqlsrvLoaded): ?>
            <div class="alert alert-warning border-0 p-4 mb-4 glass-card" style="background-color: rgba(245, 158, 11, 0.08); border-left: 4px solid var(--warning-color) !important; color: var(--text-primary);">
                <h5 class="fw-bold"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>SQL Server Driver Not Detected</h5>
                <p class="mb-3 small">
                    The Microsoft <strong>sqlsrv</strong> and <strong>pdo_sqlsrv</strong> extensions are required in order to connect directly to databases. Because they are not currently enabled on your PHP environment, you cannot execute live database comparisons.
                </p>
                <div class="d-flex gap-3 align-items-center">
                    <a href="upload.php" class="btn btn-sm btn-warning fw-bold text-dark"><i class="bi bi-file-earmark-code me-1"></i> Use SQL File Upload instead</a>
                    <span class="small text-secondary">Or follow the setup steps in the project documentation.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger border-0 p-3 mb-4 rounded-3" style="background-color: rgba(239, 68, 68, 0.08); border-left: 4px solid var(--danger-color) !important; color: var(--text-primary);">
                <ul class="mb-0 small">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="connect.php" class="row g-4">
            <!-- Source Database Column -->
            <div class="col-md-6">
                <div class="card glass-card h-100 p-4 border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="badge bg-success-color text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">S</span>
                            <h4 class="fw-bold mb-0">Source Database</h4>
                        </div>
                        <p class="text-secondary small mb-4">The reference schema that contains the correct structure you want to migrate.</p>

                        <div class="mb-3">
                            <label class="form-label">Server / Host</label>
                            <input type="text" name="src_host" class="form-control" placeholder="localhost or 192.168.1.100\SQLEXPRESS" value="<?php echo htmlspecialchars($_POST['src_host'] ?? 'localhost'); ?>" required <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Database Name</label>
                                <input type="text" name="src_dbname" class="form-control" placeholder="SourceDB" value="<?php echo htmlspecialchars($_POST['src_dbname'] ?? ''); ?>" required <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="text" name="src_port" class="form-control" placeholder="1433" value="<?php echo htmlspecialchars($_POST['src_port'] ?? '1433'); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="src_user" class="form-control" placeholder="sa" value="<?php echo htmlspecialchars($_POST['src_user'] ?? ''); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="src_password" class="form-control" placeholder="••••••••" value="<?php echo htmlspecialchars($_POST['src_password'] ?? ''); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="mb-2 form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="src_encrypt" id="src_encrypt" checked <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary small" for="src_encrypt">Encrypt connection (recommended)</label>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="src_trust" id="src_trust" checked <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary small" for="src_trust">Trust Server Certificate (required for dev environments)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Database Column -->
            <div class="col-md-6">
                <div class="card glass-card h-100 p-4 border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="badge bg-danger-color text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">T</span>
                            <h4 class="fw-bold mb-0">Target Database</h4>
                        </div>
                        <p class="text-secondary small mb-4">The database that will be compared and altered using the generated script.</p>

                        <div class="mb-3">
                            <label class="form-label">Server / Host</label>
                            <input type="text" name="tgt_host" class="form-control" placeholder="localhost or 192.168.1.100\SQLEXPRESS" value="<?php echo htmlspecialchars($_POST['tgt_host'] ?? 'localhost'); ?>" required <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Database Name</label>
                                <input type="text" name="tgt_dbname" class="form-control" placeholder="TargetDB" value="<?php echo htmlspecialchars($_POST['tgt_dbname'] ?? ''); ?>" required <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="text" name="tgt_port" class="form-control" placeholder="1433" value="<?php echo htmlspecialchars($_POST['tgt_port'] ?? '1433'); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="tgt_user" class="form-control" placeholder="sa" value="<?php echo htmlspecialchars($_POST['tgt_user'] ?? ''); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="tgt_password" class="form-control" placeholder="••••••••" value="<?php echo htmlspecialchars($_POST['tgt_password'] ?? ''); ?>" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="mb-2 form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="tgt_encrypt" id="tgt_encrypt" checked <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary small" for="tgt_encrypt">Encrypt connection (recommended)</label>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tgt_trust" id="tgt_trust" checked <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary small" for="tgt_trust">Trust Server Certificate (required for dev environments)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="col-12 text-center mt-5">
                <button type="submit" class="btn btn-primary btn-lg px-5" <?php echo !$sqlsrvLoaded ? 'disabled' : ''; ?>>
                    <i class="bi bi-lightning-charge me-1"></i> Start Schema Comparison
                </button>
            </div>
        </form>
    </div>
</div>

<?php
renderFooter();
?>
