<?php
// church/modules/church_records/baptism/edit_series.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    $_SESSION['error'] = 'You do not have permission to manage series.';
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// Handle update or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $series_year = (int)($_POST['series_year'] ?? 0);
    $action      = $_POST['action'] ?? 'update';
    $notes       = trim($_POST['notes'] ?? '');

    if ($series_year <= 0) {
        $_SESSION['error'] = 'Invalid series year.';
        header('Location: /church/modules/church_records/baptism/series_list.php');
        exit;
    }

    if ($action === 'delete') {
        // Do not allow deleting a series that still has records
        $check = $conn->prepare("SELECT COUNT(*) FROM baptism_records WHERE series_year = ? AND is_archived = 0");
        $check->bind_param("i", $series_year);
        $check->execute();
        $check->bind_result($cnt);
        $check->fetch();
        $check->close();

        if ($cnt > 0) {
            $_SESSION['error'] = "Cannot delete series {$series_year} because it still has baptism records.";
            header("Location: /church/modules/church_records/baptism/edit_series.php?year={$series_year}");
            exit;
        }

        $del = $conn->prepare("DELETE FROM baptism_series WHERE series_year = ?");
        $del->bind_param("i", $series_year);
        $del->execute();
        $del->close();

        $_SESSION['success'] = "Baptism series {$series_year} was removed.";
        header('Location: /church/modules/church_records/baptism/series_list.php');
        exit;
    }

    // Update notes only (year stays the same)
    $upd = $conn->prepare("UPDATE baptism_series SET notes = ? WHERE series_year = ?");
    $upd->bind_param("si", $notes, $series_year);
    $upd->execute();
    $upd->close();

    $_SESSION['success'] = "Baptism series {$series_year} was updated.";
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// GET: load series info
$year = (int)($_GET['year'] ?? 0);
if ($year <= 0) {
    $_SESSION['error'] = 'Invalid series year.';
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, series_year, notes FROM baptism_series WHERE series_year = ?");
$stmt->bind_param("i", $year);
$stmt->execute();
$series = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$series) {
    $_SESSION['error'] = 'Series not found.';
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

$page_title = "Edit Baptism Series {$series['series_year']}";
include $root . '/includes/header.php';
?>

<div style="max-width:640px;margin:32px auto 60px;padding:24px;border-radius:16px;background:#fff;border:1px solid #ede8de;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h1 style="font-size:1.1rem;display:flex;align-items:center;gap:8px;margin:0;">
            <i class="fas fa-droplet" style="color:#3b82f6;"></i>
            Edit Baptism Series (<?= htmlspecialchars($series['series_year']) ?>)
        </h1>
        <a href="/church/modules/church_records/baptism/series_list.php"
           style="font-size:0.8rem;color:#6b7280;text-decoration:none;">
            ← Back to series list
        </a>
    </div>

    <p style="font-size:0.8rem;color:#6b7280;margin-bottom:18px;">
        Adjust the notes for this year series or remove the series if it has no records.
    </p>

    <form method="POST" action="/church/modules/church_records/baptism/edit_series.php" style="margin-bottom:20px;">
        <input type="hidden" name="series_year" value="<?= htmlspecialchars($series['series_year']) ?>">
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:4px;">
                Series Year
            </label>
            <input type="text" value="<?= htmlspecialchars($series['series_year']) ?>"
                   readonly
                   style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #e5e7eb;
                          background:#f9fafb;font-size:0.875rem;color:#4b5563;">
            <p style="font-size:0.72rem;color:#9ca3af;margin-top:4px;">
                Year cannot be changed here. Create a new series if you need a different year.
            </p>
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:4px;">
                Notes <span style="color:#9ca3af;font-weight:400;">(optional)</span>
            </label>
            <textarea name="notes" rows="4"
                      style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #e5e7eb;
                             font-size:0.875rem;color:#111827;resize:vertical;"><?= htmlspecialchars($series['notes'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
            <button type="submit" name="action" value="update"
                    style="padding:8px 18px;border-radius:8px;border:none;background:#2563eb;
                           color:#fff;font-size:0.83rem;font-weight:600;cursor:pointer;">
                Save Changes
            </button>
        </div>
    </form>

    <hr style="border:none;border-top:1px solid #f3ede3;margin:16px 0;">

    <form method="POST" action="/church/modules/church_records/baptism/edit_series.php"
          onsubmit="return confirm('Are you sure you want to remove this series? It must have no baptism records.');">
        <input type="hidden" name="series_year" value="<?= htmlspecialchars($series['series_year']) ?>">
        <button type="submit" name="action" value="delete"
                style="padding:8px 14px;border-radius:8px;border:1px solid #fecaca;
                       background:#fef2f2;color:#b91c1c;font-size:0.8rem;font-weight:600;cursor:pointer;">
            <i class="fas fa-trash-alt" style="margin-right:6px;"></i>
            Remove Series
        </button>
        <p style="font-size:0.72rem;color:#9ca3af;margin-top:6px;">
            This only deletes the empty series definition. Any existing records will prevent deletion.
        </p>
    </form>
</div>

<?php include $root . '/includes/footer.php'; ?>

