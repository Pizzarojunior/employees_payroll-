<?php
$mysqli = new mysqli('localhost', 'root', '', 'employee_payroll_db');

$employees = [];
$employeeRows = [];
$stats = [
    'totalEmployees' => 0,
    'grossPayroll' => 0,
    'taxTotal' => 0,
    'deductionTotal' => 0,
    'netPayroll' => 0,
    'pendingEmployees' => 0,
    'leaveRequests' => 0
];

if ($mysqli->connect_error) {
    $dbError = $mysqli->connect_error;
} else {
    $dbError = null;

    $employeesSql = "SELECT e.id, e.full_name, e.department, e.email, e.basic_salary, e.overtime_hours, e.hire_date,
                COALESCE((SELECT p.gross_salary FROM payrolls p WHERE p.employee_id = e.id ORDER BY p.id DESC LIMIT 1), e.basic_salary) AS gross_salary,
                COALESCE((SELECT p.tax FROM payrolls p WHERE p.employee_id = e.id ORDER BY p.id DESC LIMIT 1), 0) AS tax,
                COALESCE((SELECT p.net_salary FROM payrolls p WHERE p.employee_id = e.id ORDER BY p.id DESC LIMIT 1), e.basic_salary) AS net_salary
                FROM employees e
                ORDER BY e.full_name ASC";

    $employeesResult = $mysqli->query($employeesSql);

    if ($employeesResult) {
        while ($row = $employeesResult->fetch_assoc()) {
            $gross = (float) $row['gross_salary'];
            $tax = (float) $row['tax'];
            $net = (float) $row['net_salary'];
            $deduction = max($gross - $net - $tax, 0);

            $employees[] = [
                'id' => (int) $row['id'],
                'name' => $row['full_name'],
                'department' => $row['department'],
                'email' => $row['email'],
                'basic_salary' => (float) $row['basic_salary'],
                'overtime_hours' => (float) $row['overtime_hours'],
                'hire_date' => $row['hire_date'],
                'gross' => $gross,
                'tax' => $tax,
                'deductions' => $deduction,
                'net' => $net,
                'status' => 'Paid'
            ];
        }
    }

    $stats['totalEmployees'] = count($employees);
    $stats['grossPayroll'] = array_sum(array_column($employees, 'gross'));
    $stats['taxTotal'] = array_sum(array_column($employees, 'tax'));
    $stats['deductionTotal'] = array_sum(array_column($employees, 'deductions'));
    $stats['netPayroll'] = array_sum(array_column($employees, 'net'));
    $stats['pendingEmployees'] = 0;
    $stats['leaveRequests'] = 0;
}

function money($value) {
    return '$' . number_format((float) $value, 2);
}

function initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    return $initials;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_submit'])) {
    $fullName = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $email = trim($_POST['email']);
    $basicSalary = (float) $_POST['basic_salary'];
    $overtimeHours = (float) $_POST['overtime_hours'];
    $hireDate = trim($_POST['hire_date']);

    if ($fullName !== '' && $department !== '' && $email !== '' && $basicSalary > 0 && $hireDate !== '') {
        $stmt = $mysqli->prepare("INSERT INTO employees (full_name, department, email, basic_salary, overtime_hours, hire_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssdss', $fullName, $department, $email, $basicSalary, $overtimeHours, $hireDate);
        $stmt->execute();
        $stmt->close();

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Payroll Management System</title>
    <style>
        :root {
            --bg: #eef5f4;
            --panel: #ffffff;
            --panel-soft: #f8fbfb;
            --text: #183a3a;
            --text-soft: #5a7171;
            --text-muted: #879c9c;
            --primary: #0f766e;
            --primary-dark: #0b5752;
            --primary-soft: #d2f2ee;
            --secondary: #f6b344;
            --danger: #df6a56;
            --danger-soft: #fee5df;
            --success: #23a36d;
            --success-soft: #d7f7e9;
            --line: #ddecec;
            --shadow: rgba(33, 82, 82, 0.10);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .brand-icon {
            background: var(--primary);
            color: white;
            border-radius: 12px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 10px 24px var(--shadow);
        }

        .nav {
            display: flex;
            gap: 34px;
            align-items: center;
            font-size: 14px;
            color: var(--text-soft);
        }

        .nav a {
            text-decoration: none;
            color: inherit;
        }

        .nav a.active {
            color: var(--primary);
            font-weight: 700;
        }

        .nav-button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .hero {
            background: linear-gradient(135deg, #114b48 0%, #0c665f 100%);
            color: white;
            border-radius: 28px;
            padding: 56px;
            min-height: 340px;
            position: relative;
            overflow: hidden;
        }

        .hero:after {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.38);
            right: -120px;
            top: -120px;
        }

        .hero h1 {
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1.02;
            letter-spacing: -0.04em;
            margin-bottom: 24px;
            max-width: 700px;
        }

        .hero p {
            max-width: 680px;
            color: #cceded;
            font-size: 18px;
            margin-bottom: 34px;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .btn-primary {
            background: white;
            color: var(--primary-dark);
            border: none;
            padding: 13px 24px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255,255,255,.44);
            color: white;
            padding: 13px 24px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            align-items: center;
            margin-top: 50px;
        }

        .hero-stat strong {
            display: block;
            font-size: 30px;
            line-height: 1;
        }

        .hero-stat small {
            color: #b2e6dd;
            font-size: 11px;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            margin-bottom: 16px;
        }

        .section-title h2 {
            font-size: 26px;
            letter-spacing: -0.03em;
        }

        .section-title .filter {
            display: flex;
            gap: 12px;
            align-items: center;
            color: var(--text-soft);
            font-size: 13px;
        }

        .filter span {
            background: white;
            border-radius: 10px;
            padding: 9px 14px;
            border: 1px solid var(--line);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(210px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }

        .card {
            background: var(--panel);
            border-radius: 18px;
            padding: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 3px 10px rgba(40, 90, 90, 0.05);
        }

        .stat-card {
            padding: 24px;
        }

        .stat-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-title {
            color: var(--text-muted);
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            background: var(--primary-soft);
            font-size: 20px;
            font-weight: bold;
        }

        .stat-number {
            margin-top: 22px;
            font-size: 34px;
            letter-spacing: -0.03em;
            font-weight: 800;
            color: var(--text);
        }

        .stat-card .subtext {
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-soft);
        }

        .trend-up {
            color: var(--success);
            font-size: 12px;
            font-weight: 700;
        }

        .trend-down {
            color: var(--danger);
            font-size: 12px;
            font-weight: 700;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .panel {
            background: var(--panel);
            border-radius: 20px;
            border: 1px solid var(--line);
            overflow: hidden;
        }

        .panel-header {
            padding: 24px 24px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .panel-actions {
            font-size: 12px;
            color: var(--primary);
            font-weight: 700;
        }

        .panel-body {
            padding: 0 24px 24px;
        }

        .chart-area {
            background: repeating-linear-gradient(180deg, transparent, transparent 30px, #eff8f7 31px), linear-gradient(180deg, #eefaf8 0%, #ffffff 100%);
            min-height: 330px;
            border-radius: 14px;
            border: 1px solid var(--line);
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 24px;
            gap: 12px;
        }

        .chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 260px;
            gap: 12px;
        }

        .chart-bar {
            width: 34px;
            border-radius: 12px 12px 4px 4px;
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
        }

        .chart-bar-wrap span {
            font-size: 11px;
            color: var(--text-soft);
        }

        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            height: 230px;
            margin-top: 4px;
        }

        .mini-bars .bar {
            width: 22px;
            border-radius: 9px 9px 3px 3px;
            background: var(--primary-soft);
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .salary-table th {
            text-align: left;
            font-size: 11px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 14px 0;
            border-bottom: 1px solid var(--line);
        }

        .salary-table td {
            padding: 14px 0;
            font-size: 13px;
            border-bottom: 1px solid var(--line);
        }

        .employee-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 900;
        }

        .status {
            padding: 7px 12px;
            font-size: 11px;
            border-radius: 999px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status.paid {
            background: var(--success-soft);
            color: var(--success);
        }

        .status.review {
            background: var(--secondary);
            color: #845609;
        }

        .status.pending {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .payroll-report {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(2, minmax(210px, 1fr));
            gap: 16px;
        }

        .progress-panel {
            background: var(--panel-soft);
            border-radius: 14px;
            padding: 18px;
            border: 1px solid var(--line);
        }

        .progress-title {
            font-size: 12px;
            color: var(--text-muted);
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .progress-value {
            margin-top: 12px;
            font-size: 30px;
            font-weight: 800;
        }

        .bar-track {
            margin-top: 12px;
            height: 12px;
            border-radius: 999px;
            background: #dceceb;
            overflow: hidden;
        }

        .bar-fill {
            display: block;
            height: 100%;
            background: var(--primary);
            border-radius: inherit;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .alert-card {
            background: linear-gradient(180deg, #eefbfa, white);
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
            color: var(--text-soft);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .checkmark {
            color: var(--success);
            font-weight: 900;
        }

        .footer {
            margin-top: 34px;
            padding: 20px 0 6px;
            color: var(--text-muted);
            font-size: 11px;
            letter-spacing: .14em;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
        }

        .notice {
            margin-top: 12px;
            padding: 12px;
            border-radius: 12px;
            background: var(--success-soft);
            color: var(--success);
            font-size: 13px;
            font-weight: 700;
            display: none;
        }

        .notice.show {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(13,45,46,0.60);
            align-items: center;
            justify-content: center;
            z-index: 10;
            padding: 20px;
        }

        .modal.open {
            display: flex;
        }

        .modal-content {
            width: min(560px, 100%);
            background: white;
            border-radius: 20px;
            padding: 28px;
        }

        .modal-content h3 {
            margin-bottom: 10px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .close-button {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-soft);
        }

        @media (max-width: 900px) {
            .stats-grid,
            .content-grid,
            .payroll-report {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 36px 24px;
            }

            .hero h1 {
                font-size: 40px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
                gap: 20px;
            }

            .nav {
                flex-wrap: wrap;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div class="brand">
                <div class="brand-icon">₱</div>
                <div>PayrollPro</div>
            </div>
            <nav class="nav">
                <a class="active" href="#">Overview</a>
                <a href="#employees">Employees</a>
                <a href="#payroll">Payroll</a>
                <a href="#leaves">Leaves</a>
                <a href="#payslips">Payslips</a>
                <button class="nav-button" id="openAddEmployee">+ Add Employee</button>
            </nav>
        </header>

        <?php if (isset($dbError)): ?>
            <div class="notice" style="display:block;">
                Database connection error: <?php echo htmlspecialchars($dbError); ?>
            </div>
        <?php endif; ?>

        <section class="hero">
            <h1>Employee Payroll Management System</h1>
            <p>Calculates employee salaries and deductions automatically, helping your HR and finance teams manage payroll with clarity and control.</p>
            <div class="hero-actions">
                <button class="btn-primary" id="generatePayroll">Generate Payroll</button>
                <button class="btn-secondary" id="viewPayslips">View Payslips</button>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?php echo $stats['totalEmployees']; ?></strong>
                    <small>Active Employees</small>
                </div>
                <div class="hero-stat">
                    <strong><?php echo money($stats['grossPayroll']); ?></strong>
                    <small>Monthly Payroll</small>
                </div>
                <div class="hero-stat">
                    <strong><?php echo count(array_unique(array_column($employees, 'department'))); ?></strong>
                    <small>Departments</small>
                </div>
            </div>
        </section>

        <div class="section-title">
            <h2>Payroll Dashboard</h2>
            <div class="filter">
                <span>October 2026</span>
                <span>All Departments</span>
            </div>
        </div>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-head">
                    <div class="stat-title">Total Employees</div>
                    <div class="icon-badge">👥</div>
                </div>
                <div class="stat-number"><?php echo $stats['totalEmployees']; ?></div>
                <div class="subtext trend-up">+<?php echo max(0, $stats['totalEmployees'] - 1); ?> new this month</div>
            </article>

            <article class="card stat-card">
                <div class="stat-head">
                    <div class="stat-title">Gross Payroll</div>
                    <div class="icon-badge">₹</div>
                </div>
                <div class="stat-number"><?php echo money($stats['grossPayroll']); ?></div>
                <div class="subtext trend-up">+5.2% vs last month</div>
            </article>

            <article class="card stat-card">
                <div class="stat-head">
                    <div class="stat-title">Tax Deductions</div>
                    <div class="icon-badge">%</div>
                </div>
                <div class="stat-number"><?php echo money($stats['taxTotal']); ?></div>
                <div class="subtext trend-down"><?php echo round(($stats['taxTotal'] / max($stats['grossPayroll'], 1)) * 100, 1); ?>% tax liability</div>
            </article>

            <article class="card stat-card">
                <div class="stat-head">
                    <div class="stat-title">Leave Requests</div>
                    <div class="icon-badge">☀</div>
                </div>
                <div class="stat-number"><?php echo $stats['leaveRequests']; ?></div>
                <div class="subtext"><?php echo $stats['pendingEmployees']; ?> pending approval</div>
            </article>
        </section>

        <section class="quick-grid">
            <div class="quick-action" data-section="employees">
                <div class="icon">👤</div>
                <strong>Employee Records</strong>
                <small>Manage employee profile, role, department and salary.</small>
            </div>
            <div class="quick-action" data-section="payroll">
                <div class="icon">💼</div>
                <strong>Run Payroll</strong>
                <small>Calculate gross salary, tax and deductions.</small>
            </div>
            <div class="quick-action" data-section="leaves">
                <div class="icon">🗓️</div>
                <strong>Leave Management</strong>
                <small>Review leave balance and approval status.</small>
            </div>
            <div class="quick-action" data-section="payslips">
                <div class="icon">📄</div>
                <strong>Generate Payslip</strong>
                <small>Create a payroll summary for each employee.</small>
            </div>
        </section>

        <section class="forms-row">
            <section class="form-panel" id="payroll">
                <h3>Payroll Calculation</h3>
                <form id="payrollForm">
                    <label for="salaryInput">Gross Salary</label>
                    <input type="number" id="salaryInput" value="4500" min="0">

                    <label for="deductionInput">Deductions</label>
                    <input type="number" id="deductionInput" value="450" min="0">

                    <label for="taxInput">Tax</label>
                    <input type="number" id="taxInput" value="425" min="0">

                    <button type="submit">Calculate Payslip</button>
                </form>
                <div class="notice" id="payrollNotice">Net Pay: $3,625.00</div>
            </section>

            <section class="form-panel" id="leaves">
                <h3>Leave Request</h3>
                <form id="leaveForm">
                    <label for="employeeName">Employee</label>
                    <select id="employeeName">
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo htmlspecialchars($employee['name']); ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="leaveDays">Leave Days</label>
                    <input type="number" id="leaveDays" value="2" min="1">

                    <button type="submit">Request Leave</button>
                </form>
                <div class="notice" id="leaveNotice">Leave request submitted.</div>
            </section>
        </section>

        <section class="content-grid">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Payroll Trend</div>
                    <div class="panel-actions">This Year</div>
                </div>
                <div class="panel-body">
                    <div class="chart-area">
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 65%;"></div>
                            <span>Jan</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 84%;"></div>
                            <span>Feb</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 58%;"></div>
                            <span>Mar</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 96%;"></div>
                            <span>Apr</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 70%;"></div>
                            <span>May</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 88%;"></div>
                            <span>Jun</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 76%;"></div>
                            <span>Jul</span>
                        </div>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height: 91%;"></div>
                            <span>Aug</span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="right-panel">
                <div class="panel alert-card">
                    <div class="panel-header">
                        <div class="panel-title">Payroll Status</div>
                        <div class="panel-actions">Live</div>
                    </div>
                    <div class="panel-body">
                        <div class="progress-panel">
                            <div class="progress-title">Monthly Completion</div>
                            <div class="progress-value">96%</div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:96%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Key Features</div>
                    </div>
                    <div class="panel-body">
                        <ul class="feature-list">
                            <li><span class="checkmark">✓</span>Employee records</li>
                            <li><span class="checkmark">✓</span>Salary computation</li>
                            <li><span class="checkmark">✓</span>Tax deduction</li>
                            <li><span class="checkmark">✓</span>Leave management</li>
                            <li><span class="checkmark">✓</span>Payslip generation</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </section>

        <section class="panel" style="margin-top:20px;" id="employees">
            <div class="panel-header">
                <div class="panel-title">Payroll Employees</div>
                <div class="panel-actions"><a href="#" id="downloadReport">Download Report</a></div>
            </div>
            <div class="panel-body">
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Gross Salary</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employees)): ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <div class="employee-name">
                                            <div class="avatar"><?php echo initials($employee['name']); ?></div>
                                            <span><?php echo htmlspecialchars($employee['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                    <td><?php echo money($employee['gross']); ?></td>
                                    <td><?php echo money($employee['deductions']); ?></td>
                                    <td><?php echo money($employee['net']); ?></td>
                                    <td><span class="status <?php echo strtolower(htmlspecialchars($employee['status'])); ?>"><?php echo htmlspecialchars($employee['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No employees found in the database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="payroll-report">
            <article class="panel">
                <div class="panel-header">
                    <div class="panel-title">Tax Deductions</div>
                    <div class="panel-actions">Q4</div>
                </div>
                <div class="panel-body">
                    <div class="progress-panel">
                        <div class="progress-title">Total Deduction Amount</div>
                        <div class="progress-value"><?php echo money($stats['deductionTotal']); ?></div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:62%; background: var(--secondary);"></div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel" id="leaves">
                <div class="panel-header">
                    <div class="panel-title">Leave Management</div>
                    <div class="panel-actions">Calendar</div>
                </div>
                <div class="panel-body">
                    <div class="mini-bars">
                        <div class="bar" style="height:30px;"></div>
                        <div class="bar" style="height:90px;"></div>
                        <div class="bar" style="height:55px;"></div>
                        <div class="bar" style="height:130px;"></div>
                        <div class="bar" style="height:80px;"></div>
                        <div class="bar" style="height:95px;"></div>
                        <div class="bar" style="height:50px;"></div>
                    </div>
                </div>
            </article>
        </section>

        <section class="panel" id="payslips" style="margin-top:20px;">
            <div class="panel-header">
                <div class="panel-title">Payslip Center</div>
                <div class="panel-actions"><button class="btn-secondary" id="createPayslip">Create Payslip</button></div>
            </div>
            <div class="panel-body">
                <div class="payroll-report">
                    <div class="progress-panel">
                        <div class="progress-title">Current Month Net Pay</div>
                        <div class="progress-value"><?php echo money($stats['netPayroll']); ?></div>
                    </div>
                    <div class="progress-panel">
                        <div class="progress-title">Employee Payslip Access</div>
                        <div class="progress-value">Enabled</div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <span>PayrollPro HR Solutions</span>
            <span>Last sync: 08 Aug 2026, 08:42 AM</span>
        </footer>
    </div>

    <div class="modal" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Employee</h3>
                <span class="close-button" id="closeModal">×</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="employee_submit" value="1">

                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required>

                <label for="department">Department</label>
                <input type="text" id="department" name="department" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>

                <label for="basic_salary">Basic Salary</label>
                <input type="number" id="basic_salary" name="basic_salary" min="1" required>

                <label for="overtime_hours">Overtime Hours</label>
                <input type="number" id="overtime_hours" name="overtime_hours" min="0" value="0" required>

                <label for="hire_date">Hire Date</label>
                <input type="date" id="hire_date" name="hire_date" required>

                <button type="submit">Save Employee</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addEmployeeModal');
        const openModal = document.getElementById('openAddEmployee');
        const closeModal = document.getElementById('closeModal');

        if (openModal && modal) {
            openModal.addEventListener('click', function () {
                modal.classList.add('open');
            });
        }

        if (closeModal && modal) {
            closeModal.addEventListener('click', function () {
                modal.classList.remove('open');
            });
        }

        window.addEventListener('click', function (event) {
            if (modal && event.target === modal) {
                modal.classList.remove('open');
            }
        });

        const payrollForm = document.getElementById('payrollForm');
        const payrollNotice = document.getElementById('payrollNotice');

        if (payrollForm && payrollNotice) {
            payrollForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const salary = Number(document.getElementById('salaryInput').value || 0);
                const deductions = Number(document.getElementById('deductionInput').value || 0);
                const tax = Number(document.getElementById('taxInput').value || 0);
                const net = salary - deductions - tax;

                payrollNotice.textContent = 'Net Pay: $' + net.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                payrollNotice.classList.add('show');
            });
        }

        const leaveForm = document.getElementById('leaveForm');
        const leaveNotice = document.getElementById('leaveNotice');

        if (leaveForm && leaveNotice) {
            leaveForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const employee = document.getElementById('employeeName').value;
                const days = Number(document.getElementById('leaveDays').value || 0);

                leaveNotice.textContent = 'Leave request submitted for ' + employee + ' for ' + days + ' day(s).';
                leaveNotice.classList.add('show');
            });
        }

        const generatePayrollButton = document.getElementById('generatePayroll');
        if (generatePayrollButton) {
            generatePayrollButton.addEventListener('click', function () {
                const payrollSection = document.getElementById('payroll');
                if (payrollSection) {
                    payrollSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                if (payrollNotice) {
                    payrollNotice.textContent = 'Payroll calculated successfully.';
                    payrollNotice.classList.add('show');
                }
            });
        }

        const viewPayslipsButton = document.getElementById('viewPayslips');
        if (viewPayslipsButton) {
            viewPayslipsButton.addEventListener('click', function () {
                const payslipSection = document.getElementById('payslips');
                if (payslipSection) {
                    payslipSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        const createPayslipButton = document.getElementById('createPayslip');
        if (createPayslipButton) {
            createPayslipButton.addEventListener('click', function () {
                if (payrollNotice) {
                    payrollNotice.textContent = 'Payslip generated for current payroll cycle.';
                    payrollNotice.classList.add('show');
                }

                const payslipsSection = document.getElementById('payslips');
                if (payslipsSection) {
                    payslipsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        const reportDownload = document.getElementById('downloadReport');
        if (reportDownload) {
            reportDownload.addEventListener('click', function (event) {
                event.preventDefault();

                const csvRows = [];
                csvRows.push(['Employee', 'Department', 'Gross Salary', 'Deductions', 'Net Salary']);

                const employeeRows = document.querySelectorAll('#employees tbody tr');
                employeeRows.forEach(function (row) {
                    const cells = Array.from(row.children);
                    if (cells.length >= 6) {
                        csvRows.push([
                            cells[0].innerText.trim(),
                            cells[1].innerText.trim(),
                            cells[2].innerText.trim(),
                            cells[3].innerText.trim(),
                            cells[4].innerText.trim()
                        ]);
                    }
                });

                const csv = csvRows.map(function (row) {
                    return row.map(function (value) {
                        return '"' + String(value).replace(/"/g, '""') + '"';
                    }).join(',');
                }).join('\n');

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'payroll-report.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        }

        const quickActions = document.querySelectorAll('[data-section]');
        quickActions.forEach(function (button) {
            button.addEventListener('click', function () {
                const sectionName = button.getAttribute('data-section');
                const target = document.getElementById(sectionName);

                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
