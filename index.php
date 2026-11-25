#!/bin/bash
dnf update -y
dnf install -y httpd php php-mysqli git
systemctl start httpd
systemctl enable httpd

# Descargar tu repositorio desde GitHub
git clone https://github.com/TU_USUARIO/eft-cloud-rs.git /var/www/html/
chown -R apache:apache /var/www/html/

# Pasar variables de entorno de RDS a Apache
cat << 'EOF' >> /etc/sysconfig/httpd
DB_HOST=PEGA_AQUI_TU_ENDPOINT_RDS
DB_USER=admin
DB_PASS=TU_PASSWORD_RDS
DB_NAME=bd_proyecto_rs
EOF

systemctl restart httpd
