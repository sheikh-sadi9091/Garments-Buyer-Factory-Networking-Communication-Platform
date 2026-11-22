<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
if ($user === null) {
    http_response_code(403);
    exit('Administrator access is required.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? 'verify_company');
    $pdo = db();
    if ($action === 'account_status') {
        $accountId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if ($accountId === false || $accountId === null || $accountId < 1
            || !in_array($status, ['active', 'suspended'], true) || (int) $accountId === (int) $user['id']) {
            http_response_code(400);
            exit('Select a valid non-admin account and status.');
        }
        $update = $pdo->prepare(
            "UPDATE users INNER JOIN roles ON roles.id = users.role_id
             SET users.status = :status
             WHERE users.id = :user_id AND roles.slug <> 'admin'"
        );
        $update->execute(['status' => $status, 'user_id' => $accountId]);
        if ($update->rowCount() !== 1) {
            http_response_code(404);
            exit('Account not found or administrator accounts cannot be changed here.');
        }
        set_flash('success', 'Account status updated.');
    } elseif ($action === 'certificate_status') {
        $certificateId = filter_var($_POST['certificate_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        if ($certificateId === false || $certificateId === null || $certificateId < 1
            || !in_array($decision, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit('Select a valid certificate decision.');
        }
        $update = $pdo->prepare(
            "UPDATE company_certificates SET status = :status
             WHERE id = :certificate_id AND status = 'pending'"
        );
        $update->execute(['status' => $decision, 'certificate_id' => $certificateId]);
        if ($update->rowCount() !== 1) {
            http_response_code(409);
            exit('Certificate not found or it has already been reviewed.');
        }
        set_flash('success', 'Certificate ' . $decision . '.');
    } elseif ($action === 'complaint_status') {
        $complaintId = filter_var($_POST['complaint_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        $resolution = trim((string) ($_POST['resolution'] ?? ''));
        if ($complaintId === false || $complaintId === null || $complaintId < 1
            || !in_array($status, ['investigating', 'resolved', 'dismissed'], true)
            || strlen($resolution) > 5000) {
            http_response_code(400);
            exit('Enter a valid complaint update.');
        }
        $update = $pdo->prepare(
            "UPDATE complaints SET status = :status, resolution = :resolution
             WHERE id = :complaint_id AND status IN ('open', 'investigating')"
        );
        $update->execute([
            'status' => $status,
            'resolution' => $resolution !== '' ? $resolution : null,
            'complaint_id' => $complaintId,
        ]);
        if ($update->rowCount() !== 1) {
            http_response_code(409);
            exit('Complaint not found or already closed.');
        }
        $notify = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, body, target_url)
             SELECT reporter_id, :type, :title, :body, :target_url FROM complaints WHERE id = :complaint_id'
        );
        $notify->execute([
            'type' => 'complaint_update',
            'title' => 'Your complaint was updated',
            'body' => 'The platform reviewed the complaint and changed its status to ' . $status . '.',
            'target_url' => '/dashboard.php',
            'complaint_id' => $complaintId,
        ]);
        set_flash('success', 'Complaint status updated.');
    } elseif ($action === 'verify_company') {
        $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($companyId === false || $companyId === null || $companyId < 1
            || !in_array($decision, ['approved', 'rejected'], true) || strlen($remarks) > 2000) {
            http_response_code(400);
            exit('Enter a valid company verification decision and remarks.');
        }

        $pdo->beginTransaction();
        try {
            $companyStatement = $pdo->prepare(
                "UPDATE companies SET verification_status = :status, verification_remarks = :remarks
                 WHERE id = :company_id AND verification_status = 'pending'"
            );
            $companyStatement->execute([
                'status' => $decision === 'approved' ? 'verified' : 'rejected',
                'remarks' => $remarks !== '' ? $remarks : null,
                'company_id' => $companyId,
            ]);
            if ($companyStatement->rowCount() !== 1) {
                $pdo->rollBack();
                set_flash('error', 'That company is no longer waiting for verification.');
                redirect_to(app_url('admin/index.php'));
            }

            $pdo->prepare(
                "UPDATE verification_requests
                 SET status = :status, remarks = :remarks, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id AND status = 'pending'"
            )->execute([
                'status' => $decision,
                'remarks' => $remarks !== '' ? $remarks : null,
                'reviewed_by' => $user['id'],
                'company_id' => $companyId,
            ]);
            $pdo->prepare(
                "UPDATE company_certificates SET status = :status
                 WHERE company_id = :company_id AND status = 'pending'"
            )->execute(['status' => $decision, 'company_id' => $companyId]);

            $ownerStatement = $pdo->prepare('SELECT owner_user_id, name FROM companies WHERE id = :company_id');
            $ownerStatement->execute(['company_id' => $companyId]);
            $company = $ownerStatement->fetch();
            if ($company === false) {
                throw new RuntimeException('The company disappeared during verification.');
            }

            $notificationStatement = $pdo->prepare(
                'INSERT INTO notifications (user_id, type, title, body, target_url)
                 VALUES (:user_id, :type, :title, :body, :target_url)'
            );
            $notificationStatement->execute([
                'user_id' => $company['owner_user_id'],
                'type' => 'verification_update',
                'title' => 'Company verification ' . ($decision === 'approved' ? 'approved' : 'rejected'),
                'body' => 'The verification request for ' . $company['name'] . ' was reviewed.'
                    . ($remarks !== '' ? ' Remarks: ' . $remarks : ''),
                'target_url' => '/companies/profile.php',
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        set_flash('success', 'Company verification was ' . $decision . '.');
    } else {
        http_response_code(400);
        exit('Unknown administrator action.');
    }

    redirect_to(app_url('admin/index.php'));
}

$pdo = db();
$counts = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'companies' => (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE verification_status = 'pending'")->fetchColumn(),
    'complaints' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open', 'investigating')")->fetchColumn(),
];

$pendingCompanies = $pdo->query(
    "SELECT companies.id, companies.name, companies.location, companies.verification_status,
            companies.created_at, users.name AS owner_name, users.email AS owner_email
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     WHERE companies.verification_status = 'pending'
     ORDER BY companies.created_at ASC
     LIMIT 50"
)->fetchAll();

$users = $pdo->query(
    'SELECT users.id, users.name, users.email, users.status, users.created_at, roles.name AS role_name, roles.slug AS role_slug
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     ORDER BY users.created_at DESC
     LIMIT 20'
)->fetchAll();
$pendingCertificates = $pdo->query(
    "SELECT company_certificates.id, company_certificates.certificate_name,
            company_certificates.certificate_number, companies.name AS company_name,
            companies.id AS company_id, company_certificates.expires_on
     FROM company_certificates
     INNER JOIN companies ON companies.id = company_certificates.company_id
     WHERE company_certificates.status = 'pending'
     ORDER BY company_certificates.created_at ASC LIMIT 50"
)->fetchAll();
$openComplaints = $pdo->query(
    "SELECT complaints.id, complaints.subject, complaints.description, complaints.status,
            complaints.created_at, reporter.name AS reporter_name, against.name AS against_name
     FROM complaints
     INNER JOIN users AS reporter ON reporter.id = complaints.reporter_id
     LEFT JOIN users AS against ON against.id = complaints.against_user_id
     WHERE complaints.status IN ('open', 'investigating')
     ORDER BY complaints.created_at ASC LIMIT 50"
)->fetchAll();
$monthlyOrders = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS order_count
     FROM orders
     WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month"
)->fetchAll();
$maxMonthlyOrders = 0;
foreach ($monthlyOrders as $monthlyOrder) {
    $maxMonthlyOrders = max($maxMonthlyOrders, (int) $monthlyOrder['order_count']);
}
$maxMonthlyOrders = max(1, $maxMonthlyOrders);

$pageTitle = 'Admin panel';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Platform administration</p>
        <h1>Admin panel</h1>
        <p>Welcome, <?= e($user['name']) ?>. Review platform activity and company verification requests.</p>
    </section>

    <section class="admin-section" aria-label="Platform summary">
        <div class="admin-stats">
            <article class="stat-card"><strong><?= $counts['users'] ?></strong><span>Registered users</span></article>
            <article class="stat-card"><strong><?= $counts['companies'] ?></strong><span>Companies</span></article>
            <article class="stat-card"><strong><?= $counts['products'] ?></strong><span>Products</span></article>
            <article class="stat-card"><strong><?= $counts['pending'] ?></strong><span>Pending verifications</span></article>
            <article class="stat-card"><strong><?= $counts['complaints'] ?></strong><span>Open complaints</span></article>
        </div>
    </section>
    <section class="admin-section card">
        <h2>Orders over the last six months</h2>
        <?php if ($monthlyOrders === []): ?><p>Order activity will appear here after the first purchase order.</p>
        <?php else: ?><div class="admin-chart" role="img" aria-label="Monthly order counts">
            <?php foreach ($monthlyOrders as $monthlyOrder): ?>
                <?php $height = max(3, (int) round(((int) $monthlyOrder['order_count'] / $maxMonthlyOrders) * 100)); ?>
                <div class="admin-chart-item" aria-label="<?= e(date('F Y', strtotime($monthlyOrder['month'] . '-01'))) ?>: <?= (int) $monthlyOrder['order_count'] ?> orders">
                    <strong><?= (int) $monthlyOrder['order_count'] ?></strong>
                    <span class="admin-chart-bar" style="--bar-height: <?= $height ?>%"></span>
                    <small><?= e(date('M Y', strtotime($monthlyOrder['month'] . '-01'))) ?></small>
                </div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </section>

    <section class="admin-section card">
        <h2>Factory verification</h2>
        <p>Approve or reject companies currently waiting for verification.</p>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Company</th><th>Owner</th><th>Location</th><th>Requested</th><th>Review</th></tr></thead>
                <tbody>
                <?php if ($pendingCompanies === []): ?>
                    <tr><td colspan="5">No companies are waiting for verification.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingCompanies as $company): ?>
                        <tr>
                            <td><?= e($company['name']) ?></td>
                            <td><?= e($company['owner_name']) ?><br><a href="mailto:<?= e($company['owner_email']) ?>"><?= e($company['owner_email']) ?></a></td>
                            <td><?= e($company['location']) ?></td>
                            <td><?= e($company['created_at']) ?></td>
                            <td>
                                <form method="post" action="<?= e(app_url('admin/index.php')) ?>" data-validate>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                                    <label>
                                        <span class="sr-only">Remarks for <?= e($company['name']) ?></span>
                                        <input name="remarks" maxlength="2000" placeholder="Optional remarks">
                                    </label>
                                    <button class="button" type="submit" name="decision" value="approved">Approve</button>
                                    <button class="button button-outline" type="submit" name="decision" value="rejected">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section card">
        <h2>Recent accounts</h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($users as $account): ?>
                    <tr>
                        <td><?= e($account['name']) ?></td>
                        <td><a href="mailto:<?= e($account['email']) ?>"><?= e($account['email']) ?></a></td>
                        <td><?= e($account['role_name']) ?></td>
                        <td><span class="status-pill status-<?= e($account['status']) ?>"><?= e(ucfirst($account['status'])) ?></span>
                            <?php if ($account['role_slug'] !== 'admin' && (int) $account['id'] !== (int) $user['id']): ?>
                                <form method="post" action="<?= e(app_url('admin/index.php')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="account_status"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                    <button class="link-button" type="submit" name="status" value="<?= $account['status'] === 'active' ? 'suspended' : 'active' ?>"><?= $account['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= e($account['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="admin-section card">
        <h2>Certificate review</h2>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>Company</th><th>Certificate</th><th>Number</th><th>Expires</th><th>Document</th><th>Decision</th></tr></thead><tbody>
            <?php foreach ($pendingCertificates as $certificate): ?><tr>
                <td><?= e($certificate['company_name']) ?></td><td><?= e($certificate['certificate_name']) ?></td><td><?= e($certificate['certificate_number'] ?? '—') ?></td><td><?= e($certificate['expires_on'] ?? '—') ?></td>
                <td><a href="<?= e(app_url('companies/certificate-download.php?id=' . (int) $certificate['id'])) ?>">Review document</a></td>
                <td><form method="post" action="<?= e(app_url('admin/index.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="certificate_status"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>">
                    <button class="button" type="submit" name="decision" value="approved">Approve</button>
                    <button class="button button-outline" type="submit" name="decision" value="rejected">Reject</button>
                </form></td>
            </tr><?php endforeach; ?>
            <?php if ($pendingCertificates === []): ?><tr><td colspan="6">No certificates are waiting for review.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="admin-section card">
        <h2>Complaints</h2>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>Subject</th><th>Reporter</th><th>About</th><th>Description</th><th>Review</th></tr></thead><tbody>
            <?php foreach ($openComplaints as $complaint): ?><tr>
                <td><?= e($complaint['subject']) ?></td><td><?= e($complaint['reporter_name']) ?></td><td><?= e($complaint['against_name'] ?? 'Platform') ?></td><td><?= e($complaint['description']) ?></td>
                <td><form method="post" action="<?= e(app_url('admin/index.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="complaint_status"><input type="hidden" name="complaint_id" value="<?= (int) $complaint['id'] ?>">
                    <label class="sr-only" for="complaint-resolution-<?= (int) $complaint['id'] ?>">Resolution</label>
                    <input id="complaint-resolution-<?= (int) $complaint['id'] ?>" name="resolution" maxlength="5000" placeholder="Resolution / notes">
                    <button class="button" type="submit" name="status" value="investigating">Investigate</button>
                    <button class="button button-outline" type="submit" name="status" value="resolved">Resolve</button>
                    <button class="link-button" type="submit" name="status" value="dismissed">Dismiss</button>
                </form></td>
            </tr><?php endforeach; ?>
            <?php if ($openComplaints === []): ?><tr><td colspan="5">No open complaints.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="admin-section card"><h2>Events and news</h2><p><a class="button button-outline" href="<?= e(app_url('admin/content.php')) ?>">Manage event and news posts</a></p></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
