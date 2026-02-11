FROM php:8.1-apache

# 1. 安装系统依赖 (增加了 libzip-dev 以支持 Zip 扩展)
RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    libzip-dev \
    ghostscript \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# 2. 安装 PHP 扩展 (Imagick 和 Zip)
RUN pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install zip

# 3. 开启 PDF 读取权限
# 使用 find 命令定位 policy.xml，无论它在 ImageMagick-6 还是 7 文件夹下都能自动修改
RUN find /etc/ImageMagick* -name "policy.xml" -exec sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' {} +

# 4. 修正 Apache 配置
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && a2enmod rewrite

# 5. 复制代码并设置权限
COPY . /var/www/html/
# 确保临时目录存在，否则 ZIP 写入可能会失败
RUN mkdir -p /var/www/html/temp_uploads && \
    chmod -R 755 /var/www/html/ && \
    chown -R www-data:www-data /var/www/html/

EXPOSE 80
