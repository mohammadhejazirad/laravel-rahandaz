[Unit]
Description=Rahandaz PHP 8.4 FPM
After=network.target

[Service]
Type=simple
Slice=rahandaz.slice
Environment=PHP_INI_SCAN_DIR=:/etc/rahandaz/php/ini
ExecStart=@@PHP_FPM_BIN@@ --nodaemonize --fpm-config /etc/rahandaz/php/php-fpm.conf
ExecReload=/bin/kill -USR2 $MAINPID
Restart=on-failure
OOMScoreAdjust=250
RuntimeDirectory=rahandaz
LogsDirectory=rahandaz
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ReadWritePaths=/var/lib/rahandaz /var/log/rahandaz /run/rahandaz

[Install]
WantedBy=multi-user.target
