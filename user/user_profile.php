<?php
session_start();
require '../db_connect.php';

// Only allow citizens
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');

        try {
            // Check if profile exists
            $check_stmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
            $check_stmt->execute([$user_id]);
            $profile_exists = $check_stmt->fetch();

            if ($profile_exists) {
                // Update existing profile
                $update_stmt = $pdo->prepare("
                    UPDATE user_profiles SET 
                    first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, 
                    gender = ?, phone_number = ?, address = ?, city = ?, province = ?, 
                    postal_code = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
                    emergency_contact_relationship = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $update_stmt->execute([
                    $first_name, $last_name, $middle_name, $date_of_birth, $gender,
                    $phone_number, $address, $city, $province, $postal_code,
                    $emergency_contact_name, $emergency_contact_phone, $emergency_contact_relationship,
                    $user_id
                ]);
            } else {
                // Create new profile
                $insert_stmt = $pdo->prepare("
                    INSERT INTO user_profiles 
                    (user_id, first_name, last_name, middle_name, date_of_birth, gender, 
                     phone_number, address, city, province, postal_code, emergency_contact_name, 
                     emergency_contact_phone, emergency_contact_relationship)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([
                    $user_id, $first_name, $last_name, $middle_name, $date_of_birth, $gender,
                    $phone_number, $address, $city, $province, $postal_code,
                    $emergency_contact_name, $emergency_contact_phone, $emergency_contact_relationship
                ]);
            }

            // Update profile completion status
            $completion_stmt = $pdo->prepare("UPDATE users SET profile_completed = TRUE WHERE id = ?");
            $completion_stmt->execute([$user_id]);

            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>';
        } catch (Exception $e) {
            $error = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error updating profile: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Fetch current profile data
$profile_stmt = $pdo->prepare("
    SELECT up.*, u.name, u.email, u.kyc_status, u.profile_completed 
    FROM users u 
    LEFT JOIN user_profiles up ON u.id = up.user_id 
    WHERE u.id = ?
");
$profile_stmt->execute([$user_id]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch KYC documents
$kyc_stmt = $pdo->prepare("SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY created_at DESC");
$kyc_stmt->execute([$user_id]);
$kyc_documents = $kyc_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile - Alerto360</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            max-width: 1000px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .back-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        .content-container {
            padding: 2.5rem;
        }
        .profile-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status-not-started { background: #f8f9fa; color: #6c757d; }
        .status-in-progress { background: #fff3cd; color: #856404; }
        .status-pending { background: #cce5ff; color: #004085; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        .kyc-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <a href="user_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <div class="subtitle">Manage your personal information and verification</div>
        </div>
        
        <div class="content-container">
            <?= $message ?>
            <?= $error ?>
            
            <!-- Profile Status Overview -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle"></i> Profile Status
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Profile Completion:</strong>
                        <span class="status-badge <?= $profile['profile_completed'] ? 'status-verified' : 'status-not-started' ?>">
                            <?= $profile['profile_completed'] ? 'Complete' : 'Incomplete' ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>KYC Status:</strong>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $profile['kyc_status'])) ?>">
                            <?= htmlspecialchars($profile['kyc_status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Personal Information Form -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-user"></i> Personal Information
                </h5>
                <form method="post">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" 
                                   value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" 
                                   value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" 
                                   value="<?= htmlspecialchars($profile['middle_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" 
                                   value="<?= htmlspecialchars($profile['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email (Account)</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" 
                                   value="<?= htmlspecialchars($profile['phone_number'] ?? '') ?>" 
                                   placeholder="+639123456789">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2" 
                                  placeholder="Street, Barangay"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" 
                                   value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Province</label>
                            <input type="text" class="form-control" name="province" 
                                   value="<?= htmlspecialchars($profile['province'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" 
                                   value="<?= htmlspecialchars($profile['postal_code'] ?? '') ?>">
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3" style="color: #667eea;">
                        <i class="fas fa-phone-alt"></i> Emergency Contact
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact_name" 
                                   value="<?= htmlspecialchars($profile['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" name="emergency_contact_phone" 
                                   value="<?= htmlspecialchars($profile['emergency_contact_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relationship</label>
                            <input type="text" class="form-control" name="emergency_contact_relationship" 
                                   value="<?= htmlspecialchars($profile['emergency_contact_relationship'] ?? '') ?>" 
                                   placeholder="e.g., Spouse, Parent, Sibling">
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- KYC Verification Section -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-shield-alt"></i> Identity Verification (KYC)
                </h5>
                
                <?php if (empty($kyc_documents)): ?>
                    <div class="text-center">
                        <p class="text-muted mb-3">
                            <i class="fas fa-id-card fa-3x mb-3"></i><br>
                            Complete your identity verification to enhance account security and enable full access to emergency services.
                        </p>
                        <a href="kyc_verification.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Start KYC Verification
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <a href="kyc_verification.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add New Document
                        </a>
                    </div>
                    
                    <?php foreach ($kyc_documents as $doc): ?>
                        <div class="kyc-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <strong><?= htmlspecialchars($doc['document_type']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($doc['document_number'] ?? 'No number provided') ?></small>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $doc['verification_status'])) ?>">
                                        <?= htmlspecialchars($doc['verification_status']) ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">
                                        Submitted: <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                                    </small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="kyc_verification.php?edit=<?= $doc['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> View/Edit
                                    </a>
                                </div>
                            </div>
                            <?php if (!empty($doc['verification_notes'])): ?>
                                <div class="mt-2">
                                    <small><strong>Notes:</strong> <?= htmlspecialchars($doc['verification_notes']) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>