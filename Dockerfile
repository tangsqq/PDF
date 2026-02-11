FROM php:8.2-apache

# 安装 ImageMagick 和 Ghostscript
RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends \
    ghostscript \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# 复制你的代码到服务器
COPY . /var/www/html/

# 给予权限
RUN chown -R www-data:www-data /var/www/html/

# 允许 ImageMagick 读取 PDF (解除安全限制)
RUN sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml || true
