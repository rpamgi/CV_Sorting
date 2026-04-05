<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include("../config/db.php");

// Fetch paths from rpa_config table
function getRpaConfigValue($conn, $key, $default) {
    $stmt = $conn->prepare("SELECT value FROM rpa_config WHERE `key` = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $value = null;
    $stmt->bind_result($value);
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    $stmt->close();
    
    // Auto-create if not exists with default
    $stmt = $conn->prepare("INSERT INTO rpa_config (`key`, `value`, `category`, `project`) VALUES (?, ?, 'File_Paths', 'General')");
    $stmt->bind_param("ss", $key, $default);
    $stmt->execute();
    $stmt->close();
    return $default;
}

$source_dir = getRpaConfigValue($conn, 'N8NJDPATH', "C:\\Users\\administrator.MEGHNAGROUP\\.n8n-files\\JD");
$dest_dir = getRpaConfigValue($conn, 'N8NJDEST', "C:\\Users\\administrator.MEGHNAGROUP\\.n8n-files\\Processed JD");

// Ensure directories exist
if (!is_dir($source_dir)) {
    echo json_encode(["status" => "error", "message" => "Source directory not found: $source_dir", "path_hint" => "Update JD_SOURCE_DIR in RPA Config."]);
    exit;
}
if (!is_dir($dest_dir)) {
    if (!mkdir($dest_dir, 0777, true)) {
        echo json_encode(["status" => "error", "message" => "Destination directory not found and could not be created: $dest_dir"]);
        exit;
    }
}

$filename = isset($_GET['filename']) ? trim($_GET['filename']) : '';
$newfilename = isset($_GET['newfilename']) ? trim($_GET['newfilename']) : '';

if (!empty($filename)) {
    // Move specific file
    $source_path = $source_dir . DIRECTORY_SEPARATOR . $filename;
    $target_name = !empty($newfilename) ? $newfilename : $filename;
    $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $target_name;

    if (file_exists($source_path)) {
        if (rename($source_path, $dest_path)) {
            echo json_encode(["status" => "success", "message" => "JD File '$filename' moved successfully."]);
        }
        else {
            echo json_encode(["status" => "error", "message" => "Failed to move JD file '$filename'."]);
        }
    }
    else {
        echo json_encode(["status" => "error", "message" => "JD File '$filename' does not exist in source directory."]);
    }
}
else {
    // Move all files if no filename provided
    $files_moved = 0;
    $errors = [];
    $dir_handle = opendir($source_dir);

    if ($dir_handle) {
        while (($file = readdir($dir_handle)) !== false) {
            if ($file != "." && $file != ".." && is_file($source_dir . DIRECTORY_SEPARATOR . $file)) {
                if (rename($source_dir . DIRECTORY_SEPARATOR . $file, $dest_dir . DIRECTORY_SEPARATOR . $file)) {
                    $files_moved++;
                }
                else {
                    $errors[] = "Failed to move '$file'.";
                }
            }
        }
        closedir($dir_handle);
    }

    echo json_encode([
        "status" => count($errors) === 0 ? "success" : "partial_success",
        "message" => "$files_moved JD files moved successfully.",
        "errors" => $errors
    ]);
}
?>
