<?php
require_once(__DIR__ . '/config.php');

if (!is_admin()) {
    http_response_code(403);
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If POST is completely empty but CONTENT_LENGTH is high, PHP dropped it due to post_max_size
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        die('Upload failed. The file you attempted to upload exceeds the server post_max_size (50MB). Please upload a smaller compressed video.');
    }

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';
    $return_url = $_POST['return_url'] ?? 'admin.php';

    if ($action === 'add') {
        $message = trim($_POST['message'] ?? '');
        $target_user = trim($_POST['target_user'] ?? '');
        $target_user_id = null;
        
        if (!empty($target_user) && strtolower($target_user) !== 'all') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id = ?");
            $stmt->execute([$target_user, $target_user]);
            $target_user_id = $stmt->fetchColumn() ?: null;
            if (!$target_user_id) {
                $_SESSION['message'] = "Target user not found!";
                $_SESSION['message_type'] = "error";
                header('Location: ' . $return_url);
                exit;
            }
        }

        $hasImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($message !== '' || $hasImage) {
            $image_path = null;
            if ($hasImage) {
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $max_size = 50 * 1024 * 1024; // 50MB
                    if ($_FILES['image']['size'] > $max_size) {
                        $_SESSION['message'] = "File too large (max 50MB). Please upload a smaller video or image.";
                        $_SESSION['message_type'] = "error";
                        header('Location: ' . $return_url);
                        exit;
                    }
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
                    if (in_array(strtolower($ext), $allowed)) {
                        if (!is_dir(__DIR__ . '/uploads/notifications')) {
                            mkdir(__DIR__ . '/uploads/notifications', 0755, true);
                        }
                        $filename = 'notif_' . time() . '_' . uniqid() . '.' . $ext;
                        $target = 'uploads/notifications/' . $filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $target)) {
                            $image_path = $target;
                        } else {
                            $_SESSION['message'] = "File upload failed to save to disk. Check folder permissions.";
                            $_SESSION['message_type'] = "error";
                            header('Location: ' . $return_url);
                            exit;
                        }
                    } else {
                        $_SESSION['message'] = "Invalid file type. Only JPG, PNG, GIF, WEBP, MP4, WEBM allowed.";
                        $_SESSION['message_type'] = "error";
                        header('Location: ' . $return_url);
                        exit;
                    }
                } else {
                    $_SESSION['message'] = "Image upload error code: " . $_FILES['image']['error'] . " (Image matches server limit constraints?)";
                    $_SESSION['message_type'] = "error";
                    header('Location: ' . $return_url);
                    exit;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO admin_notifications (target_user_id, message, image_path) VALUES (?, ?, ?)");
            $stmt->execute([$target_user_id, $message, $image_path]);
            @log_audit('ADD_NOTIFICATION', 'system', $pdo->lastInsertId(), ['message' => substr($message ?: 'Image Attachment Only', 0, 50)]);
            
        } else {
            $_SESSION['message'] = "You must provide a text message or attach an image!";
            $_SESSION['message_type'] = "error";
            header('Location: ' . $return_url);
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("SELECT image_path FROM admin_notifications WHERE id = ?");
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();
            if ($path && file_exists(__DIR__ . '/' . $path)) {
                @unlink(__DIR__ . '/' . $path);
            }

            $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
            $stmt->execute([$id]);
            @log_audit('DELETE_NOTIFICATION', 'system', $id, []);
        }
    }

    header('Location: ' . $return_url);
    exit;
}
