<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kindle 阅读统计门户</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://www.tqhyg.net/wp-content/themes/Sakurairo/css/font-awesome-animation.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card { transition: transform 0.2s; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius: 15px; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .icon-box { font-size: 3.5rem; margin-bottom: 1.5rem; color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="fw-bold text-dark bigTitle"><i class="fa-solid fa-book-open me-2"></i>Kindle 阅读统计中心</h1>
            <p class="text-muted">记录你的每一次阅读，见证知识的积累</p>
        </div>

        <div class="row justify-content-center g-4">
            <div class="col-md-5 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="card-body">
                        <div class="icon-box">
                            <i class="fa-solid fa-qrcode faa-pulse animated-hover"></i>
                        </div>
                        <h3 class="card-title">扫码获取数据</h3>
                        <p class="card-text text-secondary mt-3">
                            通过 <strong>TQHYG/kykky</strong> 插件生成二维码<br>
                            实时同步你的阅读进度
                        </p>
                        <a href="https://github.com/TQHYG/kykky" target="_blank" class="btn btn-outline-primary mt-3 w-100">
                            <i class="fa-brands fa-github me-1"></i> 获取插件
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-5 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="card-body">
                        <div class="icon-box">
                            <i class="fa-solid fa-file-arrow-up faa-float animated-hover"></i>
                        </div>
                        <h3 class="card-title">上传统计文件</h3>
                        <p class="card-text text-secondary mt-3">
                            已有数据文件？<br>
                            上传插件的 log 文件夹生成详细报告
                        </p>
                        <a href="upload.php" class="btn btn-primary mt-3 w-100">
                            <i class="fa-solid fa-upload me-1"></i> 上传文件
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5 text-muted small">
            &copy; <?php echo date('Y'); ?> Reading Statistics | Powered by <i class="fa-solid fa-bolt text-warning">TQHYG</i>
        </div>
    </div>
</body>
</html>