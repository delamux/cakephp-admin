#!/bin/sh
# nginx image entrypoint hook: serve HTTPS only if the mkcert certificate is mounted (docker/certs/).
set -e
if [ -f /etc/nginx/certs/cakeadmin.local.pem ] && [ -f /etc/nginx/certs/cakeadmin.local-key.pem ]; then
    cp /etc/nginx/https.conf.template /etc/nginx/conf.d/https.conf
    echo "HTTPS enabled: https://cakeadmin.local"
else
    rm -f /etc/nginx/conf.d/https.conf
    echo "No certificate in docker/certs (bin/dev-https-cert): HTTP only"
fi
