[Unit]
Description=Rahandaz scheduler tick

[Service]
Type=oneshot
Slice=rahandaz.slice
User=rahandaz-svc
Group=rahandaz-svc
WorkingDirectory=@@RELEASE@@
ExecStart=@@PHP_BIN@@ -d memory_limit=128M -d opcache.enable_cli=0 artisan schedule:run
Nice=15
OOMScoreAdjust=300
MemoryMax=192M
TimeoutStartSec=60
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=@@DATA_ROOT@@ /var/log/rahandaz
