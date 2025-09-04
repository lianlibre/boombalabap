<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
include "../includes/admin_sidebar.php";

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$user_role = strtolower($user['role']);

// 🔹 Role-to-recipient mapping
$role_mapping = [
    'admin' => ['Admin', 'ADMIN', 'OFFICE OF THE PRES', 'Office of the College President'],
    'student' => ['Students', 'STUDENTS', 'STUDENT'],
    'instructor' => ['Instructors', 'INSTRUCTORS', 'Instructor', 'Faculty'],
    'non_teaching' => ['Non Teaching Personnel', 'NON TEACHING PERSONNEL\'S', 'UTILITY', 'GUARD', 'Non-Teaching Staff'],
    'library' => ['Library', 'LIBRARY'],
    'soa' => ['Office of SOA', 'OFFICE OF SOA', 'SOA'],
    'guidance' => ['Guidance', 'GUIDANCE', 'Guidance Office'],
    'school_counselor' => ['School Counselor', 'SCHOOL COUNSELOR'],
    'dept_head_bsit' => ['BSIT Department Head', 'BSIT Department'],
    'dept_head_bsba' => ['BSBA Department Head', 'BSBA Department'],
    'dept_head_bshm' => ['BSHM Department Head', 'HM Department'],
    'dept_head_beed' => ['BEED Department Head', 'BEED Department']
];

$allowed_recipients = $role_mapping[$user_role] ?? [];

// 🔹 Build recipient condition (secure)
$conditions = [];
foreach ($allowed_recipients as $r) {
    $r = $conn->real_escape_string($r);
    $conditions[] = "(m.`to` = '$r')";
    $conditions[] = "(m.`to` LIKE '$r,%')";
    $conditions[] = "(m.`to` LIKE '%,$r')";
    $conditions[] = "(m.`to` LIKE '%,$r,%')";
}
$conditions[] = "(m.`to` IN ('All Personnel', 'All', 'All Departments'))";
$recipient_clause = implode(' OR ', $conditions);

// 🔹 Role groups
$full_access = ['admin'];

// 🔹 Base WHERE: user is sender OR recipient
$base_where = "(m.user_id = $user_id OR ($recipient_clause))";

// 🔹 Get counts (accurate for role)
if (in_array($user_role, $full_access)) {
    // Admin: all memos
    $total_memos = $conn->query("SELECT COUNT(*) FROM memos")->fetch_row()[0];
    $active_memos = $conn->query("SELECT COUNT(*) FROM memos WHERE archived = 0")->fetch_row()[0];
    $archived_memos = $conn->query("SELECT COUNT(*) FROM memos WHERE archived = 1")->fetch_row()[0];

    // Admin stats
    $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $admin = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
    $instructors = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetch_row()[0];
    $students = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetch_row()[0];
    $non_teaching = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('non_teaching', 'utility', 'guard')")->fetch_row()[0];
    $dept_heads = $conn->query("SELECT COUNT(*) FROM users WHERE role LIKE 'dept_head_%'")->fetch_row()[0];

    // All recent memos
    $recent_memos = $conn->query("
        SELECT m.id, m.subject, m.created_at, u.fullname, u.department 
        FROM memos m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.created_at DESC 
        LIMIT 5
    ");
} else {
    // Regular users: only memos they sent OR received
    $total_memos = $conn->query("SELECT COUNT(*) FROM memos m WHERE $base_where")->fetch_row()[0];
    $active_memos = $conn->query("SELECT COUNT(*) FROM memos m WHERE $base_where AND m.archived = 0")->fetch_row()[0];
    $archived_memos = $conn->query("SELECT COUNT(*) FROM memos m WHERE $base_where AND m.archived = 1")->fetch_row()[0];

    // Recent memos: only relevant
    $recent_memos = $conn->query("
        SELECT m.id, m.subject, m.created_at, u.fullname, u.department 
        FROM memos m 
        JOIN users u ON m.user_id = u.id 
        WHERE $base_where
        ORDER BY m.created_at DESC 
        LIMIT 5
    ");
}

// 🔹 Last login
$last_checked = $user['last_checked'] ? date('F j, Y \a\t g:i A', strtotime($user['last_checked'])) : 'Never';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard</title>
    <link rel="stylesheet" href="../includes/user_style.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        h2 {
            color: #1976d2;
            margin-bottom: 20px;
            font-weight: 500;
        }

        /* Dashboard Metrics */
        .dashboard-metrics {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .metric-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-4px);
        }

        .metric-card h2 {
            margin: 10px 0;
            font-size: 2.2rem;
            color: #1976d2;
        }

        .metric-card span {
            font-size: 1.1rem;
            color: #555;
            font-weight: 500;
        }

        .metric-card i {
            font-size: 2rem;
            color: #1976d2;
            margin-bottom: 8px;
        }

        /* Section */
        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 30px;
        }

        .section h3 {
            margin-top: 0;
            color: #1976d2;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
            font-size: 1.3rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #444;
        }

        table td {
            padding: 12px;
            border-top: 1px solid #eee;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-new {
            background: #ff6b6b;
            color: white;
            font-weight: bold;
        }

        .last-login {
            background: #e3f2fd;
            padding: 16px;
            border-radius: 8px;
            font-size: 1rem;
            color: #1976d2;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .dashboard-metrics {
                flex-direction: column;
            }

            table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Dashboard</h2>

    <!-- Metrics -->
    <div class="dashboard-metrics">
        <?php if (in_array($user_role, $full_access)): ?>
            <div class="metric-card">
                <i class="fas fa-users"></i>
                <h2><?= $total_users ?></h2>
                <span>Total Users</span>
            </div>
        <?php endif; ?>

        <div class="metric-card">
            <i class="fas fa-envelope"></i>
            <h2><?= $total_memos ?></h2>
            <span>Total Memos</span>
        </div>

        <div class="metric-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= $active_memos ?></h2>
            <span>Active Memos</span>
        </div>

        <div class="metric-card">
            <i class="fas fa-archive"></i>
            <h2><?= $archived_memos ?></h2>
            <span>Archived Memos</span>
        </div>
    </div>

    <!-- User Breakdown (Admin Only) -->
    <?php if (in_array($user_role, $full_access)): ?>
    <div class="section">
        <h3><i class="fas fa-user-tie"></i> User Roles Summary</h3>
        <table>
            <tr>
                <th>Role</th>
                <th>Count</th>
            </tr>
            <tr>
                <td>admin</td>
                <td><?= $admin ?></td>
            </tr>
            <tr>
                <td>Instructors</td>
                <td><?= $instructors ?></td>
            </tr>
            <tr>
                <td>Students</td>
                <td><?= $students ?></td>
            </tr>
            <tr>
                <td>Non-Teaching Staff</td>
                <td><?= $non_teaching ?></td>
            </tr>
            <tr>
                <td>Department Heads</td>
                <td><?= $dept_heads ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recent Memos -->
    <div class="section">
        <h3><i class="fas fa-clock"></i> Recent Memorandums</h3>
        <table>
            <tr>
                <th>Subject</th>
                <th>From</th>
                <th>Department</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
            <?php $first = true; ?>
            <?php while ($memo = $recent_memos->fetch_assoc()): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($memo['subject']) ?>
                    <?php if ($first): ?>
                        <span class="badge badge-new" style="margin-left: 8px;">NEW</span>
                        <?php $first = false; ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($memo['fullname']) ?></td>
                <td><?= htmlspecialchars($memo['department']) ?></td>
                <td><?= date('M j, Y', strtotime($memo['created_at'])) ?></td>
                <td><span class="badge badge-active">Active</span></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Last Login -->
    <div class="last-login">
        <strong>Welcome back, <?= htmlspecialchars($user['fullname']) ?>!</strong><br>
        Your last activity was on <strong><?= $last_checked ?></strong>.
    </div>
</div>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>