FROM dolibarr/dolibarr:24.0.0
COPY deploy/patch-route-guard.php /tmp/pz-patch-route-guard.php
RUN php /tmp/pz-patch-route-guard.php && php -l /var/www/html/main.inc.php
