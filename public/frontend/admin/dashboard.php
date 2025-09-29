<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

// Must be logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Not logged in.");
}

include "../includes/admin_sidebar.php";

// Get current user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}
$user_role = strtolower($user['role']);

// 🔹 Role-to-recipient mapping (based on your updated role list)
$role_mapping = [
    'admin' => [
        'Admin', 'ADMIN', 'OFFICE OF THE PRES', 'Office of the College President',
        'All Personnel', 'All', 'All Departments'
    ],

    // General Student
    'student' => [
        'Students', 'STUDENTS', 'STUDENT',
        'All Departments', 'All Personnel'
    ],

    // Specific Students by Course
    'student_bsit' => [
        'Student - BSIT', 'Students', 'All Departments', 'All Personnel'
    ],
    'student_bshm' => [
        'Student - BSHM', 'Students', 'All Departments', 'All Personnel'
    ],
    'student_bsba' => [
        'Student - BSBA', 'Students', 'All Departments', 'All Personnel'
    ],
    'student_bsed' => [
        'Student - BSED', 'Students', 'All Departments', 'All Personnel'
    ],
    'student_beed' => [
        'Student - BEED', 'Students', 'All Departments', 'All Personnel'
    ],

    // Support Offices
    'school_counselor' => [
        'School Counselor', 'SCHOOL COUNSELOR', 'school counsel', 'Guidance Office',
        'All Departments'
    ],
    'library' => [
        'Library', 'LIBRARY', 'All Departments'
    ],
    'soa' => [
        'Office of SOA', 'OFFICE OF SOA', 'SOA', 'Office of the Student Affairs',
        'All Departments'
    ],
    'guidance' => [
        'Guidance', 'GUIDANCE', 'Guidance Office',
        'All Departments'
    ],

    // Department Heads
    'dept_head_bsit' => [
        'BSIT Department Head', 'DEPT HEAD BSIT', 'BSIT Department',
        'All Departments'
    ],
    'dept_head_bsba' => [
        'BSBA Department Head', 'DEPT HEAD BSBA', 'BSBA Department',
        'All Departments'
    ],
    'dept_head_bshm' => [
        'BSHM Department Head', 'DEPT HEAD BSHM', 'HM Department',
        'All Departments'
    ],
    'dept_head_bsed' => [
        'BSED Department Head', 'DEPT HEAD BSED', 'BSED Department',
        'All Departments'
    ],
    'dept_head_beed' => [
        'BEED Department Head', 'DEPT HEAD BEED', 'BEED Department',
        'All Departments'
    ],

    // Faculty & Instructors
    'instructor' => [
        'Instructors', 'INSTRUCTORS', 'Instructor', 'Faculty',
        'All Departments'
    ],

    // Non-Teaching Staff
    'non_teaching' => [
        'Non Teaching Personnel', 'NON TEACHING PERSONNEL\'S', 'UTILITY', 'GUARD', 'Non-Teaching Staff',
        'All Departments'
    ]
];

// Get allowed recipients for this role
$allowed_recipients = $role_mapping[$user_role] ?? [];
if (empty($allowed_recipients)) {
    $allowed_recipients = ['__NEVER_MATCH__']; // Prevents access
}

// Escape each recipient safely
$escaped_recipients = array_map(fn($r) => $conn->real_escape_string(trim($r)), $allowed_recipients);

// Build flexible conditions for "to" field matching
$conditions = [];
foreach ($escaped_recipients as $r) {
    if (empty($r)) continue;

    $conditions[] = "m.`to` = '$r'";
    $conditions[] = "m.`to` LIKE '$r,%'";
    $conditions[] = "m.`to` LIKE '%,$r'";
    $conditions[] = "m.`to` LIKE '%,$r,%'";
    $conditions[] = "LOWER(m.`to`) LIKE '%" . strtolower($r) . "%'";
}
$recipient_clause = implode(' OR ', $conditions);

// Final WHERE clause: user is sender OR matches any recipient rule
$where_clause = "(m.user_id = $user_id OR ($recipient_clause))";

// Admin has full access
$is_admin = in_array($user_role, ['admin']);

// 🔹 COUNT MEMOS (Secure & Accurate)

// Active Memos: not archived
$active_sql = $is_admin 
    ? "SELECT COUNT(*) as cnt FROM memos WHERE archived = 0"
    : "SELECT COUNT(*) as cnt FROM memos m WHERE archived = 0 AND ($where_clause)";
$active_memos = $conn->query($active_sql)->fetch_assoc()['cnt'];

// Archived Memos
$archived_sql = $is_admin 
    ? "SELECT COUNT(*) as cnt FROM memos WHERE archived = 1"
    : "SELECT COUNT(*) as cnt FROM memos m WHERE archived = 1 AND ($where_clause)";
$archived_memos = $conn->query($archived_sql)->fetch_assoc()['cnt'];

// Total Memos
$total_memos = $active_memos + $archived_memos;

// 🔹 Admin-Only Stats
if ($is_admin) {
    $total_users = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
    $admin_count = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'")->fetch_assoc()['cnt'];
    $instructors = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'instructor'")->fetch_assoc()['cnt'];
    $students = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role LIKE 'student%'")->fetch_assoc()['cnt'];
    $non_teaching = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('non_teaching', 'utility', 'guard')")->fetch_assoc()['cnt'];
    $dept_heads = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role LIKE 'dept_head_%'")->fetch_assoc()['cnt'];
} else {
    $total_users = $admin_count = $instructors = $students = $non_teaching = $dept_heads = 0;
}

// 🔹 Recent Memos (visible to this user)
$recent_sql = $is_admin 
    ? "SELECT m.id, m.subject, m.created_at, u.fullname, u.department 
       FROM memos m 
       JOIN users u ON m.user_id = u.id 
       ORDER BY m.created_at DESC LIMIT 5"
    : "SELECT m.id, m.subject, m.created_at, u.fullname, u.department 
       FROM memos m 
       JOIN users u ON m.user_id = u.id 
       WHERE ($where_clause)
       ORDER BY m.created_at DESC LIMIT 5";

$recent_memos = $conn->query($recent_sql);

// 🔹 Last Login
$last_checked = $user['last_checked'] 
    ? date('F j, Y \a\t g:i A', strtotime($user['last_checked'])) 
    : 'Never';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - User Panel</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
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
            color: #007bff;
        }

        .metric-card span {
            font-size: 1.1rem;
            color: #555;
            font-weight: 500;
        }

        .metric-card i {
            font-size: 2rem;
            color: #007bff;
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
            color: #2c3e50;
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
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .dashboard-metrics {
                flex-direction: column;
            }

            .metric-card h2 {
                font-size: 1.8rem;
            }

            table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
   <!-- <h2>📊 Dashboard</h2> -->

    <!-- Metrics -->
    <div class="dashboard-metrics">
        <?php if ($is_admin): ?>
            <div class="metric-card">
                <i class="fas fa-users"></i>
                <h2><?= number_format($total_users) ?></h2>
                <span>Total Users</span>
            </div>
        <?php endif; ?>

        <div class="metric-card">
            <i class="fas fa-envelope"></i>
            <h2><?= number_format($total_memos) ?></h2>
            <span>Total Memos</span>
        </div>

        <div class="metric-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= number_format($active_memos) ?></h2>
            <span>Active Memos</span>
        </div>

        <div class="metric-card">
            <i class="fas fa-archive"></i>
            <h2><?= number_format($archived_memos) ?></h2>
            <span>Archived Memos</span>
        </div>
    </div>

    <!-- User Breakdown (Admin Only) -->
    <?php if ($is_admin): ?>
    <div class="section">
        <h3><i class="fas fa-user-tie"></i> User Roles Summary</h3>
        <table>
            <tr>
                <th>Role</th>
                <th>Count</th>
            </tr>
            <tr>
                <td>Admin</td>
                <td><?= number_format($admin_count) ?></td>
            </tr>
            <tr>
                <td>Instructors</td>
                <td><?= number_format($instructors) ?></td>
            </tr>
            <tr>
                <td>Students</td>
                <td><?= number_format($students) ?></td>
            </tr>
            <tr>
                <td>Non-Teaching Staff</td>
                <td><?= number_format($non_teaching) ?></td>
            </tr>
            <tr>
                <td>Department Heads</td>
                <td><?= number_format($dept_heads) ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recent Memos -->
    <div class="section">
        <h3><i class="fas fa-clock"></i> Recent Memorandums</h3>
        <?php if ($recent_memos && $recent_memos->num_rows > 0): ?>
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
                        <span class="badge badge-new">NEW</span>
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
        <?php else: ?>
        <p style="color: #666; text-align: center; padding: 20px;">No recent memos available.</p>
        <?php endif; ?>
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