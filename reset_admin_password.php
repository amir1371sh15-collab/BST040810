<?php
/**
 * One-Time Admin Password Reset Script ("Golden Key")
 * This script generates a new, compatible password hash for the 'admin' user
 * using the server's own PHP environment, guaranteeing a match.
 */

// 1. Include the database connection file.
require_once __DIR__ . '/config/db.php';

// --- Start HTML output for user feedback ---
echo "<!DOCTYPE html><html lang='fa' dir='rtl' style='font-family: Vazirmatn, sans-serif; padding: 20px; background-color: #f0f2f5;'>";
echo "<div style='max-width: 800px; margin: 40px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>";
echo "<h1><i style='color:#0d6efd;'>&#128273;</i> اسکریپت بازنشانی رمز عبور مدیر</h1>";
echo "<hr>";

try {
    // 2. Define the username and the new password.
    $username_to_reset = 'admin';
    $new_password = 'admin';

    // 3. Generate a secure hash for the new password using the current PHP environment.
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // 4. Prepare the SQL statement to update the user's password hash.
    $sql = "UPDATE tbl_users SET PasswordHash = ? WHERE Username = ?";
    $stmt = $pdo->prepare($sql);

    // 5. Execute the update query.
    $stmt->execute([$new_hash, $username_to_reset]);

    // 6. Check if the update was successful (if any row was affected).
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green; font-size: 1.2rem; font-weight: bold;'>&#10004; موفقیت!</p>";
        echo "<p>رمز عبور کاربر <strong>'admin'</strong> با موفقیت به <strong>'admin'</strong> بازنشانی شد.</p>";
        echo "<p style='color: red; font-weight: bold; border: 1px solid red; padding: 10px; border-radius: 5px;'>&#9888; هشدار امنیتی: لطفاً برای محافظت از سیستم، همین حالا این فایل (reset_admin_password.php) را از روی سرور خود حذف کنید.</p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>&#9888; توجه:</p>";
        echo "<p>کاربری با نام کاربری <strong>'admin'</strong> در پایگاه داده پیدا نشد. لطفاً ابتدا مطمئن شوید که اسکریپت <code>database_reset_users.sql</code> را حداقل یک بار با موفقیت اجرا کرده‌اید.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>&#10060; خطای پایگاه داده:</p>";
    echo "<p>متاسفانه در هنگام اتصال یا بروزرسانی پایگاه داده خطایی رخ داد: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";
