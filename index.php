<?php

/**
 * 高级 PDF/图像转换工具 (支持自动识别域名/IP)
 */

$magickPath = 'C:\Program Files\ImageMagick-7.1.2-Q16';
$gsPath = 'C:\Program Files\gs\gs10.04.1\bin';

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    putenv("PATH=" . getenv('PATH') . ";" . $magickPath . ";" . $gsPath);
}

$message = "";

if (isset($_POST["submit"])) {
    if (isset($_FILES["fileToUpload"]) && $_FILES["fileToUpload"]["error"] == 0) {
        $tempFile = $_FILES["fileToUpload"]["tmp_name"];
        $targetFormat = $_POST["targetFormat"];
        $extension = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION);
        $outputFileName = 'converted_' . time() . '.' . $targetFormat;

        try {
            if (!class_exists('Imagick')) {
                throw new Exception("Imagick not install。");
            }

            $image = new Imagick();

            // 如果是 PDF，设置分辨率以保证清晰度
            if (strtolower($extension) === 'pdf') {
                $image->setResolution(150, 150);
            }

            // 读取完整文件
            $image->readImage(realpath($tempFile));

            // --- 关键点：处理多页面 PDF ---
            if (strtolower($targetFormat) !== 'pdf' && $image->getNumberImages() > 1) {
                // 1. 重置迭代器到第一页
                $image->setFirstIterator();

                // 2. 将所有页面垂直拼接 (true = 垂直, false = 水平)
                // appendImages 会返回一个新的对象
                $combined = $image->appendImages(true);

                // 3. 释放旧的多页资源，替换为合并后的单页
                $image->clear();
                $image->destroy();
                $image = $combined;
            } else {
                // 如果是单页或者目标仍为 PDF，执行常规合并
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }

            // 设置背景颜色（防止 PNG 透明层变黑）
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE); // 移除透明通道，强制白底

            $image->setImageFormat($targetFormat);

            // 获取二进制流
            $fileData = $image->getImagesBlob();

            // 清理
            $image->clear();
            $image->destroy();

            // 发送下载头
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $outputFileName . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . strlen($fileData));

            echo $fileData;
            exit;
        } catch (Exception $e) {
            $message = "<div style='color:red;'>Error： " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div style='color:red;'>PLease Uplolad Valid File.。</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #666;
        }

        input[type="file"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            width: 100%;
            background: black;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        input[type="submit"]:hover {
            background: #333;
        }

        .result {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 5px solid black;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Convert</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <label>Choose File</label>
            <input type="file" name="fileToUpload" required>

            <label>Convert To</label>
            <select name="targetFormat">
                <option value="jpg">JPG (Best for photos)</option>
                <option value="png">PNG (High definition)</option>
                <option value="pdf">PDF (Document)</option>
            </select>

            <input type="submit" value="Convert" name="submit">
        </form>

        <?php if ($message): ?>
            <div class="result">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
