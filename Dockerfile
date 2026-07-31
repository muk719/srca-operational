FROM php:8.2-apache

# امتدادات قاعدة البيانات
RUN docker-php-ext-install pdo pdo_mysql

# نسخ ملفات الموقع
COPY . /var/www/html/

# حذف الملفات غير المطلوبة على السيرفر
RUN rm -f /var/www/html/portal_db.sql "/var/www/html/الملف التشغيلي.pdf" || true

# Railway يمرر رقم المنفذ في متغير PORT
# إصلاح تعارض وحدات MPM ثم ضبط المنفذ والتشغيل
CMD ["/bin/sh", "-c", "a2dismod -f mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork 2>/dev/null; sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
