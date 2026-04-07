<?php
require_once 'config/db.php';

echo "<h2>RaktSetu Admin Fix Tool</h2>";

try {
    $db = getDB();
    $email = 'admin@raktsetu.org';
    $newPass = 'Admin@123';
    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);

    // 1. Check if user exists
    $stmt = $db->prepare("SELECT id, name, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        echo "Found user: " . $user['name'] . " (Role: " . $user['role'] . ")<br>";
        
        // 2. Update password and ensure role is admin
        $update = $db->prepare("UPDATE users SET password_hash = ?, role = 'admin', is_verified = 1 WHERE email = ?");
        if ($update->execute([$hash, $email])) {
            echo "<b style='color:green'>Success!</b> Password for <b>$email</b> has been reset to <b>$newPass</b><br>";
        }
    } else {
        echo "Admin user not found. Creating it now...<br>";
        
        // 3. Create admin if missing
        $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?)");
        if ($insert->execute(['Admin RaktSetu', $email, $hash, 'admin', 1])) {
            echo "<b style='color:green'>Success!</b> Created new admin account: <b>$email</b> with password: <b>$newPass</b><br>";
        }
    }
    
    echo "<br><a href='auth.php'>Go to Login Page</a>";

} catch (Exception $e) {
    echo "<b style='color:red'>Error:</b> " . $e->getMessage();
}
?>
