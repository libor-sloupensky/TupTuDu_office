<?php
$version = "V001";
$lastPush = file_exists(__DIR__ . '/last_push.txt') ? file_get_contents(__DIR__ . '/last_push.txt') : 'N/A';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>TupTuDu</title>
</head>
<body>
    <div style="position: fixed; top: 10px; right: 10px; text-align: right; font-family: monospace; font-size: 12px; color: #888;">
        <?php echo $version; ?><br>
        Poslední push: <?php echo $lastPush; ?>
    </div>
    <?php echo "test web 1 "; ?>
</body>
</html>
