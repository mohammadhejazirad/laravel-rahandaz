[Unit]
Description=Rahandaz PHP 8.4 FPM
After=network.target

[Service]
Type=simple
ExecStart=@@PHP_FPM_BIN@@ --nodaemonize --fpm-config /etc/rahandaz/php/php-fpm.conf
ExecReload=/bin/kill -USR2 $MAINPID
Restart=on-failure
RuntimeDirectory=rahandaz
LogsDirectory=rahandaz
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ReadWritePaths=/var/lib/rahandaz /var/log/rahandaz /run/rahandaz

[Install]
WantedBy=multi-user.target
