[Unit]
Description=Rahandaz queue worker
After=network.target postgresql.service redis-rahandaz-queue.service redis-rahandaz-cache.service

[Service]
Type=simple
Slice=rahandaz.slice
User=rahandaz-svc
Group=rahandaz-svc
WorkingDirectory=@@RELEASE@@
# CLI OPcache stays off so an idle worker does not reserve a second cache.
ExecStart=@@PHP_BIN@@ -d memory_limit=256M -d opcache.enable_cli=0 artisan queue:work redis --sleep=3 --tries=3 --timeout=60 --max-time=3600
Restart=always
Nice=10
OOMScoreAdjust=250
MemoryMax=320M
RestartSec=5
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=@@DATA_ROOT@@ /var/log/rahandaz

[Install]
WantedBy=multi-user.target
