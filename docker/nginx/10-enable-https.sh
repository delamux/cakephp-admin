#!/bin/sh
# nginx image entrypoint hook: serve HTTPS only if the mkcert certificate is mounted (docker/certs/).
set -e
if [ -f /etc/nginx/certs/admindashboard.localhost.pem ] && [ -f /etc/nginx/certs/admindashboard.localhost-key.pem ]; then
    cp /etc/nginx/https.conf.template /etc/nginx/conf.d/https.conf
    echo "HTTPS enabled: https://admindashboard.localhost"
else
    rm -f /etc/nginx/conf.d/https.conf
    echo "No certificate in docker/certs (bin/dev-https-cert): HTTP only"
fi
