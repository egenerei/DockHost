<?php
/**
 * Simple PHP File Manager (no built‑in auth)
 * -------------------------------------------------
 * This variant removes the session‑based username/password login.
 * Place the script behind **your own** authentication layer 
 * (e.g. HTTP Basic auth, reverse‑proxy SSO, JWT, etc.).
 *
 * Features:
 *  • Whitelist of directories
 *  • List, upload, download, delete files
 *  • Session is now used **only** for CSRF token storage
 */

#Authentication
session_start();
require_once '../includes/classes/login.class.php';
require_once '../includes/db/db.php';
if (isset($_SESSION['login'])) {
    $login = unserialize($_SESSION['login']);
    $subdomain = $login->get_subdomain();
} else {
    header("Location: login.php");
    exit;
}

// ---------- CONFIG ----------------------------------------------------------
$ALLOWED_DIRS = [
    "/clients/$subdomain/website"
];

// ---------- HELPER FUNCTIONS ------------------------------------------------
function csrf_token(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

#declare(strict_types=1);

/**
 * Resolve $target inside one of the $GLOBALS['ALLOWED_DIRS'].
 * - Follows symlinks correctly.
 * - Always returns a canonical absolute path.
 * - Throws verb-specific HTTP errors instead of exit().
 */
function safe_path(string $dir, string $file = ''): string
{
    // Compose candidate, resolve it, bail early if the path is invalid
    $candidate = ($file === '')
        ? $dir
        : rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);

    $realCandidate = realpath($candidate);
    if ($realCandidate === false) {
        http_response_code(404);
        throw new RuntimeException('Path not found');
    }

    /* Every allowed root must itself be canonicalised because admins might
       have added a symlink inside the whitelist (e.g. /srv/clients ➜ /data/clients) */
    foreach ($GLOBALS['ALLOWED_DIRS'] as $root) {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            continue; // skip mis-configured root silently; you may choose to log
        }

        $rootReal = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($realCandidate . DIRECTORY_SEPARATOR, $rootReal)) {
            return $realCandidate;     // ✅ legit
        }
    }

    http_response_code(403);
    throw new RuntimeException('Access denied');
}


function human_filesize(int $bytes, int $decimals = 2): string
{
    $size = ['B','K','M','G','T','P'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
}

function breadcrumb_options($allowed_dirs, $current_dir, $file = null) {
    foreach ($allowed_dirs as $root) {
        $root = rtrim($root, '/');
        // If current dir is under this root
        if (strpos($current_dir, $root) === 0) {
            $rel = ltrim(substr($current_dir, strlen($root)), '/');
            $parts = $rel === '' ? [] : explode('/', $rel);
            $paths = [];
            $path = $root;
            $label = basename($root);
            $paths[] = [$path, $label, false]; // not disabled

            foreach ($parts as $part) {
                if ($part === '') continue;
                $path .= '/' . $part;
                $rel_label = ltrim(substr($path, strlen($root)), '/');
                $paths[] = [$path, $label . ($rel_label ? "/$rel_label" : ""), false];
            }

            // If editing a file, add it last as disabled
            if ($file) {
                $file_label = $label . ($rel ? "/$rel" : "") . "/$file";
                $paths[] = [null, $file_label, true]; // disabled
            }

            return $paths;
        }
    }
    // If not in any allowed dir, just show allowed roots
    return array_map(function($root) {
        return [$root, basename($root), false];
    }, $allowed_dirs);
}

function is_in_allowed($path) {
    foreach ($GLOBALS['ALLOWED_DIRS'] as $root) {
        $root = rtrim($root, '/');
        if (strpos(rtrim($path, '/'), $root) === 0 && strlen($path) >= strlen($root)) {
            return true;
        }
    }
    return false;
}
// ---------- ACTION HANDLERS -------------------------------------------------
$action = $_GET['action'] ?? 'home';

/* =========================================================
   DOWNLOAD –– send the file as “attachment”
   ========================================================= */
if ($action === 'download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $dir  = $_GET['dir']  ?? '';
    $file = $_GET['file'] ?? '';
    $path = safe_path($dir, $file);

    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit('File not found');
    }

    // Tell the browser to download
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('X-Content-Type-Options: nosniff');

    readfile($path);
    exit;
}
/* =========================================================
   EDIT  –– display a textarea with the file’s current text
   ========================================================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $dir  = $_GET['dir']  ?? '';
    $file = $_GET['file'] ?? '';

    $path = safe_path($dir, $file);

    // Guard: only real, readable files under 1 MB
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit('File not found');
    }
    if (filesize($path) > 10 * 1024 * 1024) {     // 10 MiB soft limit
        http_response_code(413);
        exit('File too large to edit in browser');
    }

    $contents = file_get_contents($path);

/* ---- render editor ---- */
render_header("Editing {$file}");

/* ---- alerts reused from directory view ---- */
if (isset($_GET['msg']) && $_GET['msg'] === 'save_ok') {
    echo "<div class='alert alert-success'>File saved</div>";
}

$escaped = htmlspecialchars($contents, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo "<form class='full-width-form' method='post' action='?action=save'>
    <input type='hidden' name='csrf' value='".csrf_token()."'>
    <input type='hidden' name='dir'  value='".htmlspecialchars($dir,  ENT_QUOTES)."'>
    <input type='hidden' name='file' value='".htmlspecialchars($file, ENT_QUOTES)."'>
    <div class='mb-3'>
        <div class='d-flex justify-content-between align-items-center mb-2'>
            <label class='form-label fw-bold mb-0'>Editing: {$file}</label>
            <div>
                <button class='btn btn-primary me-2'>Save</button>
                <a href='?dir=".urlencode($dir)."' class='btn btn-secondary'>Exit file editor</a>
            </div>
        </div>
        <div style='width: 100%;'>
            <textarea id='editor' name='contents' class='form-control full-width-textarea'
                rows='30' spellcheck='false'>{$escaped}</textarea>
        </div>
    </div>
</form>

<script>
/* make Tab insert four spaces instead of switching focus */
(function () {
    const ta = document.getElementById('editor');
    if (!ta) return;

    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            e.preventDefault();

            const tab = '    ';               // ← four spaces; use '\\t' for a literal tab
            const start = this.selectionStart;
            const end   = this.selectionEnd;

            // replace selection with TAB
            const before = this.value.slice(0, start);
            const after  = this.value.slice(end);
            this.value   = before + tab + after;

            // move cursor just after inserted spaces
            this.selectionStart = this.selectionEnd = start + tab.length;
        }
    });
})();
</script>";
exit;
}

/* =========================================================
   SAVE  –– write the posted text back to disk
   ========================================================= */
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dir  = $_POST['dir']  ?? '';
    $file = $_POST['file'] ?? '';
    $path = safe_path($dir, $file);

    if (!is_file($path) || !is_writable($path)) {
        http_response_code(403);
        exit('File not writable');
    }

    $new = $_POST['contents'] ?? '';

    /* atomic-ish update: write to temp, then rename */
    $tmp = $path . '.tmp';
    file_put_contents($tmp, $new, LOCK_EX);
    rename($tmp, $path);

    header("Location: admin.php?action=edit&dir={$dir}&file={$file}&msg=save_ok");
    exit;
}

/* =========================================================
   CREATE –– make an empty file, then jump into the editor
   ========================================================= */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dir  = $_POST['dir']  ?? '';
    $name = $_POST['name'] ?? '';

    /* basic file-name hygiene: letters, digits, dot, dash, underscore */
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        header("Location: admin.php?dir={$dir}&msg=create_invalid");
        exit;
    }

    $targetDir = safe_path($dir);
    $destPath  = $targetDir . DIRECTORY_SEPARATOR . $name;

    if (file_exists($destPath)) {                       // already there?
        header("Location: admin.php?dir={$dir}&msg=create_exists");
        exit;
    }

    /* attempt to create an empty file */
    if (file_put_contents($destPath, '') !== false) {
        header("Location: admin.php?action=edit&dir={$dir}&file={$name}&msg=create_ok");
    } else {
        header("Location: admin.php?dir={$dir}&msg=create_fail");
    }
    exit;
}

/* =========================================================
   UPLOAD –– accept many files and nested directories
   ========================================================= */
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $dir = $_POST['dir'] ?? '';
    $targetDir = safe_path($dir);

    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        header("Location: admin.php?dir={$dir}&msg=upload_fail");
        exit;
    }

    $total  = count($_FILES['files']['name']);
    $ok     = 0;
    $failed = 0;

    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }

        $relPath = $_FILES['files']['full_path'][$i];

        // Security: no absolute or parent directory references!
        $relPath = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relPath), DIRECTORY_SEPARATOR);

        if (strpos($relPath, '..') !== false) {
            $failed++;
            continue;
        }

        $fullDestPath = $targetDir . DIRECTORY_SEPARATOR . $relPath;
        $destDir = dirname($fullDestPath);

        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0775, true)) {
                $failed++;
                continue;
            }
        }

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $fullDestPath)) {
            $ok++;
        } else {
            $failed++;
        }
    }
    /* decide which banner to show */
    if ($ok && !$failed) {
        $msg = 'upload_ok';
    } elseif ($ok && $failed) {
        $msg = 'upload_partial';
    } else {
        $msg = 'upload_fail';
    }

    header("Location: admin.php?dir={$dir}&msg={$msg}");
    exit;
}

/* =========================================================
   CREATE DIRECTORY –– make a new empty directory
   ========================================================= */
if ($action === 'mkdir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dir  = $_POST['dir'] ?? '';
    $name = $_POST['name'] ?? '';

    // Allow only safe directory names
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        header("Location: admin.php?dir={$dir}&msg=mkdir_invalid");
        exit;
    }

    $targetDir = safe_path($dir);
    $destPath  = $targetDir . DIRECTORY_SEPARATOR . $name;

    if (file_exists($destPath)) {
        header("Location: admin.php?dir={$dir}&msg=mkdir_exists");
        exit;
    }

    if (mkdir($destPath, 0775)) {
        header("Location: admin.php?dir={$dir}&msg=mkdir_ok");
    } else {
        header("Location: admin.php?dir={$dir}&msg=mkdir_fail");
    }
    exit;
}
/* =========================================================
   DELETE DIRECTORY –– recursively delete a directory
   ========================================================= */
if ($action === 'rmdir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $dir  = $_POST['dir'] ?? '';
    $name = $_POST['name'] ?? '';
    $path = safe_path($dir, $name);

    function rrmdir($dir) {
        foreach (array_diff(scandir($dir), ['.','..']) as $file) {
            $full = "$dir/$file";
            if (is_dir($full)) rrmdir($full);
            else unlink($full);
        }
        rmdir($dir);
    }

    if (is_dir($path)) {
        rrmdir($path);
        header("Location: admin.php?dir={$dir}&msg=rmdir_ok");
    } else {
        header("Location: admin.php?dir={$dir}&msg=rmdir_fail");
    }
    exit;
}

// DELETE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $dir  = $_POST['dir']  ?? '';
    $file = $_POST['file'] ?? '';
    $path = safe_path($dir, $file);

    if (is_file($path) && unlink($path)) {
        header("Location: admin.php?dir={$dir}&msg=delete_ok");
    } else {
        header("Location: admin.php?dir={$dir}&msg=delete_fail");
    }
    exit;
}
/* =========================================================
   RENAME –– rename a file or directory
   ========================================================= */
if ($action === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dir     = $_POST['dir']     ?? '';
    $oldname = $_POST['oldname'] ?? '';
    $newname = $_POST['newname'] ?? '';

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $newname)) {
        header("Location: admin.php?dir={$dir}&msg=rename_invalid");
        exit;
    }

    $basePath = safe_path($dir);
    $oldPath  = $basePath . DIRECTORY_SEPARATOR . $oldname;
    $newPath  = $basePath . DIRECTORY_SEPARATOR . $newname;

    if (!file_exists($oldPath)) {
        header("Location: admin.php?dir={$dir}&msg=rename_missing");
        exit;
    }

    if (file_exists($newPath)) {
        header("Location: admin.php?dir={$dir}&msg=rename_exists");
        exit;
    }

    if (rename($oldPath, $newPath)) {
        header("Location: admin.php?dir={$dir}&msg=rename_ok");
    } else {
        header("Location: admin.php?dir={$dir}&msg=rename_fail");
    }
    exit;
}

// ---------- VIEWS -----------------------------------------------------------
function render_header(string $title = 'PHP File Manager'): void
{
    echo "<!doctype html><html lang='en'><head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width,initial-scale=1'>

        <!-- your minimal sheet first -->
        <link rel='stylesheet' href='css/style.css'>

        <!-- Bootstrap afterwards so its utility classes still work -->
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>

        <title>{$title}</title>
    </head><body class='bg-light'>";
    $login = unserialize($_SESSION['login']);
    $subdomain = $login->get_subdomain();
    $client_domain = $subdomain . '.'. get_domain();
    $client_domain_phpmyadmin = $client_domain.'/phpmyadmin';
    include("../includes/navbars/links_navbar.php");
    echo "<main class='container py-4'>";
}

function render_footer(): void
{
    echo "</main>
        <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>
    </body></html>";
}

// MAIN DIRECTORY LISTING
$dir = $_GET['dir'] ?? $ALLOWED_DIRS[0];
$file = $_GET['file'] ?? '';
$currentDir = safe_path($dir);

$files = array_values(array_filter(
    scandir($currentDir),
    fn($f) => $f !== '.' && $f !== '..'
));

render_header("Browsing {$login->get_username()}");

if (isset($_GET['msg'])) {
    $alerts = [
        'upload_ok'   => ['success', 'File uploaded successfully'],
        'upload_fail' => ['danger',  'File upload failed'],
        'delete_ok'   => ['success', 'File deleted'],
        'delete_fail' => ['danger',  'Failed to delete file'],
        'create_ok'     => ['success', 'File created'],
        'create_exists' => ['danger',  'File already exists'],
        'create_fail'   => ['danger',  'Could not create file'],
        'create_invalid'=> ['danger',  'Illegal file name'],
        'mkdir_ok'     => ['success', 'Directory created'],
        'mkdir_exists' => ['danger',  'Directory already exists'],
        'mkdir_fail'   => ['danger',  'Could not create directory'],
        'mkdir_invalid'=> ['danger',  'Illegal directory name'],
        'rmdir_ok'   => ['success', 'Directory deleted'],
        'rmdir_fail' => ['danger',  'Could not delete directory'],

        'save_ok'     => ['success', 'File saved']
    ];
    if (isset($alerts[$_GET['msg']])) {
        [$type, $text] = $alerts[$_GET['msg']];
        echo "<div class='alert alert-{$type}'>{$text}</div>";
    }
}

/* ---------- DIRECTORY SELECTOR EDITOR VIEW---------- */
echo "<form class='mb-4'>
    <label class='form-label'>Select directory:</label>
    <select name='dir'
            class='form-select w-auto d-inline-block me-2'
            onchange='this.form.submit()'>";
foreach (breadcrumb_options($GLOBALS['ALLOWED_DIRS'], $dir, $file) as $opt) {
    list($val, $label, $disabled) = $opt;
    $sel = ($val === $dir) ? 'selected' : '';
    $dis = $disabled ? 'disabled selected' : '';
    echo "<option value='$val' $sel $dis>$label</option>";
}
echo "</select></form>";

/* ---------- FILE TABLE ---------- */
echo "<div class='table-responsive'><table class='table table-striped'>
        <thead>
            <tr><th>Name</th><th>Size</th><th>Modified</th><th style='width:130px;'></th></tr>
        </thead><tbody>";

// Helper to check if a dir is in allowed roots
// Only show ".." if parent is still in allowed root
$parent = dirname($dir);
$in_root = false;
foreach ($GLOBALS['ALLOWED_DIRS'] as $root) {
    $root = rtrim($root, '/');
    if (strpos(rtrim($parent, '/'), $root) === 0 && strlen($parent) >= strlen($root)) {
        $in_root = true;
        break;
    }
}
if ($in_root && $parent !== $dir) {
    echo "<tr>
            <td><a href='?dir=" . urlencode($parent) . "'>..</a></td>
            <td>—</td><td>—</td><td></td>
          </tr>";
}

foreach ($files as $file) {
    $path  = $currentDir . DIRECTORY_SEPARATOR . $file;
    $size  = is_file($path) ? human_filesize(filesize($path)) : '—';
    $mtime = date('Y-m-d H:i', filemtime($path));

    echo '<tr><td>';

    /* -------- name column -------- */
    if (is_dir($path)) {
        $subdir = urlencode($dir . '/' . $file);
        echo "<a href='?dir={$subdir}'>{$file}/</a>";
    } else {
        echo "<a href='?action=edit&dir=" . urlencode($dir)
           . "&file=" . urlencode($file) . "'>{$file}</a>";
    }

    echo "</td>
          <td>{$size}</td>
          <td>{$mtime}</td>
          <td class='text-end text-nowrap'>"; 

    if (is_dir($path)) {
        $escDir  = htmlspecialchars($dir,  ENT_QUOTES);
        $escName = htmlspecialchars($file, ENT_QUOTES);

        echo "<button class='btn btn-primary'
                    type='button'
                    onclick=\"showRenameDialog('{$escName}')\">
                Rename
            </button>";

        echo "<form method='post' action='?action=rmdir' class='d-inline me-1'>
                <input type='hidden' name='csrf' value='".csrf_token()."'>
                <input type='hidden' name='dir'  value='{$escDir}'>
                <input type='hidden' name='name' value='{$escName}'>
                <button class='btn btn-danger'
                        onclick='return confirm(\"Delete directory {$file}? This cannot be undone!\")'>
                    Delete
                </button>
            </form>";
    }

    if (is_file($path)) {
        $escDir  = htmlspecialchars($dir,  ENT_QUOTES);
        $escFile = htmlspecialchars($file, ENT_QUOTES);

        echo "<a href='?action=download&dir={$escDir}&file={$escFile}'
        class='btn btn-success me-1'>Download</a>";

        echo "<button class='btn btn-primary'
                    type='button'
                    onclick=\"showRenameDialog('{$escFile}')\">
                Rename
            </button>";

        echo "<form method='post' action='?action=delete' class='d-inline'>
                <input type='hidden' name='csrf' value='".csrf_token()."'>
                <input type='hidden' name='dir'  value='{$escDir}'>
                <input type='hidden' name='file' value='{$escFile}'>
                <button class='btn btn-danger'>Delete</button>
            </form>";
    }

    echo "</td></tr>";
}

echo "</tbody></table></div>";

$safe_dir = htmlspecialchars($dir, ENT_QUOTES); ?>
<dialog id="uploadModal" class="dh-modal">
  <form method="post" enctype="multipart/form-data" action="?action=upload">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="dir" value="<?= $safe_dir ?>">
    <h2>Upload Files</h2>
    <input type="file" name="files[]" multiple required>
    <menu>
      <button type="submit">Upload</button>
      <button type="button" onclick="closeModal('uploadModal')">Cancel</button>
    </menu>
  </form>
</dialog>

<dialog id="uploadDirModal" class="dh-modal">
  <form method="post" enctype="multipart/form-data" action="?action=upload">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="dir" value="<?= $safe_dir ?>">
    <h2>Upload Directory</h2>
    <input type="file" name="files[]" multiple webkitdirectory directory required>
    <menu>
      <button type="submit">Upload</button>
      <button type="button" onclick="closeModal('uploadDirModal')">Cancel</button>
    </menu>
  </form>
</dialog>

<dialog id="createFileModal" class="dh-modal">
  <form method="post" action="?action=create">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="dir" value="<?= $safe_dir ?>">
    <h2>Create New File</h2>
    <input type="text" name="name" placeholder="index.php" required>
    <menu>
      <button type="submit">Create</button>
      <button type="button" onclick="closeModal('createFileModal')">Cancel</button>
    </menu>
  </form>
</dialog>

<dialog id="createDirModal" class="dh-modal">
  <form method="post" action="?action=mkdir">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="dir" value="<?= $safe_dir ?>">
    <h2>Create New Directory</h2>
    <input type="text" name="name" placeholder="new_folder" required>
    <menu>
      <button type="submit">Create</button>
      <button type="button" onclick="closeModal('createDirModal')">Cancel</button>
    </menu>
  </form>
</dialog>

<dialog id="renameModal" class="dh-modal">
  <form method="post" action="?action=rename">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="dir" value="<?= $safe_dir ?>">
    <input type="hidden" name="oldname" id="rename-oldname">
    <h2>Rename</h2>
    <label>New name:</label>
    <input type="text" name="newname" id="rename-newname" required>
    <menu>
      <button type="submit">Rename</button>
      <button type="button" onclick="closeModal('renameModal')">Cancel</button>
    </menu>
  </form>
</dialog>


<script>
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.showModal();
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.close();
}
function toggleDropdown() {
  const dropdown = document.getElementById('fileToolsDropdown');
  dropdown.classList.toggle('open');
  dropdown.style.display = dropdown.classList.contains('open') ? 'block' : 'none';
}
function showRenameDialog(oldName) {
  document.getElementById('rename-oldname').value = oldName;
  document.getElementById('rename-newname').value = oldName;
  openModal('renameModal');
}

</script>

<?php
render_footer();
?>
