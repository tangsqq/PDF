<?php

/**
 * 高级 PDF/图像转换工具 (支持分页打包 ZIP 下载)
 */

$magickPath = 'C:\Program Files\ImageMagick-7.1.2-Q16';
$gsPath = 'C:\Program Files\gs\gs10.04.1\bin';

// 提高性能上限
set_time_limit(300);
ini_set('memory_limit', '1024M');

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    putenv("PATH=" . getenv('PATH') . ";" . $magickPath . ";" . $gsPath);
}

$message = "";

if (isset($_POST["submit"])) {
    if (isset($_FILES["fileToUpload"]) && $_FILES["fileToUpload"]["error"] == 0) {
        $tempFile = $_FILES["fileToUpload"]["tmp_name"];
        $targetFormat = $_POST["targetFormat"];
        $extension = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION);
        $timestamp = time();

        try {
            if (!class_exists('Imagick')) {
                throw new Exception("Imagick not install。");
            }

            // --- 核心优化 A: 先探测页数 ---
            $identify = new Imagick();
            $identify->pingImage(realpath($tempFile));
            $numPages = $identify->getNumberImages();
            $identify->clear();
            $identify->destroy();

            // --- 情况 1: 如果是单页或者是转换 PDF，保持原逻辑直接输出 ---
            if ($numPages <= 1 || strtolower($targetFormat) === 'pdf') {
                $image = new Imagick();
                if (strtolower($extension) === 'pdf') {
                    $image->setResolution(150, 150);
                }
                $image->readImage(realpath($tempFile));

                $image->setImageBackgroundColor('white');
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $image->setImageFormat($targetFormat);

                $fileData = $image->getImagesBlob();
                $outputFileName = 'converted_' . $timestamp . '.' . $targetFormat;

                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $outputFileName . '"');
                echo $fileData;
                exit;
            }
            // --- 情况 2: 多页 PDF 转单张图片 (核心改动：ZIP 打包) ---
            else {
                if (!class_exists('ZipArchive')) {
                    throw new Exception("服务器未启用 Zip 扩展。");
                }

                $zip = new ZipArchive();
                $zipFileName = 'converted_pages_' . $timestamp . '.zip';
                // Docker/Linux 环境下使用系统临时目录
                $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

                if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                    throw new Exception("无法创建压缩文件。");
                }

                // 核心：逐页读取并转换，150 DPI 高清设置
                for ($i = 0; $i < $numPages; $i++) {
                    $page = new Imagick();
                    // 设置高清 150 DPI
                    $page->setResolution(150, 150);
                    $page->readImage(realpath($tempFile) . '[' . $i . ']'); // 只读第 i 页

                    $page->setImageBackgroundColor('white');
                    $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                    $page->setImageFormat($targetFormat);
                    $single = $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

                    // 将每一页添加进 ZIP
                    $zip->addFromString("page_" . ($i + 1) . "." . $targetFormat, $single->getImagesBlob());

                    // 彻底释放内存
                    $single->clear();
                    $single->destroy();
                    $page->clear();
                    $page->destroy();
                }
                $zip->close();

                // 下载 ZIP 包
                if (ob_get_length()) ob_end_clean();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                @unlink($zipPath);
                exit;
            }
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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📑</text></svg>">
    <title>Convert File</title>
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
        <h2>Convert File</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <label>Choose File</label>
            <input type="file" name="fileToUpload" required>

            <label>Convert To</label>
            <select name="targetFormat">
                <option value="jpg">JPG</option>
                <option value="png">PNG</option>
                <option value="pdf">PDF</option>
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

