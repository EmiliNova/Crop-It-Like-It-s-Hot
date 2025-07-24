<?php
// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file.';
    } else {
        $fileTmp = $_FILES['image']['tmp_name'];
        $originalName = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $validExt = ['jpg','jpeg','png','gif'];

        if (!in_array($extension, $validExt)) {
            $error = 'Invalid file type. Only JPG, PNG, GIF allowed.';
        } else {
            // Load image from string to support all types
            $data = file_get_contents($fileTmp);
            $src = @imagecreatefromstring($data);
            if (!$src) {
                $error = 'Failed to read image data.';
            } else {
                $width = imagesx($src);
                $height = imagesy($src);

                // Function to detect white (with tolerance)
                $isWhite = function($rgb) {
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    return ($r > 250 && $g > 250 && $b > 250);
                };

                // Find crop boundaries
                // Left
                for ($x = 0; $x < $width; $x++) {
                    $stop = false;
                    for ($y = 0; $y < $height; $y++) {
                        if (!$isWhite(imagecolorat($src, $x, $y))) {
                            $left = $x;
                            $stop = true;
                            break;
                        }
                    }
                    if ($stop) break;
                }

                // Right
                for ($x = $width - 1; $x >= 0; $x--) {
                    $stop = false;
                    for ($y = 0; $y < $height; $y++) {
                        if (!$isWhite(imagecolorat($src, $x, $y))) {
                            $right = $x;
                            $stop = true;
                            break;
                        }
                    }
                    if ($stop) break;
                }

                // Top
                for ($y = 0; $y < $height; $y++) {
                    $stop = false;
                    for ($x = 0; $x < $width; $x++) {
                        if (!$isWhite(imagecolorat($src, $x, $y))) {
                            $top = $y;
                            $stop = true;
                            break;
                        }
                    }
                    if ($stop) break;
                }

                // Bottom
                for ($y = $height - 1; $y >= 0; $y--) {
                    $stop = false;
                    for ($x = 0; $x < $width; $x++) {
                        if (!$isWhite(imagecolorat($src, $x, $y))) {
                            $bottom = $y;
                            $stop = true;
                            break;
                        }
                    }
                    if ($stop) break;
                }

                $cropWidth = $right - $left + 1;
                $cropHeight = $bottom - $top + 1;
                $crop = imagecrop($src, ['x' => $left, 'y' => $top, 'width' => $cropWidth, 'height' => $cropHeight]);

                if (!$crop) {
                    $error = 'Cropping failed.';
                } else {
                    // Prepare download
                    $newName = $originalName . '-c.' . $extension;
                    header('Content-Description: File Transfer');
                    header('Content-Type: image/' . ($extension === 'jpg' ? 'jpeg' : $extension));
                    header('Content-Disposition: attachment; filename="' . $newName . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');

                    // Output image
                    if ($extension === 'png') {
                        imagepng($crop);
                    } elseif ($extension === 'gif') {
                        imagegif($crop);
                    } else {
                        imagejpeg($crop, null, 90);
                    }

                    imagedestroy($crop);
                    imagedestroy($src);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoCrop Image</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <h1 class="mb-4">AutoCrop Image App</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="border p-4 rounded bg-light">
        <div class="mb-3">
            <label for="image" class="form-label">Choose an image</label>
            <input class="form-control" type="file" name="image" id="image" accept="image/*" required>
        </div>
        <button type="submit" class="btn btn-success">Upload & Crop</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
