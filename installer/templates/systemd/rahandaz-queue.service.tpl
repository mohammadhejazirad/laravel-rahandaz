[Unit]
Description=Rahandaz queue worker
After=network.target postgresql.service redis-server.service redis-rahandaz-cache.service

[Service]
Type=simple
User=rahandaz
Group=rahandaz
WorkingDirectory=@@RELEASE@@
ExecStart=@@PHP_BIN@@ artisan queue:work redis --sleep=3 --tries=3 --timeout=60 --max-time=3600
Restart=always
RestartSec=5
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=@@DATA_ROOT@@ /var/log/rahandaz

[Install]
WantedBy=multi-user.target
