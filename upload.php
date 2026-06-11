<?php
require_once __DIR__ . '/config/layout.php';

session_start();

$settings = require __DIR__ . '/config/settings.php';
$maxSizeMb = $settings['upload_max_size_mb'] ?? 20;
$maxSizeBytes = $maxSizeMb * 1024 * 1024;
$uploadDir = $settings['upload_dir'] ?? (__DIR__ . '/uploads/');

$errors = [];
if (isset($_SESSION['compare_error'])) {
    $errors[] = $_SESSION['compare_error'];
    unset($_SESSION['compare_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $srcUploaded = isset($_FILES['source_file']) && $_FILES['source_file']['error'] === UPLOAD_ERR_OK;
    $tgtUploaded = isset($_FILES['target_file']) && $_FILES['target_file']['error'] === UPLOAD_ERR_OK;

    if (!$srcUploaded) {
        $errors[] = "Please select a valid Source SQL file. (" . getUploadErrorMessage($_FILES['source_file']['error'] ?? UPLOAD_ERR_NO_FILE) . ")";
    }
    if (!$tgtUploaded) {
        $errors[] = "Please select a valid Target SQL file. (" . getUploadErrorMessage($_FILES['target_file']['error'] ?? UPLOAD_ERR_NO_FILE) . ")";
    }

    if ($srcUploaded && $tgtUploaded) {
        $srcFile = $_FILES['source_file'];
        $tgtFile = $_FILES['target_file'];

        // Size check
        if ($srcFile['size'] > $maxSizeBytes || $tgtFile['size'] > $maxSizeBytes) {
            $errors[] = "One or more files exceed the maximum permitted size of {$maxSizeMb}MB.";
        }

        // Extension check
        $srcExt = strtolower(pathinfo($srcFile['name'], PATHINFO_EXTENSION));
        $tgtExt = strtolower(pathinfo($tgtFile['name'], PATHINFO_EXTENSION));

        if ($srcExt !== 'sql' || $tgtExt !== 'sql') {
            $errors[] = "Only files with a .sql extension are allowed.";
        }

        if (empty($errors)) {
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            // Create UUID-like names to prevent file overwrites/collisions
            $srcName = uniqid('src_', true) . '.sql';
            $tgtName = uniqid('tgt_', true) . '.sql';

            $srcPath = $uploadDir . $srcName;
            $tgtPath = $uploadDir . $tgtName;

            if (move_uploaded_file($srcFile['tmp_name'], $srcPath) && move_uploaded_file($tgtFile['tmp_name'], $tgtPath)) {
                $_SESSION['compare_mode'] = 'upload';
                $_SESSION['source_file']  = $srcPath;
                $_SESSION['target_file']  = $tgtPath;
                $_SESSION['source_name']  = htmlspecialchars($srcFile['name']);
                $_SESSION['target_name']  = htmlspecialchars($tgtFile['name']);

                header('Location: compare.php');
                exit;
            } else {
                $errors[] = "Failed to save the uploaded files. Check folder permissions.";
            }
        }
    }
}

function getUploadErrorMessage(int $errCode): string {
    switch ($errCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File exceeds upload limits";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

renderHeader('Upload DDL Files');
?>

<div class="row justify-content-center mb-4 animate-fade-in">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary border-color" style="color: var(--text-secondary);"><i class="bi bi-chevron-left me-1"></i> Back</a>
            </div>
            <h2 class="fw-bold mb-0">Upload SQL DDL Files</h2>
            <div></div> <!-- Spacer -->
        </div>

        <div class="card glass-card p-4 border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>How to get your DDL Schema files?</h5>
                <ol class="small text-secondary mb-0">
                    <li>Open SQL Server Management Studio (SSMS).</li>
                    <li>Right-click on your database &rarr; <strong>Tasks</strong> &rarr; <strong>Generate Scripts...</strong></li>
                    <li>Choose "Script entire database and all database objects".</li>
                    <li>Under "Set Scripting Options", click **Advanced** and set **Types of data to script** to <strong>Schema only</strong>.</li>
                    <li>Save to file and upload the resulting `.sql` document here.</li>
                </ol>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger border-0 p-3 mb-4 rounded-3" style="background-color: rgba(239, 68, 68, 0.08); border-left: 4px solid var(--danger-color) !important; color: var(--text-primary);">
                <ul class="mb-0 small">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="upload.php" enctype="multipart/form-data" class="row g-4">
            <!-- Source SQL File -->
            <div class="col-md-6">
                <div class="card glass-card p-4 h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-success-color text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">S</span>
                            <h5 class="fw-bold mb-0">Source SQL File</h5>
                        </div>
                        <p class="text-secondary small mb-4">The DDL script representing the reference structure.</p>
                        
                        <div class="dropzone-area mt-auto" data-input="source_file_input">
                            <i class="bi bi-cloud-arrow-up display-5 text-muted mb-3 d-block"></i>
                            <span class="dropzone-label text-secondary small">Click or Drag DDL script here</span>
                            <input type="file" name="source_file" id="source_file_input" class="d-none" accept=".sql" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target SQL File -->
            <div class="col-md-6">
                <div class="card glass-card p-4 h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-danger-color text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">T</span>
                            <h5 class="fw-bold mb-0">Target SQL File</h5>
                        </div>
                        <p class="text-secondary small mb-4">The DDL script representing the database you wish to sync.</p>
                        
                        <div class="dropzone-area mt-auto" data-input="target_file_input">
                            <i class="bi bi-cloud-arrow-up display-5 text-muted mb-3 d-block"></i>
                            <span class="dropzone-label text-secondary small">Click or Drag DDL script here</span>
                            <input type="file" name="target_file" id="target_file_input" class="d-none" accept=".sql" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 text-center mt-5">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-lightning-charge me-1"></i> Start Schema Comparison
                </button>
                <div class="text-muted small mt-2">Maximum file size: <?php echo $maxSizeMb; ?>MB per file</div>
            </div>
        </form>
    </div>
</div>

<?php
renderFooter();
?>
