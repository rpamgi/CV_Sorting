<?php
include("../config/auth.php");
check_auth(); // All roles allowed
include("../config/db.php");
$is_admin = in_array($_SESSION['role'], ['admin', 'sub-admin', 'super-admin']);

// Fetch user profile info if not in session
if (!isset($_SESSION['profile_pic'])) {
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($prof_pic);
    $stmt->fetch();
    $_SESSION['profile_pic'] = $prof_pic;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard | CV Sorting</title>
    <link rel="stylesheet" href="../css/style.css?v=4.4">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation Bar (Fixed) -->
    <div class="top-nav">
        <div class="top-nav-left">
            <div class="sidebar-toggle" onclick="toggleSidebar()" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); color: white;">
                <span class="material-icons">menu</span>
            </div>
            <a href="../index.php" class="nav-link" style="font-size: 16px; text-decoration: none; color: white; display: flex; align-items: center; background: rgba(255,255,255,0.1); width: 38px; height: 38px; justify-content: center; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);" title="Back to Home">
                <span class="material-icons" style="font-size: 24px;">arrow_back</span>
            </a>
        </div>

        <div class="top-nav-center">
            <span id="dynamicJobTitle" onclick="window.location.reload()" style="color: #60a5fa; cursor: pointer; font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($_GET['job_title'] ?? 'Loading...'); ?></span> 
            <span id="jdBadge" class="badge-jd" style="font-size: 0.8rem; padding: 4px 12px; background: rgba(96, 165, 250, 0.1); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.2); border-radius: 6px; <?php echo empty($_GET['jd_id']) ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($_GET['jd_id'] ?? ''); ?></span>
            <span class="live-indicator" title="Live Sync Active"></span>
        </div>

        <div class="top-nav-right">
            <div class="user-profile-nav" onclick="toggleUserDropdown(event)">
                <div class="user-info-text">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                    <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <div class="profile-img-container">
                    <?php if (!empty($_SESSION['profile_pic'])): ?>
                        <img id="headerProfilePic" src="<?php echo '../' . $_SESSION['profile_pic']; ?>" alt="Profile">
                    <?php else: ?>
                        <div id="headerProfileFallback" class="profile-icon-fallback">
                            <span class="material-icons">person</span>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="material-icons expand-icon">expand_more</span>
                
                <div id="userDropdown" class="dropdown-menu">
                    <a href="javascript:void(0)" onclick="toggleProfileModal()">
                        <span class="material-icons">account_circle</span> My Profile
                    </a>
                    <a href="javascript:void(0)" onclick="toggleChangePass()">
                        <span class="material-icons">lock</span> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../index.php?logout=1" class="logout-link">
                        <span class="material-icons">logout</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
 
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <span class="material-icons" onclick="toggleSidebar()" style="cursor: pointer;">close</span>
        </div>
        <div class="sidebar-menu">
            <a href="../index.php">
                <span class="material-icons">home</span> Home
            </a>
            <?php if ($is_admin): ?>
                <a href="javascript:void(0)" onclick="toggleUserManager();">
                    <span class="material-icons">manage_accounts</span> Manage Users
                </a>
                <a href="javascript:void(0)" onclick="toggleEmployeeManager();">
                    <span class="material-icons">badge</span> Manage Employees
                </a>
                <a href="javascript:void(0)" onclick="toggleStatusManager();">
                    <span class="material-icons">settings_suggest</span> Manage Statuses
                </a>
                <a href="javascript:void(0)" onclick="toggleRpaConfig();">
                    <span class="material-icons">settings_remote</span> RPA Config
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" onclick="toggleChangePass();">
                <span class="material-icons">lock</span> Change Password
            </a>
            <a href="../index.php?logout=1" class="logout-link" style="border-top: 1px solid var(--border); margin-top: 10px; padding-top: 15px;">
                <span class="material-icons">logout</span> Logout
            </a>
        </div>
    </div>
 
    <div class="container">
        <div class="card">

            <div class="search-bar">
                <input type="text" id="searchInput" class="real-time" placeholder="Search by name, email, skills, or organization...">
                <select id="shortlistedFilter" class="real-time">
                    <option value="">Status: All</option>
                    <option value="1">Shortlisted</option>
                    <option value="0">Not Shortlisted</option>
                </select>
                <select id="confirmationFilter" class="real-time">
                    <option value="">Conf: All</option>
                    <option value="1">Confirmed</option>
                    <option value="0">Not Confirmed</option>
                </select>
                <button id="searchBtn" class="btn-primary" style="margin-top: 0; display: flex; align-items: center; gap: 5px;">
                    <span class="material-icons" style="font-size: 18px;">search</span> Search
                </button>
                <?php if ($is_admin): ?>
                    <button id="exportCsv" class="btn-primary" style="background: var(--success); display: none; padding: 8px 12px; min-width: 44px; align-items: center; justify-content: center;" title="Export Excel / CSV">
                        <span class="material-icons">file_download</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-container" id="candidateTableContainer">
                <table id="candidateTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="id">ID <span class="sort-icon">↕</span></th>
                            <th>Name & Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>DOB</th>
                            <th>Organization</th>
                            <th>Education</th>
                            <th>Institute</th>
                            <th class="sortable" data-sort="total_experience">Exp. <span class="sort-icon">↕</span></th>
                            <th class="sortable" data-sort="expected_salary">Salary <span class="sort-icon">↕</span></th>
                            <th>Skills</th>
                            <th>Strength</th>
                            <th>Weakness</th>
                            <th class="sortable" data-sort="rating">Rating <span class="sort-icon">↕</span></th>
                            <th class="sortable" data-sort="match">Matching <span class="sort-icon">↕</span></th>
                            <th>Rating Reason</th>
                            <th>Shortlisted</th>
                            <th>Conf.</th>
                            <th>Added On</th>
                                <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="candidateBody">
                        <tr><td colspan="20" style="text-align:center; padding: 20px;">Loading candidates...</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 500px; position: relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                <h3 style="margin: 0; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons">person</span> My Profile
                </h3>
                <span class="material-icons close-modal" onclick="toggleProfileModal()" style="cursor: pointer; color: #94a3b8;">close</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 25px; position: relative;">
                    <?php if (!empty($_SESSION['profile_pic'])): ?>
                        <img id="profileModalPic" src="<?php echo '../' . $_SESSION['profile_pic']; ?>" alt="Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: contain; background-color: white; border: 4px solid var(--primary); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div id="profileModalFallback" style="width: 120px; height: 120px; border-radius: 50%; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; border: 4px solid var(--primary); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
                            <span class="material-icons" style="font-size: 64px; color: #94a3b8;">person</span>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px;">
                        <label for="profileUpload" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer; padding: 8px 15px; font-size: 0.85rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                            <span class="material-icons" style="font-size: 18px;">camera_alt</span> Change Picture
                        </label>
                        <input type="file" id="profileUpload" style="display: none;" accept="image/*" onchange="uploadProfilePic()">
                    </div>
                </div>
                <div id="profileDetails" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border);">
                    <!-- Profile details loaded via JS -->
                    <div style="text-align: center; grid-column: span 2;">Loading profile details...</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- User Manager Modal -->
    <div id="userManagerModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>System User Management</h3>
                <span class="material-icons close-modal" onclick="toggleUserManager()">close</span>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 id="userManagerSubTitle" style="margin: 0; color: #64748b;">System Users List</h4>
                    <button id="toggleUserFormBtn" onclick="toggleAddUserForm()" class="btn-primary" style="padding: 8px 15px; background: #10b981; display: flex; align-items: center; gap: 5px;">
                        <span class="material-icons" style="font-size: 18px;">add</span>
                        <span id="userBtnText">Create New User</span>
                    </button>
                </div>

                <!-- Create/Add User Form -->
                <div id="addUserForm" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="position: relative;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Employee Reference</label>
                            <input type="text" id="userEmpSearch" autocomplete="off" placeholder="Type Emp ID or Name..." 
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;" 
                                oninput="searchEmployees(this.value)">
                            <input type="hidden" id="userEmpRef">
                            <div id="empSuggestions" class="suggestions-container" style="display: none; position: absolute; z-index: 1000; width: 100%; background: white; border: 1px solid #ddd; border-radius: 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);"></div>
                            <small style="color: #64748b;">Type ID or Name to link employee automatically.</small>
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Role</label>
                            <select id="newRole" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                <option value="user">Standard User</option>
                                <option value="sub-admin">Sub Admin</option>
                                <option value="admin">Administrator</option>
                                <option value="super-admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Create Password</label>
                            <input type="password" id="newPassword" placeholder="Minimum 6 characters" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Confirm Password</label>
                            <input type="password" id="newConfirmPassword" placeholder="Retype password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="createUser()" class="btn-primary" style="padding: 10px 20px;">Create User</button>
                        <button onclick="toggleAddUserForm()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                    </div>
                </div>

                <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                            <tr>
                                <th style="text-align: center;">Name & Username</th>
                                <th style="text-align: center;">Role</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Joined</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="userListBody">
                            <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading users...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Management Modal -->
    <div id="employeeManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1100px;">
            <div class="modal-header">
                <h3>Manage Employee Master</h3>
                <span class="material-icons close-modal" onclick="toggleEmployeeManager()">close</span>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 id="employeeManagerSubTitle" style="margin: 0; color: #64748b;">Employee Master List</h4>
                    <button id="toggleEmployeeFormBtn" onclick="toggleAddEmployeeForm()" class="btn-primary" style="padding: 8px 15px; background: #10b981; display: flex; align-items: center; gap: 5px;">
                        <span class="material-icons" style="font-size: 18px;">add</span>
                        <span id="employeeBtnText">Add New Employee</span>
                    </button>
                </div>

                <!-- Create/Edit Employee Form -->
                <div id="addEmployeeForm" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Employee ID</label>
                            <input type="text" id="empId" placeholder="e.g. 097727" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Full Name</label>
                            <input type="text" id="empFullName" placeholder="Enter full name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Email</label>
                            <input type="email" id="empEmail" placeholder="email@mgi.org" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Mobile</label>
                            <input type="text" id="empMobile" placeholder="018..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Designation</label>
                            <input type="text" id="empDesignation" placeholder="Designation" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Department</label>
                            <input type="text" id="empDepartment" placeholder="Department" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">IP No</label>
                            <input type="text" id="empIpNo" placeholder="IP Extension" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Floor</label>
                            <input type="text" id="empFloor" placeholder="e.g. 7th" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="saveEmployee()" class="btn-primary" style="padding: 10px 20px;">Save Employee</button>
                        <button onclick="toggleAddEmployeeForm()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                    </div>
                </div>

                <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                            <tr>
                                <th style="text-align: center;">Name & ID</th>
                                <th style="text-align: center;">Details</th>
                                <th style="text-align: center;">Email/Mobile</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="employeeListBody">
                            <tr><td colspan="4" style="text-align: center; padding: 20px;">Loading employees...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Manager Modal -->
    <div id="statusManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 style="display:flex;align-items:center;gap:8px;"><span class="material-icons">tune</span> Manage Job Statuses</h3>
                <span class="material-icons close-modal" onclick="toggleStatusManager()">close</span>
            </div>
            <div class="modal-body">
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <input type="text" id="newStatusInput" placeholder="New status name..." style="flex:1;padding:10px;border:1px solid var(--border);border-radius:8px;font-size:0.9rem;">
                    <button onclick="addStatus()" class="btn-primary" style="padding:10px 18px;display:flex;align-items:center;gap:5px;">
                        <span class="material-icons" style="font-size:18px;">add</span> Add
                    </button>
                </div>
                <div id="statusList" style="display:flex;flex-direction:column;gap:8px;max-height:350px;overflow-y:auto;">
                    <!-- Loaded via JS -->
                </div>
                <p style="font-size:0.8rem;color:#94a3b8;margin-top:12px;text-align:center;">Use ↑ ↓ arrows to reorder. Changes take effect immediately.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Change Password Modal -->
    <div id="changePassModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Change Password</h3>
                <span class="material-icons close-modal" onclick="toggleChangePass()">close</span>
            </div>
            <div class="modal-body">
                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 5px;">
                        <button type="button" onclick="toggleChangePass()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                        <button type="submit" class="btn-primary" style="padding: 10px 25px;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RPA Configuration Modal -->
    <div id="rpaConfigModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1400px; width: 98%; font-size: 1.1rem;">
            <div class="modal-header">
                <h3 style="display:flex;align-items:center;gap:8px;"><span class="material-icons">settings_remote</span> RPA System Configuration</h3>
                <span class="material-icons close-modal" onclick="toggleRpaConfig()">close</span>
            </div>
            <div class="modal-body">
                <div id="rpaForm" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border);">
                    <input type="hidden" id="rpaId">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; align-items: end;">
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Config Key</label>
                            <input type="text" id="rpaKey" placeholder="e.g. API_URL" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Project Name</label>
                            <input type="text" id="rpaProject" placeholder="e.g. CV_Sorting" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Category</label>
                            <input type="text" id="rpaCategory" placeholder="e.g. System" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Value</label>
                            <input type="text" id="rpaValue" placeholder="Config value..." style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="saveRpaConfig()" class="btn-primary" style="padding: 10px 20px;">Save Config</button>
                        <button onclick="document.getElementById('rpaForm').style.display='none'" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 20px;">
                    <h4 style="margin:0; color: #64748b; white-space: nowrap;">Configurations List</h4>
                    <div style="flex: 1; max-width: 400px; position: relative;">
                        <span class="material-icons" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 20px;">search</span>
                        <input type="text" id="rpaSearchInput" onkeyup="filterRpaConfigs()" placeholder="Filter by Key, Project or Category..." style="width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem;">
                    </div>
                    <button onclick="showAddRpaForm()" class="btn-primary" style="padding: 10px 20px; background: #10b981; display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                        <span class="material-icons" style="font-size: 18px;">add</span> Add New
                    </button>
                </div>

                <div class="table-container" style="max-height: 600px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px; text-align: left; cursor: pointer; user-select: none;" onclick="sortRpaConfigs('project')">
                                    <div style="display: flex; align-items: center; gap: 5px;">Project <span class="material-icons" style="font-size: 16px;">sort</span></div>
                                </th>
                                <th style="padding: 12px; text-align: left; cursor: pointer; user-select: none;" onclick="sortRpaConfigs('category')">
                                    <div style="display: flex; align-items: center; gap: 5px;">Category <span class="material-icons" style="font-size: 16px;">sort</span></div>
                                </th>
                                <th style="padding: 12px; text-align: left; cursor: pointer; user-select: none;" onclick="sortRpaConfigs('key')">
                                    <div style="display: flex; align-items: center; gap: 5px;">Key <span class="material-icons" style="font-size: 16px;">sort</span></div>
                                </th>
                                <th style="padding: 12px; text-align: left;">Value</th>
                                <th style="padding: 12px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="rpaConfigListBody">
                            <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading configurations...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    </script>
    <script src="../js/script.js?v=5.0"></script>
</body>
</html>
