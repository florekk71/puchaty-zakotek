FROM dolibarr/dolibarr:24.0.0
COPY deploy/patch-route-guard.php /tmp/pz-patch-route-guard.php
RUN php /tmp/pz-patch-route-guard.php && php -l /var/www/html/main.inc.php
COPY deploy/patch-login.php /tmp/pz-patch-login.php
RUN php /tmp/pz-patch-login.php && php -l /var/www/html/core/tpl/login.tpl.php
